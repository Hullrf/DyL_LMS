# Rediseño Visual Fase 2C (Actividades, Calificaciones y Certificados) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar todo color Tailwind crudo (rojo/verde/azul/amarillo/púrpura/naranja-sin-prefijo) de las 12 vistas del dominio "actividades, calificaciones y certificados" (`actividades/`, `calificaciones/`, `certificados/`), migrando a `dyl-orange`/`dyl-graphite` según el marco ya establecido en la Fase 1/2A/2B. Estas 3 pantallas están explícitamente cubiertas por los mockups del spec y usan el sistema de feedback semántico (correcto/incorrecto, aprobado/reprobado, válido/inválido), por lo que requieren más juicio que un recolor mecánico.

**Architecture:** Migración archivo por archivo aplicando un marco de mapeo fijo (ver "Marco de mapeo" abajo). Donde ya existe una clase de componente equivalente en `app.css` (`.alert-success`, `.alert-error`, `.badge-*`), se reemplaza el bloque completo por esa clase en vez de traducir colores uno a uno (consolidación DRY, mismo criterio que Fase 2B). Se agrupa en 3 tareas de migración por subdominio + 1 de verificación.

**Tech Stack:** Laravel 12 Blade + Tailwind CSS 3 (JIT) + Alpine.js. Sin dependencias nuevas.

## Global Constraints

Heredadas de la Fase 2B:
- Solo dos familias de color en toda la UI: `dyl-orange` y `dyl-graphite` — sin excepción. Esto incluye clases Tailwind de naranja **sin el prefijo `dyl-`** (`bg-orange-50`, `text-orange-500`) — también se migran a `dyl-orange-*`, no son el mismo token de diseño aunque el matiz se parezca.
- Acciones destructivas nunca se recolorean a naranja. Botón sólido destructivo → `bg-dyl-graphite-900 hover:bg-dyl-graphite-800 text-white`; badge/soft → `bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold`; texto/ícono destructivo suelto (ej. un botón "×" de eliminar) → `text-dyl-graphite-500 hover:text-dyl-graphite-900`.
- Cuando dos estados conviven en el mismo eje visual, el estado final/positivo se lleva `dyl-orange-600/700` vívido; el estado intermedio/anterior/negativo se lleva `dyl-graphite-400/500/600` apagado — nunca los dos a naranja.
- Mensajes flash (`session('success')`/`session('error')`) se reemplazan por `alert alert-success` / `alert alert-error` de `app.css`, no por traducción de color manual.
- `gray-*` no forma parte de este barrido — se deja tal cual.

Nuevas para esta fase (dominio con estados semánticos: correcto/incorrecto, aprobado/reprobado, plazo, válido/inválido):

- **Errores en línea de formulario** (`@error`, validación JS): `text-red-600` → `text-dyl-graphite-900 font-semibold` (nunca solo recolor sin subir el peso — es la convención `.form-error` del spec: "texto graphite-900 en negrita... en vez de text-red-600").
- **Indicador de plazo de 3 vías** (aparece en `actividades/show.blade.php`, `actividades/edit.blade.php` y `actividades/partials/estado-plazo-bloqueado.blade.php` — deben quedar visualmente idénticos entre sí):
  | Estado | Fondo/borde | Texto | Ícono |
  |---|---|---|---|
  | `abierta` (éxito) | `bg-dyl-orange-50 border-dyl-orange-200` | `text-dyl-orange-700/800` | `text-dyl-orange-500` |
  | `pendiente` (info) | `bg-dyl-graphite-50 border-dyl-graphite-200` | `text-dyl-graphite-700` | `text-dyl-graphite-400/500` |
  | vencida (advertencia) | `bg-dyl-graphite-50 border-2 border-dyl-orange-300` | `text-dyl-graphite-900 font-bold/semibold` | `text-dyl-orange-600` |
- **Íconos de tipo de recurso** (documento/imagen/video/texto/enlace en `actividades/show.blade.php` y `actividades/edit.blade.php`): hoy cada tipo tiene un matiz distinto (rojo/naranja/púrpura/azul/verde) para diferenciarse entre sí — eso es una categoría, no un estado, así que **todos convergen a `dyl-graphite-500`** (mismo criterio que la Fase 2B aplicó al badge de tipo de actividad anidada, que pasó de púrpura a grafito). Se diferencian por el ícono (SVG distinto por tipo) y el label, no por color.
- **Correcto/incorrecto en cuestionarios** (`actividades/edit.blade.php` al crear preguntas V/F, `calificaciones/revisar-cuestionario.blade.php` al revisar respuestas): correcto → `dyl-orange` vívido (fondo/borde/texto/ring); incorrecto → `dyl-graphite` con relleno más marcado que el "sin elegir" (`bg-dyl-graphite-100 border-dyl-graphite-300 text-dyl-graphite-900 font-semibold`, no solo un borde suave) para que se note sin depender del matiz.
- **Spinners de carga** (Office viewer, importación de rúbrica): todos a `text-dyl-orange-600` (proceso en curso = acento de marca).
- **Paneles informativos estáticos** (avisos, resúmenes, "enunciado de la actividad"): `bg-blue-50` → `bg-dyl-graphite-50` (no `dyl-orange` — el naranja se reserva para elementos interactivos/de acento, no para cajas de solo lectura).
- **Totales interactivos en vivo** (contador de puntos de rúbrica que se recalcula con Alpine): sí usan `dyl-orange` (es un acento que refleja una acción del usuario en tiempo real, no un texto estático).

---

### Task 1: Migrar `resources/views/actividades/*` (6 archivos)

**Files:**
- Modify: `resources/views/actividades/show.blade.php`
- Modify: `resources/views/actividades/edit.blade.php`
- Modify: `resources/views/actividades/create.blade.php`
- Modify: `resources/views/actividades/partials/cuestionario-con-inicio.blade.php`
- Modify: `resources/views/actividades/partials/estado-plazo-bloqueado.blade.php`
- Modify: `resources/views/actividades/partials/formulario-cuestionario.blade.php`

**Interfaces:** Ninguna — solo cambian clases CSS, no lógica ni estructura de datos.

- [ ] **Step 1: `actividades/show.blade.php` — enlaces, descripción y puntaje**

```diff
- <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
+ <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">
```
(línea 13)

```diff
- <p class="text-2xl font-bold text-blue-600">{{ $actividad->puntaje_maximo }}</p>
+ <p class="text-2xl font-bold text-dyl-orange-600">{{ $actividad->puntaje_maximo }}</p>
```
(línea 31, puntaje máximo destacado)

```diff
- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-gray-700 text-sm">
+ <div class="bg-dyl-graphite-50 border border-dyl-graphite-200 rounded-lg p-4 text-gray-700 text-sm">
```
(línea 41, caja de descripción — panel informativo estático)

- [ ] **Step 2: `actividades/show.blade.php` — indicador de plazo (3 vías)**

