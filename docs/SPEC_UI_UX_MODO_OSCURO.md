# Spec — Auditoría UI/UX y modo oscuro (D&L Quality Campus)

Fecha: 2026-10-23 · Alcance: todo el LMS (`resources/views`, `resources/css/app.css`, layouts).
Método: análisis estático de ~100 vistas + revisión visual en navegador con los 3 roles
(estudiante, instructor, admin) en modo oscuro, y cálculo de contraste WCAG.
Marca que se conserva: naranja `#EA580C` / grafito `#0F172A`, Figtree. No se adopta ningún estilo nuevo.

---

## 1. Ya hecho (no repetir)

| ID | Qué | Dónde |
|----|-----|-------|
| D1 | Modo oscuro opt-in (`html.dark`, botón en topbar, `localStorage dyl_theme`) remapeando utilidades neutras | `app.css`, `layouts/app`, `layouts/guest`, `topbar` |
| D2 | `darkMode: 'class'` en Tailwind | `tailwind.config.js` |
| D3 | Contraste de hints/KPIs/placeholders (`gray-400` → `gray-500`, 2.5:1 → 4.8:1); footer y cita del login | `app.css`, layouts |
| D4 | `prefers-reduced-motion`; controles ≥44px con `pointer: coarse` | `app.css` |
| D5 | Botón menú móvil 44px, menú de usuario cierra con Esc, `aria-haspopup="menu"` | `topbar` |
| D6 | Sidebar: `aria-current="page"` en 11 enlaces, sin landmark `navigation` duplicado | `sidebar` |
| D7 | Modal con `role="dialog" aria-modal` | `components/modal` |
| D9 | **Bug sidebar:** el botón Colapsar/Expandir parecía muerto tras cargar una página con el sidebar guardado como colapsado. `html.dyl-sb-collapsed` (puesta por el script del `<head>`) nunca se quitaba y su regla `width:72px` ganaba sobre `.is-collapsed`. `toggleSidebar()` ahora la sincroniza. Verificado: 72 → 220 → 72 px | `layouts/app.blade.php` |
| D10 | Fase 2 · bloque 1: A4 (footer libera el botón Ayuda), A5 (separadores de tabla), A6 (`aria-pressed` real), A7 (halo de foco), M5 (`rel=noopener`), M6 (badges 12px), M7 (checkbox/radio), M8 (scrim del modal), M11 (borrado `welcome.blade.php`); además campana con nombre accesible y 44px | varios |
| D11 | Fase 2 · bloque 2: A1 (Quill en oscuro), A2 (Chart.js: defaults por tema, sin animación con reduced-motion, redibujo al alternar, series con más contraste), M10 parcial (CSS de `.office-render`, `.leccion-contenido` y neutralización de negro/blanco inline de Quill) | `app.css`, `app.js`, layouts, dashboards, reportes |
| D12 | Fase 3: M3 (`scope="col"` en 76 `<th`, scroll horizontal en 6 tablas), M4 (`aria-label` en buscadores, comentarios, bibliografía y módulo; **no** en inputs con etiqueta visible ni con placeholders de ejemplo), M13 (tema por defecto = preferencia del SO si no hay elección guardada; sincroniza entre pestañas vía evento `storage`), M14 (tooltip `title` en el riel colapsado; drawer móvil con Esc, `inert` en el resto de la página, bloqueo de scroll, foco al abrir y retorno al botón) | varios |
| D13 | A3 (decisión: botón en `#C2410C`): `.btn-primary` y 61 botones/avatares con texto blanco pasan a `orange-700` (hover `orange-800`), 5.18:1; texto naranja sobre claro `orange-600` → `orange-700` (~96 usos). `orange-600` queda solo en acentos sin texto (barras, toggles, puntos, foco). En oscuro el texto naranja pasa a `#FB923C` (≈9:1) | `app.css`, ~50 vistas |
| D14 | Fase 4 (consistencia): M1 (componente `x-icon` con los paths Heroicons del modelo `RecursoActividad`; sustituye los emojis de portada, medalla, tipo de actividad y tipo de recurso; los glifos `✓ ✗ ○ ✕` se dejan por ser tipográficos), M2 (solo desentonaban `admin/categorias` 5xl→7xl y `notificaciones` 2xl→3xl; resto ya era coherente: formularios 2xl, listas 3xl, edición 4xl), M9 (punto de color + texto neutro, y validación `regex:#RRGGBB` en las categorías, que antes aceptaba cualquier cadena de ≤20 caracteres en un `style`), M12 (páginas 403/404/419/429/500/503 en español con layout propio sin consultas a BD), B2 (CSS de topbar, sidebar y `.desc-justify` movido a `app.css`), B3 (éxito se descarta a los 5 s; errores con botón de cerrar). 3 tests nuevos (462 en total) | varios |
| D8 | Corregidos en oscuro: badge "En progreso", pista de progreso, textarea/select/input sin clase, dropzone `bg-gray-50/40`, tarjetas sin borde | `app.css` |

