@if($curso->subtitulo())
    <p class="text-xs text-gray-500 -mt-1 mb-2 truncate" title="{{ $curso->subtitulo() }}">{{ $curso->subtitulo() }}</p>
@endif
@php $res = $resumen[$curso->id] ?? ['porcentaje' => null, 'proxima' => null]; @endphp
@if($res['porcentaje'] === null)
    <p class="text-xs text-gray-400 mb-2">Sin contenido aún</p>
@else
    <div class="mb-2" role="progressbar" aria-valuenow="{{ $res['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="flex justify-between text-xs text-gray-500 mb-1"><span>Progreso</span><span>{{ $res['porcentaje'] }}%</span></div>
        <div class="h-1.5 rounded-full bg-gray-200"><div class="h-1.5 rounded-full bg-dyl-orange-600" style="width: {{ $res['porcentaje'] }}%"></div></div>
    </div>
@endif
@if($res['proxima'])
    <p class="text-xs text-gray-600 mb-3">Próxima entrega: <span class="font-medium">{{ $res['proxima']->format('d/m H:i') }}</span></p>
@endif

@if(($res['nota'] ?? null) !== null)
    <p class="text-xs text-gray-600 mb-3">Nota actual: <span class="font-medium">{{ $res['nota'] }}%</span></p>
@endif
