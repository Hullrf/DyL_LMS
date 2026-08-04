# Rediseño Visual Fase 2B (Curso y contenido) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar todo color Tailwind crudo (rojo/verde/azul/amarillo/púrpura) de las 15 vistas del dominio "curso y contenido" (`cursos/`, `lecciones/`, `modulos/`, `anuncios/`, `foros/`), migrando a `dyl-orange`/`dyl-graphite` según el marco ya establecido en la Fase 1/2A.

**Architecture:** Migración mecánica archivo por archivo aplicando un mapeo de color fijo (ver "Marco de mapeo" abajo). Donde ya existe una clase de componente equivalente en `app.css` (`.alert-success`, `.alert-error`, `.alert-info`), se reemplaza el bloque completo por esa clase en vez de traducir colores uno a uno (consolidación DRY). Se agrupa en 3 tareas de migración por subdominio + 1 de verificación, mismo patrón que las Fases 1 y 2A.

**Tech Stack:** Laravel 11 Blade + Tailwind CSS 3 (JIT). Sin dependencias nuevas.

## Global Constraints

- Solo dos familias de color en toda la UI: `dyl-orange` y `dyl-graphite` — sin excepción.
- Acciones destructivas (antes rojo) nunca se recolorean a naranja (naranja = positivo/marca). Botón sólido destructivo → `bg-dyl-graphite-900 hover:bg-dyl-graphite-800 text-white`; variante suave/badge → `bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold`... (ver detalle en cada tarea).
- Cuando dos estados conviven en el mismo eje visual (ej. "completado" vs "en progreso" en el mismo indicador) — **nunca** los dos a naranja (quedarían indistinguibles). El estado final/positivo se lleva `dyl-orange-600/700` vívido; el estado intermedio/anterior se lleva `dyl-graphite-400/500` apagado.
- Mensajes flash (`session('success')`/`session('error')`) que duplican el patrón `bg-green-50 border ... text-green-800` / `bg-red-50 border ... text-red-700` se reemplazan por las clases `alert alert-success` / `alert alert-error` de `app.css`, no por una traducción de color manual.
- `gray-*` no forma parte de este barrido (no es un color semántico) — se deja tal cual salvo que el plan lo indique explícitamente por consistencia dentro de un mismo bloque condicional.

---

### Task 1: Migrar `resources/views/cursos/*` (5 archivos)

**Files:**
- Modify: `resources/views/cursos/index.blade.php`
- Modify: `resources/views/cursos/create.blade.php`
- Modify: `resources/views/cursos/edit.blade.php`
- Modify: `resources/views/cursos/show.blade.php`
- Modify: `resources/views/cursos/inscripcion-masiva.blade.php`

**Interfaces:** Ninguna — solo cambian clases CSS, no lógica ni estructura de datos.

- [ ] **Step 1: `cursos/index.blade.php`**

Badge de inscripción — regla de "dos estados en un eje" (línea 48-49): el lado "en progreso" ya está en naranja desde la Fase 1; el lado "completado" sigue en verde. Se invierte para que sigan siendo distinguibles: completado → naranja vivo (estado final), en progreso → grafito.

```diff
- @if($inscripcion->estado === 'completado') text-green-600 bg-green-100
- @else text-dyl-orange-600 bg-dyl-orange-100 @endif
+ @if($inscripcion->estado === 'completado') text-dyl-orange-700 bg-dyl-orange-100
+ @else text-dyl-graphite-600 bg-dyl-graphite-100 @endif
```
(línea 48-49)

```diff
- focus:ring-2 focus:ring-blue-500
+ focus:ring-2 focus:ring-dyl-orange-600
```
(aplica a líneas 115 y 123, idéntico)

```diff
- <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Buscar</button>
+ <button type="submit" class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Buscar</button>
```
(línea 124)

```diff
- <div class="w-full h-44 bg-gradient-to-br from-blue-400 to-blue-700 flex items-center justify-center">
+ <div class="w-full h-44 bg-gradient-to-br from-dyl-orange-400 to-dyl-orange-700 flex items-center justify-center">
```
(línea 139)