```diff
    @if($estadoPlazo !== 'sin_plazo')
    <div class="mb-6 flex items-start gap-3 px-5 py-4 rounded-xl border
-       @if($estadoPlazo === 'abierta')   bg-green-50  border-green-200
-       @elseif($estadoPlazo === 'pendiente') bg-yellow-50 border-yellow-200
-       @else bg-red-50 border-red-200 @endif">
+       @if($estadoPlazo === 'abierta')   bg-dyl-orange-50 border-dyl-orange-200
+       @elseif($estadoPlazo === 'pendiente') bg-dyl-graphite-50 border-dyl-graphite-200
+       @else bg-dyl-graphite-50 border-2 border-dyl-orange-300 @endif">
        {{-- Icono --}}
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0
-           @if($estadoPlazo === 'abierta') text-green-500
-           @elseif($estadoPlazo === 'pendiente') text-yellow-500
-           @else text-red-500 @endif"
+           @if($estadoPlazo === 'abierta') text-dyl-orange-500
+           @elseif($estadoPlazo === 'pendiente') text-dyl-graphite-400
+           @else text-dyl-orange-600 @endif"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1 text-sm">
            @if($estadoPlazo === 'abierta')
-               <span class="font-semibold text-green-800">Actividad abierta</span>
+               <span class="font-semibold text-dyl-orange-800">Actividad abierta</span>
                @if($actividad->fecha_apertura)
-                   <span class="text-green-700"> — disponible desde el {{ $actividad->fecha_apertura->format('d/m/Y H:i') }}</span>
+                   <span class="text-dyl-orange-700"> — disponible desde el {{ $actividad->fecha_apertura->format('d/m/Y H:i') }}</span>
                @endif
                @if($actividad->fecha_cierre)
-                   <span class="text-green-700"> · Cierra el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
+                   <span class="text-dyl-orange-700"> · Cierra el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
                @endif
            @elseif($estadoPlazo === 'pendiente')
-               <span class="font-semibold text-yellow-800">Aún no disponible</span>
-               <span class="text-yellow-700"> — abre el <strong>{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</strong></span>
+               <span class="font-semibold text-dyl-graphite-700">Aún no disponible</span>
+               <span class="text-dyl-graphite-600"> — abre el <strong>{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</strong></span>
            @else
-               <span class="font-semibold text-red-800">Plazo vencido</span>
-               <span class="text-red-700"> — la entrega cerró el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
+               <span class="font-semibold text-dyl-graphite-900">Plazo vencido</span>
+               <span class="text-dyl-graphite-700"> — la entrega cerró el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
            @endif
        </div>
    </div>
    @endif
```
(líneas 52-84 — usa el marco de "indicador de plazo de 3 vías" de Global Constraints)

- [ ] **Step 3: `actividades/show.blade.php` — íconos de tipo de recurso (convergen a grafito)**

```diff
{{-- DOCUMENTO --}}
- <div class="flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-red-300 hover:shadow-sm transition-all">
-     <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
-         <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-dyl-graphite-300 hover:shadow-sm transition-all">
+     <div class="w-10 h-10 rounded-lg bg-dyl-graphite-100 flex items-center justify-center flex-shrink-0">
+         <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 155-157)

```diff
{{-- IMAGEN --}}
- <div class="w-9 h-9 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
-     <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="w-9 h-9 rounded-lg bg-dyl-graphite-100 flex items-center justify-center flex-shrink-0">
+     <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 211-212 — nota: usaba `orange-*` sin prefijo `dyl-`, no es el mismo token; además converge a grafito igual que los demás tipos, ver Global Constraints)

```diff
{{-- VIDEO --}}
- <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
-     <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="w-9 h-9 rounded-lg bg-dyl-graphite-100 flex items-center justify-center flex-shrink-0">
+     <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 243-244)

```diff
{{-- TEXTO --}}
- <div class="px-5 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2">
-     <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="px-5 py-3 bg-dyl-graphite-100 border-b border-dyl-graphite-200 flex items-center gap-2">
+     <svg class="w-4 h-4 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
      </svg>
-     <span class="font-medium text-blue-800 text-sm">{{ $recurso->titulo }}</span>
-     @if($recurso->descripcion)<span class="text-xs text-blue-500 ml-1">— {{ $recurso->descripcion }}</span>@endif
+     <span class="font-medium text-dyl-graphite-800 text-sm">{{ $recurso->titulo }}</span>
+     @if($recurso->descripcion)<span class="text-xs text-dyl-graphite-500 ml-1">— {{ $recurso->descripcion }}</span>@endif
```
(líneas 270-275)

```diff
{{-- ENLACE EXTERNO --}}
- <a href="{{ $recurso->url }}" target="_blank" rel="noopener noreferrer"
-    class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-green-400 hover:shadow-sm transition-all group">
-     <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0 group-hover:bg-green-100 transition-colors">
-         <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <a href="{{ $recurso->url }}" target="_blank" rel="noopener noreferrer"
+    class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-dyl-graphite-400 hover:shadow-sm transition-all group">
+     <div class="w-10 h-10 rounded-lg bg-dyl-graphite-100 flex items-center justify-center flex-shrink-0 group-hover:bg-dyl-graphite-200 transition-colors">
+         <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
          </svg>
      </div>
      <div class="flex-1 min-w-0">
-         <p class="font-medium text-gray-900 group-hover:text-green-700 transition-colors">{{ $recurso->titulo }}</p>
+         <p class="font-medium text-gray-900 group-hover:text-dyl-graphite-700 transition-colors">{{ $recurso->titulo }}</p>
          @if($recurso->descripcion)<p class="text-xs text-gray-500 mt-0.5">{{ $recurso->descripcion }}</p>@endif
          <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $recurso->url }}</p>
      </div>
-     <svg class="w-4 h-4 text-gray-400 group-hover:text-green-500 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+     <svg class="w-4 h-4 text-gray-400 group-hover:text-dyl-graphite-600 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 285-296)

- [ ] **Step 4: `actividades/show.blade.php` — botones "Ver" del visor y modal**

Los 3 botones "Ver" (PDF/Office/imagen, cuando la descarga está bloqueada) son acciones interactivas, no categorías — se quedan en naranja:

```diff
- class="btn-outline btn-sm flex-shrink-0 text-blue-600 border-blue-300 hover:bg-blue-50">
+ class="btn-outline btn-sm flex-shrink-0 text-dyl-orange-600 border-dyl-orange-300 hover:bg-dyl-orange-50">
```
(aplica idéntico a líneas 176, 185 y 194 — botones Ver PDF, Ver Office, Ver imagen)

Modal — spinner y error del visor Office (convención de error en línea + spinner de Global Constraints):

```diff
- <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
+ <svg class="animate-spin h-8 w-8 text-dyl-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
```
(línea 350)

```diff
- <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <svg class="w-12 h-12 text-dyl-orange-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
- <p class="text-red-600 text-sm font-medium text-center" x-text="officeError"></p>
+ <p class="text-dyl-graphite-900 text-sm font-semibold text-center" x-text="officeError"></p>
```
(líneas 358-361 — ícono de error en `dyl-orange-600` + texto `dyl-graphite-900` en negrita, convención de error en línea del spec)

- [ ] **Step 5: `actividades/show.blade.php` — actividad completada / marcar completada**

```diff
- <div class="mb-6 flex items-center gap-3 px-5 py-4 bg-green-50 border border-green-200 rounded-xl">
-     <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="mb-6 flex items-center gap-3 px-5 py-4 bg-dyl-orange-50 border border-dyl-orange-200 rounded-xl">
+     <svg class="w-5 h-5 text-dyl-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
-     <span class="text-sm font-medium text-green-700">Actividad completada</span>
+     <span class="text-sm font-medium text-dyl-orange-700">Actividad completada</span>
  </div>
  @elseif(!$actividad->tieneCalificacion())
  <div class="mb-6">
      <form action="{{ route('actividades.completar', $actividad) }}" method="POST">
          @csrf
          <button type="submit"
-                 class="w-full sm:w-auto bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-medium transition-colors flex items-center gap-2">
+                 class="w-full sm:w-auto bg-dyl-orange-600 text-white px-8 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium transition-colors flex items-center gap-2">
```
(líneas 414-425)

- [ ] **Step 6: `actividades/show.blade.php` — tabla de rúbrica**

```diff
- <span class="text-green-600 font-bold">{{ number_format($nivelHeader->puntos, 2) }} pts</span>
+ <span class="text-dyl-orange-600 font-bold">{{ number_format($nivelHeader->puntos, 2) }} pts</span>
```
(línea 454, encabezado de nivel)

```diff
- <td class="px-3 py-4 align-top text-xs text-gray-600 leading-relaxed
-     {{ $estaSeleccionado ? 'bg-green-50 border-l-2 border-green-400' : '' }}">
+ <td class="px-3 py-4 align-top text-xs text-gray-600 leading-relaxed
+     {{ $estaSeleccionado ? 'bg-dyl-orange-50 border-l-2 border-dyl-orange-400' : '' }}">
      @if($estaSeleccionado)
-         <span class="inline-block mb-1 text-green-600 font-semibold text-[10px] uppercase tracking-wide">✓ Nivel obtenido</span><br>
+         <span class="inline-block mb-1 text-dyl-orange-600 font-semibold text-[10px] uppercase tracking-wide">✓ Nivel obtenido</span><br>
      @endif
      {{ $nivel->descripcion }}
-     <span class="block mt-2 font-bold text-sm {{ $estaSeleccionado ? 'text-green-600' : 'text-gray-400' }}">
+     <span class="block mt-2 font-bold text-sm {{ $estaSeleccionado ? 'text-dyl-orange-600' : 'text-gray-400' }}">
```
(líneas 465-471)

