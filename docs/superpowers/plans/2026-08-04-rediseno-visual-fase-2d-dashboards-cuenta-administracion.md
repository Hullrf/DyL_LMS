# Rediseño Visual Fase 2D (Dashboards, Cuenta y Administración) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar todo color Tailwind crudo (rojo/verde/azul/amarillo/púrpura/índigo/naranja-sin-prefijo) de las 20 vistas restantes del rediseño visual — `dashboard/*`, `reportes/*`, `mensajes/*`, `notificaciones/index`, `profile/*`, `admin/*`, `auth/*` — migrando a `dyl-orange`/`dyl-graphite`. A diferencia de la Fase 2C, ninguna de estas pantallas tiene mockup específico (el spec las marca explícitamente como "heredan tokens, sin rediseño de layout"), así que el trabajo es mayormente recolor mecánico reutilizando los mapeos ya establecidos en Fases 2B/2C. La única pieza genuinamente nueva es la paleta de gráficos Chart.js (3 archivos usan hex literal en `<script>`, fuera del alcance de cualquier grep de clases Tailwind — mismo tipo de hallazgo que `certificados/plantilla-pdf.blade.php` en la Fase 2C, pero detectado aquí de antemano en vez de en la revisión final).

**Architecture:** 5 tareas de migración agrupadas por subdominio + 1 de verificación final, mismo patrón que Fases 2B/2C. Task 1 y 2 incluyen migración de colores Chart.js (hex, no Tailwind) además de las clases Blade.

**Tech Stack:** Laravel 12 Blade + Tailwind CSS 3 (JIT) + Alpine.js + Chart.js (CDN, ya integrado). Sin dependencias nuevas.

## Global Constraints

Heredadas de Fases 2B/2C (aplican íntegras a esta fase):
- Solo dos familias de color en toda la UI: `dyl-orange` y `dyl-graphite` — sin excepción. Incluye Tailwind sin prefijo `dyl-` (`bg-orange-500`, `focus:ring-indigo-500`, etc.) y colores hex literales fuera de clases Tailwind (bloques `<script>` de Chart.js en esta fase).
- Acciones destructivas nunca se recolorean a naranja. Botón sólido destructivo → `bg-dyl-graphite-900 hover:bg-dyl-graphite-800 text-white` (ya son `.btn-danger`, sin cambios de clase). Panel/confirmación destructiva → tratamiento "advertencia": `bg-dyl-graphite-50 border-2 border-dyl-orange-300`, texto `text-dyl-graphite-900 font-semibold`.
- Dos estados en un mismo eje visual: el estado final/positivo → `dyl-orange-600/700` vívido; el intermedio/anterior → `dyl-graphite-400/500/600` apagado — nunca los dos a naranja.
- Mensajes flash (`session('success')`) → clase `alert alert-success` de `app.css`, no traducción manual.
- Errores en línea de formulario: `text-red-600` → `text-dyl-graphite-900 font-semibold`.
- `gray-*` no forma parte de este barrido.
- Íconos de categoría/tipo (no de estado) convergen a `dyl-graphite-500` — mismo criterio que los íconos de tipo de recurso en Fase 2C.

**Mapeos reutilizados literalmente de fases anteriores (para consistencia entre pantallas que repiten el mismo patrón):**

| Patrón | Mapeo (de Fase 2B, `cursos/index.blade.php`) |
|---|---|
| Badge estado de curso (`publicado`/`borrador`/resto) | `publicado` → `bg-dyl-orange-100 text-dyl-orange-700`; `borrador` → `bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold`; resto → `bg-gray-100 text-gray-500` (sin cambio) |
| Badge estado de inscripción/progreso (`completado`/`en_progreso`/resto) | `completado` → `bg-dyl-orange-100 text-dyl-orange-700` (estado final); `en_progreso` → `bg-dyl-graphite-100 text-dyl-graphite-600` (intermedio); resto → `bg-gray-100 text-gray-500` |

**Mapeos reutilizados de Fase 2C:**

| Patrón | Mapeo |
|---|---|
| Aprobado/reprobado (`>= 60 ? … : …`) | aprobado → `text-dyl-orange-600`; reprobado → `text-dyl-graphite-500` |
| Dorado/certificado (`yellow-*` en insignias, íconos 🎓) | → `dyl-orange-*` al mismo tono numérico |
| Barra de progreso 100% vs. en curso | 100% → `bg-dyl-orange-600`; en curso → `bg-dyl-graphite-400` (o el tono ya usado, `bg-blue-500`→`bg-dyl-graphite-400`) |

**Nuevo para esta fase — paleta de gráficos Chart.js (hex literal, no hay tokens `dyl-*` disponibles en JS, se usa el valor hex exacto tomado de `tailwind.config.js`):**

| Rol | Hex | Token |
|---|---|---|
| Serie única (barra/línea con 1 dataset) | `#EA580C` | `dyl-orange-600` |
| Relleno de área bajo línea (line chart) | `rgba(234,88,12,0.08)` | `dyl-orange-600` al 8% opacidad |
| Categoría "positiva/activa" en gráfico de 3 categorías (dona) | `#F97316` | `dyl-orange-500` |
| Categoría "neutra/pendiente" en gráfico de 3 categorías | `#CBD5E1` | `dyl-graphite-300` |
| Categoría "cerrada/inactiva" en gráfico de 3 categorías | `#475569` | `dyl-graphite-600` |

**Nuevo para esta fase — filas de KPI con 3-4 tarjetas de conteo, cada una en un color crudo distinto solo para variedad visual (no son estados):** por fila, **una sola tarjeta** se queda con el acento naranja (la métrica más "positiva/relevante" del grupo — se indica cuál en cada Step) y el resto pasa a `text-dyl-graphite-700` (conteo neutro). Mismo criterio que la Fase 2C aplicó en los KPIs de `mis-calificaciones.blade.php`.

---

### Task 1: Migrar `resources/views/dashboard/*` (3 archivos, incluye paleta de gráficos)

**Files:**
- Modify: `resources/views/dashboard/admin.blade.php`
- Modify: `resources/views/dashboard/instructor.blade.php`
- Modify: `resources/views/dashboard/estudiante.blade.php`

