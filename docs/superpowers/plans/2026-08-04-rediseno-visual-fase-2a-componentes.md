# Rediseño Visual Fase 2A (Componentes compartidos) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar los últimos colores Tailwind crudos (rojo/verde/azul/amarillo) de los componentes Blade compartidos (`resources/views/components/*`), reutilizando las clases del sistema de diseño ya construidas en `resources/css/app.css` durante la Fase 1, y borrar los componentes de Breeze que quedaron sin uso tras el rediseño del navbar.

**Architecture:** Auditoría confirmó que de los 9 archivos en `resources/views/components/` con color crudo, 4 no tienen ningún uso real en la app (quedaron huérfanos cuando el navbar se reemplazó por el sidebar en la Fase 1) y se eliminan en vez de migrarse. Los 5 restantes se migran para que deleguen en clases ya existentes de `app.css` (`.btn-primary`, `.form-input`, `.form-error`) en vez de duplicar estilos Tailwind crudos — esto es principalmente una consolidación DRY, no una invención de estilos nuevos.

**Tech Stack:** Laravel 11 Blade + Tailwind CSS 3 (JIT). Sin dependencias nuevas.

## Global Constraints

- Solo dos familias de color en toda la UI: `dyl-orange` y `dyl-graphite` — sin excepción, ni siquiera para mensajes de éxito/error de formularios (ya establecido en la Fase 1, `docs/superpowers/specs/2026-08-03-rediseno-visual-naranja-grafito-design.md`).
- No se reinventan estilos: donde exista una clase equivalente en `app.css` (`.btn-primary`, `.form-input`, `.form-error`), el componente debe reutilizarla en vez de duplicar Tailwind crudo.
- Antes de borrar un archivo, confirmar con grep que no tiene ninguna referencia (`x-nombre-componente` o `@include`/`@extends` con su ruta) en todo `resources/` y `app/`.

---

### Task 1: Eliminar componentes de Breeze sin uso

**Files:**
- Delete: `resources/views/layouts/navigation.blade.php`
- Delete: `resources/views/components/nav-link.blade.php`
- Delete: `resources/views/components/responsive-nav-link.blade.php`
- Delete: `resources/views/components/danger-button.blade.php`
- Delete: `resources/views/components/secondary-button.blade.php`

**Interfaces:**
- No produce nada — estos archivos no son consumidos por ningún otro archivo de la app (verificado antes de escribir este plan: `layouts/navigation.blade.php` no aparece en ningún `@extends`/`@include`/`view()`; `nav-link` y `responsive-nav-link` solo se usaban dentro de `layouts/navigation.blade.php`, que también se borra; `danger-button` y `secondary-button` tienen cero usos de `x-danger-button`/`x-secondary-button` en todo `resources/views`).

- [ ] **Step 1: Confirmar de nuevo que los 5 archivos siguen sin referencias (por si algo cambió desde que se escribió este plan)**

Run:
```bash
grep -rn "layouts.navigation\|layouts\\\\navigation" resources/ app/ routes/
grep -rln "x-nav-link\b" resources/views --include="*.blade.php" | grep -v "components/nav-link.blade.php\|layouts/navigation.blade.php"
grep -rln "x-responsive-nav-link" resources/views --include="*.blade.php" | grep -v "components/responsive-nav-link.blade.php\|layouts/navigation.blade.php"
grep -rln "x-danger-button" resources/views --include="*.blade.php" | grep -v "components/danger-button.blade.php"
grep -rln "x-secondary-button" resources/views --include="*.blade.php" | grep -v "components/secondary-button.blade.php"
```
Expected: todos vacíos. Si alguno devuelve un archivo real (no el propio componente), STOP — ese archivo dejó de estar muerto, sácalo de esta tarea y avisa en el reporte.

- [ ] **Step 2: Borrar los 5 archivos**

```bash
rm resources/views/layouts/navigation.blade.php
rm resources/views/components/nav-link.blade.php
rm resources/views/components/responsive-nav-link.blade.php
rm resources/views/components/danger-button.blade.php
rm resources/views/components/secondary-button.blade.php
```

- [ ] **Step 3: Correr la suite completa**

Run: `php artisan test`
Expected: todo en verde (son archivos sin ninguna referencia, no debería haber ningún cambio en el comportamiento).

- [ ] **Step 4: Compilar assets**

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 5: Commit**

