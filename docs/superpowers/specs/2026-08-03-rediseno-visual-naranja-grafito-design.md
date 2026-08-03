# Rediseño visual: naranja + grafito, navegación en sidebar

**Fecha:** 2026-08-03
**Estado:** Aprobado

## Resumen

Rediseño visual completo del LMS, decidido en sesión de brainstorming con mockups iterativos (login, dashboard, catálogo, detalle de curso, lección, cuestionario, calificaciones, certificados — ver `context/diseño minimalista.jpg` como referencia inicial de estilo).

Hoy el sistema de color mezcla cuatro familias (`dyl-navy`, `dyl-blue`, `dyl-gold`, `dyl-orange`) sin un color primario consistente: el naranja solo cubre el navbar, mientras que botones, links y focus rings usan azul. Este rediseño:

1. **Reduce la paleta a dos escalas sistemáticas**: `dyl-orange` (marca, único color de acento/interactivo) y `dyl-graphite` (neutro — reemplaza navy + blue + gold).
2. **Reemplaza también los colores semánticos** (rojo/verde/ámbar de alertas, badges de estado, calificaciones) por tonalidades de naranja/grafito — la distinción de estado se comunica con ícono, peso y relleno vs. borde, no con matiz. Decisión explícita del usuario: "todo en naranja/grafito, sin excepción".
3. **Cambia la navegación principal** de navbar superior sólido a **sidebar colapsable** (grafito-900, iconos de línea, colapsa a solo iconos) + una **barra superior delgada** para notificaciones y usuario.
4. Adopta un lenguaje visual minimalista: botones tipo píldora (`rounded-full`), cards `rounded-2xl`, sin ilustraciones ni formas decorativas.

Tipografía: se mantiene Figtree/sans en toda la app (sin añadir una segunda familia), con más peso/tracking en H1 de páginas clave.

## Paleta de color

### `tailwind.config.js`

Reemplaza el bloque `colors.dyl` completo:

```js
colors: {
    dyl: {
        orange: {
            50:  '#FFF7ED',
            100: '#FFEDD5',
            200: '#FED7AA',
            300: '#FDBA74',
            400: '#FB923C',
            500: '#F97316',   // acentos, íconos, texto destacado
            600: '#EA580C',   // botones sólidos, estados activos (color "operativo")
            700: '#C2410C',   // hover de botones sólidos
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
            900: '#0F172A',   // sidebar, topbar oscuro, footer
        },
    },
},
```

`dyl-navy`, `dyl-blue`, `dyl-gold` se eliminan del config — ver tabla de migración más abajo para el mapeo de clases.

### Mapeo de migración (clases viejas → nuevas)

| Clase vieja | Uso actual | Clase nueva |
|---|---|---|
| `bg-dyl-orange` (navbar) | Fondo del navbar | *(el navbar desaparece; ver sección Navegación)* |
| `bg-dyl-orange` / `text-dyl-orange` (botones, badges, progreso) | Acento naranja en contenido | `bg-dyl-orange-600` / `text-dyl-orange-600` |
| `dyl-orange-700` | Hover, menú móvil | `dyl-orange-700` (se mantiene, ahora dentro de la escala completa) |
| `dyl-gold`, `dyl-gold-400` | Badge del logo D&L | `dyl-orange-500` / `dyl-orange-400` |
| `dyl-gold-100` | Fondo de badge dorado | `dyl-orange-100` |
| `dyl-navy`, `dyl-navy-700`, `dyl-navy-800` | Footer, panel de login, texto sobre dorado | `dyl-graphite-900` / `dyl-graphite-800` / `dyl-graphite-900` |
| `dyl-blue`, `dyl-blue-600` | Botón primario, links, focus ring | `dyl-orange-600` / `dyl-orange-700` |

## Sistema de feedback sin colores semánticos

Con solo dos familias de color disponibles, los cuatro estados de feedback (éxito, información/neutral, advertencia, error) se distinguen por **relleno vs. contorno, peso de texto e ícono** — no por matiz:

