@php
    $compacto = $compacto ?? false;
    $atrasadas = $lineaDeTiempo['atrasadas'];
    $dias = $lineaDeTiempo['dias'];
    $total = $atrasadas->count() + $dias->flatten(1)->count();
    if ($compacto) {
        $atrasadas = $atrasadas->take(5);
        $restantes = 5;
        $dias = $dias->map(function ($items) use (&$restantes) {
            $tomados = $items->take($restantes);
            $restantes -= $tomados->count();
            return $tomados;
        })->filter(fn($items) => $items->isNotEmpty());
    }
    $topeAtrasadas = !$compacto && $atrasadas->count() >= \App\Services\LineaDeTiempoService::MAX_ATRASADAS;
@endphp

@if($atrasadas->isNotEmpty())
<div class="mb-6 rounded-xl border-2 border-dyl-orange-300 bg-dyl-graphite-50">
    <h3 class="px-4 pt-4 font-display text-lg font-semibold text-gray-900">Atrasadas ({{ $atrasadas->count() }}{{ $topeAtrasadas ? '+' : '' }})</h3>
    <ul class="divide-y divide-gray-200">
        @foreach($atrasadas as $i)
            @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => true])
        @endforeach
    </ul>
</div>
@endif

@forelse($dias as $fecha => $items)
    <div class="mb-5">
        <h3 class="font-display text-lg font-semibold text-gray-900 mb-2 first-letter:uppercase">
            {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        </h3>
        <ul class="card divide-y divide-gray-100">
            @foreach($items as $i)
                @include('partials.linea-de-tiempo-item', ['i' => $i, 'conFecha' => false])
            @endforeach
        </ul>
    </div>
@empty
    @if($atrasadas->isEmpty())
        <div class="card p-8 text-center text-gray-500">No tienes entregas próximas.</div>
    @endif
@endforelse

@if($compacto && $total > 5)
    <a href="{{ route('linea-de-tiempo.index') }}" class="text-sm text-dyl-orange-700 hover:underline">Ver todo ({{ $total }})</a>
@endif
