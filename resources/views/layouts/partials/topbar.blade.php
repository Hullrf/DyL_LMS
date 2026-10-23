<header class="dyl-topbar" role="banner">
    <div class="flex items-center gap-3">
        @guest
            <a href="{{ route('login') }}" class="flex items-center gap-2.5" aria-label="D&amp;L Quality Campus - Ir al inicio">
                <img src="{{ asset('images/logo/logo-icon-light-256.png') }}" alt="" class="w-11 h-11 flex-shrink-0" aria-hidden="true">
                <span class="font-bold text-dyl-graphite-900">Quality Campus</span>
            </a>
        @endguest
        @auth
            <button @click="$dispatch('sidebar-open')" type="button" id="sidebar-toggle"
                    class="lg:hidden inline-flex items-center justify-center min-w-[44px] min-h-[44px] -ml-2 text-dyl-graphite-500 hover:text-dyl-graphite-900 rounded-lg"
                    aria-label="Abrir menú de navegación">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        @endauth

        @hasSection('breadcrumbs')
        <nav aria-label="Breadcrumb" class="hidden sm:block text-sm text-dyl-graphite-500 truncate">
            @yield('breadcrumbs')
        </nav>
        @endif
    </div>

    <div class="flex items-center gap-1 sm:gap-3">
        <button type="button" onclick="dylToggleTheme()" data-theme-toggle aria-pressed="false"
                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] rounded-lg text-dyl-graphite-500 hover:text-dyl-graphite-900"
                aria-label="Modo oscuro">
            <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>
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
                <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                        class="flex items-center gap-2 text-dyl-graphite-600 hover:text-dyl-graphite-900 rounded-lg px-2 py-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-dyl-orange-600"
                        aria-haspopup="menu" :aria-expanded="open">
                    <div class="w-8 h-8 bg-dyl-orange-700 rounded-full flex items-center justify-center" aria-hidden="true">
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