- [ ] **Step 7: `actividades/show.blade.php` — flujo de múltiples intentos**

Bloque "intento pendiente de revisión" (advertencia — requiere acción del instructor, no es positivo):

```diff
- <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
-     <h2 class="font-bold text-yellow-800 mb-2">Intento pendiente de revisión</h2>
+ <div class="bg-dyl-graphite-50 border-2 border-dyl-orange-300 rounded-lg p-6 mb-6">
+     <h2 class="font-bold text-dyl-graphite-900 mb-2">Intento pendiente de revisión</h2>
      <p class="text-gray-600">
          Tu intento más reciente incluye preguntas de respuesta corta que el instructor debe revisar
          antes de que puedas iniciar un nuevo intento.
      </p>
      <div class="mt-4">
-         <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
+         <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-dyl-graphite-800 hover:bg-dyl-graphite-900 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              Volver a la lección
          </a>
      </div>
  </div>
```
(líneas 491-502)

Bloque "respuesta enviada / calificación vigente" (éxito — aparece 2 veces con estructura idéntica: multi-intento en líneas 505-548 y comportamiento sin cambios en líneas 561-581):

```diff
- <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
-     <h2 class="font-bold text-green-800 mb-2">
+ <div class="bg-dyl-orange-50 border border-dyl-orange-200 rounded-lg p-6 mb-6">
+     <h2 class="font-bold text-dyl-orange-800 mb-2">
          {{-- (título varía entre los 2 bloques, sin cambios) --}}
      </h2>
      @if($respuesta->calificacion !== null)
-         <p class="text-2xl font-bold text-green-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
+         <p class="text-2xl font-bold text-dyl-orange-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
      @else
          <p class="text-gray-600">Tu respuesta está pendiente de calificación.</p>
      @endif
      @if($respuesta->feedback)
-         <div class="mt-3 pt-3 border-t border-green-200">
+         <div class="mt-3 pt-3 border-t border-dyl-orange-200">
              <p class="text-sm font-medium text-gray-700 mb-1">Retroalimentación:</p>
              <p class="text-sm text-gray-600">{{ $respuesta->feedback }}</p>
          </div>
      @endif
      <div class="mt-4">
-         <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
+         <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="inline-flex items-center bg-dyl-orange-600 hover:bg-dyl-orange-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
```
(aplica a líneas 506-543 y, con el mismo patrón, a 562-576; en el bloque de líneas 505-548 hay además un historial de intentos con `border-t border-green-200` en línea 526 y `text-green-700`/`text-gray-500` en línea 532 — ambos siguen la misma sustitución: `border-t border-dyl-orange-200` y `text-dyl-orange-700`/`text-gray-500`)

- [ ] **Step 8: `actividades/show.blade.php` — formulario de respuesta (ensayo/tarea/práctica)**

```diff
- <textarea name="respuesta" rows="8"
-           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
+ <textarea name="respuesta" rows="8"
+           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600"
           placeholder="Escribe tu respuesta aquí...">{{ old('respuesta') }}</textarea>
- @error('respuesta')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
+ @error('respuesta')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
```
(línea 596-599)

Dropzone de archivo adjunto (3 estados: con archivo / con error / vacío):

```diff
  <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
         :class="nombre && !errorArchivo
-            ? 'border-green-400 bg-green-50/40 hover:bg-green-50'
+            ? 'border-dyl-orange-400 bg-dyl-orange-50/40 hover:bg-dyl-orange-50'
             : errorArchivo
-                ? 'border-red-400 bg-red-50/40'
-                : 'border-gray-300 bg-gray-50/40 hover:border-blue-400 hover:bg-blue-50/30'">
+                ? 'border-dyl-graphite-400 bg-dyl-graphite-100'
+                : 'border-gray-300 bg-gray-50/40 hover:border-dyl-orange-400 hover:bg-dyl-orange-50/30'">
```
(línea 607-612)

```diff
- <div x-show="nombre && !errorArchivo" class="flex items-center gap-2 px-4 text-green-700 pointer-events-none">
+ <div x-show="nombre && !errorArchivo" class="flex items-center gap-2 px-4 text-dyl-orange-700 pointer-events-none">
```
(línea 621)

```diff
- <div x-show="errorArchivo" class="flex items-center gap-2 px-4 text-red-600 pointer-events-none">
+ <div x-show="errorArchivo" class="flex items-center gap-2 px-4 text-dyl-graphite-900 font-semibold pointer-events-none">
```
(línea 628)

```diff
- <p x-show="errorArchivo" x-text="errorArchivo" class="text-red-600 text-xs mt-1"></p>
- @error('archivo_adjunto')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
+ <p x-show="errorArchivo" x-text="errorArchivo" class="text-dyl-graphite-900 font-semibold text-xs mt-1"></p>
+ @error('archivo_adjunto')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
```
(líneas 653-654)

```diff
- <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
+ <button type="submit" class="bg-dyl-orange-600 text-white px-8 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium">
      Enviar respuesta
  </button>
```
(línea 658)

- [ ] **Step 9: `actividades/show.blade.php` — actividad de consulta (sin calificación)**

```diff
- <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="ml-auto inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium shrink-0">
+ <a href="{{ route('lecciones.show', $actividad->leccion) }}" class="ml-auto inline-flex items-center text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium shrink-0">
```
(línea 677)

- [ ] **Step 10: `actividades/edit.blade.php` — badge de tipo y paneles**

```diff
  <span class="badge mb-4
      @if($actividad->tipo === 'cuestionario') badge-blue
-     @elseif($actividad->tipo === 'ensayo') bg-purple-100 text-purple-800
+     @elseif($actividad->tipo === 'ensayo') bg-dyl-graphite-100 text-dyl-graphite-600
      @elseif($actividad->tipo === 'tarea') badge-yellow
      @else badge-green @endif">
```
(línea 16-20 — `badge-blue`/`badge-yellow`/`badge-green` ya son alias de `.badge-info`/`.badge-warning`/`.badge-success` en `app.css` desde la Fase 1, no requieren cambio; solo el literal `purple` de "ensayo")

```diff
- <div class="mb-4 p-4 bg-blue-50 rounded-xl border border-blue-200">
+ <div class="mb-4 p-4 bg-dyl-graphite-50 rounded-xl border border-dyl-graphite-200">
      <p class="text-sm font-semibold text-gray-800 mb-3">Intentos del cuestionario</p>
```
(línea 46, panel informativo estático)

- [ ] **Step 11: `actividades/edit.blade.php` — indicador de plazo (idéntico al de `show.blade.php`)**

```diff
  <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium
-     @if($estadoPlazo === 'abierta')  bg-green-50 text-green-700 border border-green-200
-     @elseif($estadoPlazo === 'pendiente') bg-yellow-50 text-yellow-700 border border-yellow-200
-     @else bg-red-50 text-red-700 border border-red-200 @endif">
+     @if($estadoPlazo === 'abierta')  bg-dyl-orange-50 text-dyl-orange-700 border border-dyl-orange-200
+     @elseif($estadoPlazo === 'pendiente') bg-dyl-graphite-50 text-dyl-graphite-700 border border-dyl-graphite-200
+     @else bg-dyl-graphite-50 text-dyl-graphite-900 font-semibold border-2 border-dyl-orange-300 @endif">
      <span class="w-2 h-2 rounded-full flex-shrink-0
-         @if($estadoPlazo === 'abierta') bg-green-500
-         @elseif($estadoPlazo === 'pendiente') bg-yellow-500
-         @else bg-red-500 @endif"></span>
+         @if($estadoPlazo === 'abierta') bg-dyl-orange-500
+         @elseif($estadoPlazo === 'pendiente') bg-dyl-graphite-400
+         @else bg-dyl-orange-600 @endif"></span>
```
(líneas 129-136, usa el mismo marco de 3 vías que Step 2)

