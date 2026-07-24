# Diseño: Pantalla de Inicio y Temporizador para Cuestionarios

**Fecha:** 2026-07-24
**Proyecto:** LMS DyL Quality Consulting
**Estado:** Aprobado

---

## Resumen

Antes de ver las preguntas de un cuestionario, el estudiante verá una pantalla de inicio con la información del intento (cuántos intentos tiene permitidos, cuántas preguntas hay, y el tiempo límite si lo hay) y un botón **"Iniciar cuestionario"**. Si el cuestionario tiene tiempo límite (`duracion_minutos`), al iniciar aparece un temporizador de cuenta regresiva que persiste en el servidor — si el estudiante refresca o cierra la pestaña, el tiempo restante refleja el transcurrido real, no se reinicia. Al llegar a 0, la respuesta se envía automáticamente con lo que el estudiante haya marcado hasta ese momento.

Esta funcionalidad es exclusiva de actividades tipo `cuestionario`; el resto de tipos (`ensayo`, `tarea`, `practica`) no cambian.

---

## 1. Pantalla de inicio

Reemplaza el formulario de preguntas que hoy se muestra directamente en `resources/views/actividades/show.blade.php`, en los dos lugares donde aparece: la rama de múltiples intentos y la rama sin cambios (un solo intento). Ambas ya incluyen `actividades/partials/formulario-cuestionario.blade.php` — se extrae un nuevo partial `actividades/partials/cuestionario-con-inicio.blade.php` que envuelve ese formulario y se usa en ambos lugares, evitando duplicar la lógica de inicio/temporizador.

Contenido de la tarjeta de inicio:
- **Intentos permitidos:** `{{ $actividad->intentos_permitidos }}` (si `permiteMultiplesIntentos()`, agrega "ya usaste {{ $intentosUsados }} de {{ $actividad->intentos_permitidos }}")
- **Tiempo límite:** `{{ $actividad->duracion_minutos }} minutos` — solo si `duracion_minutos` no es null
- **Número de preguntas:** conteo de `$actividad->preguntas()->count()`
- Botón **"Iniciar cuestionario"**

**Flujo unificado (con o sin tiempo límite):** el botón siempre hace `POST` a una nueva ruta (ver sección 2) que registra el inicio del intento en el servidor antes de redirigir de vuelta a la página. Esto es necesario para que las preguntas nunca lleguen al HTML antes de iniciar — un toggle puramente client-side obligaría a enviar las preguntas ocultas por CSS, lo cual no oculta nada realmente (un estudiante podría verlas en el código fuente antes de hacer clic). La única diferencia entre un cuestionario con tiempo límite y uno sin él es que este último no calcula ni muestra el cronómetro — el registro del inicio del intento ocurre igual en ambos casos.

---

## 2. Persistencia del intento en progreso

Nueva migración `create_intentos_en_progreso_table` (mismo patrón que `progreso_actividades`):

```php
Schema::create('intentos_en_progreso', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
    $table->timestamp('fecha_inicio');
    $table->timestamps();
    $table->unique(['user_id', 'actividad_id']);
});
```

Nuevo modelo `App\Models\IntentoEnProgreso` (fillable: `user_id`, `actividad_id`, `fecha_inicio`; cast `fecha_inicio` como `datetime`).

### Nueva ruta

```
POST /actividades/{actividad}/iniciar-intento   →  actividades.iniciarIntento
```

Ubicada junto a `actividades.completar` (mismo grupo de middleware, cualquier estudiante autenticado que pueda ver la actividad).

### `ActividadController::iniciarIntento(Actividad $actividad)`

```php
public function iniciarIntento(Actividad $actividad)
{
    $this->authorize('view', $actividad->leccion->modulo->curso);
    abort_unless($actividad->tipo === 'cuestionario', 403);

    IntentoEnProgreso::firstOrCreate(
        ['user_id' => auth()->id(), 'actividad_id' => $actividad->id],
        ['fecha_inicio' => now()]
    );

    return redirect()->route('actividades.show', $actividad);
}
```

`firstOrCreate` es la pieza clave: si el estudiante refresca la página de inicio y vuelve a hacer clic en "Iniciar" (poco probable pero posible con doble clic o back/forward), no se pisa `fecha_inicio` — el reloj no se reinicia.

### Nuevo método en `CalificacionService`

Para no fabricar un `Request` a mano ni acoplar `ActividadController` a `RespuestaEstudianteController`, se agrega un método dedicado que encapsula la creación de un intento vencido sin envío — reutiliza `tienePreguntasCortas()` y `calcularCuestionario()`, ya existentes en el mismo servicio:

```php
/**
 * Registra un intento de cuestionario vencido sin envío del estudiante (el tiempo se
 * agotó mientras la pestaña estaba cerrada, por lo que el auto-envío de JS nunca corrió).
 * Usa la misma lógica de calificación que un envío manual, con respuesta vacía.
 */
public function registrarIntentoExpirado(Actividad $actividad, int $userId): RespuestaEstudiante
{
    if ($this->tienePreguntasCortas($actividad)) {
        $calificacion = null;
        $estado       = 'en_revision';
    } else {
        $calificacion = $this->calcularCuestionario($actividad, '{}');
        $estado       = 'calificada';
    }

    $respuesta = RespuestaEstudiante::create([
        'user_id'      => $userId,
        'actividad_id' => $actividad->id,
        'respuesta'    => '{}',
        'calificacion' => $calificacion,
        'estado'       => $estado,
        'fecha_envio'  => now(),
    ]);

    $actividad->completarPara($userId);

    return $respuesta;
}
```