```bash
git add -A -- resources/views/layouts/navigation.blade.php resources/views/components/nav-link.blade.php resources/views/components/responsive-nav-link.blade.php resources/views/components/danger-button.blade.php resources/views/components/secondary-button.blade.php
git commit -m "chore: eliminar componentes de Breeze sin uso (navbar viejo ya reemplazado)"
```

---

### Task 2: Migrar primary-button, text-input, auth-session-status e input-error a las clases del sistema de diseño

**Files:**
- Modify: `resources/views/components/primary-button.blade.php`
- Modify: `resources/views/components/text-input.blade.php`
- Modify: `resources/views/components/auth-session-status.blade.php`
- Modify: `resources/views/components/input-error.blade.php`

**Interfaces:**
- Consumes: `.btn`, `.btn-primary`, `.form-input`, `.form-error` de `resources/css/app.css` (definidas en la Fase 1, sin cambios necesarios ahí).
- Usado por: `auth/confirm-password.blade.php`, `auth/forgot-password.blade.php`, `auth/register.blade.php`, `auth/reset-password.blade.php`, `auth/verify-email.blade.php` (vía `<x-primary-button>`), y varias vistas de `profile/` y `auth/` (vía `<x-text-input>`, `<x-input-error>`, `<x-auth-session-status>`) — ninguna de esas vistas consumidoras necesita cambios, el componente cambia de apariencia mientras la interfaz (`{{ $slot }}`, `$attributes->merge(...)`, `@props`) se mantiene idéntica.

- [ ] **Step 1: `primary-button.blade.php` — delega en `.btn-primary`**

Contenido actual:
```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
```

Reemplazar por:
```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary']) }}>
    {{ $slot }}
</button>
```

Esto hace que los botones de envío en `confirm-password`, `forgot-password`, `register`, `reset-password` y `verify-email` pasen de gris/rectangular (estilo Breeze sin tocar desde la Fase 1) a naranja/píldora, igual que el botón de `auth/login.blade.php` — son la misma familia de pantallas y hoy se ven inconsistentes entre sí.

- [ ] **Step 2: `text-input.blade.php` — delega en `.form-input`**

Contenido actual:
```blade
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>
```

Reemplazar por:
```blade
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-input']) }}>
```

- [ ] **Step 3: `auth-session-status.blade.php` — recolor sin cambiar de tratamiento visual (sigue siendo texto simple, no una alerta con caja)**

Contenido actual:
```blade
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
```

Reemplazar `text-green-600` por `text-dyl-orange-700`:
```blade
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-dyl-orange-700']) }}>
        {{ $status }}
    </div>
@endif
```

No se convierte a `.alert-success` (caja con fondo/borde) a propósito: este componente se usa como una línea de confirmación pequeña junto al botón de envío (p. ej. "Te hemos enviado el enlace de restablecimiento"), y convertirlo en una caja completa sería un cambio de peso visual no pedido.

- [ ] **Step 4: `input-error.blade.php` — mismo tratamiento que `.form-error`**

Contenido actual:
```blade
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

Reemplazar `text-sm text-red-600` por `text-sm text-dyl-graphite-900 font-semibold` (mismo color/peso que `.form-error` en `app.css`, adaptado a que este componente ya trae su propio `text-sm` en vez de `text-xs`):
```blade
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-dyl-graphite-900 font-semibold space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

- [ ] **Step 5: Verificar que no hay tests que dependan de las clases viejas**

Run: `grep -rln "bg-gray-800\|indigo-500\|text-green-600\|text-red-600" tests/`
Expected: sin resultados (ya confirmado antes de escribir este plan, pero verificar de nuevo por si algo cambió).

- [ ] **Step 6: Correr la suite completa**

Run: `php artisan test`
Expected: todo en verde. Estos componentes son usados por varios tests de auth/profile (`tests/Feature/ProfileTest.php`, `tests/Feature/LmsAuthTest.php`) que verifican comportamiento (redirects, mensajes de sesión), no clases CSS — no deberían verse afectados por este cambio puramente visual.