Badge de estado del curso, 3 vías (línea 147-149): publicado→naranja suave, borrador→grafito con peso (tratamiento "advertencia"), cualquier otro estado se queda en `gray` (fuera de alcance semántico).

```diff
- @if($curso->estado === 'publicado') bg-green-100 text-green-700
- @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-700
- @else bg-gray-100 text-gray-500 @endif
+ @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-700
+ @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
+ @else bg-gray-100 text-gray-500 @endif
```
(línea 147-149)

```diff
- class="block w-full text-center bg-blue-50 text-blue-700 py-2 rounded-lg hover:bg-blue-100 text-sm">
+ class="block w-full text-center bg-dyl-orange-50 text-dyl-orange-700 py-2 rounded-lg hover:bg-dyl-orange-100 text-sm">
```
(línea 174, link "Editar")

- [ ] **Step 2: `cursos/create.blade.php`**

```diff
- focus:ring-2 focus:ring-blue-500 focus:border-transparent
+ focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent
```
(aplica a líneas 12, 24, 30)

```diff
- <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
+ <p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>
```
(aplica a líneas 13, 19, 25, 51 — `@error` de titulo/descripcion/duracion_horas/imagen_portada)

```diff
- <p x-show="errorPortada" x-text="errorPortada" class="text-red-600 text-xs mt-1"></p>
+ <p x-show="errorPortada" x-text="errorPortada" class="text-dyl-graphite-900 font-semibold text-xs mt-1"></p>
```
(línea 50)

```diff
- <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Crear Curso</button>
+ <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg hover:bg-dyl-orange-700">Crear Curso</button>
```
(línea 55)

- [ ] **Step 3: `cursos/edit.blade.php`**

```diff
- <a href="{{ route('cursos.show', $curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">Ver curso &rarr;</a>
+ <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver curso &rarr;</a>
```
(línea 7)

```diff
- <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
+ <p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>
```
(aplica a líneas 21, 27)

```diff
- <p x-show="errorPortada" x-text="errorPortada" class="text-red-600 text-xs mt-1"></p>
+ <p x-show="errorPortada" x-text="errorPortada" class="text-dyl-graphite-900 font-semibold text-xs mt-1"></p>
```
(línea 67)

```diff
- <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-medium text-sm">
+ <button type="submit" class="w-full bg-dyl-orange-600 text-white py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
```
(línea 69, "Guardar Cambios")

Botones destructivos (líneas 77, 113, 134) — tratamiento sólido oscuro, igual en los 3:

```diff
- <button type="submit" class="w-full bg-red-50 text-red-600 py-2 rounded-lg hover:bg-red-100 text-sm">
+ <button type="submit" class="w-full bg-dyl-graphite-900 text-white py-2 rounded-lg hover:bg-dyl-graphite-800 text-sm">
```
(línea 77, "Eliminar Curso")

```diff
- <button type="submit" class="text-xs bg-red-100 text-red-600 px-3 py-1 rounded hover:bg-red-200">Eliminar</button>
+ <button type="submit" class="text-xs bg-dyl-graphite-900 text-white px-3 py-1 rounded hover:bg-dyl-graphite-800">Eliminar</button>
```
(línea 113, eliminar módulo)

```diff
- <button type="submit" class="text-xs bg-red-50 text-red-500 px-3 py-1 rounded hover:bg-red-100">Eliminar</button>
+ <button type="submit" class="text-xs bg-dyl-graphite-900 text-white px-3 py-1 rounded hover:bg-dyl-graphite-800">Eliminar</button>
```
(línea 134, eliminar lección)

```diff
- <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
+ <button type="submit" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm font-medium">
```
(línea 93, "+ Módulo" — acción positiva de creación)

