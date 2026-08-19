@auth
{{-- collapsed/mobileOpen/toggleSidebar() vienen del x-data compartido en el wrapper
     de layouts/app.blade.php (ver C1/C2 del fix de revisión) --}}
<aside
    :class="{ 'is-collapsed': collapsed, 'is-open': mobileOpen }"
    class="dyl-sidebar"
    role="navigation"
    aria-label="Navegación principal">

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
        <button @click="toggleSidebar()" type="button" class="dyl-collapse-btn" :aria-expanded="!collapsed" aria-label="Colapsar o expandir la navegación">
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
    /* Literal en vez de theme(): este <style> es HTML crudo enviado al navegador,
       no pasa por el pipeline de Tailwind/PostCSS (solo resources/css/app.css lo hace).
       dyl-graphite-900 = #0F172A (ver tailwind.config.js) */
    background: #0F172A;
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
/* Por debajo de 1024px el drawer cerrado se saca del árbol de accesibilidad y del
   tab order (además del transform que ya lo mueve fuera de pantalla visualmente). */
@media (max-width: 1023.98px) {
    .dyl-sidebar:not(.is-open) { visibility: hidden; }
}
@media (min-width: 1024px) {
    .dyl-sidebar { position: sticky; top: 0; height: 100vh; transform: none !important; }
    /* `html.dyl-sb-collapsed` la fija el script bloqueante en <head> (ver layouts/app.blade.php)
       ANTES de que Alpine cargue, para que la primera pintura del <aside> ya nazca colapsada
       y la transition de `width`/`transform` no tenga un "antes" del cual animar en cada
       navegación de página completa. `.is-collapsed` es la que aplica Alpine en vivo al hacer
       clic en Colapsar/Expandir dentro de la misma carga de página (ahí sí debe animar). */
    .dyl-sidebar.is-collapsed,
    html.dyl-sb-collapsed .dyl-sidebar { width: 72px; }
    .dyl-sidebar.is-collapsed .dyl-sb-top,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-sb-top { justify-content: center; padding: 18px 0; }
    .dyl-sidebar.is-collapsed .dyl-sb-link,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-sb-link { justify-content: center; padding: 9px 0; }
    .dyl-sidebar.is-collapsed .dyl-collapse-btn,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-collapse-btn { justify-content: center; padding: 9px 0; }
    .dyl-sidebar.is-collapsed .dyl-sb-label,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-sb-label { max-width: 0; opacity: 0; }
    /* La etiqueta oculta sigue siendo un item flex con `gap` aunque su ancho sea 0,
       así que reserva espacio fantasma y descentra el ícono. Sin esto el ícono
       queda ~6px a la izquierda del centro real del riel de 72px. */
    .dyl-sidebar.is-collapsed .dyl-sb-logo,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-sb-logo,
    .dyl-sidebar.is-collapsed .dyl-sb-link,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-sb-link,
    .dyl-sidebar.is-collapsed .dyl-collapse-btn,
    html.dyl-sb-collapsed .dyl-sidebar .dyl-collapse-btn { gap: 0; }
}
.dyl-sb-top { display: flex; align-items: center; padding: 18px; }
.dyl-sb-logo { display: flex; align-items: center; gap: 10px; }
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
.dyl-sb-ic { width: 18px; height: 18px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.55); }
.dyl-sb-ic svg { width: 18px; height: 18px; }
.dyl-sb-link:hover .dyl-sb-ic { color: #fff; }
.dyl-sb-link.active { color: #fff; background: rgba(255,255,255,.08); }
/* dyl-orange-500 = #F97316 (ver tailwind.config.js) */
.dyl-sb-link.active .dyl-sb-ic { color: #F97316; }
.dyl-sb-link.active::before { content: ""; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: #F97316; }
.dyl-sb-bottom { padding: 12px; border-top: 1px solid rgba(255,255,255,.08); }
.dyl-collapse-btn {
    width: 100%; display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 9px;
    background: rgba(255,255,255,.05); color: #94a3b8; font-size: 12px; font-weight: 600;
    border: none; cursor: pointer; font-family: inherit;
}
.dyl-collapse-btn:hover { color: #fff; background: rgba(255,255,255,.09); }
</style>
@endauth