| Estado | Fondo | Borde | Texto | Ícono |
|---|---|---|---|---|
| Éxito / positivo | `orange-50` | `orange-200` | `orange-800` | ✓ check, `orange-600` |
| Información / neutro / pendiente | `graphite-50` | `graphite-200` | `graphite-700` | ⓘ info, `graphite-500` |
| Advertencia | `graphite-50` | `orange-300` (borde grueso, 2px) | `graphite-900` bold | ⚠ alerta, `orange-600` |
| Error / crítico | `graphite-900` (relleno sólido) | — | blanco | ✕ error, `orange-400` |

El error usa relleno sólido oscuro (en vez de borde claro) precisamente para que se distinga de los otros tres sin depender de un matiz "de alarma": es el único estado invertido (fondo oscuro + texto blanco), lo que lo hace inconfundible incluso para quien no percibe bien el naranja.

Para errores en línea bajo un campo de formulario (`.form-error`, sin espacio para un bloque con fondo), la distinción es peso + ícono: texto `graphite-900` en negrita con un ✕ pequeño en `orange-600` delante, en vez de `text-red-600`.

### `resources/css/app.css` — bloque de alertas y badges actualizado

```css
/* ---- Alertas ---- */
.alert { @apply rounded-lg p-4 text-sm border flex items-start gap-3; }
.alert-success { @apply alert bg-dyl-orange-50 border-dyl-orange-200 text-dyl-orange-800; }
.alert-info    { @apply alert bg-dyl-graphite-50 border-dyl-graphite-200 text-dyl-graphite-700; }
.alert-warning { @apply alert bg-dyl-graphite-50 border-2 border-dyl-orange-300 text-dyl-graphite-900 font-medium; }
.alert-error   { @apply alert bg-dyl-graphite-900 text-white border-transparent; }

/* ---- Badges / etiquetas ---- */
.badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
.badge-success { @apply badge bg-dyl-orange-100 text-dyl-orange-700; }
.badge-info    { @apply badge bg-dyl-graphite-100 text-dyl-graphite-600; }
.badge-warning { @apply badge bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold; }
.badge-error   { @apply badge bg-dyl-graphite-800 text-white; }

/* ---- Formularios ---- */
.form-error { @apply mt-1 text-xs text-dyl-graphite-900 font-semibold; }
```

`badge-green`, `badge-blue`, `badge-yellow`, `badge-red`, `badge-gold` y las clases sueltas `bg-green-100 text-green-800` / `bg-red-100 text-red-700` / etc. usadas directamente en las vistas Blade se reemplazan por las variantes de arriba en el barrido de implementación (ver "Archivos afectados").

## Componentes base

```css
/* ---- Botones: pill en vez de rounded-lg ---- */
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
    @apply btn bg-dyl-graphite-100 text-dyl-graphite-700 hover:bg-dyl-graphite-200;
}

/* ---- Cards: más redondeadas ---- */
.card { @apply bg-white rounded-2xl shadow-card; }
```

`btn-navy` y `btn-gold` se eliminan (sin uso tras el barrido — todo botón sólido de acento usa `btn-primary`).

Focus ring global (`resources/css/app.css`, `@layer base`): `ring-dyl-blue` → `ring-dyl-orange-600`.

## Navegación: sidebar colapsable + topbar

Reemplaza el `<nav>` superior de `resources/views/layouts/app.blade.php` (líneas 78–320 actuales) por una estructura de dos columnas: `<aside>` fijo a la izquierda + columna principal con topbar delgado arriba.

Comportamiento (decidido en brainstorming):
- **Colapsable**: botón al final del sidebar alterna entre expandido (220px, ícono + texto) y colapsado (72px, solo ícono). Estado se persiste en `localStorage` (nuevo, no existía antes) para que no vuelva a expandirse en cada carga de página.
- **Overlay en móvil**: en breakpoints `<lg`, el sidebar se comporta como drawer (mismo patrón que el menú móvil actual con Alpine `x-show`/`x-transition`), no colapsado.
- **Topbar delgado** (56px, fondo blanco): breadcrumb/título de página a la izquierda, campana de notificaciones (`<x-notification-bell />`, se reutiliza el componente actual) + dropdown de usuario a la derecha — mismo contenido que el dropdown actual del navbar, solo se mueve de contenedor.

Ítems del sidebar (mismas rutas que el navbar actual):