```diff
- <a href="{{ route('lecciones.edit', $leccion) }}"
-    class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100">Editar</a>
+ <a href="{{ route('lecciones.edit', $leccion) }}"
+    class="text-xs bg-dyl-orange-50 text-dyl-orange-600 px-3 py-1 rounded hover:bg-dyl-orange-100">Editar</a>
```
(línea 127-128)

Fila de actividad anidada (líneas 142-153) — el púrpura no era un estado de éxito/error sino un indicador de jerarquía/anidamiento. La estructura de la fila (tinte, borde, badge de tipo, drag handle) pasa a grafito; el link "Editar" pasa a naranja para ser consistente con todos los demás links "Editar" del archivo:

```diff
- <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-purple-50/40 border-t border-purple-100/60 hover:bg-purple-50"
+ <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-dyl-graphite-50 border-t border-dyl-graphite-200/60 hover:bg-dyl-graphite-100"
```
(línea 142 — se quita el `/40` de opacidad, `dyl-graphite-50` ya es suficientemente claro sin atenuar)

```diff
- <svg class="drag-handle w-3.5 h-3.5 text-purple-400 shrink-0 cursor-grab active:cursor-grabbing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <svg class="drag-handle w-3.5 h-3.5 text-dyl-graphite-400 shrink-0 cursor-grab active:cursor-grabbing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(línea 145)

```diff
- <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
+ <span class="text-xs bg-dyl-graphite-100 text-dyl-graphite-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
```
(línea 149)

```diff
- <a href="{{ route('actividades.edit', $actividad) }}"
-    class="text-xs bg-purple-100 text-purple-700 px-3 py-1 rounded hover:bg-purple-200">
+ <a href="{{ route('actividades.edit', $actividad) }}"
+    class="text-xs bg-dyl-orange-100 text-dyl-orange-700 px-3 py-1 rounded hover:bg-dyl-orange-200">
```
(línea 152-153, "Editar" de actividad)

```diff
- <a href="{{ route('lecciones.create', $modulo) }}"
-    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
+ <a href="{{ route('lecciones.create', $modulo) }}"
+    class="text-sm text-dyl-orange-600 hover:text-dyl-orange-700 font-medium">
```
(línea 162-163, "+ Agregar lección")

- [ ] **Step 4: `cursos/show.blade.php`**

Mensajes flash — reemplazo completo por clases del sistema:

```diff
- <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
+ <div class="alert alert-success mb-6">{{ session('success') }}</div>
```
(línea 7)

```diff
- <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm">{{ session('error') }}</div>
+ <div class="alert alert-error mb-6">{{ session('error') }}</div>
```
(línea 10)

```diff
- {{ $curso->imagen_portada ? '' : 'bg-gradient-to-br from-blue-500 to-blue-700' }}">
+ {{ $curso->imagen_portada ? '' : 'bg-gradient-to-br from-dyl-orange-500 to-dyl-orange-700' }}">
```
(línea 17)

Badge de estado sólido, 3 vías (línea 24-26) — publicado→naranja sólido, borrador→grafito suave con texto oscuro, cualquier otro→grafito sólido oscuro:

```diff
- @if($curso->estado === 'publicado') bg-green-500 text-white
- @elseif($curso->estado === 'borrador') bg-yellow-400 text-gray-900
- @else bg-gray-500 text-white @endif">
+ @if($curso->estado === 'publicado') bg-dyl-orange-600 text-white
+ @elseif($curso->estado === 'borrador') bg-dyl-graphite-200 text-dyl-graphite-900
+ @else bg-dyl-graphite-600 text-white @endif">
```
(línea 24-26)

```diff
- class="block w-full text-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium mb-2">
+ class="block w-full text-center bg-dyl-orange-600 text-white px-6 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium mb-2">
```
(línea 64, "Editar curso")

Regla de "dos estados en un eje", aplicada al mismo dato (`$porcentaje`) dos veces seguidas: completo (100%) → naranja vivo; en progreso → grafito. El relleno de la barra usa `graphite-400` (no `500`) para que tenga contraste visible contra el track `bg-gray-200`:

```diff
- <span class="{{ $porcentaje === 100 ? 'text-green-600' : 'text-blue-600' }}">{{ $porcentaje }}%</span>
+ <span class="{{ $porcentaje === 100 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">{{ $porcentaje }}%</span>
```
(línea 77)

```diff
- <div class="h-full rounded-full transition-all
-     {{ $porcentaje === 100 ? 'bg-green-500' : 'bg-blue-500' }}"
+ <div class="h-full rounded-full transition-all
+     {{ $porcentaje === 100 ? 'bg-dyl-orange-600' : 'bg-dyl-graphite-400' }}"
```
(línea 80-81)

```diff
- <a href="{{ route('lecciones.show', $primeraLeccionSinCompletar) }}"
-    class="block w-full text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium text-sm">
+ <a href="{{ route('lecciones.show', $primeraLeccionSinCompletar) }}"
+    class="block w-full text-center bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
```
(línea 90-91, "Continuar")

```diff
- <div class="text-center text-green-600 font-medium text-sm mb-2">
+ <div class="text-center text-dyl-orange-700 font-medium text-sm mb-2">
```
(línea 95, "✓ Curso completado" — estado final, sin competidor en el mismo elemento)

Botones de certificado (amarillo = logro/recompensa, no advertencia) → naranja, mismo tratamiento que "Continuar":

```diff
- <a href="{{ route('certificados.show', $certExistente) }}"
-    class="block w-full text-center bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 font-medium text-sm">
+ <a href="{{ route('certificados.show', $certExistente) }}"
+    class="block w-full text-center bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
```
(línea 103-104, "Ver Certificado")

```diff
- <button type="submit"
-         class="w-full bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 font-medium text-sm">
+ <button type="submit"
+         class="w-full bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
```
(línea 110-111, "Obtener Certificado")

```diff
- <button type="submit"
-         class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
+ <button type="submit"
+         class="w-full bg-dyl-orange-600 text-white px-6 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium">
```
(línea 122-123, "Inscribirse al curso")

```diff
- <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
+ <svg class="w-5 h-5 text-dyl-orange-600" fill="currentColor" viewBox="0 0 20 20">
```
(línea 170, ícono de lección completada)

```diff
- class="text-sm font-medium {{ $done ? 'text-gray-500 line-through' : 'text-gray-800 hover:text-blue-600' }}">
+ class="text-sm font-medium {{ $done ? 'text-gray-500 line-through' : 'text-gray-800 hover:text-dyl-orange-600' }}">
```
(línea 184)

- [ ] **Step 5: `cursos/inscripcion-masiva.blade.php`**

```diff
- <a href="{{ route('cursos.edit', $curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
+ <a href="{{ route('cursos.edit', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
```
(línea 11)

```diff
- <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
+ <div class="alert alert-success mb-4">{{ session('success') }}</div>
```
(línea 15)

```diff
- class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
+ class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-dyl-orange-600">
```
(línea 21)

```diff
- <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Buscar</button>
+ <button type="submit" class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Buscar</button>
```
(línea 22)

```diff
- <input type="checkbox" name="usuarios[]" value="{{ $usuario->id }}" class="w-4 h-4 rounded text-blue-600">
+ <input type="checkbox" name="usuarios[]" value="{{ $usuario->id }}" class="w-4 h-4 rounded text-dyl-orange-600">
```
(línea 42)

```diff
- @error('usuarios')<p class="text-red-600 text-sm mt-2">{{ $message }}</p>@enderror
+ @error('usuarios')<p class="text-dyl-graphite-900 font-semibold text-sm mt-2">{{ $message }}</p>@enderror
```
(línea 63)

- [ ] **Step 6: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose)-[0-9]+" resources/views/cursos`
Expected: sin resultados.

