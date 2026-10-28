@extends('layouts.app')
@section('title', 'Dashboard instructor - D&L Quality Campus')

@section('content')
@php $pendientes = $stats['pendientes_calificar']; @endphp

<header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3 mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    <dl class="flex gap-8 text-sm text-gray-500">
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['mis_cursos'] }}</dd><dt>{{ $stats['mis_cursos'] === 1 ? 'curso' : 'cursos' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['cursos_publicados'] }}</dd><dt>{{ $stats['cursos_publicados'] === 1 ? 'publicado' : 'publicados' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['estudiantes_inscritos'] }}</dd><dt>{{ $stats['estudiantes_inscritos'] === 1 ? 'estudiante' : 'estudiantes' }}</dt></div>
    </dl>
</header>

{{-- Lo primero para un instructor: qué espera su revisión --}}
<section aria-labelledby="pendientes-titulo" class="card p-6 sm:p-8 mb-10 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 id="pendientes-titulo" class="font-display text-2xl font-semibold text-gray-900">
            @if($pendientes > 0)
                {{ $pendientes }} {{ $pendientes === 1 ? 'entrega por calificar' : 'entregas por calificar' }}
            @else
                Nada pendiente por calificar
            @endif
        </h2>
        <p class="text-gray-600 mt-1">
            {{ $pendientes > 0 ? 'Tus estudiantes están esperando su retroalimentación.' : 'Cuando un estudiante entregue una actividad, aparecerá aquí.' }}
        </p>
    </div>
    <a href="{{ route('calificaciones.index') }}" class="{{ $pendientes > 0 ? 'btn-primary btn-lg' : 'btn-outline btn-lg' }}">
        {{ $pendientes > 0 ? 'Calificar ahora' : 'Ver calificaciones' }}
    </a>
</section>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="font-display text-2xl font-semibold text-gray-900">Mis cursos</h2>
    <a href="{{ route('cursos.create') }}" class="btn-primary">+ Nuevo curso</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($cursos as $curso)
        <article class="card-hover overflow-hidden flex flex-col">
            <a href="{{ route('cursos.show', $curso) }}" tabindex="-1" aria-hidden="true" class="block h-32">
                @if($curso->imagen_portada)
                    <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="" class="w-full h-full object-cover">
                @else
                    <x-portada-curso :curso="$curso"/>
                @endif
            </a>
            <div class="p-5 flex flex-col flex-1">
                <div class="flex justify-between items-start gap-3 mb-1">
                    <h3 class="font-display text-xl font-semibold text-gray-900 break-words">{{ $curso->titulo }}</h3>
                    <span class="{{ $curso->estado === 'publicado' ? 'badge-success' : ($curso->estado === 'borrador' ? 'badge-warning' : 'badge-info') }} shrink-0">{{ ucfirst($curso->estado) }}</span>
                </div>
                <p class="text-sm text-gray-500 mb-4">{{ $curso->modulos->count() }} {{ $curso->modulos->count() === 1 ? 'módulo' : 'módulos' }}</p>
                <div class="flex gap-2 mt-auto">
                    <a href="{{ route('cursos.show', $curso) }}" class="btn-outline flex-1">Ver</a>
                    <a href="{{ route('cursos.edit', $curso) }}" class="btn-primary flex-1">Editar</a>
                </div>
            </div>
        </article>
    @empty
        <div class="card md:col-span-2 lg:col-span-3 p-12 text-center text-gray-500">
            Aún no tienes cursos.
            <a href="{{ route('cursos.create') }}" class="text-dyl-orange-700 font-medium hover:underline ml-1">Crea tu primer curso</a>
        </div>
    @endforelse
</div>

@if($cursos->isNotEmpty())
<section class="card mt-10 p-6" aria-labelledby="progreso-titulo">
    <h2 id="progreso-titulo" class="font-display text-xl font-semibold text-gray-900 mb-4">Progreso promedio por curso</h2>
    <div style="height: 200px;">
        <canvas id="chartProgresoCursos" role="img" aria-label="Gráfico de barras con el progreso promedio de los estudiantes en cada curso"></canvas>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartProgresoCursos'), {
        type: 'bar',
        data: {
            labels: @json($cursos->pluck('titulo')),
            datasets: [{
                label: 'Progreso (%)',
                data: @json($stats['progreso_por_curso']),
                backgroundColor: '#EA580C',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush
@endif
@endsection
