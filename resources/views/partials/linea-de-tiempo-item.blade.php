<li class="flex items-start gap-3 px-4 py-3">
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ $i->actividad->tituloCompleto() }}</p>
        <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-500">
            <span class="font-medium text-gray-700 tabular-nums">{{ $i->actividad->fecha_cierre->format($conFecha ? 'd/m H:i' : 'H:i') }}</span>
            <span>{{ ucfirst($i->actividad->tipo) }}</span>
            <span class="min-w-0 max-w-full truncate">{{ $i->curso->titulo }}</span>
        </p>
    </div>
    <a href="{{ route('actividades.show', $i->actividad) }}"
       class="shrink-0 {{ $i->estado === 'entregada' ? 'btn-outline' : 'btn-primary' }} btn-sm">
        {{ $i->accion }}
    </a>
</li>