Run: `php artisan test`
Expected: todo en verde (cambio puramente visual, sin lógica ni test nuevo).

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 7: Commit**

```bash
git add resources/views/cursos
git commit -m "feat: migrar resources/views/cursos a naranja/grafito"
```

---

### Task 2: Migrar `resources/views/lecciones/*` y `resources/views/modulos/edit.blade.php` (4 archivos)

**Files:**
- Modify: `resources/views/lecciones/create.blade.php`
- Modify: `resources/views/lecciones/edit.blade.php`
- Modify: `resources/views/lecciones/show.blade.php`
- Modify: `resources/views/modulos/edit.blade.php`

**Interfaces:** Ninguna — solo clases CSS.

- [ ] **Step 1: `lecciones/create.blade.php`**

```diff
- <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
+ <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
```
(línea 6)

```diff
- focus:ring-2 focus:ring-blue-500
+ focus:ring-2 focus:ring-dyl-orange-600
```
(aplica a líneas 19, 25, 41, 48)

```diff
- <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
+ <p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>
```
(aplica a líneas 20, 42, 76 — `@error('titulo')`, `@error('video_url')`, `@error('contenido_html')`)

Toggle "Permitir descarga de archivos adjuntos" (línea 61):

```diff
- <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
+ <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-dyl-orange-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-dyl-orange-600"></div>
```
(línea 61)