- [ ] **Step 12: `actividades/edit.blade.php` — errores en línea y file inputs**

```diff
- <p x-show="archivoError" x-text="archivoError" class="text-red-600 text-xs mt-1"></p>
+ <p x-show="archivoError" x-text="archivoError" class="text-dyl-graphite-900 font-semibold text-xs mt-1"></p>
```
(aplica a líneas 216 y 242, idéntico)

```diff
- class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
+ class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-dyl-orange-50 file:text-dyl-orange-700 hover:file:bg-dyl-orange-100 cursor-pointer"
```
(línea 205, input de archivo "documento" — la variante "imagen" en línea 225 ya usa `dyl-orange`, esto la iguala)

- [ ] **Step 13: `actividades/edit.blade.php` — íconos de tipo de recurso (lista existente, converge a grafito)**

```diff
- <svg class="w-5 h-5 mt-0.5 flex-shrink-0
-     @if($recurso->tipo === 'documento') text-red-500
-     @elseif($recurso->tipo === 'imagen') text-orange-500
-     @elseif($recurso->tipo === 'video') text-purple-500
-     @elseif($recurso->tipo === 'texto') text-blue-500
-     @else text-green-500 @endif"
+ <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-dyl-graphite-500"
       fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 289-294 — los 5 tipos convergen al mismo grafito, así que se colapsa todo el condicional a una sola clase; consolidación DRY explícita del spec)

```diff
- <button type="submit" class="text-gray-500 hover:text-red-600 transition-colors"
+ <button type="submit" class="text-gray-500 hover:text-dyl-graphite-900 transition-colors"
          title="Eliminar">
```
(línea 305, botón eliminar recurso — patrón de texto destructivo suelto de Global Constraints)

- [ ] **Step 14: `actividades/edit.blade.php` — resumen de puntaje, botón "+ Pregunta" e importar**

```diff
- <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-100 rounded-xl text-sm">
+ <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-dyl-graphite-50 border border-dyl-graphite-100 rounded-xl text-sm">
```
(línea 326, banner informativo estático)

```diff
- <button type="submit" class="btn bg-green-600 text-white hover:bg-green-700">
+ <button type="submit" class="btn bg-dyl-orange-600 text-white hover:bg-dyl-orange-700">
      + Pregunta
  </button>
```
(línea 386, acción de creación positiva)

```diff
- class="flex-1 text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
+ class="flex-1 text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-dyl-orange-50 file:text-dyl-orange-700 hover:file:bg-dyl-orange-100 cursor-pointer"
```
(línea 451, input de archivo para importar Google Forms)

```diff
- <button type="submit" class="btn bg-blue-600 text-white hover:bg-blue-700 shrink-0">Importar</button>
+ <button type="submit" class="btn bg-dyl-orange-600 text-white hover:bg-dyl-orange-700 shrink-0">Importar</button>
```
(línea 453)

- [ ] **Step 15: `actividades/edit.blade.php` — selector Verdadero/Falso (crear pregunta)**

```diff
  <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors"
         :class="correctaVF === 'verdadero'
-            ? 'border-green-500 bg-green-50 text-green-700 font-semibold ring-1 ring-green-400'
+            ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-700 font-semibold ring-1 ring-dyl-orange-400'
             : 'border-gray-200 text-gray-500 hover:border-gray-300'">
      <input type="radio" name="correcta_vf" value="verdadero"
             x-model="correctaVF" class="sr-only">
-     <span class="text-base" :class="correctaVF === 'verdadero' ? 'text-green-600' : 'text-gray-300'">✓</span>
+     <span class="text-base" :class="correctaVF === 'verdadero' ? 'text-dyl-orange-600' : 'text-gray-300'">✓</span>
      Verdadero
  </label>
  <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors"
         :class="correctaVF === 'falso'
-            ? 'border-red-500 bg-red-50 text-red-700 font-semibold ring-1 ring-red-400'
+            ? 'border-dyl-graphite-600 bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold ring-1 ring-dyl-graphite-400'
             : 'border-gray-200 text-gray-500 hover:border-gray-300'">
      <input type="radio" name="correcta_vf" value="falso"
             x-model="correctaVF" class="sr-only">
-     <span class="text-base" :class="correctaVF === 'falso' ? 'text-red-500' : 'text-gray-300'">✗</span>
+     <span class="text-base" :class="correctaVF === 'falso' ? 'text-dyl-graphite-600' : 'text-gray-300'">✗</span>
      Falso
  </label>
