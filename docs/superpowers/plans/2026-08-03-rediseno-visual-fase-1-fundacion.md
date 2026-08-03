# Rediseño Visual Fase 1 (Fundación) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrar el sistema de color del LMS (navy/blue/gold/orange dispersos) a dos escalas sistemáticas (`dyl-orange` + `dyl-graphite`), actualizar los componentes base (botones, cards, badges, alertas) para usarlas sin colores semánticos, y reemplazar el navbar superior por un sidebar colapsable + topbar delgado.

**Architecture:** Cambio en 3 capas, en orden de dependencia: (1) tokens de color en `tailwind.config.js` + barrido de todo uso directo de los tokens viejos, (2) clases de componente en `resources/css/app.css` que consumen esos tokens, (3) estructura de navegación (`layouts/app.blade.php`) que consume ambos. Cada capa deja la app en estado funcional antes de pasar a la siguiente.

**Tech Stack:** Laravel 11 Blade + Tailwind CSS 3 (JIT) + Alpine.js (ya en uso para dropdowns/menús). Sin dependencias nuevas.

## Global Constraints

- Solo dos familias de color en toda la UI: `dyl-orange` (marca/acento/interactivo) y `dyl-graphite` (neutro). Ningún rojo/verde/amarillo/azul nuevo, ni siquiera para feedback del sistema (alertas, badges) — decisión explícita del usuario.
- Botones: `rounded-full` (píldora). Cards: `rounded-2xl`.
- Sin ilustraciones ni formas decorativas nuevas.
- Tipografía: Figtree/sans en toda la app, no se agrega una segunda familia tipográfica.
- El test existente `tests/Feature/LmsAuthTest.php::test_...` asume que `/login` contiene el texto exacto `"Iniciar sesión"` — no debe desaparecer.
- Sidebar: colapsable (icono-solo / expandido), estado persistido en `localStorage` (no en BD). Overlay/drawer en móvil, nunca colapsado en móvil.
- Fuera de alcance de esta fase (Fase 1): las ~57 vistas que usan colores Tailwind crudos (`blue-600`, `green-500`, `red-600`, etc., ej. `components/input-error.blade.php`, `cursos/show.blade.php` botones "Editar"/"Inscribirme", todas las vistas de `actividades/`, `certificados/`, `mensajes/`, `foros/`, `admin/`, `reportes/`). Ese barrido es una fase 2 independiente — no se toca en este plan salvo los archivos listados explícitamente en las tareas de abajo.

---

## Referencia: spec de diseño

Todas las decisiones de color/componentes de este plan vienen de `docs/superpowers/specs/2026-08-03-rediseno-visual-naranja-grafito-design.md`. Consultarlo si un valor de color o nombre de clase no está claro aquí.

---

### Task 1: Migrar tokens de color y todo uso directo de `dyl-orange`/`dyl-gold`/`dyl-navy`/`dyl-blue`

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/views/actividades/create.blade.php:92`
- Modify: `resources/views/actividades/edit.blade.php` (líneas 67, 107, 160, 175, 225, 327, 359, 421, 521, 746, 755)
- Modify: `resources/views/actividades/show.blade.php:138,440`
- Modify: `resources/views/auth/login.blade.php:34,50,66`
- Modify: `resources/views/calificaciones/index.blade.php:21,25,29,81,86`
- Modify: `resources/views/calificaciones/mis-calificaciones.blade.php:34,48`
- Modify: `resources/views/calificaciones/revisar-cuestionario.blade.php:40`
- Modify: `resources/views/calificaciones/show.blade.php:90`
- Modify: `resources/views/cursos/index.blade.php:10,22,39,49,68`
- Modify: `resources/views/cursos/inscripcion-masiva.blade.php:33,59`
- Modify: `resources/views/layouts/app.blade.php:71,90,93,94,95,177,365,369,370`
- Modify: `resources/views/layouts/guest.blade.php:18,28,29,50,64,65,67`

**Interfaces:**
- Produces: clases Tailwind `bg-dyl-orange-{50..900}`, `text-dyl-orange-{50..900}`, `border-dyl-orange-{50..900}`, `ring-dyl-orange-{50..900}` y sus equivalentes `dyl-graphite-{50..900}`, disponibles para las Tasks 2 y 3.
- No produce nada consumido por otra clase Tailwind vieja: después de esta tarea, `grep -rE "dyl-(navy|blue|gold)" resources/views` debe devolver 0 resultados, y `dyl-orange`/`dyl-orange-700` sin escala numérica ya no existen (todo pasa a `dyl-orange-500/600/700`).

- [ ] **Step 1: Reemplazar el bloque `colors.dyl` en `tailwind.config.js`**

```js
// tailwind.config.js — dentro de theme.extend
colors: {
    dyl: {
        orange: {
            50:  '#FFF7ED',
            100: '#FFEDD5',
            200: '#FED7AA',
            300: '#FDBA74',
            400: '#FB923C',
            500: '#F97316',
            600: '#EA580C',
            700: '#C2410C',
            800: '#9A3412',
            900: '#7C2D12',
        },
        graphite: {
            50:  '#F8FAFC',
            100: '#F1F5F9',
            200: '#E2E8F0',
            300: '#CBD5E1',
            400: '#94A3B8',
            500: '#64748B',
            600: '#475569',
            700: '#334155',
            800: '#1E293B',
            900: '#0F172A',
        },
    },
},
```

- [ ] **Step 2: `actividades/create.blade.php` y `actividades/show.blade.php` (3 líneas)**

`actividades/create.blade.php:92`:
```diff
- <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
+ <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-orange-600 rounded-full transition-colors"></div>
```

`actividades/show.blade.php:138` y `:440` (mismo patrón, 2 apariciones idénticas):
```diff
- <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```

- [ ] **Step 3: `actividades/edit.blade.php` (11 líneas)**

Reemplazos uno a uno (mismo archivo):

```diff
- 67:  <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
+ 67:  <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-orange-600 rounded-full transition-colors"></div>

