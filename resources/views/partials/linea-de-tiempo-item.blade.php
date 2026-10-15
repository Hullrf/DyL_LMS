<li class="flex items-center gap-4 px-4 py-3">
    <span class="w-16 shrink-0 text-sm font-medium text-gray-800">
        {{ $i->actividad->fecha_cierre->format($conFecha ? 'd/m H:i' : 'H:i') }}
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-gray-900">{{ $i->actividad->tituloCompleto() }}</p>
        <p class="truncate text-xs text-gray-500">
            {{ ucfirst($i->actividad->tipo) }} · {{ $i->curso->titulo }}
        </p>
    </div>
    <a href="{{ route('actividades.show', $i->actividad) }}"
       class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium
              {{ $i->estado === 'entregada' ? 'border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-dyl-orange-600 text-white hover:bg-dyl-orange-700' }}">
        {{ $i->accion }}
    </a>
</li>