Pruebas: `php artisan test` → 459 passed tras D1–D7.

---

## 2. Pendiente — por prioridad

Severidad: **A** = se ve roto o falla WCAG AA · **M** = inconsistencia/deuda visible · **B** = pulido.
Esfuerzo: S ≤ 1 h · M ≤ half-day · L ≥ 1 día.

### A1 — ✅  Quill ilegible en oscuro · A · M
**Visto en:** `actividades/edit` (captura): iconos de la barra y selectores casi invisibles, placeholder tenue.
**Afecta:** `cursos/create|edit`, `lecciones/create|edit`, `actividades/create|edit`, `foros/create`, `modulos/edit`, `components/quill-init`.
**Causa:** `quill.snow.css` (CDN) pinta `.ql-stroke`/`.ql-fill` en `#444` y bordes claros; nuestro remapeo no lo toca.
**Fix:** bloque `.dark .ql-toolbar, .ql-container, .ql-editor, .ql-stroke, .ql-fill, .ql-picker-label, .ql-picker-options, .ql-tooltip` en `app.css` (stroke/fill `#cbd5e1`, bordes `#334155`, fondo `#0f172a`, `.ql-editor.ql-blank::before` `#94a3b8`, hover/active naranja).
**Aceptación:** barra completa visible (≥3:1 los iconos), texto del editor ≥4.5:1, dropdowns de color/tamaño legibles.
**Riesgo conocido:** Quill guarda estilos inline (`color: rgb(0,0,0)`) en el HTML. En oscuro ese texto queda negro sobre oscuro. Ver M10.

### A2 — ✅  Chart.js no sigue el tema · A · M
**Visto en:** `dashboard/instructor` (ejes y cuadrícula grises por defecto, tenues en oscuro).
**Afecta:** `dashboard/admin`, `dashboard/instructor`, `reportes/index` (3 vistas, 6 gráficos).
**Problemas:**
1. `Chart.defaults.color` / `borderColor` no se ajustan al tema.
2. Al alternar el tema los gráficos no se redibujan.
3. Color `#475569` (serie "Archivado") sobre tarjeta oscura ≈ 2:1.
4. Animación no respeta reduced-motion.
**Fix:** helper único en `resources/js/app.js` (`dylChartTheme()`): aplica defaults según `html.dark`, `Chart.defaults.animation = false` si `prefers-reduced-motion`, y escucha un evento `themechange` que `dylToggleTheme()` ya puede disparar para `chart.update()`. Paleta de series con ≥3:1 en ambos temas; no depender solo del color (leyenda visible ya existe).
**Aceptación:** ejes/leyenda legibles en ambos temas; alternar tema actualiza sin recargar. Verificar con datos reales (hoy todas las barras están en 0).

### A3 — ✅ Contraste de la marca en modo claro · A · M (decisión de marca)
Cálculo WCAG:

| Par | Ratio | AA texto normal (4.5) |
|-----|------:|:--:|
| Blanco sobre `orange-600` (todos los `.btn-primary`) | **3.56** | ✗ |
| `text-dyl-orange-600` sobre blanco (126 usos: enlaces "Ver reporte", "Ver todo"…) | **3.56** | ✗ |
| Idem sobre `gray-50` | **3.41** | ✗ |
| `orange-700` sobre blanco | 5.18 | ✓ |
| Blanco sobre `orange-700` | 5.18 | ✓ |

(En oscuro: naranja-600 sobre fondo/tarjeta 5.26 / 4.84 ✓; el botón blanco-sobre-600 sigue en 3.56 ✗.)
**Fix propuesto:** `.btn-primary` base `orange-700`, hover `orange-800`; en vistas `text-dyl-orange-600` → `text-dyl-orange-700` para texto sobre claro (búsqueda/reemplazo acotado; el naranja-600 queda para barras, bordes, íconos y fondos grandes). En oscuro mantener texto `600`.
**Decisión tomada:** botón en `#C2410C` (ver D13).

