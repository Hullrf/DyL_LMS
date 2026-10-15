@php $compacto = $compacto ?? false; @endphp

@if($lineaDeTiempo['atrasadas']->isNotEmpty())
<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
    <h3 class="font-semibold text-red-800 mb-2">Atrasadas ({{ $lineaDeTiempo['atrasadas']->count() }})</h3>
    <ul class="divide-y divide-red-100">
        @foreach($lineaDeTiempo['atrasadas'] as $i)
            @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => true])
        @endforeach
    </ul>
</div>
@endif

@forelse($lineaDeTiempo['dias'] as $fecha => $items)
    <div class="mb-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">
            {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        </h3>
        <ul class="bg-white rounded-xl shadow divide-y divide-gray-100">
            @foreach($items as $i)
                @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => false])
            @endforeach
        </ul>
    </div>
@empty
    @if($lineaDeTiempo['atrasadas']->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">No tienes entregas próximas.</div>
    @endif
@endforelse
