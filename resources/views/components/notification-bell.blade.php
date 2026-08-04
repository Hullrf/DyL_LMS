@php
    use App\Models\Notificacion;

    $unread = Notificacion::where('user_id', Auth::id())->where('leido', false)->count();
    $notificaciones = Notificacion::where('user_id', Auth::id())
        ->latest()
        ->take(5)
        ->get();
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = !open" class="relative p-1.5 text-dyl-graphite-500 hover:text-dyl-graphite-900 rounded-full transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unread > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-dyl-orange-600 rounded-full">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Notificaciones</h3>
            @if($unread > 0)
                <form method="POST" action="{{ route('notificaciones.marcar-todas') }}">
                    @csrf
                    <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">Marcar todas leídas</button>
                </form>
            @endif
        </div>

        <div class="max-h-72 overflow-y-auto">
            @forelse($notificaciones as $n)
                <a href="{{ route('notificaciones.marcar', $n) }}"
                   class="block px-4 py-3 hover:bg-gray-50 transition-colors {{ $n->leido ? '' : 'bg-blue-50/60' }}">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 shrink-0">
                            @if($n->tipo === 'calificacion')
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($n->tipo === 'entrega')
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            @elseif($n->tipo === 'certificado')
                                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            @else
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $n->titulo }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $n->mensaje }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @if(!$n->leido)
                            <span class="w-2 h-2 bg-blue-500 rounded-full shrink-0 mt-2"></span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    No tienes notificaciones
                </div>
            @endforelse
        </div>

        <div class="px-4 py-2 border-t border-gray-100 text-center">
            <a href="{{ route('notificaciones.index') }}" class="text-xs text-blue-600 hover:text-blue-800">Ver todas las notificaciones</a>
        </div>
    </div>
</div>