### A4 — ✅  Botón flotante "Ayuda" tapa contenido · A · S
**Visto en:** todas las páginas (captura): cubre el enlace "Dashboard" del footer y los últimos elementos al final del scroll.
**Fix:** `padding-bottom` del `<main>`/footer ≥ altura del botón + 16px, o mover el botón dentro del footer en móvil. Mantener `z-30` por debajo de modales (`z-50`).
**Aceptación:** ningún enlace o botón queda tapado en 1528 px y 390 px.

### A5 — ✅  Separadores de tabla claros en oscuro · A · S
**Visto en:** `reportes/index`, `reportes/cursos/1`, `ponderaciones`, listas de `admin/*`: líneas de fila casi blancas.
**Causa:** `.tbl tbody tr` usa `@apply border-gray-50` (no es una clase en el DOM, el remapeo `.dark .border-gray-50` no aplica).
**Fix:** `.dark .tbl tbody tr { border-color:#1f2b42 }` y revisar `.tbl` (`divide-gray-100`).

### A6 — ✅  Estado `aria-pressed` del botón de tema incorrecto · A · S
El botón nace con `aria-pressed="false"` fijo; tras recargar en oscuro el lector de pantalla anuncia "no pulsado".
**Fix:** en el script de `<head>`, o en `DOMContentLoaded`, poner `aria-pressed` según `html.classList.contains('dark')`.

### A7 — ✅  Anillo de foco con halo blanco en oscuro · A · S
`:focus-visible` usa `ring-offset-2` con offset por defecto `#fff`. En oscuro dibuja un anillo blanco alrededor.
**Fix:** `.dark { --tw-ring-offset-color: #0b1220 }` (o `ring-offset-dyl-graphite-900`).

---

### M1 — ✅ Emojis como iconos (20 apariciones) · M · M
`actividades/edit` (10: selector "Tipo de recurso" 📄🖼▶…), `calificaciones/revisar-cuestionario` (5), `cursos/index` (3), `actividades/show` (1), `admin/backups/index` (1). Además el 📚 de las tarjetas sin portada (dashboard y catálogo).
Regla de la skill: SVG de una sola familia (los del sidebar ya son stroke 1.8). Los emojis varían por SO y no heredan color de tema.
**Fix:** componente `x-icon name="..."` (Blade) con ~10 SVG, o SVG inline; sustituir.

### M2 — ✅ Anchos de contenedor inconsistentes · M · M
`max-w-2xl` ×15, `3xl` ×14, `4xl` ×7, `5xl` ×2, `6xl` ×1, `7xl` ×5. Visto: `admin/usuarios` ancho completo, `backups` estrecho, `categorias` medio, `mensajes` estrecho.
**Fix:** convención en la spec de diseño: listados/tablas `7xl` (default del layout), formularios `3xl`, lectura (lección, actividad) `4xl`. Aplicar por tipo de página; sin tocar contenido.

### M3 — ✅ Tablas: scroll móvil y semántica · M · M
- 9 vistas con `<table>` sin wrapper `overflow-x-auto`: `admin/categorias`, `admin/usuarios`, `calificaciones/mis-calificaciones`, `calificaciones/partials/resumen-nota-curso`, `cursos/partials/ficha`, `cursos/ponderaciones` (+ 3 plantillas PDF que no aplican). Heurística por archivo: confirmar a mano.
- 91 `<th>` sin `scope="col"`.
- `.table-auto-responsive` ya existe en `app.css` y casi no se usa.
**Fix:** envolver con `.table-auto-responsive`; `scope="col"` por búsqueda/reemplazo.
**No verificado:** el redimensionado de ventana no cambió el viewport en esta sesión, así que lo móvil falta probarlo (ver §4).

### M4 — ✅ Inputs sin label visible · M · S
(Pendiente menor: `actividades/create` tiene `<label>` sin `for`/`id` que lo asocie al input.)
8 vistas con `<input placeholder>`: `cursos/ficha` (4), `foros/show` (2), buscadores en `cursos/index`, `linea-de-tiempo`, `inscripcion-masiva`, `actividades/create|edit`.
**Fix:** `<label class="sr-only">` o `aria-label` en los buscadores; label visible en formularios.