No se envía notificación al instructor en este caso (a diferencia de un envío manual) — es un intento forzosamente vencido, no una entrega real que el instructor necesite revisar de inmediato; aparecerá igualmente en su cola de calificación si quedó `en_revision`, o en el historial del estudiante si quedó `calificada`.

### Cambios en `ActividadController::show()`

Cuando la actividad es `cuestionario` (con o sin `duracion_minutos` — el registro de "intento en progreso" aplica a ambos casos; solo el cálculo de segundos restantes depende de que haya tiempo límite):

```php
$intentoEnProgreso = ($actividad->tipo === 'cuestionario')
    ? IntentoEnProgreso::where('user_id', auth()->id())->where('actividad_id', $actividad->id)->first()
    : null;

$segundosRestantes = null;
if ($intentoEnProgreso && $actividad->duracion_minutos) {
    $segundosRestantes = $actividad->duracion_minutos * 60 - now()->diffInSeconds($intentoEnProgreso->fecha_inicio);

    if ($segundosRestantes <= 0) {
        app(CalificacionService::class)->registrarIntentoExpirado($actividad, auth()->id());
        $intentoEnProgreso->delete();

        return redirect()->route('actividades.show', $actividad);
    }
}
```

Variables nuevas pasadas a la vista: `$intentoEnProgreso` (null o el modelo) y `$segundosRestantes` (null o int — solo relevante cuando `$intentoEnProgreso` no es null).

### Limpieza al enviar

En `RespuestaEstudianteController::store()`, después de crear la fila `RespuestaEstudiante` (en la rama `cuestionario`), se agrega:

```php
IntentoEnProgreso::where('user_id', Auth::id())
    ->where('actividad_id', $actividad->id)
    ->delete();
```

Esto es seguro de ejecutar siempre (aunque no exista la fila, `delete()` sobre un query vacío no falla) — no hace falta condicionar por si el cuestionario tiene tiempo límite o no.

---

## 3. Temporizador visual y auto-envío

Dentro de `actividades/partials/cuestionario-con-inicio.blade.php`, cuando `$segundosRestantes !== null`:

```blade
<div x-data="{
        segundos: {{ $segundosRestantes }},
        intervalo: null,
        init() {
            this.intervalo = setInterval(() => {
                this.segundos--;
                if (this.segundos <= 0) {
                    clearInterval(this.intervalo);
                    document.getElementById('form-respuesta').requestSubmit();
                }
            }, 1000);
        },
        get mmss() {
            const m = Math.floor(this.segundos / 60);
            const s = this.segundos % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        }
     }"
     class="sticky top-4 z-10 mb-4 flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-mono text-lg font-bold"
     :class="segundos <= 60 ? 'bg-red-50 text-red-700 animate-pulse' : 'bg-blue-50 text-blue-700'">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span x-text="mmss"></span>
</div>
```

`requestSubmit()` dispara el evento `submit` del formulario, que ya tiene el listener existente en `formulario-cuestionario.blade.php` que serializa las respuestas a JSON antes de enviar — el auto-envío usa exactamente el mismo camino que un envío manual, sin código duplicado.

---

## 4. Fuera de alcance (explícito)

- Pausar o extender el tiempo manualmente (por el instructor o el estudiante).
- Advertencias sonoras o notificaciones del navegador al acercarse el límite de tiempo.
- Sincronizar el reloj del cliente con el servidor más allá del valor inicial `segundosRestantes` (no hay proteción contra que el estudiante manipule el reloj de su propio navegador vía devtools — la validación real de tiempo vive en el servidor a través de `fecha_inicio`, que es lo único que importa para prevenir trampa).
- Pantalla de inicio o temporizador para tipos de actividad distintos a `cuestionario`.
- Cronómetro, pausas o extensiones de tiempo cuando no hay `duracion_minutos` — el registro de "intento en progreso" existe igual en ese caso (para ocultar las preguntas hasta iniciar), simplemente no hay segundos que contar ni auto-envío por expiración.

---

## 5. Testing

Nuevo `tests/Feature/CuestionarioTemporizadorTest.php`:

1. La página de un cuestionario muestra la tarjeta de inicio con intentos permitidos, número de preguntas, y (si aplica) tiempo límite, con el botón "Iniciar cuestionario" — y el formulario de preguntas no es lo primero visible.
2. `POST actividades.iniciarIntento` en cualquier cuestionario (con o sin tiempo límite) crea la fila en `intentos_en_progreso` con `fecha_inicio` cercano a `now()`.
3. Un segundo `POST` a la misma ruta no cambia `fecha_inicio` de la fila existente (protección contra reinicio del reloj).
4. `iniciarIntento` responde 403 si la actividad no es `cuestionario`.
5. `ActividadController::show()` con un `IntentoEnProgreso` reciente y tiempo restante > 0 no muestra el botón "Iniciar cuestionario" — muestra el formulario directamente.
6. `ActividadController::show()` con un `IntentoEnProgreso` cuyo tiempo ya expiró: se crea automáticamente una `RespuestaEstudiante` (calificación 0 y `estado='calificada'` si no hay preguntas de respuesta corta; `estado='en_revision'` si las hay), se borra la fila de `intentos_en_progreso`, y la página redirige mostrando el resultado del intento.
7. Enviar una respuesta normalmente borra la fila `intentos_en_progreso` correspondiente, si existía.
8. Actividades tipo `tarea`, `ensayo`, `practica` no muestran ninguna pantalla de inicio ni temporizador — comportamiento actual sin cambios.
