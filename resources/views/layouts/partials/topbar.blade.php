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