### M5 — ✅  `target="_blank"` sin `rel` · M · S
7 enlaces (excluyendo `welcome`): `actividades/edit` ×2, `actividades/show`, `calificaciones/show` ×3, `certificados/show`. Añadir `rel="noopener noreferrer"`.

### M6 — ✅  Texto bajo 12 px · M · S
`text-[10px]` en badges de `actividades/edit` (×3), `calificaciones/revisar-cuestionario`, etiqueta en `actividades/show`, contador de `notification-bell`. La marca de agua de `actividades/show` es decorativa y se excluye.
**Fix:** `text-xs` (12 px); el contador de la campana puede mantener 10–11 px solo si es número con `aria-label`.

### M7 — ✅  Checkbox/radio nativos en oscuro · B · S
Cajas blancas del plugin `@tailwindcss/forms` (visto en `competencias`, `ponderaciones`, `inscripcion-masiva`). Legibles pero no coherentes.
**Fix:** `.dark input[type=checkbox|radio] { background-color:#0f172a; border-color:#475569 }`, `:checked` naranja.

### M8 — (scrim hecho; falta devolver el foco) — Modal · M · S
Scrim `bg-gray-500 opacity-75` se vuelve más claro que el fondo oscuro. Usar `bg-black/60` en oscuro (la skill pide 40–60 % negro). Devolver el foco al disparador al cerrar. El atrapado de Tab actual falla si no hay elementos enfocables.

### M9 — ✅ Color dinámico de categoría · M · S
`cursos/index:173`: `style="background:{{color}}20; color:{{color}}"`. El contraste depende del color que elija el admin y en oscuro colores oscuros (p. ej. morado) quedan ilegibles.
**Fix:** texto neutro (`text-gray-700` / oscuro `#e2e8f0`) con un punto del color de la categoría, o calcular color legible. Misma lógica en `admin/categorias` (punto, sin texto: correcto).

### M10 — ✅  Contenido enriquecido con colores propios · M · M (riesgo alto)
- `actividades/show` `.office-render`: bordes y fondos `#d1d5db/#f3f4f6/#f9fafb` fijos → tabla clara con texto claro en oscuro.
- `lecciones/show` `.leccion-contenido img`: fondo `#f9fafb`, borde `#e5e7eb` fijos.
- Descripciones y lecciones creadas con Quill llevan estilos inline (`color`, `background-color`). Texto negro inline sobre fondo oscuro = ilegible.
**Fix:** reglas `.dark .office-render …` y `.dark .leccion-contenido …`; en oscuro neutralizar color inline de contenido de usuario (`.dark .desc-justify [style*="color"]{color:inherit!important}` salvo que se decida respetar colores elegidos). Decisión de producto: ¿respetar o normalizar?
**Falta verificar** con una lección real con texto coloreado.

### M11 — ✅  Código muerto: `welcome.blade.php` · M · S
Sin ruta; copia de Breeze con ~100 KB de CSS Tailwind v4 inline y 22 clases `dark:`. Eliminar tras confirmar que ninguna ruta/test lo referencia (`grep -rn "view('welcome')"`).

### M12 — ✅ (parcial) Páginas de error sin marca · M · M
403/404 son las de Laravel por defecto, en inglés ("NOT FOUND"), sin layout. Además `/certificados/` devuelve el 403 de Apache ("Forbidden … Apache/2.4.58") porque existe un directorio físico `public/certificados` que sombrea la ruta `certificados`.
**Fix:** hecho (`errors/*.blade.php` + `errors/layout`). **Colisión `/certificados/`:** `public/certificados/plantilla-diploma.pdf` es un archivo **sin seguimiento en git** (solo existe en el equipo local), así que producción no se ve afectada; localmente el 403 de Apache sale porque ese directorio tapa la ruta. Mover el PDF fuera de `public/` (p. ej. `resources/` o `storage/`) o trackearlo en otra ruta si debe viajar con el repo.

### M13 — ✅ Tema por defecto y sincronización · M · S
Hoy el default es claro aunque el SO esté en oscuro, y el cambio no se sincroniza entre pestañas (se comprobó: otra pestaña dejó `dyl_theme=light`).
**Fix:** si no hay valor guardado, usar `matchMedia('(prefers-color-scheme: dark)')`; escuchar el evento `storage`. Opcional: columna `users.tema` para persistir por cuenta.

