# Archivo de ejemplo descargable para importar cuestionarios

**Fecha:** 2026-07-31
**Estado:** Aprobado

## Resumen

Los instructores que suben un JSON manualmente (sin pasar por la plantilla de Apps Script de `docs/apps-script/`) reciben el error "El archivo no tiene la estructura esperada" sin una referencia concreta de qué formato se espera. Se agrega un enlace de descarga "Descargar archivo de ejemplo" junto al formulario de importación en `actividades/edit.blade.php`, que sirve un JSON estático con una pregunta de cada tipo soportado.

Mismo patrón ya usado para la rúbrica (`RubricaController::ejemplo()` + ruta `rubrica.ejemplo`), adaptado a JSON en vez de Excel.

## Contenido del archivo de ejemplo

Cobertura completa de los 4 casos que interpreta `ImportacionCuestionarioController::store()`:

```json
{
  "version": 1,
  "preguntas": [
    {
      "texto": "¿Cuál es la capital de Francia?",
      "tipo": "opcion_multiple",
      "multiple": false,
      "opciones": [
        { "texto": "Madrid", "correcta": false },
        { "texto": "París", "correcta": true },
        { "texto": "Roma", "correcta": false }
      ]
    },
    {
      "texto": "Selecciona los lenguajes de programación (puede haber varias correctas)",
      "tipo": "opcion_multiple",
      "multiple": true,
      "opciones": [
        { "texto": "PHP", "correcta": true },
        { "texto": "Photoshop", "correcta": false },
        { "texto": "JavaScript", "correcta": true }
      ]
    },
    {
      "texto": "El sol es una estrella",
      "tipo": "opcion_multiple",
      "multiple": false,
      "opciones": [
        { "texto": "Verdadero", "correcta": true },
        { "texto": "Falso", "correcta": false }
      ]
    },
    {
      "texto": "Explica brevemente qué es la fotosíntesis",
      "tipo": "respuesta_corta"
    }
  ]
}
```

Cubre: opción múltiple con respuesta única conocida, selección múltiple (varias correctas), verdadero/falso auto-detectado por texto de opciones, y respuesta corta. Nombre del archivo descargado: `ejemplo-cuestionario.json`.

## Backend

Nuevo método en `app/Http/Controllers/ImportacionCuestionarioController.php`:

```php
public function ejemplo()
{
    $contenido = [
        'version' => 1,
        'preguntas' => [
            [
                'texto' => '¿Cuál es la capital de Francia?',
                'tipo' => 'opcion_multiple',
                'multiple' => false,
                'opciones' => [
                    ['texto' => 'Madrid', 'correcta' => false],
                    ['texto' => 'París', 'correcta' => true],
                    ['texto' => 'Roma', 'correcta' => false],
                ],
            ],
            [
                'texto' => 'Selecciona los lenguajes de programación (puede haber varias correctas)',
                'tipo' => 'opcion_multiple',
                'multiple' => true,
                'opciones' => [
                    ['texto' => 'PHP', 'correcta' => true],
                    ['texto' => 'Photoshop', 'correcta' => false],
                    ['texto' => 'JavaScript', 'correcta' => true],
                ],
            ],
            [
                'texto' => 'El sol es una estrella',
                'tipo' => 'opcion_multiple',
                'multiple' => false,
                'opciones' => [
                    ['texto' => 'Verdadero', 'correcta' => true],
                    ['texto' => 'Falso', 'correcta' => false],
                ],
            ],
            [
                'texto' => 'Explica brevemente qué es la fotosíntesis',
                'tipo' => 'respuesta_corta',
            ],
        ],
    ];

    return response()->streamDownload(
        fn () => print(json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        'ejemplo-cuestionario.json',
        ['Content-Type' => 'application/json']
    );
}
```

Nueva ruta en `routes/web.php`, junto a `rubrica.ejemplo` (mismo criterio: solo `auth`, no depende de una actividad concreta porque el ejemplo es genérico):

```php
Route::middleware('auth')->get('/preguntas/ejemplo', [ImportacionCuestionarioController::class, 'ejemplo'])
    ->name('preguntas.ejemplo');
```

## Frontend

En `resources/views/actividades/edit.blade.php`, dentro de la tarjeta "Importar desde Google Forms" (línea ~432-449), agregar antes del `<form>` de subida un enlace con el mismo estilo `btn-outline` que usa la rúbrica (`edit.blade.php:882-888`):

```blade
<a href="{{ route('preguntas.ejemplo') }}" target="_blank"
   class="btn-outline w-full mb-3 flex items-center justify-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    Descargar archivo de ejemplo
</a>
```

## Testing

Agregar un test a `tests/Feature/ImportarCuestionarioGoogleFormsTest.php`:

| # | Test | Resultado esperado |
|---|------|--------------------|
| 1 | `GET preguntas.ejemplo` autenticado | Status 200, `Content-Disposition` de descarga, y el JSON devuelto pasa la misma validación (`Validator::make` con las reglas de `store()`) sin errores — detecta si el ejemplo se desincroniza del esquema real |

No se agrega test de UI para el enlace en Blade (no amerita, mismo criterio que el resto de esta pantalla).

## Fuera de alcance

- Generar el ejemplo dinámicamente a partir de las reglas del `Validator` (se mantiene como array estático, igual que `EjemploRubricaExport`).
- Traducir o versionar el ejemplo si `version` deja de ser `1` en el futuro — se actualiza a mano si el esquema cambia.

## Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/ImportacionCuestionarioController.php` | Nuevo método `ejemplo()` |
| `routes/web.php` | Nueva ruta `preguntas.ejemplo` |
| `resources/views/actividades/edit.blade.php` | Enlace "Descargar archivo de ejemplo" en la tarjeta de importación |
| `tests/Feature/ImportarCuestionarioGoogleFormsTest.php` | 1 test nuevo |
