@extends('layouts.app')
@section('title', 'Dashboard admin - D&L Quality Campus')

@section('content')
<header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3 mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Administración</h1>
    <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-500">
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['total_cursos'] }}</dd><dt>{{ $stats['total_cursos'] === 1 ? 'curso' : 'cursos' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['cursos_publicados'] }}</dd><dt>{{ $stats['cursos_publicados'] === 1 ? 'publicado' : 'publicados' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['total_usuarios'] }}</dd><dt>{{ $stats['total_usuarios'] === 1 ? 'usuario' : 'usuarios' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-dyl-orange-700 tabular-nums">{{ $stats['total_instructores'] }}</dd><dt>{{ $stats['total_instructores'] === 1 ? 'instructor' : 'instructores' }}</dt></div>
    </dl>
</header>

{{-- Accesos rápidos --}}
<div class="flex flex-wrap gap-3 mb-8">
    <a href="{{ route('reportes.index') }}" class="btn-primary btn-lg">Ver reportes</a>
    <a href="{{ route('calificaciones.index') }}" class="btn-outline btn-lg">Calificaciones</a>
    <a href="{{ route('cursos.create') }}" class="btn-outline btn-lg">+ Nuevo curso</a>
</div>

{{-- Cursos recientes --}}
<section class="card overflow-hidden" aria-labelledby="recientes-titulo">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
        <h2 id="recientes-titulo" class="font-display text-2xl font-semibold text-gray-900">Cursos recientes</h2>
        <a href="{{ route('reportes.index') }}" class="text-sm text-dyl-orange-700 hover:underline">Ver todos los reportes</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-500">Título</th>
                    <th scope="col" class="hidden md:table-cell px-6 py-3 text-left text-sm font-medium text-gray-500">Instructor</th>
                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-500">Estado</th>
                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cursos_recientes as $curso)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 text-sm">
                            {{ $curso->titulo }}
                            <span class="md:hidden block mt-0.5 text-xs font-normal text-gray-500">{{ $curso->creador->name }}</span>
                        </td>
                        <td class="hidden md:table-cell px-6 py-4 text-gray-600 text-sm">{{ $curso->creador->name }}</td>
                        <td class="px-4 sm:px-6 py-4">
                            <span class="{{ $curso->estado === 'publicado' ? 'badge-success' : ($curso->estado === 'borrador' ? 'badge-warning' : 'badge-info') }}">{{ ucfirst($curso->estado) }}</span>
                        </td>
                        <td class="px-4 sm:px-6 py-4">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <a href="{{ route('reportes.curso', $curso) }}" class="btn-outline btn-sm">Reporte</a>
                                <a href="{{ route('cursos.edit', $curso) }}" class="btn-ghost btn-sm">Editar</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Aún no hay cursos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- Gráficos --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <section class="card p-6" aria-labelledby="estado-titulo">
        <h2 id="estado-titulo" class="font-display text-xl font-semibold text-gray-900 mb-4">Cursos por estado</h2>
        <div style="height:200px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartCursosEstado" role="img" aria-label="Gráfico de dona con los cursos en borrador, publicados y archivados"></canvas>
        </div>
    </section>
    <section class="card p-6 lg:col-span-2" aria-labelledby="inscripciones-titulo">
        <h2 id="inscripciones-titulo" class="font-display text-xl font-semibold text-gray-900 mb-4">Inscripciones de los últimos 6 meses</h2>
        <div style="height:200px;">
            <canvas id="chartInscripciones" role="img" aria-label="Gráfico de barras con las inscripciones de cada uno de los últimos seis meses"></canvas>
        </div>
    </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartCursosEstado'), {
        type: 'doughnut',
        data: {
            labels: ['Borrador', 'Publicado', 'Archivado'],
            datasets: [{
                data: [{{ $stats['cursos_borrador'] }}, {{ $stats['cursos_publicados'] }}, {{ $stats['cursos_archivados'] }}],
                backgroundColor: ['#94A3B8', '#F97316', '#64748B'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });

    new Chart(document.getElementById('chartInscripciones'), {
        type: 'bar',
        data: {
            labels: @json($stats['meses_labels']),
            datasets: [{
                label: 'Inscripciones',
                data: @json($stats['meses_data']),
                backgroundColor: '#EA580C',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush
@endsection