- 107: <input type="checkbox" x-model="conPlazo" class="rounded text-dyl-orange">
+ 107: <input type="checkbox" x-model="conPlazo" class="rounded text-dyl-orange-600">

- 160: <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ 160: <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">

- 175: :class="tipoRecurso === '{{ $val }}' ? 'border-dyl-blue bg-blue-50 text-dyl-blue font-medium' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
+ 175: :class="tipoRecurso === '{{ $val }}' ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-600 font-medium' : 'border-gray-200 text-gray-600 hover:border-gray-300'">

- 225: ... file:bg-orange-50 file:text-dyl-orange hover:file:bg-orange-100 cursor-pointer"
+ 225: ... file:bg-dyl-orange-50 file:text-dyl-orange-600 hover:file:bg-dyl-orange-100 cursor-pointer"

- 327: <svg class="w-4 h-4 text-dyl-blue shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ 327: <svg class="w-4 h-4 text-dyl-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">

- 359: ... file:bg-orange-50 file:text-dyl-orange hover:file:bg-orange-100 cursor-pointer"
+ 359: ... file:bg-dyl-orange-50 file:text-dyl-orange-600 hover:file:bg-dyl-orange-100 cursor-pointer"

- 421: <input ... class="w-4 h-4 rounded text-dyl-blue">
+ 421: <input ... class="w-4 h-4 rounded text-dyl-orange-600">

- 521: ... file:bg-orange-50 file:text-dyl-orange hover:file:bg-orange-100 cursor-pointer">
+ 521: ... file:bg-dyl-orange-50 file:text-dyl-orange-600 hover:file:bg-dyl-orange-100 cursor-pointer">

- 746: <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ 746: <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">

- 755: <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
+ 755: <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-orange-600 rounded-full transition-colors"></div>
```

- [ ] **Step 4: `auth/login.blade.php` (3 líneas)**

```diff
- 34: class="text-xs text-dyl-blue hover:underline">
+ 34: class="text-xs text-dyl-orange-600 hover:underline">

- 50: class="rounded border-gray-300 text-dyl-blue focus:ring-dyl-blue">
+ 50: class="rounded border-gray-300 text-dyl-orange-600 focus:ring-dyl-orange-600">

