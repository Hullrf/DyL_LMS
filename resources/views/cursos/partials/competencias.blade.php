@php
    $esEstudianteDelCurso = auth()->check() && auth()->id() !== $curso->created_by && !auth()->user()->esAdmin()
        && $curso->inscripciones()->where('user_id', auth()->id())->where('estado', '!=', 'abandonado')->exists();
    $avance = $esEstudianteDelCurso ? app(\App\Services\CompetenciasService::class)->avance(auth()->user(), $curso) : collect();
@endphp
@if($avance->isNotEmpty())
<details class="mt-8 rounded-xl bg-white shadow" open>
    <summary class="cursor-pointer px-6 py-4 text-lg font-bold text-gray-900">Competencias del curso</summary>
    <ul class="divide-y divide-gray-100 px-6 pb-4">
        @foreach($avance as $f)
            <li class="py-3">
                <p class="text-sm font-medium text-gray-900">{{ $f->competencia->descripcion }}</p>
                @if($f->total === 0)
                    <p class="mt-1 text-xs text-gray-500">Sin actividades enlazadas</p>
                @else
                    @php $pct = (int) round($f->cumplidas / $f->total * 100); @endphp
                    <div class="mt-2 h-1.5 rounded-full bg-gray-200" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-1.5 rounded-full bg-dyl-orange-600" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-600">
                        {{ $f->cumplidas }} de {{ $f->total }} actividades cumplidas
                        @if($f->nota_media !== null) · Nota media: {{ $f->nota_media }}% @endif
                    </p>
                @endif
            </li>
        @endforeach
    </ul>
</details>
@endif