| Ítem | Ruta | Ícono |
|---|---|---|
| Cursos | `cursos.index` | libro abierto |
| Mensajes | `mensajes.bandeja` | sobre |
| Anuncios | `anuncios.todos` | megáfono |
| Calificaciones | `calificaciones.index` / `calificaciones.mis` (según rol) | portapapeles con check |
| Reportes | `reportes.index` (solo instructor/admin) | barras |
| Certificados | `certificados.mis` | insignia |
| Usuarios | `admin.usuarios.index` (solo admin) | dos siluetas de persona |
| Auditoría | `admin.auditoria.index` (solo admin) | escudo o reloj con flecha (registro de actividad) |

Color de íconos: `graphite-400`/blanco translúcido en estado inactivo (se aclara a blanco puro en hover), `orange-500` en el ítem activo — junto con el indicador de barra vertical naranja ya usado hoy (`nav-link-active`), que se mantiene.

Footer (`bg-dyl-navy` → `bg-dyl-graphite-900`) y layout de login (`layouts/guest.blade.php`) no cambian de estructura, solo de color — ya tienen el layout de dos paneles (panel izquierdo oscuro decorativo + formulario) que se usó como base del mockup.

## Pantallas cubiertas por los mockups (para referencia visual del barrido)

Además de navegación, login y dashboard, se diseñaron mockups de todo el recorrido de curso desde la vista de estudiante: `cursos/show.blade.php` (detalle con progreso y módulos en acordeón), `lecciones/show.blade.php` (video + contenido + sub-índice del curso), `actividades/show.blade.php` (cuestionario en progreso), `calificaciones/mis-calificaciones.blade.php` y `certificados/mis-certificados.blade.php`. Estas vistas no cambian de estructura/lógica, solo adoptan la nueva paleta, `rounded-2xl`/`rounded-full` y el sidebar.

## Fuera de alcance

- Modo oscuro (la app no lo tiene hoy; no se agrega en este rediseño).
- Cambios de copy/contenido, lógica de negocio o estructura de datos.
- Ilustraciones, imágenes decorativas o formas de fondo (se descartaron explícitamente a favor de un estilo minimalista limpio).
- Animaciones más allá de las transiciones ya existentes (Alpine `x-transition`).
- Rediseño de las pantallas de administración (`admin/*`, `reportes/*`) más allá de heredar los nuevos tokens de color/componentes — no se diseñaron mockups específicos para ellas.
- Persistencia del estado colapsado/expandido del sidebar en servidor (se usa `localStorage`, no una preferencia de usuario en BD).

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `tailwind.config.js` | Reemplazar `colors.dyl` (navy/blue/gold/orange) por `dyl.orange` + `dyl.graphite` (escalas 50–900) |
| `resources/css/app.css` | Botones pill, cards `rounded-2xl`, alertas/badges/form-error sin rojo/verde/amarillo, focus ring a naranja |
| `resources/views/layouts/app.blade.php` | Navbar superior → sidebar colapsable + topbar delgado; footer a `graphite-900` |
| `resources/views/layouts/guest.blade.php` | Recolor: `dyl-navy`→`dyl-graphite-900`, `dyl-gold`→`dyl-orange-500` |
| `resources/views/auth/login.blade.php` | `dyl-blue` → `dyl-orange-600` en links/focus |
| Vistas con `bg-dyl-orange`/`dyl-orange-700` sueltos (`cursos/index`, `cursos/show`, `calificaciones/mis-calificaciones`, etc. — confirmar con grep al iniciar el barrido) | Ajustar a la escala nueva (mayormente ya compatible, verificar tono 600 vs 700) |
| Vistas con `badge-green`/`badge-blue`/`badge-yellow`/`badge-red`/`text-red-600`/`bg-green-100` etc. (barrido completo, ~15+ archivos según grep inicial de `dyl-navy`/`dyl-blue` + clases Tailwind de color directas) | Migrar a `badge-success`/`badge-info`/`badge-warning`/`badge-error` o a tonos `dyl-orange`/`dyl-graphite` directos |
| Nuevo componente de íconos SVG para el sidebar (inline en el partial o `resources/views/components/icons/*.blade.php`) | 6–8 íconos de línea (cursos, mensajes, anuncios, calificaciones, reportes, certificados, usuarios, auditoría) |
