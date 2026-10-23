@auth
{{-- collapsed/mobileOpen/toggleSidebar() vienen del x-data compartido en el wrapper
     de layouts/app.blade.php (ver C1/C2 del fix de revisión) --}}
<aside
    :class="{ 'is-collapsed': collapsed, 'is-open': mobileOpen }"
    class="dyl-sidebar"
    aria-label="Barra lateral">

    <div class="dyl-sb-top">
        <a href="{{ route('dashboard') }}" class="dyl-sb-logo" aria-label="D&amp;L Quality Campus - Ir al inicio">
            <img src="{{ asset('images/logo/logo-icon-dark-256.png') }}" alt="" class="w-11 h-11 flex-shrink-0" aria-hidden="true">
            <span class="dyl-sb-label">Quality Campus</span>
        </a>
    </div>

    <nav class="dyl-sb-nav" aria-label="Navegación principal">
        <a href="{{ route('cursos.index') }}" :title="collapsed ? 'Cursos' : null" class="dyl-sb-link {{ request()->routeIs('cursos.*') ? 'active' : '' }}" @if(request()->routeIs('cursos.*')) aria-current="page" @endif>
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.2c-1.6-1.1-3.6-1.7-5.6-1.7-1 0-1.9.1-2.9.4v13.3c1-.3 1.9-.4 2.9-.4 2 0 4 .6 5.6 1.7"/><path d="M12 6.2c1.6-1.1 3.6-1.7 5.6-1.7 1 0 1.9.1 2.9.4v13.3c-1-.3-1.9-.4-2.9-.4-2 0-4 .6-5.6 1.7"/><path d="M12 6.2v13.3"/></svg>
            </span>
            <span class="dyl-sb-label">Cursos</span>
        </a>
        @if(auth()->user()->esEstudiante())
        <a href="{{ route('linea-de-tiempo.index') }}" :title="collapsed ? 'Línea de tiempo' : null" class="dyl-sb-link {{ request()->routeIs('linea-de-tiempo.*') ? 'active' : '' }}" @if(request()->routeIs('linea-de-tiempo.*')) aria-current="page" @endif>
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
            </span>
            <span class="dyl-sb-label">Línea de tiempo</span>
        </a>
        <a href="{{ route('calendario.index') }}" :title="collapsed ? 'Calendario' : null" class="dyl-sb-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" @if(request()->routeIs('calendario.*')) aria-current="page" @endif>
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4M8 14h2M14 14h2"/></svg>
            </span>
            <span class="dyl-sb-label">Calendario</span>
        </a>
        @endif
        <a href="{{ route('mensajes.bandeja') }}" :title="collapsed ? 'Mensajes' : null" class="dyl-sb-link {{ request()->routeIs('mensajes.*') || request()->routeIs('anuncios.todos') ? 'active' : '' }}" @if(request()->routeIs('mensajes.*') || request()->routeIs('anuncios.todos')) aria-current="page" @endif>
            <span class="dyl-sb-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.2" y="5.5" width="17.6" height="13" rx="2.2"/><path d="M4 6.8l8 6.4 8-6.4"/></svg>
            </span>
            <span class="dyl-sb-label">Mensajes</span>
        </a>
        @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
            <a href="{{ route('calificaciones.index') }}" :title="collapsed ? 'Calificaciones' : null" class="dyl-sb-link {{ request()->routeIs('calificaciones.index') ? 'active' : '' }}" @if(request()->routeIs('calificaciones.index')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4.2" width="14" height="17" rx="2"/><path d="M9 4.2v-.7a1 1 0 011-1h4a1 1 0 011 1v.7"/><path d="M8.3 12.8l2.1 2.1 4.3-4.6"/></svg>
                </span>
                <span class="dyl-sb-label">Calificaciones</span>
            </a>
            <a href="{{ route('reportes.index') }}" :title="collapsed ? 'Reportes' : null" class="dyl-sb-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" @if(request()->routeIs('reportes.*')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V4.8"/><path d="M4 20h16"/><rect x="7" y="12.5" width="3" height="7.5" rx=".6"/><rect x="12.3" y="8" width="3" height="12" rx=".6"/><rect x="17.5" y="14.8" width="3" height="5.2" rx=".6"/></svg>
                </span>
                <span class="dyl-sb-label">Reportes</span>
            </a>
        @else
            <a href="{{ route('calificaciones.mis') }}" :title="collapsed ? 'Mis Calificaciones' : null" class="dyl-sb-link {{ request()->routeIs('calificaciones.mis') ? 'active' : '' }}" @if(request()->routeIs('calificaciones.mis')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4.2" width="14" height="17" rx="2"/><path d="M9 4.2v-.7a1 1 0 011-1h4a1 1 0 011 1v.7"/><path d="M8.3 12.8l2.1 2.1 4.3-4.6"/></svg>
                </span>
                <span class="dyl-sb-label">Mis Calificaciones</span>
            </a>
        @endif
        @if(!auth()->user()->esAdmin() || auth()->user()->esEstudiante())
            <a href="{{ route('certificados.mis') }}" :title="collapsed ? 'Certificados' : null" class="dyl-sb-link {{ request()->routeIs('certificados.mis') ? 'active' : '' }}" @if(request()->routeIs('certificados.mis')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9.2" r="4.7"/><path d="M8.8 13.2L7.2 20l4.8-2.7 4.8 2.7-1.6-6.8"/></svg>
                </span>
                <span class="dyl-sb-label">Certificados</span>
            </a>
        @endif
        @if(auth()->user()->esAdmin())
            <a href="{{ route('admin.usuarios.index') }}" :title="collapsed ? 'Usuarios' : null" class="dyl-sb-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19a6 6 0 0112 0"/><path d="M15.5 6.2a3 3 0 010 5.6"/><path d="M16 13.3a5.2 5.2 0 014.5 5.7"/></svg>
                </span>
                <span class="dyl-sb-label">Usuarios</span>
            </a>
            <a href="{{ route('admin.auditoria.index') }}" :title="collapsed ? 'Auditoría' : null" class="dyl-sb-link {{ request()->routeIs('admin.auditoria.*') ? 'active' : '' }}" @if(request()->routeIs('admin.auditoria.*')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5.5c0 4.6-3 8-7 9.5-4-1.5-7-4.9-7-9.5V6l7-3z"/><path d="M9.3 12.2l1.9 1.9 3.5-3.8"/></svg>
                </span>
                <span class="dyl-sb-label">Auditoría</span>
            </a>
            <a href="{{ route('admin.backups.index') }}" :title="collapsed ? 'Backups' : null" class="dyl-sb-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" @if(request()->routeIs('admin.backups.*')) aria-current="page" @endif>
                <span class="dyl-sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5.5" rx="7" ry="2.5"/><path d="M5 5.5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-6"/><path d="M5 11.5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-6"/></svg>
                </span>
                <span class="dyl-sb-label">Backups</span>
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
@endauth