**Interfaces:** Ninguna — solo clases CSS y colores hex de Chart.js, sin cambios de lógica ni de datos que se pasan a las vistas.

- [ ] **Step 1: `dashboard/admin.blade.php` — KPIs**

Fila de 4 KPIs: "Instructores" ya está en naranja crudo (`orange-500`) — se queda como el acento naranja de la fila (solo se corrige el token). Los otros 3 pasan a grafito neutro:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['total_cursos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['total_cursos'] }}</p>
```
(línea 11, "Total Cursos")

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['cursos_publicados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['cursos_publicados'] }}</p>
```
(línea 15, "Publicados")

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['total_usuarios'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['total_usuarios'] }}</p>
```
(línea 19, "Total Usuarios")

```diff
- <p class="text-3xl font-bold text-orange-500 mt-1">{{ $stats['total_instructores'] }}</p>
+ <p class="text-3xl font-bold text-dyl-orange-500 mt-1">{{ $stats['total_instructores'] }}</p>
```
(línea 23, "Instructores" — corrección de token, ya era el acento de la fila)

- [ ] **Step 2: `dashboard/admin.blade.php` — accesos rápidos y tabla**

```diff
- <a href="{{ route('reportes.index') }}"
-    class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium text-sm">
+ <a href="{{ route('reportes.index') }}"
+    class="bg-dyl-orange-600 text-white px-5 py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
```
(línea 29-30, "Ver Reportes")

```diff
- <a href="{{ route('reportes.index') }}" class="text-sm text-blue-600 hover:underline">Ver todos los reportes</a>
+ <a href="{{ route('reportes.index') }}" class="text-sm text-dyl-orange-600 hover:underline">Ver todos los reportes</a>
```
(línea 47)

Badge de estado del curso (mapeo reutilizado de Fase 2B, ver tabla de Global Constraints):

```diff
  <span class="px-2 py-1 rounded-full text-xs font-medium
-     @if($curso->estado === 'publicado') bg-green-100 text-green-700
-     @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-700
+     @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-700
+     @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
      @else bg-gray-100 text-gray-500 @endif">
```
(línea 65-68)

```diff
- <a href="{{ route('reportes.curso', $curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">Reporte</a>
+ <a href="{{ route('reportes.curso', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Reporte</a>
```
(línea 73 — el link "Editar" junto a él, `text-gray-600 hover:text-gray-900`, no cambia: gray fuera de alcance)

- [ ] **Step 3: `dashboard/admin.blade.php` — paleta de gráficos Chart.js**

Doughnut "Cursos por Estado" (Borrador/Publicado/Archivado — 3 categorías, usa el mapeo de Global Constraints: Publicado=positiva, Borrador=neutra, Archivado=cerrada):

```diff
              labels: ['Borrador', 'Publicado', 'Archivado'],
              datasets: [{
                  data: [{{ $stats['cursos_borrador'] }}, {{ $stats['cursos_publicados'] }}, {{ $stats['cursos_archivados'] }}],
-                 backgroundColor: ['#FCD34D', '#34D399', '#9CA3AF'],
+                 backgroundColor: ['#CBD5E1', '#F97316', '#475569'],
                  borderWidth: 0,
              }]
```
(línea 107-111 — reordenado semánticamente: Borrador=`#CBD5E1` graphite-300 neutra, Publicado=`#F97316` orange-500 positiva, Archivado=`#475569` graphite-600 cerrada, en ese orden porque así están las etiquetas)

Bar chart "Inscripciones — Últimos 6 meses" (serie única):

```diff
              datasets: [{
                  label: 'Inscripciones',
                  data: @json($stats['meses_data']),
-                 backgroundColor: '#3B82F6',
+                 backgroundColor: '#EA580C',
                  borderRadius: 6,
              }]
```
(línea 124-128)

- [ ] **Step 4: `dashboard/instructor.blade.php` — flash y KPIs**

```diff
- @if(session('success'))
-     <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
- @endif
+ @if(session('success'))
+     <div class="alert alert-success mb-6">{{ session('success') }}</div>
+ @endif
```
(línea 7-9 — consolidación DRY con `.alert-success`, mismo criterio que fases anteriores)