```diff
- <button type="submit" id="btn-guardar" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
+ <button type="submit" id="btn-guardar" class="bg-dyl-orange-600 text-white px-6 py-2 rounded-lg hover:bg-dyl-orange-700">
```
(línea 81, "Crear Lección")

- [ ] **Step 2: `lecciones/edit.blade.php`**

Mismo formulario gemelo de `create.blade.php` con los mismos 5 cambios (no existe el bloque de error de `contenido_html`):

```diff
- <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
+ <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
```
(línea 6)

```diff
- focus:ring-2 focus:ring-blue-500
+ focus:ring-2 focus:ring-dyl-orange-600
```
(aplica a líneas 19, 25, 41, 48)

```diff
- <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
+ <p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>
```
(aplica a líneas 20, 42 — `@error('titulo')`, `@error('video_url')`)

```diff
- <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
+ <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-dyl-orange-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-dyl-orange-600"></div>
```
(línea 62)

```diff
- <button type="submit" id="btn-guardar" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
+ <button type="submit" id="btn-guardar" class="bg-dyl-orange-600 text-white px-6 py-2 rounded-lg hover:bg-dyl-orange-700">
```
(línea 80, "Guardar Cambios")

- [ ] **Step 3: `lecciones/show.blade.php`**

```diff
- <a href="{{ route('cursos.show', $curso) }}" class="text-xs text-blue-600 hover:underline">&larr; Ver curso</a>
+ <a href="{{ route('cursos.show', $curso) }}" class="text-xs text-dyl-orange-600 hover:underline">&larr; Ver curso</a>
```
(línea 28)

```diff
- <div class="h-full bg-blue-500 rounded-full transition-all" style="width: {{ $pctSidebar }}%"></div>
+ <div class="h-full bg-dyl-orange-600 rounded-full transition-all" style="width: {{ $pctSidebar }}%"></div>
```
(línea 41, relleno de mini barra de progreso)

```diff
- {{ $isActual ? 'bg-blue-50 text-blue-700 font-medium border-l-2 border-blue-500' : 'text-gray-700 hover:bg-gray-50' }}">
+ {{ $isActual ? 'bg-dyl-orange-50 text-dyl-orange-700 font-medium border-l-2 border-dyl-orange-600' : 'text-gray-700 hover:bg-gray-50' }}">
```
(línea 59, lección activa en el índice — la rama `false` queda igual, es `gray`)

Ícono de estado de lección (líneas 62-74) — regla de "dos estados en un eje": `isDone` (verde) → naranja vivo (estado final); `isActual` (azul) → grafito apagado (estado intermedio). La tercera rama (`else`, no vista aún) también se normaliza de `gray-300` a `dyl-graphite-300` para que las 3 ramas del mismo `@if/@elseif/@else` usen la misma familia de color, en vez de mezclar `gray` de Tailwind con `dyl-graphite`:

```diff
 @if($isDone)
-    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
+    <svg class="w-4 h-4 text-dyl-orange-600" fill="currentColor" viewBox="0 0 20 20">
         <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
     </svg>
 @elseif($isActual)
-    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
+    <svg class="w-4 h-4 text-dyl-graphite-400" fill="currentColor" viewBox="0 0 20 20">
         <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
     </svg>
 @else
-    <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
+    <svg class="w-4 h-4 text-dyl-graphite-300" fill="currentColor" viewBox="0 0 20 20">
         <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
     </svg>
 @endif
```
(líneas 62-74)

Mensajes flash — reemplazo completo por clases del sistema:

```diff
         @if(session('success'))
-            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">
+            <div class="mb-6 alert alert-success">
                 {{ session('success') }}
             </div>
         @endif
         @if(session('error'))
-            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm">
+            <div class="mb-6 alert alert-error">
                 {{ session('error') }}
             </div>
         @endif
```
(líneas 91, 96)

```diff
- <a href="{{ route('cursos.show', $curso) }}" class="text-blue-600 hover:underline truncate">{{ $curso->titulo }}</a>
+ <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-600 hover:underline truncate">{{ $curso->titulo }}</a>
```
(línea 106, breadcrumb móvil)

Badge "Completada" (éxito solitario, sin competidor de color en el mismo elemento) — mismo patrón en 2 lugares:

```diff
- <span class="ml-1 px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">Completada</span>
+ <span class="ml-1 px-2 py-0.5 bg-dyl-orange-100 text-dyl-orange-700 rounded-full font-medium">Completada</span>
```
(línea 120)

```diff
- <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium">Completada</span>
+ <span class="px-2 py-1 bg-dyl-orange-100 text-dyl-orange-700 text-xs rounded-full font-medium">Completada</span>
```
(línea 199)

```diff
- prose-headings:text-gray-900 prose-p:text-gray-700 prose-a:text-blue-600">
+ prose-headings:text-gray-900 prose-p:text-gray-700 prose-a:text-dyl-orange-600">
```
(línea 154, link dentro de contenido HTML renderizado)

Banner "Esta lección aún no tiene contenido" (advertencia grande, mismo tratamiento que `.alert-warning`):

```diff
- <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8 text-center text-yellow-700">
+ <div class="bg-dyl-graphite-50 border-2 border-dyl-orange-300 rounded-xl p-6 mb-8 text-center text-dyl-graphite-900 font-medium">
```
(línea 170 — el borde pasa de 1px a 2px, igual que `.alert-warning`)

Card de actividad — hovers:

```diff
- class="flex items-center justify-between bg-white rounded-lg border border-gray-200 px-5 py-4 hover:border-blue-300 hover:shadow-sm transition-all group">
+ class="flex items-center justify-between bg-white rounded-lg border border-gray-200 px-5 py-4 hover:border-dyl-orange-300 hover:shadow-sm transition-all group">
```
(línea 183)

```diff
- <p class="font-medium text-gray-900 group-hover:text-blue-700">{{ $actividad->titulo }}</p>
+ <p class="font-medium text-gray-900 group-hover:text-dyl-orange-700">{{ $actividad->titulo }}</p>
```
(línea 194)

```diff
- <span class="text-gray-400 text-sm group-hover:text-blue-600">&rarr;</span>
+ <span class="text-gray-400 text-sm group-hover:text-dyl-orange-600">&rarr;</span>
```
(línea 201)

Banner informativo "Completa todas las actividades..." — se convierte al componente `alert-info` (misma estructura `flex items-start gap-3` que ya trae `.alert`):