### M14 — ✅ Sidebar: riel colapsado y drawer móvil · M · M
- Colapsado (72 px) los iconos no tienen `title`/tooltip; el nombre accesible existe pero un usuario vidente no ve el texto.
- Drawer móvil: cierra solo por clic en overlay; falta Esc, atrapar foco y devolverlo al botón hamburguesa; `body` sigue haciendo scroll.
**Fix:** `title` condicional (`:title="collapsed ? label : null"`), `@keydown.escape.window="mobileOpen=false"`, `x-trap` (Alpine focus plugin, ya compatible) o foco manual.

### M15 — Reduced motion en Alpine y Chart · B · S
`app.css` ya apaga transiciones CSS. Las `x-transition` de Alpine y la animación de Chart.js hay que cubrirlas (Chart en A2; Alpine respeta las transiciones CSS, quedaría cubierto — confirmar).

### B1 — Tokens semánticos en lugar de remapeo · B · L (estrategia)
El modo oscuro actual (D1/D8) remapea ~35 utilidades por nombre. Cualquier clase nueva (`bg-slate-*`, `text-gray-400` en una vista nueva…) puede quedar sin mapear, como pasó con `bg-gray-200` y `bg-gray-50/40`.
**Fase 2 sugerida:** variables CSS (`--surface`, `--surface-2`, `--text`, `--text-muted`, `--border`, `--accent`) por tema, expuestas como colores de Tailwind (`bg-surface`, `text-muted`), y migrar vistas por módulo. Hasta entonces: regla de revisión "toda vista nueva se abre en oscuro antes de aprobarse".

### B2 — ✅ Estilos inline y duplicados · B · S
`topbar` (`#fff`, `#EDF1F5`), `sidebar` (hex literales) y `app.blade` (`.desc-justify`) llevan `<style>` propio. Mover a `app.css` para que el modo oscuro los gestione en un solo sitio.

### B3 — ✅ Alertas · B · S
`alert-success|error` de sesión no se descartan y no tienen botón de cierre; la skill sugiere auto-cierre 3–5 s para éxito (`toast-dismiss`) y botón de cerrar en errores.

---

## 3. Orden de ejecución sugerido

1. **Rápidos y visibles (≈ 1 jornada):** A4, A5, A6, A7, M5, M6, M7, M8 (scrim), M11.
2. **Editores y gráficos (≈ 1 jornada):** A1, A2, M10 (reglas CSS) → verificar con contenido real.
3. **Marca y a11y (≈ 1 jornada):** A3 (tras decisión), M3, M4, M14, M13.
4. **Consistencia (≈ 1–2 jornadas):** M1, M2, M9, M12, B2, B3.
5. **Fase 2:** B1 (tokens), si se quiere evitar deuda a medio plazo.

## 4. Sin verificar todavía (hay que abrirlas antes de dar por cerrada la spec)

- **Móvil (390 px):** el redimensionado no tuvo efecto; probar con DevTools o un dispositivo real: sidebar drawer, tablas de M3, formularios largos, botón de Ayuda.
- **Pantallas no vistas en oscuro:** realización de un cuestionario (`actividades/show` tipo cuestionario con temporizador), `calificaciones/show` y `revisar-cuestionario`, `calendario`, `linea-de-tiempo`, `mensajes/conversacion`, `foros/show`, `certificados/show` y `verificar` (pública), `auth/*` (registro, recuperar, 2FA), `reportes/seguimiento`, `cursos/inscripcion-masiva`, `anuncios`.
- **Gráficos con datos reales** y modales de confirmación (eliminar usuario/actividad).
- **Lectores de pantalla:** pasar NVDA/VoiceOver por login, dashboard y una actividad.

## 5. Criterios de aceptación globales

- Ninguna pantalla con texto o control ilegible en ambos temas (≥4.5:1 texto, ≥3:1 iconos/bordes de controles).
- Navegación completa por teclado con foco visible en claro y oscuro.
- `php artisan test` en verde; añadir una prueba de feature que compruebe que el layout incluye el botón de tema y los `aria-current` (hoy `SidebarNavigationTest` no los cubre).
- Revisión visual con los 3 roles en 1528 px y 390 px, ambos temas, antes de cerrar cada bloque.
- PDFs de certificados e informes permanecen siempre en claro (por diseño de impresión).
