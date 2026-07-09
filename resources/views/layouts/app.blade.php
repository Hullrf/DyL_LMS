<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'DyL Quality Consulting — Plataforma de Aprendizaje')">
    <title>@yield('title', 'LMS DyL')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" rel="stylesheet">
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full flex flex-col bg-gray-50">

    {{-- Skip to main content (accesibilidad) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50
              focus:bg-dyl-navy focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:text-sm">
        Saltar al contenido principal
    </a>

    {{-- ================================================================
         NAVEGACIÓN PRINCIPAL
    ================================================================ --}}
    <header role="banner">
    <nav class="bg-dyl-orange shadow-md"
         role="navigation"
         aria-label="Navegación principal"
         x-data="{ mobileOpen: false }">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">

                {{-- Logo --}}
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-2.5 group focus-visible:ring-2 focus-visible:ring-dyl-gold rounded"
                       aria-label="LMS DyL - Ir al inicio">
                        {{-- Recuadro DyL --}}
                        <div class="bg-dyl-gold rounded-lg flex items-center justify-center flex-shrink-0 px-2 h-8
                                    group-hover:bg-dyl-gold-400 transition-colors">
                            <span class="text-dyl-navy font-bold text-sm leading-none tracking-tight">D&amp;L</span>
                        </div>
                        <span class="text-white font-bold text-lg tracking-tight leading-none">LMS</span>
                    </a>

                    {{-- Nav desktop --}}
                    @auth
                    <div class="hidden md:flex items-center gap-1">
                        <a href="{{ route('cursos.index') }}"
                           class="nav-link {{ request()->routeIs('cursos.*') ? 'nav-link-active' : '' }}">
                            Cursos
                        </a>

                        <a href="{{ route('mensajes.bandeja') }}"
                           class="nav-link {{ request()->routeIs('mensajes.*') ? 'nav-link-active' : '' }}">
                            Mensajes
                        </a>

                        <a href="{{ route('anuncios.todos') }}"
                           class="nav-link {{ request()->routeIs('anuncios.*') ? 'nav-link-active' : '' }}">
                            Anuncios
                        </a>

                        @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
                            <a href="{{ route('calificaciones.index') }}"
                               class="nav-link {{ request()->routeIs('calificaciones.index') ? 'nav-link-active' : '' }}">
                                Calificaciones
                            </a>
                            <a href="{{ route('reportes.index') }}"
                               class="nav-link {{ request()->routeIs('reportes.*') ? 'nav-link-active' : '' }}">
                                Reportes
                            </a>
                        @else
                            <a href="{{ route('calificaciones.mis') }}"
                               class="nav-link {{ request()->routeIs('calificaciones.mis') ? 'nav-link-active' : '' }}">
                                Mis Calificaciones
                            </a>
                        @endif
                        @if(!auth()->user()->esAdmin() || auth()->user()->esEstudiante())
                            <a href="{{ route('certificados.mis') }}"
                               class="nav-link {{ request()->routeIs('certificados.mis') ? 'nav-link-active' : '' }}">
                                Certificados
                            </a>
                        @endif
                        @if(auth()->user()->esAdmin())
                            <a href="{{ route('admin.usuarios.index') }}"
                               class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'nav-link-active' : '' }}">
                                Usuarios
                            </a>
                            <a href="{{ route('admin.auditoria.index') }}"
                               class="nav-link {{ request()->routeIs('admin.auditoria.*') ? 'nav-link-active' : '' }}">
                                Auditoría
                            </a>
                        @endif
                    </div>
                    @endauth
                </div>

                {{-- Acciones derecha --}}
                <div class="flex items-center gap-3">
                    @auth
                        {{-- Notificaciones --}}
                        <x-notification-bell />

                        {{-- Botón nuevo curso (desktop) --}}
                        @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
                            <a href="{{ route('cursos.create') }}"
                               class="hidden sm:flex btn btn-sm border border-white/50 text-white bg-white/10 hover:bg-white/20 focus-visible:ring-white"
                               aria-label="Crear nuevo curso">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Nuevo Curso
                            </a>
                        @endif

                        {{-- Dropdown de usuario (desktop) --}}
                        <div class="hidden md:block relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                    @click.outside="open = false"
                                    class="flex items-center gap-2 text-white/80 hover:text-white
                                           rounded-lg px-3 py-2 transition-colors focus-visible:ring-2
                                           focus-visible:ring-dyl-gold"
                                    aria-haspopup="true"
                                    :aria-expanded="open">
                                <div class="w-7 h-7 bg-white/20 rounded-full flex items-center justify-center
                                            border border-white/40" aria-hidden="true">
                                    <span class="text-white text-xs font-bold">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </span>
                                </div>
                                <span class="text-sm max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''"
                                     fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 x-cloak
                                 class="absolute right-0 top-full mt-1 w-56 bg-white rounded-xl shadow-lg
                                        border border-gray-100 z-50 overflow-hidden"
                                 role="menu">
                                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                    <p class="text-xs text-gray-500">Sesión activa</p>
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->email }}</p>
                                </div>
                                <a href="{{ route('dashboard') }}"
                                   class="dropdown-item" role="menuitem">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                    Dashboard
                                </a>
                                <a href="{{ route('certificados.mis') }}"
                                   class="dropdown-item" role="menuitem">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                    Mis Certificados
                                </a>
                                <a href="{{ route('profile.edit') }}"
                                   class="dropdown-item" role="menuitem">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Mi Perfil
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <form method="POST" action="{{ route('logout') }}" role="none">
                                    @csrf
                                    <button type="submit"
                                            class="dropdown-item w-full text-red-600 hover:bg-red-50"
                                            role="menuitem">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        Cerrar sesión
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Botón hamburguesa (móvil) --}}
                        <button @click="mobileOpen = !mobileOpen"
                                class="md:hidden text-white/80 hover:text-white p-2 rounded-lg
                                       focus-visible:ring-2 focus-visible:ring-white/60 transition-colors"
                                :aria-expanded="mobileOpen"
                                aria-controls="mobile-menu"
                                aria-label="Menú de navegación">
                            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @else
                        <a href="{{ route('login') }}"
                           class="btn btn-outline text-sm border-white/30 text-white hover:bg-white/10">
                            Ingresar
                        </a>
                    @endauth
                </div>
            </div>
        </div>

        {{-- Menú móvil --}}
        @auth
        <div id="mobile-menu"
             x-show="mobileOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden bg-dyl-orange-700 border-t border-white/10">

            <div class="px-4 py-3 space-y-1">
                {{-- Info usuario --}}
                <div class="flex items-center gap-3 pb-3 mb-2 border-b border-white/10">
                    <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center border border-white/40">
                        <span class="text-white font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="text-white/50 text-xs">{{ auth()->user()->email }}</p>
                    </div>
                </div>

                <a href="{{ route('cursos.index') }}" class="mobile-nav-link">Cursos</a>

                <a href="{{ route('mensajes.bandeja') }}" class="mobile-nav-link">Mensajes</a>
                <a href="{{ route('anuncios.todos') }}" class="mobile-nav-link">Anuncios</a>

                @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
                    <a href="{{ route('calificaciones.index') }}" class="mobile-nav-link">Calificaciones</a>
                    <a href="{{ route('reportes.index') }}" class="mobile-nav-link">Reportes</a>
                    <a href="{{ route('cursos.create') }}" class="mobile-nav-link">+ Nuevo Curso</a>
                @else
                    <a href="{{ route('calificaciones.mis') }}" class="mobile-nav-link">Mis Calificaciones</a>
                @endif

                <a href="{{ route('certificados.mis') }}" class="mobile-nav-link">Mis Certificados</a>
                <a href="{{ route('profile.edit') }}" class="mobile-nav-link">Mi Perfil</a>

                <form method="POST" action="{{ route('logout') }}" class="pt-2 border-t border-white/10 mt-2">
                    @csrf
                    <button type="submit" class="mobile-nav-link w-full text-left text-red-400">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
        @endauth

    </nav>
    </header>

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
    <footer class="bg-dyl-navy mt-auto" role="contentinfo">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="bg-dyl-gold rounded-md flex items-center justify-center px-1.5 h-7">
                        <span class="text-dyl-navy font-bold text-xs tracking-tight">D&amp;L</span>
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

    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    @stack('scripts')
</body>
</html>

{{-- ============================================================
     Clases auxiliares de navegación definidas aquí como
     Tailwind no puede procesar clases dinámicas en @layer
     si no aparecen al menos una vez en los templates.
     Referencia en app.css @layer components si se mueve.
============================================================ --}}
<style>
.nav-link {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(255,255,255,0.75);
    transition: color 0.15s, background-color 0.15s;
}
.nav-link:hover, .nav-link:focus-visible {
    color: #fff;
    background-color: rgba(255,255,255,0.08);
}
.nav-link-active {
    color: #fff !important;
    background-color: rgba(255,255,255,0.18) !important;
}
.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    color: #374151;
    transition: background-color 0.1s;
}
.dropdown-item:hover {
    background-color: #f9fafb;
}
.mobile-nav-link {
    display: block;
    padding: 0.625rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(255,255,255,0.75);
    transition: color 0.15s, background-color 0.15s;
}
.mobile-nav-link:hover {
    color: #fff;
    background-color: rgba(255,255,255,0.08);
}
</style>