```
(líneas 395-412 — correcto/incorrecto: naranja vívido vs. grafito marcado, marco de Global Constraints)

- [ ] **Step 16: `actividades/edit.blade.php` — lista de preguntas (badges, editar, V/F, opciones)**

```diff
- @if($pregunta->tipo !== 'respuesta_corta' && !$pregunta->opciones->contains('es_correcta', true))
-     <span class="ml-2 badge text-[10px] bg-yellow-100 text-yellow-700">Falta marcar la correcta</span>
+ @if($pregunta->tipo !== 'respuesta_corta' && !$pregunta->opciones->contains('es_correcta', true))
+     <span class="ml-2 badge text-[10px] bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold">Falta marcar la correcta</span>
```
(línea 471-472, advertencia)

```diff
- <button type="submit"
-         class="text-xs text-red-400 hover:text-red-600 transition-colors flex items-center gap-1">
+ <button type="submit"
+         class="text-xs text-dyl-graphite-500 hover:text-dyl-graphite-900 transition-colors flex items-center gap-1">
```
(línea 485-486, "Quitar imagen")

```diff
- <button type="button" @click="editing = !editing"
-         class="text-xs text-blue-600 hover:text-blue-800 font-medium">
+ <button type="button" @click="editing = !editing"
+         class="text-xs text-dyl-orange-600 hover:text-dyl-orange-700 font-medium">
```
(línea 497-498)

Selector V/F en modo edición (misma lógica que Step 15, pero con `{{ }}` en vez de `:class`):

```diff
- <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors
-     {{ $correctaVF === 'Verdadero' ? 'border-green-400 bg-green-50 text-green-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
+ <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors
+     {{ $correctaVF === 'Verdadero' ? 'border-dyl-orange-500 bg-dyl-orange-50 text-dyl-orange-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
```
(línea 530-531)

```diff
- <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors
-     {{ $correctaVF === 'Falso' ? 'border-red-400 bg-red-50 text-red-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
+ <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors
+     {{ $correctaVF === 'Falso' ? 'border-dyl-graphite-500 bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
```
(línea 536-537)

```diff
- <span class="font-medium {{ $correctaVF === 'Verdadero' ? 'text-green-700' : 'text-red-700' }}">
+ <span class="font-medium {{ $correctaVF === 'Verdadero' ? 'text-dyl-orange-700' : 'text-dyl-graphite-900' }}">
```
(línea 559, respuesta correcta en modo vista)

```diff
- <div class="flex items-center justify-between px-3 py-2 rounded-lg {{ $opcion->es_correcta ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}">
+ <div class="flex items-center justify-between px-3 py-2 rounded-lg {{ $opcion->es_correcta ? 'bg-dyl-orange-50 border border-dyl-orange-200' : 'bg-gray-50' }}">
      <div class="flex items-center gap-2">
          <form action="{{ route('opciones.marcarCorrecta', $opcion) }}" method="POST">
              @csrf @method('PUT')
              <button type="submit"
-                     class="text-lg leading-none {{ $opcion->es_correcta ? 'text-green-600 font-bold' : 'text-gray-300 hover:text-gray-400' }}"
+                     class="text-lg leading-none {{ $opcion->es_correcta ? 'text-dyl-orange-600 font-bold' : 'text-gray-300 hover:text-gray-400' }}"
```
(líneas 567-572, lista de opciones de "opción múltiple")

- [ ] **Step 17: `actividades/edit.blade.php` — respuestas recibidas (ensayo/tarea/práctica) y rúbrica**

```diff
- <p class="text-sm font-medium text-green-600 mt-1">
+ <p class="text-sm font-medium text-dyl-orange-600 mt-1">
      Calificación: {{ number_format($resp->calificacion, 2) }} / {{ number_format($actividad->puntaje_maximo, 2) }}
  </p>
```
(línea 648)

```diff
- <a href="{{ route('rubrica.ejemplo') }}" ...>
- {{-- sin cambios en este link, ya usa btn-outline --}}
```
(sin cambios — solo referencia de contexto, no tocar)

```diff
- <button type="button" @click="agregarNivel(ci)"
-         class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
+ <button type="button" @click="agregarNivel(ci)"
+         class="text-xs text-dyl-orange-600 hover:text-dyl-orange-700 flex items-center gap-1">
```
(línea 825-826, "+ Nivel")

```diff
- <div class="flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl">
-     <span class="text-sm font-medium text-blue-800">Puntaje máximo calculado:</span>
-     <span class="text-xl font-bold text-blue-700" x-text="totalPuntos + ' pts'"></span>
+ <div class="flex items-center justify-between px-4 py-3 bg-dyl-orange-50 border border-dyl-orange-200 rounded-xl">
+     <span class="text-sm font-medium text-dyl-orange-800">Puntaje máximo calculado:</span>
+     <span class="text-xl font-bold text-dyl-orange-700" x-text="totalPuntos + ' pts'"></span>
```
(línea 842-844 — total interactivo en vivo, sí lleva naranja según Global Constraints)

```diff
- <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800 space-y-1">
+ <div class="mb-4 p-4 bg-dyl-graphite-50 border border-dyl-graphite-200 rounded-xl text-sm text-dyl-graphite-700 space-y-1">
```
(línea 882, explicación de formato del modal de importación — estático)

```diff
- class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 cursor-pointer">
+ class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-dyl-orange-50 file:text-dyl-orange-700 cursor-pointer">
```
(línea 902)

```diff
- <p x-show="importando" class="text-sm text-blue-600 mt-1 flex items-center gap-2">
+ <p x-show="importando" class="text-sm text-dyl-orange-600 mt-1 flex items-center gap-2">
```
(línea 905, spinner de importación — convención de Global Constraints)

```diff
- <p x-show="importError" x-text="importError" class="text-sm text-red-600 mt-1"></p>
+ <p x-show="importError" x-text="importError" class="text-sm text-dyl-graphite-900 font-semibold mt-1"></p>
```
(línea 912, error en línea)

- [ ] **Step 18: `actividades/create.blade.php`**

```diff
- <a href="{{ route('cursos.edit', $leccion->modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
+ <a href="{{ route('cursos.edit', $leccion->modulo->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
```
(línea 6)

```diff
- class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
+ class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600">
```
(aplica a líneas 16, 48, 63, 68, 78 y 82 — todos los `focus:ring-blue-500` de inputs/selects del formulario, idéntico)

```diff
- <p x-show="tipo === 'tarea'" x-cloak
-    class="mt-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 flex items-start gap-2">
+ <p x-show="tipo === 'tarea'" x-cloak
+    class="mt-2 text-xs text-dyl-graphite-700 bg-dyl-graphite-50 border border-dyl-graphite-200 rounded-lg px-3 py-2 flex items-start gap-2">
```
(línea 30-31, aviso informativo)

```diff
- @error('titulo')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
+ @error('titulo')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
```
(línea 49)

```diff
- <div class="mb-6 p-5 bg-blue-50 rounded-xl border border-blue-200" x-show="tipo === 'cuestionario'" x-cloak>
+ <div class="mb-6 p-5 bg-dyl-graphite-50 rounded-xl border border-dyl-graphite-200" x-show="tipo === 'cuestionario'" x-cloak>
```
(línea 71)

```diff
- <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
+ <button type="submit" class="bg-dyl-orange-600 text-white px-6 py-2 rounded-lg hover:bg-dyl-orange-700">
      Crear y configurar
  </button>
```
(línea 116)

- [ ] **Step 19: `actividades/partials/cuestionario-con-inicio.blade.php`**

```diff
  class="sticky top-4 z-10 mb-4 flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-mono text-lg font-bold"
- :class="segundos <= 60 ? 'bg-red-50 text-red-700 animate-pulse' : 'bg-blue-50 text-blue-700'">
+ :class="segundos <= 60 ? 'bg-dyl-graphite-50 border-2 border-dyl-orange-300 text-dyl-graphite-900 font-bold animate-pulse' : 'bg-dyl-graphite-50 text-dyl-graphite-700'">
```
(línea 28-29 — cronómetro: últimos 60s = advertencia con borde naranja grueso pulsante, sin depender de rojo)

```diff
- <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
+ <button type="submit" class="bg-dyl-orange-600 text-white px-8 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium">
      Enviar respuesta
  </button>
```
(línea 40)

```diff
- <svg class="w-12 h-12 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <svg class="w-12 h-12 text-dyl-graphite-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(línea 48, ícono de pantalla de inicio — informativo)

```diff
- <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
+ <button type="submit" class="bg-dyl-orange-600 text-white px-8 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium">
      {{ $respuesta ? 'Reintentar' : 'Iniciar cuestionario' }}
  </button>
```
(línea 72)

- [ ] **Step 20: `actividades/partials/estado-plazo-bloqueado.blade.php`**

Debe quedar visualmente idéntico al tramo "pendiente"/"vencida" del indicador de plazo de los Steps 2 y 11:

```diff
  @if($estadoPlazo === 'pendiente')
-     <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+     <svg class="w-12 h-12 text-dyl-graphite-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-gray-700 font-medium">La actividad estará disponible el</p>
-     <p class="text-xl font-bold text-yellow-600 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
+     <p class="text-xl font-bold text-dyl-graphite-700 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
  @else
-     <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+     <svg class="w-12 h-12 text-dyl-orange-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5V7m0 0V5m0 2h2M12 7H10m10 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-gray-700 font-medium">El plazo de entrega venció el</p>
-     <p class="text-xl font-bold text-red-600 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
+     <p class="text-xl font-bold text-dyl-graphite-900 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
  @endif
```
(líneas 3-13)

- [ ] **Step 21: `actividades/partials/formulario-cuestionario.blade.php`**

```diff
- <p class="mt-2 text-xs text-blue-600 font-medium">
+ <p class="mt-2 text-xs text-dyl-graphite-600 font-medium">
      Selecciona todas las respuestas correctas.
  </p>
```
(línea 26, hint informativo)

```diff
- <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
+ <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-dyl-orange-50 transition-colors">
      <input type="checkbox"
             name="respuesta_{{ $pregunta->id }}[]"
             value="{{ $opcion->id }}"
             {{ $checked ? 'checked' : '' }}
-            class="w-4 h-4 rounded text-blue-600">
+            class="w-4 h-4 rounded text-dyl-orange-600">
```
(líneas 32-37, opción múltiple con checkbox)

```diff
- <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
-        {{ $checked ? 'checked' : '' }}
-        class="text-blue-600" required>
+ <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
+        {{ $checked ? 'checked' : '' }}
+        class="text-dyl-orange-600" required>
```
(línea 48-50, opción única con radio)

```diff
-         contenedor.classList.remove('ring-2', 'ring-red-400');
+         contenedor.classList.remove('ring-2', 'ring-dyl-graphite-500');
```
(línea 72, JS — quitar el aro de "pendiente de responder")

```diff
-                 contenedor.classList.add('ring-2', 'ring-red-400');
+                 contenedor.classList.add('ring-2', 'ring-dyl-graphite-500');
```
(línea 92, JS — poner el aro de "pendiente de responder"; debe coincidir con la clase que se quita en la línea 72)

- [ ] **Step 22: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/actividades | grep -v dyl-orange`
Expected: sin resultados (el `grep -v dyl-orange` filtra los `dyl-orange-*` legítimos que sí matchean `orange-[0-9]+`).

Run: `php artisan test`
Expected: todo en verde.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 23: Commit**

```bash
git add resources/views/actividades
git commit -m "feat: migrar resources/views/actividades a naranja/grafito"
```

---

### Task 2: Migrar `resources/views/calificaciones/*` (3 archivos)

**Files:**
- Modify: `resources/views/calificaciones/mis-calificaciones.blade.php`
- Modify: `resources/views/calificaciones/revisar-cuestionario.blade.php`
- Modify: `resources/views/calificaciones/show.blade.php`

**Interfaces:** Ninguna — solo cambian clases CSS, no lógica ni estructura de datos.

- [ ] **Step 1: `mis-calificaciones.blade.php` — flash y KPIs**

```diff
- @if(session('success'))
-     <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
- @endif
+ @if(session('success'))
+     <div class="alert alert-success mb-6">{{ session('success') }}</div>
+ @endif
```
(línea 10-12 — consolidación DRY con `.alert-success`, mismo criterio que Fase 2B)

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $respuestas->count() }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $respuestas->count() }}</p>
```
(línea 26, KPI "Enviadas" — conteo neutro, no es un acento)

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $calificadas->count() }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $calificadas->count() }}</p>
```
(línea 30, KPI "Calificadas" — el KPI "Cursos" en línea 34 ya usa `dyl-orange-600`, se queda como el único acento de la fila)

```diff
- <p class="text-3xl font-bold mt-1 {{ $promedio >= 60 ? 'text-green-600' : 'text-red-500' }}">{{ $promedio }}%</p>
+ <p class="text-3xl font-bold mt-1 {{ $promedio >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">{{ $promedio }}%</p>
```
(línea 38 — dos estados en un eje: aprobado=naranja vivo, bajo=grafito apagado)

- [ ] **Step 2: `mis-calificaciones.blade.php` — encabezado de curso y tabla**

```diff
- <span class="ml-auto text-sm font-semibold {{ $promedioCurso >= 60 ? 'text-green-600' : 'text-red-500' }}">
+ <span class="ml-auto text-sm font-semibold {{ $promedioCurso >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      Promedio: {{ $promedioCurso }}%
  </span>
```
(línea 61, mismo patrón que Step 1)

```diff
- <span class="inline-block mt-1 ml-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">Cuenta para tu nota</span>
+ <span class="inline-block mt-1 ml-1 px-2 py-0.5 bg-dyl-orange-100 text-dyl-orange-700 text-xs rounded font-medium">Cuenta para tu nota</span>
```
(línea 86)

```diff
- <span class="text-xl font-bold {{ $pct >= 60 ? 'text-green-600' : 'text-red-500' }}">
      {{ number_format($respuesta->calificacion, 2) }}
      <span class="text-sm text-gray-400 font-normal">/ {{ number_format($max, 2) }}</span>
  </span>
- <span class="text-xs {{ $pct >= 60 ? 'text-green-600' : 'text-red-500' }}">{{ $pct }}%</span>
+ <span class="text-xl font-bold {{ $pct >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      {{ number_format($respuesta->calificacion, 2) }}
      <span class="text-sm text-gray-400 font-normal">/ {{ number_format($max, 2) }}</span>
  </span>
+ <span class="text-xs {{ $pct >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">{{ $pct }}%</span>
```
(líneas 100 y 104, mismo patrón, dentro de la misma celda)

```diff
- <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">En revisión</span>
+ <span class="px-2 py-1 bg-dyl-graphite-100 text-dyl-graphite-600 text-xs rounded-full font-medium">En revisión</span>
  @else
- <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full font-medium">Pendiente</span>
+ <span class="px-2 py-1 bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold text-xs rounded-full">Pendiente</span>
```
(líneas 107 y 109 — info vs. advertencia, distinguibles por peso de texto aunque el fondo sea el mismo grafito)

```diff
- <summary class="text-blue-600 hover:text-blue-800 text-sm">Ver comentario</summary>
- <p class="mt-2 text-gray-700 bg-blue-50 p-3 rounded text-xs whitespace-pre-wrap leading-relaxed">{{ $respuesta->feedback }}</p>
+ <summary class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver comentario</summary>
+ <p class="mt-2 text-gray-700 bg-dyl-graphite-50 p-3 rounded text-xs whitespace-pre-wrap leading-relaxed">{{ $respuesta->feedback }}</p>
```
(líneas 115-116)

```diff
- <a href="{{ route('cursos.index') }}" class="text-blue-600 hover:underline ml-1">Ver cursos disponibles</a>
+ <a href="{{ route('cursos.index') }}" class="text-dyl-orange-600 hover:underline ml-1">Ver cursos disponibles</a>
```
(línea 131, estado vacío)

- [ ] **Step 3: `revisar-cuestionario.blade.php` — respuesta y decisión (respuesta corta)**

```diff
- <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-gray-800">
+ <div class="bg-dyl-graphite-50 border border-dyl-graphite-200 rounded-lg px-4 py-3 text-sm text-gray-800">
      {{ $respuestaEstudiante ?? '— Sin respuesta —' }}
  </div>
```
(línea 70)

```diff
  <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm font-medium transition-colors"
-        :class="decision === '1' ? 'border-green-500 bg-green-50 text-green-700 ring-1 ring-green-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
+        :class="decision === '1' ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-700 ring-1 ring-dyl-orange-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
      <input type="radio" name="decisiones[{{ $pregunta->id }}]" value="1"
             x-model="decision" class="sr-only">
-     <span :class="decision === '1' ? 'text-green-600' : 'text-gray-300'">✓</span>
+     <span :class="decision === '1' ? 'text-dyl-orange-600' : 'text-gray-300'">✓</span>
      Correcto
      <span class="text-xs font-normal opacity-70">({{ $pregunta->puntaje }} pts)</span>
  </label>

  <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm font-medium transition-colors"
-        :class="decision === '0' ? 'border-red-500 bg-red-50 text-red-700 ring-1 ring-red-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
+        :class="decision === '0' ? 'border-dyl-graphite-600 bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold ring-1 ring-dyl-graphite-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
      <input type="radio" name="decisiones[{{ $pregunta->id }}]" value="0"
             x-model="decision" class="sr-only">
-     <span :class="decision === '0' ? 'text-red-500' : 'text-gray-300'">✗</span>
+     <span :class="decision === '0' ? 'text-dyl-graphite-600' : 'text-gray-300'">✗</span>
      Incorrecto
      <span class="text-xs font-normal opacity-70">(0 pts)</span>
  </label>
```
(líneas 78-94)

- [ ] **Step 4: `revisar-cuestionario.blade.php` — opción múltiple/V-F auto-calificado**

```diff
  <div class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
-     @if($esCorrecta && $fueElegida) bg-green-50 border border-green-200
-     @elseif(!$esCorrecta && $fueElegida) bg-red-50 border border-red-200
-     @elseif($esCorrecta) bg-green-50/50 border border-green-100
+     @if($esCorrecta && $fueElegida) bg-dyl-orange-50 border border-dyl-orange-200
+     @elseif(!$esCorrecta && $fueElegida) bg-dyl-graphite-100 border border-dyl-graphite-300
+     @elseif($esCorrecta) bg-dyl-orange-50/50 border border-dyl-orange-100
      @else bg-gray-50 border border-gray-100 @endif">
      <span class="w-4 text-center font-bold shrink-0
-         @if($esCorrecta) text-green-600
-         @elseif($fueElegida) text-red-500
+         @if($esCorrecta) text-dyl-orange-600
+         @elseif($fueElegida) text-dyl-graphite-600
          @else text-gray-300 @endif">
          @if($esCorrecta) ✓ @elseif($fueElegida) ✗ @else ○ @endif
      </span>
      <span class="{{ $fueElegida ? 'font-medium' : 'text-gray-600' }}">{{ $opcion->texto }}</span>
      @if($fueElegida && !$esCorrecta)
-         <span class="ml-auto text-xs text-red-400">Elegida incorrectamente</span>
+         <span class="ml-auto text-xs text-dyl-graphite-600 font-semibold">Elegida incorrectamente</span>
      @elseif($esCorrecta && $fueElegida)
-         <span class="ml-auto text-xs text-green-600">Correcta ✓</span>
+         <span class="ml-auto text-xs text-dyl-orange-600">Correcta ✓</span>
      @elseif($esCorrecta)
-         <span class="ml-auto text-xs text-green-500 opacity-60">Correcta (no elegida)</span>
+         <span class="ml-auto text-xs text-dyl-orange-500 opacity-60">Correcta (no elegida)</span>
      @endif
  </div>
```
(líneas 120-138 — la opción incorrecta elegida pasa de "borde rojo suave" a "relleno grafito sólido" para notarse sin depender del matiz)

```diff
- <span class="{{ $ptsObtenidos > 0 ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
+ <span class="{{ $ptsObtenidos > 0 ? 'text-dyl-orange-600 font-semibold' : 'text-gray-400' }}">
      {{ $ptsObtenidos }} / {{ $pregunta->puntaje }} pts
  </span>
```
(línea 144)

- [ ] **Step 5: `calificaciones/show.blade.php` — errores, rúbrica y focus rings**

```diff
- @if($errors->any())
-     <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">
-         @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
-     </div>
- @endif
+ @if($errors->any())
+     <div class="alert alert-error mb-4">
+         <div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
+     </div>
+ @endif
```
(línea 53-57 — consolidación con `.alert-error`; se envuelve el `@foreach` en un `<div>` porque `.alert` es `flex items-start gap-3`)

```diff
- <label class="cursor-pointer p-3 hover:bg-blue-50 transition-colors"
+ <label class="cursor-pointer p-3 hover:bg-dyl-orange-50 transition-colors"
         :class="selecciones[{{ $criterio->id }}] == {{ $nivel->id }} ? 'bg-dyl-orange-50 ring-2 ring-inset ring-dyl-orange-600' : ''">
      <input type="radio" ...>
      <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $nivel->descripcion }}</p>
-     <p class="text-sm font-bold text-green-600">{{ number_format($nivel->puntos, 2) }} pts</p>
+     <p class="text-sm font-bold text-dyl-orange-600">{{ number_format($nivel->puntos, 2) }} pts</p>
```
(líneas 89 y 97)

```diff
- <div class="flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl mb-4">
-     <span class="text-sm font-medium text-blue-800">Calificación actual:</span>
-     <span class="text-2xl font-bold text-blue-700">
+ <div class="flex items-center justify-between px-4 py-3 bg-dyl-orange-50 border border-dyl-orange-200 rounded-xl mb-4">
+     <span class="text-sm font-medium text-dyl-orange-800">Calificación actual:</span>
+     <span class="text-2xl font-bold text-dyl-orange-700">
          <span x-text="totalSeleccionado"></span>
-         <span class="text-base font-normal text-blue-500"> / {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
+         <span class="text-base font-normal text-dyl-orange-500"> / {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
```
(líneas 106-110, total en vivo — naranja según Global Constraints)

```diff
- class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
+ class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-dyl-orange-600 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
```
(línea 120, aplica también línea 160 con textarea de calificación manual, idéntico)

```diff
- :class="todosSeleccionados ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
+ :class="todosSeleccionados ? 'bg-dyl-orange-600 hover:bg-dyl-orange-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
```
(línea 125, botón de calificación con rúbrica)

- [ ] **Step 6: `calificaciones/show.blade.php` — calificación manual y respuesta del estudiante**

```diff
- class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-center text-2xl font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
+ class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-center text-2xl font-bold focus:ring-2 focus:ring-dyl-orange-600 focus:border-dyl-orange-600"
```
(línea 148)

```diff
- class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium transition-colors">
+ class="w-full bg-dyl-orange-600 text-white py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium transition-colors">
      Guardar Calificación
```
(línea 163-164, botón de calificación manual)

```diff
- <div class="bg-blue-50 rounded p-3 mb-4 text-sm text-blue-800">
+ <div class="bg-dyl-graphite-50 rounded p-3 mb-4 text-sm text-dyl-graphite-700">
      <span class="font-medium">Enunciado:</span>
```
(línea 182, caja estática)

```diff
- <a href="{{ $urlAdj }}" target="_blank" download
-    class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-xs font-medium">
+ <a href="{{ $urlAdj }}" target="_blank" download
+    class="inline-flex items-center gap-1.5 text-dyl-orange-600 hover:text-dyl-orange-700 text-xs font-medium">
```
(línea 197-198, aplica idéntico a la línea 257-258 del visor de PDF a ancho completo)

```diff
- <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(línea 242, ícono de tipo de archivo PDF — categórico, converge a grafito igual que los íconos de recurso de Task 1)

- [ ] **Step 7: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/calificaciones | grep -v dyl-orange`
Expected: sin resultados.

Run: `php artisan test`
Expected: todo en verde.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 8: Commit**

```bash
git add resources/views/calificaciones
git commit -m "feat: migrar resources/views/calificaciones a naranja/grafito"
```

---

### Task 3: Migrar `resources/views/certificados/*` (3 archivos)

**Files:**
- Modify: `resources/views/certificados/mis-certificados.blade.php`
- Modify: `resources/views/certificados/show.blade.php`
- Modify: `resources/views/certificados/verificar.blade.php`

**Interfaces:** Ninguna — solo cambian clases CSS, no lógica ni estructura de datos.

- [ ] **Step 1: `mis-certificados.blade.php` — flash, acento dorado→naranja y botones**

```diff
- @if(session('success'))
-     <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
- @endif
- @if(session('error'))
-     <div class="bg-red-100 border border-red-300 text-red-700 rounded-lg p-4 mb-6 text-sm">{{ session('error') }}</div>
- @endif
+ @if(session('success'))
+     <div class="alert alert-success mb-6">{{ session('success') }}</div>
+ @endif
+ @if(session('error'))
+     <div class="alert alert-error mb-6">{{ session('error') }}</div>
+ @endif
```
(líneas 10-15, consolidación DRY con `.alert-*`)

```diff
- <div class="bg-white rounded-xl shadow hover:shadow-md transition-shadow mb-4 overflow-hidden border-l-4 border-yellow-400">
      <div class="p-6 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div class="flex items-center gap-4">
-             <div class="w-12 h-12 bg-yellow-50 rounded-full flex items-center justify-center flex-shrink-0">
-                 <svg class="w-7 h-7 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
+ <div class="bg-white rounded-xl shadow hover:shadow-md transition-shadow mb-4 overflow-hidden border-l-4 border-dyl-orange-400">
      <div class="p-6 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div class="flex items-center gap-4">
+             <div class="w-12 h-12 bg-dyl-orange-50 rounded-full flex items-center justify-center flex-shrink-0">
+                 <svg class="w-7 h-7 text-dyl-orange-500" fill="currentColor" viewBox="0 0 20 20">
```
(líneas 18-22 — mapeo `dyl-gold`→`dyl-orange` del spec, aplicado al matiz "dorado" bare que quedaba)

```diff
- <span>Calificación: <strong class="text-green-600">{{ $cert->calificacion_final }}%</strong></span>
+ <span>Calificación: <strong class="text-dyl-orange-600">{{ $cert->calificacion_final }}%</strong></span>
```
(línea 31)

```diff
- <a href="{{ route('certificados.descargar', $cert) }}"
-    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium flex items-center gap-1">
+ <a href="{{ route('certificados.descargar', $cert) }}"
+    class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg hover:bg-dyl-orange-700 text-sm font-medium flex items-center gap-1">
```
(línea 42-43)

```diff
- <div class="w-20 h-20 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-4">
-     <svg class="w-10 h-10 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="w-20 h-20 bg-dyl-orange-50 rounded-full flex items-center justify-center mx-auto mb-4">
+     <svg class="w-10 h-10 text-dyl-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 54-55, estado vacío)

```diff
- <a href="{{ route('cursos.index') }}" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium">
+ <a href="{{ route('cursos.index') }}" class="bg-dyl-orange-600 text-white px-6 py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium">
      Ver cursos disponibles
  </a>
```
(línea 61)

- [ ] **Step 2: `certificados/show.blade.php` — encabezado y tarjeta del certificado**

```diff
- <a href="{{ route('certificados.descargar', $certificado) }}"
-    class="flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium">
+ <a href="{{ route('certificados.descargar', $certificado) }}"
+    class="flex items-center gap-2 bg-dyl-orange-600 text-white px-5 py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium">
```
(línea 12-13)

```diff
- <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border-2 border-yellow-300">
-     {{-- Franja superior decorativa --}}
-     <div class="h-3 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-400"></div>
+ <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border-2 border-dyl-orange-300">
+     {{-- Franja superior decorativa --}}
+     <div class="h-3 bg-gradient-to-r from-dyl-orange-400 via-dyl-orange-500 to-dyl-orange-400"></div>
```
(líneas 22-25 — la misma franja se repite en línea 67, idéntica sustitución)

```diff
- <p class="text-xs font-bold tracking-[4px] text-blue-900 uppercase mb-1">DyL Quality Consulting</p>
- <div class="w-24 h-0.5 bg-yellow-400 mx-auto mb-6"></div>
+ <p class="text-xs font-bold tracking-[4px] text-dyl-graphite-900 uppercase mb-1">DyL Quality Consulting</p>
+ <div class="w-24 h-0.5 bg-dyl-orange-400 mx-auto mb-6"></div>
```
(líneas 29-30 — `text-blue-900` es el "navy" formal del certificado, mapea a `dyl-graphite-900` según la tabla de migración del spec, no a naranja)

```diff
- <h2 class="text-4xl font-serif tracking-[6px] text-yellow-500 uppercase mb-2">Certificado</h2>
+ <h2 class="text-4xl font-serif tracking-[6px] text-dyl-orange-500 uppercase mb-2">Certificado</h2>
```
(línea 32)

```diff
- <p class="text-4xl font-serif italic font-bold text-blue-900 border-b border-yellow-400 pb-3 inline-block px-8 mb-6">
+ <p class="text-4xl font-serif italic font-bold text-dyl-graphite-900 border-b border-dyl-orange-400 pb-3 inline-block px-8 mb-6">
      {{ $certificado->usuario->name }}
  </p>
```
(línea 37)

```diff
- <p class="text-2xl font-bold text-blue-900 mb-4">{{ $certificado->curso->titulo }}</p>
+ <p class="text-2xl font-bold text-dyl-graphite-900 mb-4">{{ $certificado->curso->titulo }}</p>
```
(línea 43)

```diff
- <div class="inline-flex gap-6 text-sm text-gray-500 border border-yellow-300 rounded-lg px-6 py-2 mb-8">
-     <span>Calificación: <strong class="text-blue-900">{{ $certificado->calificacion_final }}%</strong></span>
+ <div class="inline-flex gap-6 text-sm text-gray-500 border border-dyl-orange-300 rounded-lg px-6 py-2 mb-8">
+     <span>Calificación: <strong class="text-dyl-graphite-900">{{ $certificado->calificacion_final }}%</strong></span>
      <span>·</span>
-     <span>Duración: <strong class="text-blue-900">{{ $certificado->curso->duracion_horas }} h</strong></span>
+     <span>Duración: <strong class="text-dyl-graphite-900">{{ $certificado->curso->duracion_horas }} h</strong></span>
      <span>·</span>
-     <span>Fecha: <strong class="text-blue-900">{{ ... }}</strong></span>
+     <span>Fecha: <strong class="text-dyl-graphite-900">{{ ... }}</strong></span>
  </div>
```
(líneas 45-50)

```diff
- <a href="{{ route('certificados.verificar', $certificado->numero_certificado) }}"
-    target="_blank"
-    class="text-blue-600 hover:underline text-sm font-mono break-all">
+ <a href="{{ route('certificados.verificar', $certificado->numero_certificado) }}"
+    target="_blank"
+    class="text-dyl-orange-600 hover:underline text-sm font-mono break-all">
```
(línea 78-80)

- [ ] **Step 3: `certificados/verificar.blade.php` — encabezado, válido e inválido**

```diff
- <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
-     <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="w-16 h-16 bg-dyl-graphite-100 rounded-full flex items-center justify-center mx-auto mb-4">
+     <svg class="w-8 h-8 text-dyl-graphite-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(línea 8-9 — encabezado neutro de la página, no es un botón/acento)

```diff
- <div class="h-2 bg-gradient-to-r from-green-400 to-green-500"></div>
+ <div class="h-2 bg-gradient-to-r from-dyl-orange-400 to-dyl-orange-500"></div>
```
(línea 20, franja de certificado válido)

```diff
- <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
+ <div class="inline-flex items-center gap-2 bg-dyl-orange-50 text-dyl-orange-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
```
(línea 23, badge "Certificado Válido y Auténtico")

```diff
- <span class="text-sm font-semibold text-green-600">{{ $certificado->calificacion_final }}%</span>
+ <span class="text-sm font-semibold text-dyl-orange-600">{{ $certificado->calificacion_final }}%</span>
```
(línea 41)

```diff
- <div class="h-2 bg-gradient-to-r from-red-400 to-red-500"></div>
+ <div class="h-2 bg-dyl-graphite-900"></div>
```
(línea 66 — certificado no encontrado: único estado "error/crítico" real de todo el dominio, usa el relleno sólido oscuro del spec en vez de gradiente)

```diff
- <div class="inline-flex items-center gap-2 bg-red-50 text-red-600 px-4 py-2 rounded-full text-sm font-medium mb-6">
+ <div class="inline-flex items-center gap-2 bg-dyl-graphite-900 text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
```
(línea 69, badge "Certificado No Encontrado" — mismo tratamiento de error sólido)

- [ ] **Step 4: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/certificados | grep -v dyl-orange`
Expected: sin resultados.

Run: `php artisan test`
Expected: todo en verde.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 5: Commit**

```bash
git add resources/views/certificados
git commit -m "feat: migrar resources/views/certificados a naranja/grafito"
```

---

### Task 4: Verificación final de la Fase 2C

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Confirmar que no queda color crudo en todo el dominio**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/actividades resources/views/calificaciones resources/views/certificados | grep -v dyl-orange`
Expected: sin resultados.

- [ ] **Step 2: Suite completa + build**

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 3: Checklist visual manual**

- [ ] `/actividades/{id}` (vista estudiante) — indicador de plazo en sus 3 estados (abierta/pendiente/vencida), materiales de apoyo con íconos por tipo (ahora todos grafito), formulario de respuesta con dropzone de archivo, banner de "actividad completada".
- [ ] `/actividades/{id}/editar` (vista instructor) — badge de tipo, panel de intentos del cuestionario, selector Verdadero/Falso al crear pregunta, lista de preguntas con badge "Falta marcar la correcta", constructor de rúbrica con total en vivo.
- [ ] Cuestionario con cronómetro — verificar que los últimos 60s se distingan claramente (borde naranja grueso pulsante) sin usar rojo.
- [ ] `/calificaciones/mis-calificaciones` — KPIs, badges "En revisión" vs. "Pendiente" (deben distinguirse por peso aunque compartan fondo grafito), promedio aprobado/reprobado.
- [ ] `/calificaciones/{respuesta}/revisar-cuestionario` — opciones correctas/incorrectas del auto-calificado, decisión manual de respuesta corta.
- [ ] `/calificaciones/{respuesta}` — calificación con y sin rúbrica.
- [ ] `/certificados/mis-certificados` y `/certificados/{id}` — acento dorado→naranja en tarjeta y certificado formal.
- [ ] `/verificar-certificado/{numero}` — probar con un número válido y uno inválido; el inválido debe verse claramente "de error" (relleno oscuro sólido).

- [ ] **Step 4: Commit final (si el checklist encontró algo que corregir)**

```bash
git add -A
git commit -m "fix: ajustes visuales tras verificacion manual de la sub-fase 2c"
```
(omitir si no hizo falta)