KPIs: "Por Calificar" ya tenía su propio eje de 2 estados (`orange-500` si hay pendientes, `gray-400` si no) — se queda como el acento de la fila, solo se corrige el token naranja. Las otras 3 tarjetas pasan a grafito:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['mis_cursos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['mis_cursos'] }}</p>
```
(línea 15, "Mis Cursos")

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['cursos_publicados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['cursos_publicados'] }}</p>
```
(línea 19, "Publicados")

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['estudiantes_inscritos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['estudiantes_inscritos'] }}</p>
```
(línea 23, "Estudiantes")

```diff
- <p class="text-3xl font-bold mt-1 {{ $stats['pendientes_calificar'] > 0 ? 'text-orange-500' : 'text-gray-400' }}">
+ <p class="text-3xl font-bold mt-1 {{ $stats['pendientes_calificar'] > 0 ? 'text-dyl-orange-500' : 'text-gray-400' }}">
      {{ $stats['pendientes_calificar'] }}
  </p>
  @if($stats['pendientes_calificar'] > 0)
-     <p class="text-xs text-orange-500 mt-1">Ver pendientes &rarr;</p>
+     <p class="text-xs text-dyl-orange-500 mt-1">Ver pendientes &rarr;</p>
  @endif
```
(línea 27-31, "Por Calificar")

- [ ] **Step 5: `dashboard/instructor.blade.php` — acciones, tarjetas de curso**

```diff
- <a href="{{ route('cursos.create') }}"
-    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
+ <a href="{{ route('cursos.create') }}"
+    class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm">
      + Nuevo Curso
  </a>
```
(línea 44-47)

Badge de estado (mismo mapeo reutilizado de 2B, con los tonos 800 que ya usaba este archivo — se mantiene el peso 800 en lugar de 700 para no perder legibilidad, el criterio de color es el mismo):

```diff
  <span class="px-2 py-1 rounded text-xs font-medium ml-2 flex-shrink-0
-     @if($curso->estado === 'publicado') bg-green-100 text-green-800
-     @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-800
+     @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-800
+     @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
      @else bg-gray-100 text-gray-800 @endif">
```
(línea 56-59)

```diff
- <a href="{{ route('cursos.edit', $curso) }}"
-    class="flex-1 text-center bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-sm">
+ <a href="{{ route('cursos.edit', $curso) }}"
+    class="flex-1 text-center bg-dyl-orange-600 text-white py-2 rounded hover:bg-dyl-orange-700 text-sm">
      Editar
  </a>
```
(línea 69-72 — el botón "Ver" junto a él, `bg-gray-100 text-gray-700`, no cambia)

```diff
- <a href="{{ route('cursos.create') }}" class="text-blue-600 hover:underline">Crea tu primer curso</a>
+ <a href="{{ route('cursos.create') }}" class="text-dyl-orange-600 hover:underline">Crea tu primer curso</a>
```
(línea 78, estado vacío)

- [ ] **Step 6: `dashboard/instructor.blade.php` — paleta de gráficos**

Bar chart "Progreso promedio por curso" (serie única):

```diff
              datasets: [{
                  label: 'Progreso (%)',
                  data: @json($stats['progreso_por_curso']),
-                 backgroundColor: '#6366F1',
+                 backgroundColor: '#EA580C',
                  borderRadius: 6,
              }]
```
(línea 97-101)

- [ ] **Step 7: `dashboard/estudiante.blade.php`**

```diff
- @if(session('success'))
-     <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
- @endif
+ @if(session('success'))
+     <div class="alert alert-success mb-6">{{ session('success') }}</div>
+ @endif
```
(línea 7-9)

KPIs: "Completados" es el logro/estado final del estudiante — se queda como el acento naranja de la fila; las otras 2 pasan a grafito:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['cursos_activos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['cursos_activos'] }}</p>
```
(línea 14, "Cursos Activos")

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-orange-600 mt-1">{{ $stats['completados'] }}</p>
```
(línea 18, "Completados" — acento de la fila)

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['progreso_general'] }}%</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['progreso_general'] }}%</p>
```
(línea 22, "Progreso General")

```diff
- <div class="w-full h-32 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
+ <div class="w-full h-32 bg-gradient-to-br from-dyl-orange-400 to-dyl-orange-600 flex items-center justify-center">
```
(línea 46, portada decorativa de la tarjeta de curso)

```diff
- <a href="{{ route('cursos.show', $curso) }}"
-    class="block w-full bg-blue-600 text-white py-2 rounded text-center hover:bg-blue-700 text-sm font-medium">
+ <a href="{{ route('cursos.show', $curso) }}"
+    class="block w-full bg-dyl-orange-600 text-white py-2 rounded text-center hover:bg-dyl-orange-700 text-sm font-medium">
      Continuar
  </a>
```
(línea 52-55)

```diff
- <a href="{{ route('cursos.index') }}" class="text-blue-600 hover:underline ml-1">Ver cursos disponibles</a>
+ <a href="{{ route('cursos.index') }}" class="text-dyl-orange-600 hover:underline ml-1">Ver cursos disponibles</a>
```
(línea 61, estado vacío)

- [ ] **Step 8: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/dashboard | grep -v dyl-orange`
Expected: sin resultados.

Run: `grep -n "FCD34D\|34D399\|9CA3AF\|3B82F6\|6366F1" resources/views/dashboard/admin.blade.php resources/views/dashboard/instructor.blade.php`
Expected: sin resultados (paleta de gráficos también migrada).

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 9: Commit**

```bash
git add resources/views/dashboard
git commit -m "feat: migrar resources/views/dashboard a naranja/grafito (incluye paleta Chart.js)"
```

---

### Task 2: Migrar `resources/views/reportes/*` (3 archivos, incluye paleta de gráficos)

**Files:**
- Modify: `resources/views/reportes/index.blade.php`
- Modify: `resources/views/reportes/curso.blade.php`
- Modify: `resources/views/reportes/estudiante.blade.php`

**Interfaces:** Ninguna.

- [ ] **Step 1: `reportes/index.blade.php` — KPIs globales (fila 1)**

"Tasa de completitud" y "Certificados emitidos" ya tienen su propio criterio de acento (ver Global Constraints); "Estudiantes" e "Inscripciones" pasan a grafito:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $kpis['total_estudiantes'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['total_estudiantes'] }}</p>
```
(línea 16, "Estudiantes")

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $kpis['total_inscripciones'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['total_inscripciones'] }}</p>
```
(línea 20, "Inscripciones")

Tasa de completitud — eje de 2 estados (≥70% positivo, resto atención):

```diff
- <p class="text-3xl font-bold mt-1 {{ $kpis['tasa_completitud'] >= 70 ? 'text-green-600' : 'text-orange-500' }}">
+ <p class="text-3xl font-bold mt-1 {{ $kpis['tasa_completitud'] >= 70 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      {{ $kpis['tasa_completitud'] }}%
  </p>
  <div class="mt-2 h-1.5 bg-gray-200 rounded-full">
-     <div class="h-full rounded-full {{ $kpis['tasa_completitud'] >= 70 ? 'bg-green-500' : 'bg-orange-400' }}"
+     <div class="h-full rounded-full {{ $kpis['tasa_completitud'] >= 70 ? 'bg-dyl-orange-500' : 'bg-dyl-graphite-400' }}"
           style="width: {{ min($kpis['tasa_completitud'], 100) }}%"></div>
  </div>
```
(línea 24-30)

Certificados emitidos — dorado→naranja:

```diff
- <p class="text-3xl font-bold text-yellow-500 mt-1">{{ $kpis['total_certificados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-orange-500 mt-1">{{ $kpis['total_certificados'] }}</p>
```
(línea 34)

- [ ] **Step 2: `reportes/index.blade.php` — KPIs (fila 2)**

"Publicados" es el acento naranja de esta segunda fila (curso publicado = hito positivo, mismo criterio que el badge de estado); "Completaron cursos" pasa a grafito. "Total cursos" (`text-gray-800`) y "Promedio calificación" (ya tiene su propio eje `>=60 verde:gris`) no forman parte de este cambio salvo el eje de aprobado/reprobado:

```diff
- <p class="text-2xl font-bold text-green-600 mt-1">{{ $kpis['cursos_publicados'] }}</p>
+ <p class="text-2xl font-bold text-dyl-orange-600 mt-1">{{ $kpis['cursos_publicados'] }}</p>
```
(línea 45, "Publicados")

```diff
- <p class="text-2xl font-bold text-blue-600 mt-1">{{ $kpis['completados'] }}</p>
+ <p class="text-2xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['completados'] }}</p>
```
(línea 49, "Completaron cursos")

```diff
- <p class="text-2xl font-bold mt-1 {{ ($kpis['promedio_calificacion'] ?? 0) >= 60 ? 'text-green-600' : 'text-gray-500' }}">
+ <p class="text-2xl font-bold mt-1 {{ ($kpis['promedio_calificacion'] ?? 0) >= 60 ? 'text-dyl-orange-600' : 'text-gray-500' }}">
```
(línea 53, "Promedio calificación" — el lado reprobado ya era `gray-500`, se deja igual; solo el lado aprobado pasa a naranja)

- [ ] **Step 3: `reportes/index.blade.php` — tabla de cursos y usuarios**

Badge de estado de curso (mapeo reutilizado 2B):

```diff
  <span class="px-2 py-1 text-xs rounded-full font-medium
-     @if($curso->estado === 'publicado') bg-green-100 text-green-700
-     @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-700
+     @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-700
+     @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
      @else bg-gray-100 text-gray-500 @endif">
```
(línea 89-92)

```diff
- <a href="{{ route('reportes.curso', $curso) }}"
-    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
+ <a href="{{ route('reportes.curso', $curso) }}"
+    class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
      Ver reporte &rarr;
  </a>
```
(línea 100-103)

```diff
- <a href="{{ route('reportes.estudiante', $usr) }}"
-    class="text-blue-600 hover:text-blue-800 text-sm">Ver &rarr;</a>
+ <a href="{{ route('reportes.estudiante', $usr) }}"
+    class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver &rarr;</a>
```
(línea 139-140)

- [ ] **Step 4: `reportes/index.blade.php` — paleta de gráficos**

Line chart "Inscripciones por mes" (serie única, con relleno de área):

```diff
              datasets: [{
                  label: 'Inscripciones',
                  data: @json($chartData['meses_data']),
-                 borderColor: '#3B82F6',
-                 backgroundColor: 'rgba(59,130,246,0.08)',
+                 borderColor: '#EA580C',
+                 backgroundColor: 'rgba(234,88,12,0.08)',
                  tension: 0.4,
                  fill: true,
-                 pointBackgroundColor: '#3B82F6',
+                 pointBackgroundColor: '#EA580C',
              }]
```
(línea 173-181)

Doughnut "Estado de Respuestas" (Sin calificar/Calificada/En revisión — 3 categorías: Calificada=positiva, Sin calificar=neutra/pendiente, En revisión=activa/atención):

```diff
              labels: ['Sin calificar', 'Calificada', 'En revisión'],
              datasets: [{
                  data: @json($chartData['resp_estados']),
-                 backgroundColor: ['#FCD34D', '#34D399', '#60A5FA'],
+                 backgroundColor: ['#CBD5E1', '#F97316', '#475569'],
                  borderWidth: 0,
              }]
```
(línea 193-197 — Sin calificar=`#CBD5E1` graphite-300, Calificada=`#F97316` orange-500, En revisión=`#475569` graphite-600, en ese orden porque así están las etiquetas)

- [ ] **Step 5: `reportes/curso.blade.php` — botones de exportación**

Los 3 botones de exportación (CSV/PDF/Excel) son acciones primarias equivalentes, no estados — los 3 pasan a naranja uniforme (se distinguen por texto e ícono, no por color):

```diff
- <a href="{{ route('reportes.csv', $curso) }}"
-    class="flex items-center gap-1.5 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
+ <a href="{{ route('reportes.csv', $curso) }}"
+    class="flex items-center gap-1.5 bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm font-medium">
```
(línea 13-14, "Exportar CSV")

```diff
- <a href="{{ route('reportes.pdf', $curso) }}"
-    class="flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
+ <a href="{{ route('reportes.pdf', $curso) }}"
+    class="flex items-center gap-1.5 bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm font-medium">
```
(línea 20-21, "Exportar PDF")

```diff
- <a href="{{ route('reportes.excel.curso', $curso) }}"
-    class="flex items-center gap-1.5 bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 text-sm font-medium">
+ <a href="{{ route('reportes.excel.curso', $curso) }}"
+    class="flex items-center gap-1.5 bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm font-medium">
```
(línea 27-28, "Excel")

- [ ] **Step 6: `reportes/curso.blade.php` — KPIs del curso**

"Completados" es el acento naranja (estado final); "En progreso" corrige su token naranja crudo a grafito (es el estado intermedio, mismo criterio que el badge de estado de inscripción); "Inscritos" y "Lecciones" pasan a grafito:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $reporte['total_inscritos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['total_inscritos'] }}</p>
```
(línea 41, "Inscritos")

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $reporte['completados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-orange-600 mt-1">{{ $reporte['completados'] }}</p>
```
(línea 45, "Completados")

```diff
- <p class="text-3xl font-bold text-orange-500 mt-1">{{ $reporte['en_progreso'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-500 mt-1">{{ $reporte['en_progreso'] }}</p>
```
(línea 49, "En progreso" — pasa a grafito para no competir con "Completados" como estado final)

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $reporte['total_lecciones'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['total_lecciones'] }}</p>
```
(línea 53, "Lecciones")

```diff
- <p class="text-3xl font-bold mt-1 {{ ($reporte['promedio_global'] ?? 0) >= 60 ? 'text-green-600' : 'text-gray-400' }}">
+ <p class="text-3xl font-bold mt-1 {{ ($reporte['promedio_global'] ?? 0) >= 60 ? 'text-dyl-orange-600' : 'text-gray-400' }}">
```
(línea 57, "Promedio global")

- [ ] **Step 7: `reportes/curso.blade.php` — tasa de completitud y tabla de estudiantes**

```diff
- <span class="{{ $tasaCurso >= 70 ? 'text-green-600' : 'text-orange-500' }}">{{ $tasaCurso }}%</span>
+ <span class="{{ $tasaCurso >= 70 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">{{ $tasaCurso }}%</span>
  </div>
  <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
-     <div class="h-full rounded-full {{ $tasaCurso >= 70 ? 'bg-green-500' : 'bg-orange-400' }}"
+     <div class="h-full rounded-full {{ $tasaCurso >= 70 ? 'bg-dyl-orange-500' : 'bg-dyl-graphite-400' }}"
           style="width: {{ $tasaCurso }}%"></div>
  </div>
```
(línea 69-73, mismo eje de 2 estados que Step 1 de este archivo)

Badge de estado de inscripción (mapeo reutilizado 2B):

```diff
  <span class="px-2 py-1 text-xs rounded-full font-medium
-     @if($e['estado'] === 'completado') bg-green-100 text-green-700
-     @elseif($e['estado'] === 'en_progreso') bg-blue-100 text-blue-700
+     @if($e['estado'] === 'completado') bg-dyl-orange-100 text-dyl-orange-700
+     @elseif($e['estado'] === 'en_progreso') bg-dyl-graphite-100 text-dyl-graphite-600
      @else bg-gray-100 text-gray-500 @endif">
```
(línea 104-107)

Barra de progreso individual (100% vs. en curso):

```diff
- <div class="h-full rounded-full {{ $e['progreso_pct'] === 100 ? 'bg-green-500' : 'bg-blue-500' }}"
+ <div class="h-full rounded-full {{ $e['progreso_pct'] === 100 ? 'bg-dyl-orange-600' : 'bg-dyl-graphite-400' }}"
       style="width: {{ $e['progreso_pct'] }}%"></div>
```
(línea 114)

Calificación individual (aprobado/reprobado, mapeo 2C):

```diff
- <span class="text-sm font-bold {{ $e['promedio'] >= 60 ? 'text-green-600' : 'text-red-500' }}">
+ <span class="text-sm font-bold {{ $e['promedio'] >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      {{ $e['promedio'] }}%
  </span>
```
(línea 125)

Ícono de certificado emitido (dorado→naranja):

```diff
- <span class="text-green-500 text-lg" title="Certificado emitido">&#127941;</span>
+ <span class="text-dyl-orange-500 text-lg" title="Certificado emitido">&#127941;</span>
```
(línea 134)

```diff
- <a href="{{ route('reportes.estudiante', $e['usuario']) }}"
-    class="text-blue-600 hover:text-blue-800 text-xs">Ver &rarr;</a>
+ <a href="{{ route('reportes.estudiante', $e['usuario']) }}"
+    class="text-dyl-orange-600 hover:text-dyl-orange-700 text-xs">Ver &rarr;</a>
```
(línea 143-144)

- [ ] **Step 8: `reportes/estudiante.blade.php` — KPIs**

"Certificados" ya usa el precedente dorado→naranja y se queda como acento; el resto pasa a grafito:

```diff
- <p class="text-3xl font-bold text-blue-600 mt-1">{{ $reporte['total_cursos'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['total_cursos'] }}</p>
```
(línea 19, "Cursos inscritos")

```diff
- <p class="text-3xl font-bold text-green-600 mt-1">{{ $reporte['completados'] }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['completados'] }}</p>
```
(línea 23, "Completados")

```diff
- <p class="text-3xl font-bold text-yellow-500 mt-1">
+ <p class="text-3xl font-bold text-dyl-orange-500 mt-1">
      {{ $reporte['cursos']->where('tiene_certificado', true)->count() }}
  </p>
```
(línea 27, "Certificados")

```diff
- <p class="text-3xl font-bold text-purple-600 mt-1">{{ $reporte['respuestas_historial']->count() }}</p>
+ <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['respuestas_historial']->count() }}</p>
```
(línea 33, "Actividades enviadas")

- [ ] **Step 9: `reportes/estudiante.blade.php` — progreso por curso**

Badge de estado (mismo mapeo que Step 7):

```diff
  <span class="px-2 py-0.5 text-xs rounded-full font-medium
-     @if($cd['estado'] === 'completado') bg-green-100 text-green-700
-     @elseif($cd['estado'] === 'en_progreso') bg-blue-100 text-blue-700
+     @if($cd['estado'] === 'completado') bg-dyl-orange-100 text-dyl-orange-700
+     @elseif($cd['estado'] === 'en_progreso') bg-dyl-graphite-100 text-dyl-graphite-600
      @else bg-gray-100 text-gray-500 @endif">
```
(línea 49-52)

```diff
- <span class="text-yellow-500" title="Certificado emitido">&#127941;</span>
+ <span class="text-dyl-orange-500" title="Certificado emitido">&#127941;</span>
```
(línea 56)

```diff
- <div class="h-full rounded-full {{ $cd['progreso_pct'] === 100 ? 'bg-green-500' : 'bg-blue-500' }}"
+ <div class="h-full rounded-full {{ $cd['progreso_pct'] === 100 ? 'bg-dyl-orange-600' : 'bg-dyl-graphite-400' }}"
       style="width: {{ $cd['progreso_pct'] }}%"></div>
```
(línea 63)

```diff
- <p class="text-2xl font-bold {{ $cd['promedio'] >= 60 ? 'text-green-600' : 'text-red-500' }}">
+ <p class="text-2xl font-bold {{ $cd['promedio'] >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      {{ $cd['promedio'] }}%
  </p>
```
(línea 73)

- [ ] **Step 10: `reportes/estudiante.blade.php` — historial de actividades**

```diff
- <span class="text-sm font-bold {{ $pct >= 60 ? 'text-green-600' : 'text-red-500' }}">
+ <span class="text-sm font-bold {{ $pct >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
      {{ $resp->calificacion }}{{ $resp->actividad ? '/' . $resp->actividad->puntaje_maximo : '' }}
  </span>
```
(línea 122)

```diff
- <span class="text-xs text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded-full">Pendiente</span>
+ <span class="text-xs text-dyl-graphite-900 font-semibold bg-dyl-graphite-100 px-2 py-0.5 rounded-full">Pendiente</span>
```
(línea 126 — advertencia/pendiente, mismo criterio que el badge "Pendiente" de `mis-calificaciones.blade.php` en Fase 2C)

- [ ] **Step 11: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/reportes | grep -v dyl-orange`
Expected: sin resultados.

Run: `grep -n "FCD34D\|34D399\|60A5FA\|3B82F6" resources/views/reportes/index.blade.php`
Expected: sin resultados.

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 12: Commit**

```bash
git add resources/views/reportes
git commit -m "feat: migrar resources/views/reportes a naranja/grafito (incluye paleta Chart.js)"
```

---

### Task 3: Migrar `resources/views/mensajes/*` + `resources/views/notificaciones/index.blade.php` (4 archivos)

**Files:**
- Modify: `resources/views/mensajes/bandeja.blade.php`
- Modify: `resources/views/mensajes/conversacion.blade.php`
- Modify: `resources/views/mensajes/create.blade.php`
- Modify: `resources/views/notificaciones/index.blade.php`

**Interfaces:** Ninguna.

- [ ] **Step 1: `mensajes/bandeja.blade.php`**

```diff
- <a href="{{ route('mensajes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Nuevo mensaje</a>
+ <a href="{{ route('mensajes.create') }}" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-dyl-orange-700">+ Nuevo mensaje</a>
```
(línea 7)

```diff
- @if(session('success'))
-     <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
- @endif
+ @if(session('success'))
+     <div class="alert alert-success mb-4">{{ session('success') }}</div>
+ @endif
```
(línea 10-12)

No leído = eje de 2 estados (no leído = necesita atención → naranja; leído = neutro, sin resaltar, sin cambio):

```diff
  <a href="{{ route('mensajes.conversacion', $m) }}"
-    class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $m->leido ? '' : 'border-l-4 border-l-blue-500' }}">
+    class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $m->leido ? '' : 'border-l-4 border-l-dyl-orange-500' }}">
```
(línea 15-16)

```diff
- <span class="text-xs text-blue-600 font-medium">{{ $m->curso->titulo }}</span>
+ <span class="text-xs text-dyl-orange-600 font-medium">{{ $m->curso->titulo }}</span>
  @if(!$m->leido)
-     <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
+     <span class="w-2 h-2 bg-dyl-orange-500 rounded-full"></span>
  @endif
```
(línea 22-24 — el nombre del curso es un dato interactivo/de acento, se queda naranja independiente del estado de lectura)

- [ ] **Step 2: `mensajes/conversacion.blade.php`**

```diff
- <a href="{{ route('mensajes.bandeja') }}" class="text-blue-600 hover:text-blue-800 text-sm mb-4 inline-block">&larr; Volver a la bandeja</a>
+ <a href="{{ route('mensajes.bandeja') }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm mb-4 inline-block">&larr; Volver a la bandeja</a>
```
(línea 5)

```diff
- Curso: <span class="font-medium text-blue-600">{{ $mensaje->curso->titulo }}</span>
+ Curso: <span class="font-medium text-dyl-orange-600">{{ $mensaje->curso->titulo }}</span>
```
(línea 14)

```diff
- <a href="{{ route('mensajes.create', ['curso_id' => $mensaje->curso_id, 'padre_id' => $mensaje->id]) }}"
-    class="text-sm text-blue-600 hover:text-blue-800">Responder</a>
+ <a href="{{ route('mensajes.create', ['curso_id' => $mensaje->curso_id, 'padre_id' => $mensaje->id]) }}"
+    class="text-sm text-dyl-orange-600 hover:text-dyl-orange-700">Responder</a>
```
(línea 17-18 — `border-l-4 border-l-gray-300` de las respuestas anidadas, línea 26, no cambia: gray fuera de alcance)

- [ ] **Step 3: `mensajes/create.blade.php`**

```diff
- <input type="checkbox" x-model="enviarATodos" class="w-4 h-4 rounded text-blue-600">
+ <input type="checkbox" x-model="enviarATodos" class="w-4 h-4 rounded text-dyl-orange-600">
```
(línea 52, acento de checkbox marcado)

Chips de destinatarios seleccionados:

```diff
- <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 text-sm px-2.5 py-1 rounded-full">
      <span x-text="user.name"></span>
-     <button type="button" @click="remover(user)" class="text-blue-500 hover:text-blue-700">&times;</button>
+ <span class="inline-flex items-center gap-1 bg-dyl-orange-100 text-dyl-orange-800 text-sm px-2.5 py-1 rounded-full">
+     <span x-text="user.name"></span>
+     <button type="button" @click="remover(user)" class="text-dyl-orange-500 hover:text-dyl-orange-700">&times;</button>
```
(línea 60-62)

```diff
- <button type="button" @click="seleccionar(user)"
-         class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center justify-between">
+ <button type="button" @click="seleccionar(user)"
+         class="w-full text-left px-3 py-2 text-sm hover:bg-dyl-orange-50 flex items-center justify-between">
```
(línea 76-77, hover de sugerencia)

```diff
- <span x-show="seleccionados.find(s => s.id === user.id)" class="text-xs text-green-600">Seleccionado</span>
+ <span x-show="seleccionados.find(s => s.id === user.id)" class="text-xs text-dyl-orange-600">Seleccionado</span>
```
(línea 82)

Errores en línea (4 ocurrencias idénticas):

```diff
- <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
+ <p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>
```
(aplica a líneas 45, 93, 94, 100, 106 — todos los `@error(...)<p class="text-red-600 text-xs mt-1">`, idéntico)

```diff
- <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Enviar</button>
+ <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Enviar</button>
```
(línea 111)

- [ ] **Step 4: `notificaciones/index.blade.php`**

```diff
- <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Marcar todas como leídas</button>
+ <button type="submit" class="text-sm text-dyl-orange-600 hover:text-dyl-orange-700 font-medium">Marcar todas como leídas</button>
```
(línea 10)

No leída = eje de 2 estados, mismo criterio que `mensajes/bandeja.blade.php` Step 1:

```diff
  <a href="{{ route('notificaciones.marcar', $n) }}"
-    class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $n->leido ? '' : 'border-l-4 border-l-blue-500 bg-blue-50/30' }}">
+    class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $n->leido ? '' : 'border-l-4 border-l-dyl-orange-500 bg-dyl-orange-50/30' }}">
```
(línea 16-17)

Íconos por tipo de notificación — convergen a grafito (categoría, no estado, mismo criterio que los íconos de tipo de recurso de Fase 2C):

```diff
  @if($n->tipo === 'calificacion')
-     <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
+     <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
  @elseif($n->tipo === 'entrega')
-     <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
+     <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
  @elseif($n->tipo === 'certificado')
-     <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
+     <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
  @else
      <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">...</svg>
  @endif
```
(líneas 20-27 — los 3 primeros branches convergen al mismo `dyl-graphite-500`; el `path d="..."` de cada ícono no cambia, solo la clase de color; el branch `else` en `gray-400` se deja igual, fuera de alcance)

- [ ] **Step 5: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/mensajes resources/views/notificaciones | grep -v dyl-orange`
Expected: sin resultados.

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 6: Commit**

```bash
git add resources/views/mensajes resources/views/notificaciones
git commit -m "feat: migrar resources/views/mensajes y notificaciones a naranja/grafito"
```

---

### Task 4: Migrar `resources/views/profile/*` + `resources/views/auth/*` (7 archivos)

**Files:**
- Modify: `resources/views/profile/edit.blade.php`
- Modify: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Modify: `resources/views/profile/partials/update-password-form.blade.php`
- Modify: `resources/views/profile/partials/delete-user-form.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/views/auth/verify-email.blade.php`
- Modify: `resources/views/auth/two-factor/verify.blade.php`

**Interfaces:** Ninguna. Los componentes `x-primary-button`, `x-text-input`, `x-input-error`, `x-input-label` ya fueron migrados en Fase 2A — no se tocan aquí, solo las clases sueltas en los archivos que los usan.

- [ ] **Step 1: `profile/edit.blade.php`**

```diff
- <div class="card border border-red-200">
+ <div class="card">
      <div class="card-body">
          @include('profile.partials.delete-user-form')
```
(línea 29 — el borde rojo de la tarjeta contenedora se retira; el tratamiento de advertencia de la sección "Eliminar cuenta" ya lo aporta el propio partial, ver Step 4, así que la tarjeta exterior no necesita repetirlo)

```diff
- <p class="text-green-600 text-sm mb-4 flex items-center gap-1">
+ <p class="text-dyl-orange-600 text-sm mb-4 flex items-center gap-1">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">...</svg>
      2FA activo — tu cuenta tiene una capa extra de seguridad.
  </p>
```
(línea 39, estado positivo activo)

- [ ] **Step 2: `profile/partials/update-profile-information-form.blade.php`**

Errores en línea (3 ocurrencias idénticas):

```diff
- <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
+ <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $message }}</p>
```
(aplica a líneas 29, 46, 63, idéntico)

Aviso de email sin verificar — advertencia (fondo `graphite-50` + borde grueso naranja, mismo criterio que Fase 2C):

```diff
- <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
-     <p class="text-sm text-yellow-800">
+ <div class="mt-2 p-3 bg-dyl-graphite-50 border-2 border-dyl-orange-300 rounded-lg">
+     <p class="text-sm text-dyl-graphite-900 font-medium">
          Tu dirección de correo no ha sido verificada.
-         <button form="send-verification" class="underline font-medium hover:text-yellow-900">
+         <button form="send-verification" class="underline font-medium hover:text-dyl-graphite-700">
              Haz clic aquí para reenviar el correo de verificación.
          </button>
      </p>
      @if (session('status') === 'verification-link-sent')
-         <p class="mt-1 text-sm text-green-700 font-medium">
+         <p class="mt-1 text-sm text-dyl-orange-700 font-medium">
              Se envió un nuevo enlace de verificación a tu correo.
          </p>
      @endif
  </div>
```
(líneas 67-79)

```diff
- class="text-sm text-green-600 font-medium"
+ class="text-sm text-dyl-orange-600 font-medium"
  >Cambios guardados.</p>
```
(línea 94, confirmación efímera de guardado — positivo)

- [ ] **Step 3: `profile/partials/update-password-form.blade.php`**

Errores en línea (3 ocurrencias idénticas):

```diff
- <p class="mt-1 text-sm text-red-600">{{ $errors->updatePassword->first('current_password') }}</p>
+ <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $errors->updatePassword->first('current_password') }}</p>
```
(aplica a líneas 21, 35, 49 — mismo patrón para `current_password`, `password`, `password_confirmation`)

```diff
- class="text-sm text-green-600 font-medium"
+ class="text-sm text-dyl-orange-600 font-medium"
  >Contraseña actualizada.</p>
```
(línea 64)

- [ ] **Step 4: `profile/partials/delete-user-form.blade.php`**

Sección completa de "advertencia máxima" (la acción más destructiva de la app) — se reemplaza el rojo por el tratamiento de advertencia (borde grueso naranja + fondo/texto grafito), no por el tratamiento "error" de relleno sólido, porque esto es una confirmación previa a la acción, no un error del sistema:

```diff
- <h2 class="text-lg font-semibold text-red-700">Eliminar cuenta</h2>
+ <h2 class="text-lg font-semibold text-dyl-graphite-900">Eliminar cuenta</h2>
```
(línea 3)

```diff
- <div x-show="confirmar" x-cloak class="mt-4 p-5 bg-red-50 border border-red-200 rounded-xl space-y-4">
-     <p class="text-sm font-medium text-red-800">
+ <div x-show="confirmar" x-cloak class="mt-4 p-5 bg-dyl-graphite-50 border-2 border-dyl-orange-300 rounded-xl space-y-4">
+     <p class="text-sm font-semibold text-dyl-graphite-900">
          ¿Estás seguro de que quieres eliminar tu cuenta? Ingresa tu contraseña para confirmar.
      </p>
```
(línea 19-22)

```diff
- @if ($errors->userDeletion->has('password'))
-     <p class="mt-1 text-sm text-red-600">{{ $errors->userDeletion->first('password') }}</p>
- @endif
+ @if ($errors->userDeletion->has('password'))
+     <p class="mt-1 text-sm text-dyl-graphite-900 font-semibold">{{ $errors->userDeletion->first('password') }}</p>
+ @endif
```
(línea 37-39 — el botón "Eliminar mi cuenta"/"Sí, eliminar mi cuenta" ya usa `.btn-danger`, sin cambios de clase)

- [ ] **Step 5: `auth/register.blade.php`**

```diff
- <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
+ <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-dyl-orange-600" href="{{ route('login') }}">
      {{ __('Already registered?') }}
  </a>
```
(línea 43)

- [ ] **Step 6: `auth/verify-email.blade.php`**

```diff
- <div class="mb-4 font-medium text-sm text-green-600">
+ <div class="mb-4 font-medium text-sm text-dyl-orange-600">
      {{ __('A new verification link has been sent to the email address you provided during registration.') }}
  </div>
```
(línea 7)

```diff
- <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
+ <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-dyl-orange-600">
      {{ __('Log Out') }}
  </button>
```
(línea 26)

- [ ] **Step 7: `auth/two-factor/verify.blade.php`**

```diff
- <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
-     <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
+ <div class="w-12 h-12 rounded-full bg-dyl-orange-100 flex items-center justify-center">
+     <svg class="w-6 h-6 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```
(línea 7-8 — ícono de escudo/seguridad, único acento de la pantalla, se queda naranja por ser el ícono principal de una pantalla de auth positiva/neutra, no informativa estática)

- [ ] **Step 8: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/profile resources/views/auth | grep -v dyl-orange`
Expected: sin resultados.

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 9: Commit**

```bash
git add resources/views/profile resources/views/auth
git commit -m "feat: migrar resources/views/profile y auth a naranja/grafito"
```

---

### Task 5: Migrar `resources/views/admin/*` (3 archivos)

**Files:**
- Modify: `resources/views/admin/usuarios/create.blade.php`
- Modify: `resources/views/admin/usuarios/edit.blade.php`
- Modify: `resources/views/admin/auditoria/index.blade.php`

**Interfaces:** Ninguna.

- [ ] **Step 1: `admin/usuarios/create.blade.php`**

```diff
- <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
+ <a href="{{ route('admin.usuarios.index') }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">← Usuarios</a>
```
(línea 7)

```diff
- <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
-        @checked(in_array($rol->id, old('roles', [])))
-        class="rounded text-blue-600">
+ <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
+        @checked(in_array($rol->id, old('roles', [])))
+        class="rounded text-dyl-orange-600">
```
(línea 51-53)

- [ ] **Step 2: `admin/usuarios/edit.blade.php`**

```diff
- <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
+ <a href="{{ route('admin.usuarios.index') }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">← Usuarios</a>
```
(línea 7)

```diff
- <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
-        @checked(in_array($rol->id, old('roles', $rolesActivos)))
-        class="rounded text-blue-600">
+ <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
+        @checked(in_array($rol->id, old('roles', $rolesActivos)))
+        class="rounded text-dyl-orange-600">
```
(línea 54-56)

- [ ] **Step 3: `admin/auditoria/index.blade.php`**

```diff
- <summary class="text-blue-600 hover:text-blue-800 select-none">
+ <summary class="text-dyl-orange-600 hover:text-dyl-orange-700 select-none">
      {{ count($audit->new_values) }} campo(s)
  </summary>
```
(línea 64 — las clases `badge-green`/`badge-red`/`badge-blue` de la línea 52-56 ya son alias de `.badge-success`/`.badge-error`/`.badge-info` desde la Fase 1, no requieren cambio)

- [ ] **Step 4: Verificar y correr la suite**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/admin | grep -v dyl-orange`
Expected: sin resultados.

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin
git commit -m "feat: migrar resources/views/admin a naranja/grafito"
```

---

### Task 6: Verificación final de la Fase 2D

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Confirmar que no queda color crudo en todo el dominio**

Run: `grep -rlE "(bg|text|border|ring|from|to|via)-(red|green|blue|yellow|indigo|purple|emerald|sky|amber|rose|orange)-[0-9]+" resources/views/dashboard resources/views/reportes resources/views/mensajes resources/views/notificaciones resources/views/profile resources/views/admin resources/views/auth | grep -v dyl-orange`
Expected: sin resultados.

- [ ] **Step 2: Confirmar que no queda hex crudo de la paleta de gráficos**

Run: `grep -rnE "#(FCD34D|34D399|9CA3AF|3B82F6|6366F1|60A5FA)" resources/views/dashboard resources/views/reportes`
Expected: sin resultados.

- [ ] **Step 3: Suite completa + build**

Run: `php artisan test && npm run build`
Expected: todo en verde, build exitoso.

- [ ] **Step 4: Checklist visual (navegador si está disponible; si no, smoke test HTTP con login real + grep del HTML renderizado, mismo sustituto usado en la verificación final de Fase 2C)**

- [ ] `/dashboard` como admin, instructor y estudiante — KPIs, gráficos (colores de dona/barra/línea), badges de estado de curso.
- [ ] `/reportes` (admin) — KPIs, gráficos, tabla de cursos y usuarios.
- [ ] `/reportes/{curso}` — botones de exportación, tasa de completitud, tabla de estudiantes.
- [ ] `/reportes/estudiante/{usuario}` — progreso por curso, historial de actividades.
- [ ] `/mensajes` (bandeja) y una conversación — indicador de no leído, formulario de nuevo mensaje con selector de destinatarios.
- [ ] `/notificaciones` — íconos por tipo, indicador de no leída.
- [ ] `/profile` — 2FA activo/inactivo, panel de "Eliminar cuenta" expandido (verificar que se ve como advertencia seria, no como botón cualquiera).
- [ ] `/register`, `/verify-email`, verificación 2FA (`/2fa/verificar` o equivalente) — focus rings, mensajes de estado.
- [ ] `/admin/usuarios/crear`, `/admin/usuarios/{id}/editar`, `/admin/auditoria` — checkboxes de roles, expandir "campo(s)" en auditoría.

- [ ] **Step 5: Commit final (si el checklist encontró algo que corregir)**

```bash
git add -A
git commit -m "fix: ajustes visuales tras verificacion manual de la fase 2d"
```
(omitir si no hizo falta)