- 66: <a href="{{ route('register') }}" class="text-dyl-blue font-medium hover:underline">
+ 66: <a href="{{ route('register') }}" class="text-dyl-orange-600 font-medium hover:underline">
```

- [ ] **Step 5: `calificaciones/*` (4 archivos, 9 líneas)**

`calificaciones/index.blade.php:21,25,29` (mismo patrón, 3 apariciones — cambiar solo la clase `bg-dyl-blue`):
```diff
- {{ $estado === 'pendiente' ? 'bg-dyl-blue text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}
+ {{ $estado === 'pendiente' ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}
```
(igual para `'calificada'` en la línea 25 y `'todas'` en la línea 29)

`calificaciones/index.blade.php:81,86`:
```diff
- class="text-dyl-blue hover:text-dyl-blue-600 text-sm font-medium">
+ class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
```

`calificaciones/mis-calificaciones.blade.php:34,48`:
```diff
- 34: <p class="text-3xl font-bold text-dyl-orange mt-1">{{ $porCurso->count() }}</p>
+ 34: <p class="text-3xl font-bold text-dyl-orange-600 mt-1">{{ $porCurso->count() }}</p>

- 48: <div class="w-2 h-6 bg-dyl-orange rounded-full flex-shrink-0"></div>
+ 48: <div class="w-2 h-6 bg-dyl-orange-600 rounded-full flex-shrink-0"></div>
```

`calificaciones/revisar-cuestionario.blade.php:40`:
```diff
- <div class="card overflow-hidden {{ $esCorta ? 'ring-2 ring-dyl-blue/30' : '' }}">
+ <div class="card overflow-hidden {{ $esCorta ? 'ring-2 ring-dyl-orange-600/30' : '' }}">
```

`calificaciones/show.blade.php:90`:
```diff
- :class="selecciones[{{ $criterio->id }}] == {{ $nivel->id }} ? 'bg-blue-50 ring-2 ring-inset ring-dyl-blue' : ''">
+ :class="selecciones[{{ $criterio->id }}] == {{ $nivel->id }} ? 'bg-dyl-orange-50 ring-2 ring-inset ring-dyl-orange-600' : ''">
```

- [ ] **Step 6: `cursos/*` (2 archivos, 7 líneas)**

`cursos/index.blade.php`:
```diff
- 10: class="bg-dyl-orange text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
+ 10: class="bg-dyl-orange-600 text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">

- 22: <div class="w-1 h-6 bg-dyl-orange rounded-full"></div>
+ 22: <div class="w-1 h-6 bg-dyl-orange-600 rounded-full"></div>

- 39: <div class="w-full h-44 bg-gradient-to-br from-dyl-orange to-orange-700 flex items-center justify-center">
+ 39: <div class="w-full h-44 bg-gradient-to-br from-dyl-orange-600 to-dyl-orange-700 flex items-center justify-center">

- 49: @else text-dyl-orange bg-orange-100 @endif
+ 49: @else text-dyl-orange-600 bg-dyl-orange-100 @endif

- 68: class="block w-full text-center py-2 rounded-lg font-medium text-sm bg-dyl-orange text-white hover:bg-dyl-orange-700 transition-colors">
+ 68: class="block w-full text-center py-2 rounded-lg font-medium text-sm bg-dyl-orange-600 text-white hover:bg-dyl-orange-700 transition-colors">
```

`cursos/inscripcion-masiva.blade.php:33,59` (mismo patrón, 2 apariciones):
```diff
- class="px-4 py-1.5 bg-dyl-orange text-white text-sm rounded-lg hover:bg-dyl-orange-700">Inscribir seleccionados</button>
+ class="px-4 py-1.5 bg-dyl-orange-600 text-white text-sm rounded-lg hover:bg-dyl-orange-700">Inscribir seleccionados</button>
```
(línea 59: mismo cambio `bg-dyl-orange` → `bg-dyl-orange-600`, mantiene `hover:bg-dyl-orange-700`)

- [ ] **Step 7: `layouts/app.blade.php` (recolor, sin cambiar estructura — la estructura la reemplaza la Task 3)**

```diff
- 71: focus:bg-dyl-navy focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm">
+ 71: focus:bg-dyl-graphite-900 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm">

- 79: <nav class="bg-dyl-orange shadow-md"
+ 79: <nav class="bg-dyl-orange-600 shadow-md"

- 90: class="flex items-center gap-2.5 group focus-visible:ring-2 focus-visible:ring-dyl-gold rounded"
+ 90: class="flex items-center gap-2.5 group focus-visible:ring-2 focus-visible:ring-dyl-orange-500 rounded"

- 93: <div class="bg-dyl-gold rounded-lg flex items-center justify-center flex-shrink-0 px-2 h-8
+ 93: <div class="bg-dyl-orange-500 rounded-lg flex items-center justify-center flex-shrink-0 px-2 h-8

- 94: group-hover:bg-dyl-gold-400 transition-colors">
+ 94: group-hover:bg-dyl-orange-400 transition-colors">

- 95: <span class="text-dyl-navy font-bold text-sm leading-none tracking-tight">D&amp;L</span>
+ 95: <span class="text-dyl-graphite-900 font-bold text-sm leading-none tracking-tight">D&amp;L</span>

- 177: focus-visible:ring-dyl-gold"
+ 177: focus-visible:ring-dyl-orange-500"

- 365: <footer class="bg-dyl-navy mt-auto" role="contentinfo">
+ 365: <footer class="bg-dyl-graphite-900 mt-auto" role="contentinfo">

- 369: <div class="bg-dyl-gold rounded-md flex items-center justify-center px-1.5 h-7">
+ 369: <div class="bg-dyl-orange-500 rounded-md flex items-center justify-center px-1.5 h-7">

- 370: <span class="text-dyl-navy font-bold text-xs tracking-tight">D&amp;L</span>
+ 370: <span class="text-dyl-graphite-900 font-bold text-xs tracking-tight">D&amp;L</span>
```

(la línea 279, `bg-dyl-orange-700` en el menú móvil, ya es válida bajo la escala nueva — no requiere cambio)

- [ ] **Step 8: `layouts/guest.blade.php` (7 líneas)**

```diff
- 18: <div class="hidden lg:flex lg:w-1/2 bg-dyl-navy flex-col justify-between p-12 relative overflow-hidden">
+ 18: <div class="hidden lg:flex lg:w-1/2 bg-dyl-graphite-900 flex-col justify-between p-12 relative overflow-hidden">

- 28: <div class="bg-dyl-gold rounded-xl flex items-center justify-center px-2.5 h-10">
+ 28: <div class="bg-dyl-orange-500 rounded-xl flex items-center justify-center px-2.5 h-10">

- 29: <span class="text-dyl-navy font-bold text-base tracking-tight">D&amp;L</span>
+ 29: <span class="text-dyl-graphite-900 font-bold text-base tracking-tight">D&amp;L</span>

- 50: <svg class="w-5 h-5 text-dyl-gold-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
+ 50: <svg class="w-5 h-5 text-dyl-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">

- 64: <div class="bg-dyl-gold rounded-lg flex items-center justify-center px-2 h-9">
+ 64: <div class="bg-dyl-orange-500 rounded-lg flex items-center justify-center px-2 h-9">

- 65: <span class="text-dyl-navy font-bold tracking-tight">D&amp;L</span>
+ 65: <span class="text-dyl-graphite-900 font-bold tracking-tight">D&amp;L</span>

- 67: <span class="text-dyl-navy font-bold text-lg">LMS</span>
+ 67: <span class="text-dyl-graphite-900 font-bold text-lg">LMS</span>
```

- [ ] **Step 9: Verificar que no queda ningún token viejo**

Run: `grep -rE "dyl-(navy|blue|gold)" resources/views resources/css`
Expected: sin resultados (exit code 1 / vacío). Si aparece algo, falta un archivo — corregirlo antes de continuar.

- [ ] **Step 10: Correr la suite de tests completa**

Run: `php artisan test`
Expected: todos los tests existentes en verde (esta tarea es solo recoloreo de clases Tailwind, no cambia lógica ni estructura — no se agrega un test nuevo para esto, mismo criterio que otros cambios puramente visuales en este proyecto).

- [ ] **Step 11: Compilar assets**

Run: `npm run build`
Expected: build exitoso sin errores.

- [ ] **Step 12: Commit**

```bash
git add tailwind.config.js resources/views/actividades resources/views/auth/login.blade.php resources/views/calificaciones resources/views/cursos resources/views/layouts/app.blade.php resources/views/layouts/guest.blade.php
git commit -m "feat: migrar tokens de color a escalas dyl-orange y dyl-graphite"
```

---

### Task 2: Componentes base sin colores semánticos (`resources/css/app.css`)

**Files:**
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `dyl-orange-{50..900}`, `dyl-graphite-{50..900}` de la Task 1.
- Produces: clases `.btn`, `.btn-primary`, `.btn-outline`, `.btn-ghost`, `.card`, `.badge-success`, `.badge-info`, `.badge-warning`, `.badge-error`, `.alert-success`, `.alert-info`, `.alert-warning`, `.alert-error`, `.form-error` — usadas por la Task 3 y por vistas existentes (`badge-blue`/`badge-green`/`badge-yellow`/`badge-red`/`badge-gold` se mantienen como alias hacia los mismos valores para no romper las ~14 vistas que ya los usan).

- [ ] **Step 1: Reemplazar el `@layer base` — focus ring**

```diff
  :focus-visible {
-     @apply outline-none ring-2 ring-dyl-blue ring-offset-2;
+     @apply outline-none ring-2 ring-dyl-orange-600 ring-offset-2;
  }
```

- [ ] **Step 2: Reemplazar el bloque de botones**

```css
/* ---- Botones ---- */
.btn {
    @apply inline-flex items-center justify-center gap-2 px-4 py-2 rounded-full
           font-medium text-sm transition-all duration-150 focus-visible:ring-2
           focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-primary {
    @apply btn bg-dyl-orange-600 text-white hover:bg-dyl-orange-700
           focus-visible:ring-dyl-orange-600;
}
.btn-outline {
    @apply btn bg-white text-dyl-graphite-700 border border-dyl-graphite-300
           hover:bg-dyl-graphite-50 focus-visible:ring-dyl-graphite-400;
}
.btn-ghost {
    @apply btn bg-dyl-graphite-100 text-dyl-graphite-700 hover:bg-dyl-graphite-200
           focus-visible:ring-dyl-graphite-400;
}
.btn-danger {
    @apply btn bg-dyl-graphite-900 text-white hover:bg-dyl-graphite-800
           focus-visible:ring-dyl-graphite-900;
}
.btn-sm {
    @apply px-3 py-1.5 text-xs;
}
.btn-lg {
    @apply px-6 py-3 text-base;
}
```

(`.btn-navy` y `.btn-gold` se eliminan — `grep -rl "btn-navy\|btn-gold" resources/views` ya da 0 resultados hoy, confirmado antes de escribir este plan. `.btn-danger` deja de usar rojo: pasa a `graphite-900`, mismo criterio "todo en naranja/grafito" aplicado a acciones destructivas — se distingue de `.btn-primary` por ser oscuro sólido en vez de naranja, no por matiz.)

- [ ] **Step 3: Reemplazar el bloque de cards**

```diff
  .card { @apply bg-white rounded-2xl shadow-card; }
```
(antes `rounded-xl` — único cambio en este bloque, `.card-hover`, `.card-header`, `.card-body` quedan igual)

- [ ] **Step 4: Reemplazar el bloque de badges**

```css
/* ---- Badges / etiquetas ---- */
.badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
.badge-success { @apply badge bg-dyl-orange-100 text-dyl-orange-700; }
.badge-info    { @apply badge bg-dyl-graphite-100 text-dyl-graphite-600; }
.badge-warning { @apply badge bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold; }
.badge-error   { @apply badge bg-dyl-graphite-800 text-white; }

/* Alias de compatibilidad — usados hoy en ~14 vistas, apuntan a los mismos valores
   de arriba. Se pueden renombrar en las vistas y borrar estos alias en una fase
   posterior; no es necesario para que el color sea correcto. */
.badge-green  { @apply badge-success; }
.badge-blue   { @apply badge-info; }
.badge-yellow { @apply badge-warning; }
.badge-red    { @apply badge-error; }
.badge-gold   { @apply badge-success; }
.badge-gray   { @apply badge-info; }
```

- [ ] **Step 5: Reemplazar el bloque de alertas**

```css
/* ---- Alertas ---- */
.alert { @apply rounded-lg p-4 text-sm border flex items-start gap-3; }
.alert-success { @apply alert bg-dyl-orange-50 border-dyl-orange-200 text-dyl-orange-800; }
.alert-info    { @apply alert bg-dyl-graphite-50 border-dyl-graphite-200 text-dyl-graphite-700; }
.alert-warning { @apply alert bg-dyl-graphite-50 border-2 border-dyl-orange-300 text-dyl-graphite-900 font-medium; }
.alert-error   { @apply alert bg-dyl-graphite-900 text-white border-transparent; }
```

- [ ] **Step 6: Reemplazar `.form-error` y los `progress-*`**

```css
.form-error { @apply mt-1 text-xs text-dyl-graphite-900 font-semibold; }
```

```diff
- .progress-blue  { @apply progress-fill bg-dyl-blue; }
- .progress-green { @apply progress-fill bg-green-500; }
- .progress-gold  { @apply progress-fill bg-dyl-gold; }
+ .progress-blue  { @apply progress-fill bg-dyl-orange-600; }
+ .progress-green { @apply progress-fill bg-dyl-orange-600; }
+ .progress-gold  { @apply progress-fill bg-dyl-orange-500; }
```

(se mantienen los tres nombres por compatibilidad con vistas existentes — los tres apuntan a tonos de naranja ahora; no hay verde en el sistema)

- [ ] **Step 7: Actualizar el comentario de cabecera del archivo**

```diff
  /* ============================================================
     DyL Quality LMS — Design System
-    Paleta:  Navy #1e3a5f · Blue #2563eb · Gold #d97706
+    Paleta:  Naranja #EA580C (dyl-orange-600) · Grafito #0F172A (dyl-graphite-900)
  ============================================================ */
```

- [ ] **Step 8: Correr la suite de tests completa**

Run: `php artisan test`
Expected: todo en verde (cambio puramente de CSS, sin lógica ni HTML nuevo).

- [ ] **Step 9: Compilar assets**

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 10: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: botones pill, cards redondeadas y sistema de feedback sin colores semanticos"
```

---

### Task 3: Sidebar colapsable + topbar (reemplaza el navbar)

**Files:**
- Create: `resources/views/layouts/partials/sidebar.blade.php`
- Create: `resources/views/layouts/partials/topbar.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (reemplaza el bloque `<nav>`, líneas 78–320 del archivo previo a la Task 1, y el `<body>` que lo envuelve)
- Modify: `resources/css/app.css` (agrega `.dropdown-item`, movido desde el `<style>` inline que se elimina de `app.blade.php`)
- Test: `tests/Feature/SidebarNavigationTest.php`

**Interfaces:**
- Consumes: rutas ya existentes (`cursos.index`, `mensajes.bandeja`, `anuncios.todos`, `calificaciones.index`, `calificaciones.mis`, `reportes.index`, `certificados.mis`, `admin.usuarios.index`, `admin.auditoria.index`, `cursos.create`, `dashboard`, `profile.edit`, `logout`, `login`), métodos de rol (`esInstructor()`, `esAdmin()`, `esEstudiante()`) ya definidos en `App\Models\User`, componente `<x-notification-bell />` ya existente.
- Produces: evento del navegador `sidebar-open` (via `$dispatch`) que el topbar dispara para abrir el drawer del sidebar en móvil.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php
// tests/Feature/SidebarNavigationTest.php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol): User
    {
        $rol = Rol::factory()->create(['nombre' => $nombreRol]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    public function test_dashboard_renders_sidebar_element_instead_of_old_navbar(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('<aside', false);
        $response->assertSee('Cursos');
        $response->assertDontSee('bg-dyl-orange-600 shadow-md', false);
    }

    public function test_sidebar_hides_admin_only_links_from_estudiante(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertDontSee('Usuarios');
        $response->assertDontSee('Auditoría');
        $response->assertDontSee('Reportes');
    }

    public function test_sidebar_shows_admin_only_links_to_administrador(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertSee('Usuarios');
        $response->assertSee('Auditoría');
        $response->assertSee('Reportes');
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=SidebarNavigationTest`
Expected: FAIL en `test_dashboard_renders_sidebar_element_instead_of_old_navbar` (no existe `<aside` en el dashboard actual, y sí existe `bg-dyl-orange-600 shadow-md`). Las otras dos pueden pasar ya (la lógica de rol ya es correcta hoy) — lo importante es que la primera falle, confirmando que el test detecta la estructura vieja.

- [ ] **Step 3: Crear `resources/views/layouts/partials/sidebar.blade.php`**

```blade
@auth
<aside
    x-data="{
        collapsed: localStorage.getItem('dyl_sidebar_collapsed') === '1',
        mobileOpen: false,
        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('dyl_sidebar_collapsed', this.collapsed ? '1' : '0');
        }
    }"
    @sidebar-open.window="mobileOpen = true"
    :class="{ 'is-collapsed': collapsed, 'is-open': mobileOpen }"
    class="dyl-sidebar"
    role="navigation"
    aria-label="Navegación principal">

    {{-- Overlay móvil --}}
    <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
         class="lg:hidden fixed inset-0 bg-black/50 z-40"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div class="dyl-sb-top">
        <a href="{{ route('dashboard') }}" class="dyl-sb-logo" aria-label="LMS DyL - Ir al inicio">
            <span class="dyl-sb-sq" aria-hidden="true">D&amp;L</span>
            <span class="dyl-sb-label">LMS</span>
        </a>
    </div>

    <nav class="dyl-sb-nav">
        <a href="{{ route('cursos.index') }}" class="dyl-sb-link {{ request()->routeIs('cursos.*') ? 'active' : '' }}">
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.2c-1.6-1.1-3.6-1.7-5.6-1.7-1 0-1.9.1-2.9.4v13.3c1-.3 1.9-.4 2.9-.4 2 0 4 .6 5.6 1.7"/><path d="M12 6.2c1.6-1.1 3.6-1.7 5.6-1.7 1 0 1.9.1 2.9.4v13.3c-1-.3-1.9-.4-2.9-.4-2 0-4 .6-5.6 1.7"/><path d="M12 6.2v13.3"/></svg>
            </span>
            <span class="dyl-sb-label">Cursos</span>
        </a>
        <a href="{{ route('mensajes.bandeja') }}" class="dyl-sb-link {{ request()->routeIs('mensajes.*') ? 'active' : '' }}">
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.2" y="5.5" width="17.6" height="13" rx="2.2"/><path d="M4 6.8l8 6.4 8-6.4"/></svg>
            </span>
            <span class="dyl-sb-label">Mensajes</span>
        </a>
        <a href="{{ route('anuncios.todos') }}" class="dyl-sb-link {{ request()->routeIs('anuncios.*') ? 'active' : '' }}">
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.2v3.6a1 1 0 001 1h1.8l6.2 3.7V5.5L5.8 9.2H4a1 1 0 00-1 1z"/><path d="M15.5 9.3a3.2 3.2 0 010 5.4"/></svg>
            </span>
            <span class="dyl-sb-label">Anuncios</span>
        </a>
        @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
            <a href="{{ route('calificaciones.index') }}" class="dyl-sb-link {{ request()->routeIs('calificaciones.index') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4.2" width="14" height="17" rx="2"/><path d="M9 4.2v-.7a1 1 0 011-1h4a1 1 0 011 1v.7"/><path d="M8.3 12.8l2.1 2.1 4.3-4.6"/></svg>
                </span>
                <span class="dyl-sb-label">Calificaciones</span>
            </a>
            <a href="{{ route('reportes.index') }}" class="dyl-sb-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V4.8"/><path d="M4 20h16"/><rect x="7" y="12.5" width="3" height="7.5" rx=".6"/><rect x="12.3" y="8" width="3" height="12" rx=".6"/><rect x="17.5" y="14.8" width="3" height="5.2" rx=".6"/></svg>
                </span>
                <span class="dyl-sb-label">Reportes</span>
            </a>
        @else
            <a href="{{ route('calificaciones.mis') }}" class="dyl-sb-link {{ request()->routeIs('calificaciones.mis') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4.2" width="14" height="17" rx="2"/><path d="M9 4.2v-.7a1 1 0 011-1h4a1 1 0 011 1v.7"/><path d="M8.3 12.8l2.1 2.1 4.3-4.6"/></svg>
                </span>
                <span class="dyl-sb-label">Mis Calificaciones</span>
            </a>
        @endif
        @if(!auth()->user()->esAdmin() || auth()->user()->esEstudiante())
            <a href="{{ route('certificados.mis') }}" class="dyl-sb-link {{ request()->routeIs('certificados.mis') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9.2" r="4.7"/><path d="M8.8 13.2L7.2 20l4.8-2.7 4.8 2.7-1.6-6.8"/></svg>
                </span>
                <span class="dyl-sb-label">Certificados</span>
            </a>
        @endif
        @if(auth()->user()->esAdmin())
            <a href="{{ route('admin.usuarios.index') }}" class="dyl-sb-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19a6 6 0 0112 0"/><path d="M15.5 6.2a3 3 0 010 5.6"/><path d="M16 13.3a5.2 5.2 0 014.5 5.7"/></svg>
                </span>
                <span class="dyl-sb-label">Usuarios</span>
            </a>
            <a href="{{ route('admin.auditoria.index') }}" class="dyl-sb-link {{ request()->routeIs('admin.auditoria.*') ? 'active' : '' }}">
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5.5c0 4.6-3 8-7 9.5-4-1.5-7-4.9-7-9.5V6l7-3z"/><path d="M9.3 12.2l1.9 1.9 3.5-3.8"/></svg>
                </span>
                <span class="dyl-sb-label">Auditoría</span>
            </a>
        @endif
    </nav>

    <div class="dyl-sb-bottom">
        <button @click="toggle()" type="button" class="dyl-collapse-btn" :aria-expanded="!collapsed" aria-label="Colapsar o expandir la navegación">
            <span class="dyl-sb-ic" x-show="!collapsed" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="15" rx="2.2"/><path d="M14.5 4.5v15"/><path d="M11.3 9.5L8.8 12l2.5 2.5"/></svg>
            </span>
            <span class="dyl-sb-ic" x-show="collapsed" x-cloak aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="15" rx="2.2"/><path d="M9.5 4.5v15"/><path d="M12.7 9.5l2.5 2.5-2.5 2.5"/></svg>
            </span>
            <span class="dyl-sb-label" x-text="collapsed ? 'Expandir' : 'Colapsar'"></span>
        </button>
    </div>
</aside>

<style>
.dyl-sidebar {
    width: 220px;
    background: theme('colors.dyl.graphite.900');
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 50;
    transform: translateX(-100%);
    transition: width .18s ease, transform .2s ease;
}
.dyl-sidebar.is-open { transform: translateX(0); }
@media (min-width: 1024px) {
    .dyl-sidebar { position: sticky; top: 0; height: 100vh; transform: none !important; }
    .dyl-sidebar.is-collapsed { width: 72px; }
}
.dyl-sb-top { display: flex; align-items: center; padding: 18px; }
.dyl-sidebar.is-collapsed .dyl-sb-top { justify-content: center; padding: 18px 0; }
.dyl-sb-logo { display: flex; align-items: center; gap: 10px; }
.dyl-sb-sq {
    width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0;
    background: theme('colors.dyl.orange.500'); color: #1E1108;
    display: flex; align-items: center; justify-content: center;
    font-size: 10.5px; font-weight: 800;
}
.dyl-sb-label {
    display: inline-block; overflow: hidden; white-space: nowrap; max-width: 150px;
    opacity: 1; color: #fff; font-weight: 800; font-size: 14px;
    transition: max-width .15s ease, opacity .1s ease;
}
.dyl-sb-nav { flex: 1; padding: 6px 12px; display: flex; flex-direction: column; gap: 2px; overflow-y: auto; }
.dyl-sb-link {
    display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 9px;
    color: #94a3b8; font-size: 13px; font-weight: 500; text-decoration: none; position: relative;
}
.dyl-sidebar.is-collapsed .dyl-sb-link { justify-content: center; padding: 9px 0; }
.dyl-sb-ic { width: 18px; height: 18px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.55); }
.dyl-sb-ic svg { width: 18px; height: 18px; }
.dyl-sb-link:hover .dyl-sb-ic { color: #fff; }
.dyl-sb-link.active { color: #fff; background: rgba(255,255,255,.08); }
.dyl-sb-link.active .dyl-sb-ic { color: theme('colors.dyl.orange.500'); }
.dyl-sb-link.active::before { content: ""; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: theme('colors.dyl.orange.500'); }
.dyl-sb-bottom { padding: 12px; border-top: 1px solid rgba(255,255,255,.08); }
.dyl-collapse-btn {
    width: 100%; display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 9px;
    background: rgba(255,255,255,.05); color: #94a3b8; font-size: 12px; font-weight: 600;
    border: none; cursor: pointer; font-family: inherit;
}
.dyl-sidebar.is-collapsed .dyl-collapse-btn { justify-content: center; padding: 9px 0; }
.dyl-collapse-btn:hover { color: #fff; background: rgba(255,255,255,.09); }
.dyl-sidebar.is-collapsed .dyl-sb-label { max-width: 0; opacity: 0; }
</style>
@endauth
```

- [ ] **Step 4: Crear `resources/views/layouts/partials/topbar.blade.php`**

```blade
<header class="dyl-topbar" role="banner">
    <div class="flex items-center gap-3">
        @guest
            <a href="{{ route('login') }}" class="flex items-center gap-2.5" aria-label="LMS DyL - Ir al inicio">
                <span class="dyl-sb-sq" aria-hidden="true">D&amp;L</span>
                <span class="font-bold text-dyl-graphite-900">LMS</span>
            </a>
        @endguest
        @auth
            <button @click="$dispatch('sidebar-open')" type="button"
                    class="lg:hidden text-dyl-graphite-500 hover:text-dyl-graphite-900 p-2 -ml-2 rounded-lg"
                    aria-label="Abrir menú de navegación">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        @endauth
    </div>

    <div class="flex items-center gap-3">
        @auth
            <x-notification-bell />

            @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
                <a href="{{ route('cursos.create') }}" class="hidden sm:inline-flex btn btn-outline btn-sm" aria-label="Crear nuevo curso">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo Curso
                </a>
            @endif

            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-2 text-dyl-graphite-600 hover:text-dyl-graphite-900 rounded-lg px-2 py-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-dyl-orange-600"
                        aria-haspopup="true" :aria-expanded="open">
                    <div class="w-8 h-8 bg-dyl-orange-600 rounded-full flex items-center justify-center" aria-hidden="true">
                        <span class="text-white text-xs font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </div>
                    <span class="hidden md:inline text-sm max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                    <svg class="w-4 h-4 transition-transform hidden md:block" :class="open ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 top-full mt-1 w-56 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden" role="menu">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <p class="text-xs text-gray-500">Sesión activa</p>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="dropdown-item" role="menuitem">Dashboard</a>
                    <a href="{{ route('certificados.mis') }}" class="dropdown-item" role="menuitem">Mis Certificados</a>
                    <a href="{{ route('profile.edit') }}" class="dropdown-item" role="menuitem">Mi Perfil</a>
                    <div class="border-t border-gray-100"></div>
                    <form method="POST" action="{{ route('logout') }}" role="none">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-dyl-graphite-900 font-semibold hover:bg-dyl-graphite-50" role="menuitem">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Ingresar</a>
        @endauth
    </div>
</header>

<style>
.dyl-topbar {
    height: 56px; background: #fff; border-bottom: 1px solid #EDF1F5;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px; flex-shrink: 0; position: sticky; top: 0; z-index: 30;
}
</style>
```

- [ ] **Step 5: Reescribir `resources/views/layouts/app.blade.php`**

Reemplazar TODO el bloque desde `<body class="h-full flex flex-col bg-gray-50">` (línea 66 tras la Task 1) hasta el cierre de `</footer>` (línea 383), por:

```blade
<body class="h-full bg-gray-50">

    {{-- Skip to main content (accesibilidad) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50
              focus:bg-dyl-graphite-900 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm">
        Saltar al contenido principal
    </a>

    <div class="lg:flex lg:min-h-full">

        @include('layouts.partials.sidebar')

        <div class="flex-1 flex flex-col min-w-0 lg:h-screen lg:overflow-y-auto">

            @include('layouts.partials.topbar')

            {{-- ================================================================
                 CONTENIDO PRINCIPAL
            ================================================================ --}}
            <main id="main-content"
                  role="main"
                  class="flex-1 {{ !isset($fullWidth) ? 'max-w-7xl mx-auto w-full py-6 px-4 sm:px-6 lg:px-8' : '' }}">

                {{-- Breadcrumbs --}}
                @hasSection('breadcrumbs')
                <nav aria-label="Breadcrumb" class="mb-4">
                    @yield('breadcrumbs')
                </nav>
                @endif

                {{-- Mensajes globales --}}
                @unless(isset($fullWidth))
                @if(!empty($errors) && $errors->any())
                    <div class="alert alert-error mb-5" role="alert" aria-live="polite">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success mb-5" role="status" aria-live="polite">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error mb-5" role="alert" aria-live="polite">
                        {{ session('error') }}
                    </div>
                @endif
                @endunless

                @yield('content')
            </main>

            {{-- ================================================================
                 FOOTER
            ================================================================ --}}
            <footer class="bg-dyl-graphite-900 mt-auto" role="contentinfo">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2.5">
                            <div class="bg-dyl-orange-500 rounded-md flex items-center justify-center px-1.5 h-7">
                                <span class="text-dyl-graphite-900 font-bold text-xs tracking-tight">D&amp;L</span>
                            </div>
                            <span class="text-white/80 font-semibold text-sm">DyL Quality Consulting</span>
                        </div>
                        <p class="text-white/40 text-xs">
                            &copy; {{ date('Y') }} DyL Quality Consulting LTDA. Todos los derechos reservados.
                        </p>
                        <div class="flex gap-4 text-xs text-white/40">
                            <a href="{{ route('cursos.index') }}" class="hover:text-white/70 transition-colors">Cursos</a>
                            <a href="{{ route('dashboard') }}"    class="hover:text-white/70 transition-colors">Dashboard</a>
                        </div>
                    </div>
                </div>
            </footer>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    @stack('scripts')
</body>
</html>
```

Notas de este reemplazo:
- Se elimina el `<nav>` viejo completo y el bloque `<style>` final con `.nav-link`/`.mobile-nav-link`/`.dropdown-item` (`.dropdown-item` se mueve a `resources/css/app.css` porque ahora lo usa el nuevo `topbar.blade.php` — agregar `.dropdown-item { @apply flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-gray-700 transition-colors; } .dropdown-item:hover { @apply bg-gray-50; }` al `@layer components` de `app.css` en este mismo step).
- El resto del `<head>` (líneas 1–65 del archivo original) no cambia.

- [ ] **Step 6: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=SidebarNavigationTest`
Expected: los 3 tests en verde.

- [ ] **Step 7: Correr la suite completa**

Run: `php artisan test`
Expected: todo en verde — presta especial atención a `AccesControlTest` y `LmsAuthTest` (dependen de que `/dashboard` y `/login` sigan respondiendo 200/403 igual que antes).

- [ ] **Step 8: Compilar assets y revisión manual**

Run: `npm run build`

Luego, con `php artisan serve` corriendo, verificar manualmente en el navegador:
- El sidebar colapsa/expande con el botón y el estado sobrevive a un refresh (localStorage).
- En una ventana angosta (`<1024px`), el sidebar es un drawer que se abre con el botón hamburguesa del topbar y se cierra al hacer click fuera.
- Como estudiante: no se ven "Usuarios", "Auditoría" ni "Reportes". Como admin: se ven los tres.

- [ ] **Step 9: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/layouts/partials/sidebar.blade.php resources/views/layouts/partials/topbar.blade.php resources/css/app.css tests/Feature/SidebarNavigationTest.php
git commit -m "feat: reemplazar navbar por sidebar colapsable + topbar"
```

---

### Task 4: Verificación final de la Fase 1

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Confirmar que no queda ningún token viejo en todo el repo**

Run: `grep -rE "dyl-(navy|blue|gold)" resources/`
Expected: sin resultados.

- [ ] **Step 2: Confirmar que `btn-navy` y `btn-gold` no se usan en ningún lado**

Run: `grep -rl "btn-navy\|btn-gold" resources/views`
Expected: sin resultados (ya lo eran antes de este plan).

- [ ] **Step 3: Suite completa + build**

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 4: Checklist visual manual contra los mockups aprobados**

Con `php artisan serve` + `npm run dev` corriendo, comparar contra el artifact de la sesión de diseño (login, dashboard, catálogo de cursos):
- [ ] `/login` — panel izquierdo grafito-900 con acento naranja, botón "Iniciar sesión" naranja píldora.
- [ ] `/dashboard` — sidebar grafito-900, ítem activo con barra naranja, cards `rounded-2xl`.
- [ ] `/cursos` — botones y acentos naranja-600, sin azul/dorado/navy visibles en ningún lado.
- [ ] Un flash de error (ej. login con credenciales inválidas) se ve como bloque grafito-900 sólido, no rojo.

- [ ] **Step 5: Actualizar el estado de la spec**

En `docs/superpowers/specs/2026-08-03-rediseno-visual-naranja-grafito-design.md`, no se cambia nada (la Fase 1 es una implementación parcial de la spec completa; el resto — sweep de colores crudos en ~57 vistas restantes, pantallas de detalle de curso/lección/cuestionario/calificaciones/certificados — queda para una Fase 2 con su propio plan).

- [ ] **Step 6: Commit final (si hubo ajustes del checklist manual)**

```bash
git add -A
git commit -m "fix: ajustes visuales tras verificacion manual de fase 1"
```
(omitir si el checklist no encontró nada que corregir)