```diff
-        <div class="mb-8 px-5 py-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700 flex items-start gap-3">
-            <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+        <div class="mb-8 alert alert-info">
+            <svg class="w-5 h-5 text-dyl-graphite-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(líneas 211-212 — se pierde `rounded-xl`/`px-5 py-4` a favor de `.alert`; se conserva `mb-8` como margen contextual)

Enlaces "Anterior"/"Siguiente":

```diff
- class="flex items-center gap-2 text-gray-600 hover:text-blue-600 group">
+ class="flex items-center gap-2 text-gray-600 hover:text-dyl-orange-600 group">
```
(aplica a líneas 224 y 238, idéntico)

CTA "Has terminado el módulo" (éxito/positivo solitario):

```diff
- <a href="{{ route('cursos.show', $curso) }}"
-    class="flex items-center gap-2 text-green-600 hover:text-green-700 font-medium group">
+ <a href="{{ route('cursos.show', $curso) }}"
+    class="flex items-center gap-2 text-dyl-orange-600 hover:text-dyl-orange-700 font-medium group">
```
(línea 249)

```diff
- <p class="text-xs text-green-500">Has terminado el módulo</p>
+ <p class="text-xs text-dyl-orange-600">Has terminado el módulo</p>
```
(línea 251)

```diff
- <a href="{{ route('actividades.create', $leccion) }}"
-    class="text-sm bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200">
+ <a href="{{ route('actividades.create', $leccion) }}"
+    class="text-sm bg-dyl-orange-100 text-dyl-orange-700 px-4 py-2 rounded-lg hover:bg-dyl-orange-200">
```
(línea 270, "+ Agregar actividad")

- [ ] **Step 4: `modulos/edit.blade.php`**

```diff
- <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
+ <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
```
(línea 6)

```diff
- focus:ring-2 focus:ring-blue-500
+ focus:ring-2 focus:ring-dyl-orange-600
```
(aplica a líneas 15, 26)

```diff
- @error('titulo')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
+ @error('titulo')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
```
(línea 16)

```diff
- <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Guardar</button>
+ <button type="submit" class="bg-dyl-orange-600 text-white px-6 py-2 rounded-lg hover:bg-dyl-orange-700">Guardar</button>
```
(línea 29)

- [ ] **Step 5: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose)-[0-9]+" resources/views/lecciones resources/views/modulos`
Expected: sin resultados.

Run: `php artisan test`
Expected: todo en verde.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 6: Commit**

```bash
git add resources/views/lecciones resources/views/modulos
git commit -m "feat: migrar resources/views/lecciones y modulos a naranja/grafito"
```

---

### Task 3: Migrar `resources/views/anuncios/*` y `resources/views/foros/*` (6 archivos)

**Files:**
- Modify: `resources/views/anuncios/create.blade.php`
- Modify: `resources/views/anuncios/index.blade.php`
- Modify: `resources/views/anuncios/todos.blade.php`
- Modify: `resources/views/foros/create.blade.php`
- Modify: `resources/views/foros/index.blade.php`
- Modify: `resources/views/foros/show.blade.php`

**Interfaces:** Ninguna — solo clases CSS.

- [ ] **Step 1: `anuncios/create.blade.php`**

```diff
- @error('titulo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
+ @error('titulo')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
```

```diff
- @error('contenido')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
+ @error('contenido')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
```

```diff
- <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Publicar anuncio</button>
+ <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Publicar anuncio</button>
```

- [ ] **Step 2: `anuncios/index.blade.php`**

```diff
- <a href="{{ route('anuncios.create', $curso) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Nuevo anuncio</a>
+ <a href="{{ route('anuncios.create', $curso) }}" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-dyl-orange-700">+ Nuevo anuncio</a>
```

- [ ] **Step 3: `anuncios/todos.blade.php`**

Etiqueta de nombre de curso sobre cada anuncio — se lleva `dyl-orange-600` porque es el único identificador visual de a qué curso pertenece cada anuncio en una vista que mezcla anuncios de todos los cursos (dato más relevante de la fila, análogo a un link primario):

```diff
- <span class="text-xs text-blue-600 font-medium">{{ $a->curso->titulo }}</span>
+ <span class="text-xs text-dyl-orange-600 font-medium">{{ $a->curso->titulo }}</span>
```

- [ ] **Step 4: `foros/create.blade.php`**