- [ ] **Step 7: Compilar assets**

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/primary-button.blade.php resources/views/components/text-input.blade.php resources/views/components/auth-session-status.blade.php resources/views/components/input-error.blade.php
git commit -m "feat: migrar primary-button, text-input, auth-session-status e input-error al sistema de diseño"
```

---

### Task 3: Migrar el panel desplegable de notificaciones

**Files:**
- Modify: `resources/views/components/notification-bell.blade.php`

**Interfaces:**
- No cambia la interfaz del componente (sigue sin props, se incluye igual en `resources/views/layouts/partials/topbar.blade.php`).
- El botón/badge de este componente ya se recoloreó en la Fase 1 (líneas 12 y 17 ya usan `dyl-graphite-500`/`dyl-graphite-900`/`dyl-orange-600` — no tocar esas líneas, solo el panel desplegable de abajo).

- [ ] **Step 1: Reemplazar los 6 usos de azul/verde/amarillo en el panel desplegable**

```diff
- <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">Marcar todas leídas</button>
+ <button type="submit" class="text-xs text-dyl-orange-600 hover:text-dyl-orange-700">Marcar todas leídas</button>
```

```diff
- class="block px-4 py-3 hover:bg-gray-50 transition-colors {{ $n->leido ? '' : 'bg-blue-50/60' }}">
+ class="block px-4 py-3 hover:bg-gray-50 transition-colors {{ $n->leido ? '' : 'bg-dyl-orange-50/60' }}">
```

Los tres íconos por tipo de notificación (`calificacion`, `entrega`, `certificado`) hoy usan tres matices distintos (verde/azul/amarillo) para diferenciar categorías — pero el ícono (el `<path>` del SVG) ya es distinto por tipo, así que el color no necesita cargar esa distinción. Los tres pasan a `dyl-orange-600` (son eventos "notables", tratamiento positivo consistente); el ícono por defecto (sin tipo) se queda en gris neutro como ya está:

```diff
-                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
+                                <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
```
```diff
-                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
+                                <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
```
```diff
-                                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
+                                <svg class="w-5 h-5 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
```

Punto sin leer (el circulito):
```diff
-                            <span class="w-2 h-2 bg-blue-500 rounded-full shrink-0 mt-2"></span>
+                            <span class="w-2 h-2 bg-dyl-orange-600 rounded-full shrink-0 mt-2"></span>
```

Enlace "Ver todas":
```diff
- <a href="{{ route('notificaciones.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Ver todas las notificaciones</a>
+ <a href="{{ route('notificaciones.index') }}" class="text-xs text-dyl-orange-600 hover:text-dyl-orange-700">Ver todas las notificaciones</a>
```

- [ ] **Step 2: Verificar que no queda ningún color crudo en el archivo**

Run: `grep -riE "(red|green|blue|yellow|indigo)-[0-9]+" resources/views/components/notification-bell.blade.php`
Expected: sin resultados.

- [ ] **Step 3: Correr la suite completa**

Run: `php artisan test`
Expected: todo en verde.

- [ ] **Step 4: Compilar assets**

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/notification-bell.blade.php
git commit -m "feat: migrar panel de notificaciones a naranja/grafito"
```

---

### Task 4: Verificación final de la Sub-fase 2A

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Confirmar que no queda color crudo en `resources/views/components/`**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose)-[0-9]+" resources/views/components`
Expected: sin resultados.

- [ ] **Step 2: Confirmar que los 5 archivos borrados en la Task 1 realmente no existen y nada los referencia**

Run:
```bash
ls resources/views/layouts/navigation.blade.php resources/views/components/nav-link.blade.php resources/views/components/responsive-nav-link.blade.php resources/views/components/danger-button.blade.php resources/views/components/secondary-button.blade.php
```
Expected: error "No such file or directory" para los 5 (confirma que se borraron y nadie los regeneró).

- [ ] **Step 3: Suite completa + build**

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 4: Checklist visual manual (para que el usuario la haga con su entorno local corriendo)**

- [ ] `/register`, `/forgot-password`, `/reset-password/...`, `/verify-email` — el botón de envío ahora es naranja/píldora, no gris/rectangular.
- [ ] Cualquier campo de formulario en esas pantallas usa el mismo estilo de input que el resto de la app.
- [ ] El panel de notificaciones (clic en la campana del topbar) — ítems, "Marcar todas leídas" y "Ver todas" en naranja, sin azul/verde/amarillo.

- [ ] **Step 5: Commit final (si el checklist manual encontró algo que corregir)**

```bash
git add -A
git commit -m "fix: ajustes visuales tras verificacion manual de la sub-fase 2a"
```
(omitir si no hizo falta)
