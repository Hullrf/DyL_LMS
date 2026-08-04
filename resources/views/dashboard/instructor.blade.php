@extends('layouts.app')
@section('title', 'Dashboard Instructor - LMS DyL')

@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-8">Panel del Instructor</h1>

@if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Mis Cursos</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['mis_cursos'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Publicados</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['cursos_publicados'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Estudiantes</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $stats['estudiantes_inscritos'] }}</p>
    </div>
    <a href="{{ route('calificaciones.index') }}" class="bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow block">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Por Calificar</p>
        <p class="text-3xl font-bold mt-1 {{ $stats['pendientes_calificar'] > 0 ? 'text-dyl-orange-500' : 'text-gray-400' }}">
            {{ $stats['pendientes_calificar'] }}
        </p>
        @if($stats['pendientes_calificar'] > 0)
            <p class="text-xs text-dyl-orange-500 mt-1">Ver pendientes &rarr;</p>
        @endif
    </a>
</div>

{{-- Acciones rápidas --}}
<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold text-gray-900">Mis Cursos</h2>
    <div class="flex gap-3">
        <a href="{{ route('calificaciones.index') }}"
           class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">
            Calificaciones
        </a>
        <a href="{{ route('cursos.create') }}"
           class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm">
            + Nuevo Curso
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($cursos as $curso)
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-2">
                <h3 class="font-bold text-gray-900">{{ $curso->titulo }}</h3>
                <span class="px-2 py-1 rounded text-xs font-medium ml-2 flex-shrink-0
                    @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-800
                    @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ ucfirst($curso->estado) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-4">{{ $curso->modulos->count() }} módulo(s)</p>
            <div class="flex gap-2">
                <a href="{{ route('cursos.show', $curso) }}"
                   class="flex-1 text-center bg-gray-100 text-gray-700 py-2 rounded hover:bg-gray-200 text-sm">
                    Ver
                </a>
                <a href="{{ route('cursos.edit', $curso) }}"
                   class="flex-1 text-center bg-dyl-orange-600 text-white py-2 rounded hover:bg-dyl-orange-700 text-sm">
                    Editar
                </a>
            </div>
        </div>
    @empty
        <div class="col-span-3 text-center py-12 text-gray-500">
            No tienes cursos aún.
            <a href="{{ route('cursos.create') }}" class="text-dyl-orange-600 hover:underline">Crea tu primer curso</a>
        </div>
    @endforelse
</div>

@if($cursos->isNotEmpty())
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Progreso promedio por curso (%)</h3>
    <div style="height: 200px;">
        <canvas id="chartProgresoCursos"></canvas>
    </div>
</div>
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