Solo hay un `@error` en este archivo (`titulo`) — la descripción usa un editor Quill sin bloque `@error` visible:

```diff
- @error('titulo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
+ @error('titulo')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
```

```diff
- <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Crear foro</button>
+ <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Crear foro</button>
```

- [ ] **Step 5: `foros/index.blade.php`**

```diff
- <a href="{{ route('foros.create', $curso) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Nuevo foro</a>
+ <a href="{{ route('foros.create', $curso) }}" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-dyl-orange-700">+ Nuevo foro</a>
```

Etiqueta "Lección: X" — vive dentro de una fila de metadatos apagados (autor/fecha/comentarios en `gray-400`), es un dato secundario, no el identificador principal de la tarjeta (a diferencia del caso de `anuncios/todos.blade.php`), por eso se lleva grafito discreto en vez de naranja:

```diff
- @if($foro->leccion)
-     <span class="text-blue-500">Lección: {{ $foro->leccion->titulo }}</span>
- @endif
+ @if($foro->leccion)
+     <span class="text-dyl-graphite-500">Lección: {{ $foro->leccion->titulo }}</span>
+ @endif
```

- [ ] **Step 6: `foros/show.blade.php`**

```diff
- <a href="{{ route('foros.index', $foro->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm mb-4 inline-block">&larr; Volver a foros</a>
+ <a href="{{ route('foros.index', $foro->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm mb-4 inline-block">&larr; Volver a foros</a>
```
(nota: el original usa `hover:text-blue-800`, salto de 2 pasos; se normaliza a `hover:text-dyl-orange-700` por consistencia con el resto del dominio, que siempre usa el salto 600→700)

```diff
- <button onclick="document.getElementById('reply-{{ $c->id }}').classList.toggle('hidden')" class="text-xs text-blue-600 hover:underline">Responder</button>
+ <button onclick="document.getElementById('reply-{{ $c->id }}').classList.toggle('hidden')" class="text-xs text-dyl-orange-600 hover:underline">Responder</button>
```

```diff
- <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Enviar</button>
+ <button type="submit" class="px-3 py-1.5 bg-dyl-orange-600 text-white text-xs rounded hover:bg-dyl-orange-700">Enviar</button>
```

```diff
- <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Comentar</button>
+ <button type="submit" class="px-5 py-2 bg-dyl-orange-600 text-white text-sm rounded-lg hover:bg-dyl-orange-700">Comentar</button>
```

- [ ] **Step 7: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose)-[0-9]+" resources/views/anuncios resources/views/foros`
Expected: sin resultados.

Run: `php artisan test`
Expected: todo en verde.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 8: Commit**

```bash
git add resources/views/anuncios resources/views/foros
git commit -m "feat: migrar resources/views/anuncios y foros a naranja/grafito"
```

---

### Task 4: Verificación final de la Fase 2B

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Confirmar que no queda color crudo en todo el dominio**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose)-[0-9]+" resources/views/cursos resources/views/lecciones resources/views/modulos resources/views/anuncios resources/views/foros`
Expected: sin resultados.

- [ ] **Step 2: Suite completa + build**

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 3: Checklist visual manual**

- [ ] `/cursos` — badges de estado (publicado/borrador), tarjetas con degradado, botón "Buscar".
- [ ] `/cursos/{id}` — banner de progreso, botón de certificado, mensajes flash.
- [ ] `/cursos/{id}/editar` — fila de actividades anidadas bajo lecciones (antes púrpura), botones "Eliminar" en gris oscuro (no rojo).
- [ ] `/lecciones/{id}` (visor de lección) — índice lateral con lección activa/completada/pendiente distinguibles entre sí, banner "sin contenido" y banner "completa las actividades".
- [ ] `/anuncios` y `/foros` de un curso — botones "+ Nuevo".

- [ ] **Step 4: Commit final (si el checklist encontró algo que corregir)**

```bash
git add -A
git commit -m "fix: ajustes visuales tras verificacion manual de la sub-fase 2b"
```
(omitir si no hizo falta)
