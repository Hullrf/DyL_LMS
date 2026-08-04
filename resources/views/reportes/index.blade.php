@extends('layouts.app')
@section('title', 'Reportes - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('reportes.index') }}@endsection

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Reportes y Analytics</h1>
    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dashboard</a>
</div>

{{-- KPIs globales (solo admin) --}}
@if($kpis)
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Estudiantes</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['total_estudiantes'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Inscripciones</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['total_inscripciones'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Tasa de completitud</p>
        <p class="text-3xl font-bold mt-1 {{ $kpis['tasa_completitud'] >= 70 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
            {{ $kpis['tasa_completitud'] }}%
        </p>
        <div class="mt-2 h-1.5 bg-gray-200 rounded-full">
            <div class="h-full rounded-full {{ $kpis['tasa_completitud'] >= 70 ? 'bg-dyl-orange-500' : 'bg-dyl-graphite-400' }}"
                 style="width: {{ min($kpis['tasa_completitud'], 100) }}%"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Certificados emitidos</p>
        <p class="text-3xl font-bold text-dyl-orange-500 mt-1">{{ $kpis['total_certificados'] }}</p>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Total cursos</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $kpis['total_cursos'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Publicados</p>
        <p class="text-2xl font-bold text-dyl-orange-600 mt-1">{{ $kpis['cursos_publicados'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Completaron cursos</p>
        <p class="text-2xl font-bold text-dyl-graphite-700 mt-1">{{ $kpis['completados'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Promedio calificación</p>
        <p class="text-2xl font-bold mt-1 {{ ($kpis['promedio_calificacion'] ?? 0) >= 60 ? 'text-dyl-orange-600' : 'text-gray-500' }}">
            {{ $kpis['promedio_calificacion'] !== null ? $kpis['promedio_calificacion'] . '%' : '—' }}
        </p>
    </div>
</div>
@endif

{{-- Tabla de cursos --}}
<div class="bg-white rounded-xl shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
        <h2 class="text-lg font-bold text-gray-900">Reportes por Curso</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                    @if($kpis)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructor</th>
                    @endif
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Inscritos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($cursos as $curso)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $curso->titulo }}</p>
                        <p class="text-xs text-gray-400">{{ $curso->duracion_horas }} h</p>
                    </td>
                    @if($kpis)
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $curso->creador->name }}</td>
                    @endif
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-medium
                            @if($curso->estado === 'publicado') bg-dyl-orange-100 text-dyl-orange-700
                            @elseif($curso->estado === 'borrador') bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold
                            @else bg-gray-100 text-gray-500 @endif">
                            {{ ucfirst($curso->estado) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-lg font-bold text-gray-800">{{ $curso->inscripciones_count }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('reportes.curso', $curso) }}"
                           class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
                            Ver reporte &rarr;
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-400">Sin cursos</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Tabla de usuarios (solo admin) --}}
@if($kpis && $usuarios->isNotEmpty())
<div class="bg-white rounded-xl shadow">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-900">Actividad de Estudiantes</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estudiante</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cursos inscritos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ver reporte</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($usuarios as $usr)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $usr->name }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $usr->email }}</td>
                    <td class="px-6 py-3 text-center text-sm font-bold text-gray-700">{{ $usr->cursos_count }}</td>
                    <td class="px-6 py-3">
                        <a href="{{ route('reportes.estudiante', $usr) }}"
                           class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver &rarr;</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Gráficos (solo admin ve $chartData completo) --}}
@if(isset($chartData))
<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Inscripciones por mes (últimos 6 meses)</h3>
        <div style="height: 220px;">
            <canvas id="chartInscMes"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Estado de Respuestas</h3>
        <div style="height: 220px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartRespEstado"></canvas>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartInscMes'), {
        type: 'line',
        data: {
            labels: @json($chartData['meses_labels']),
            datasets: [{
                label: 'Inscripciones',
                data: @json($chartData['meses_data']),
                borderColor: '#EA580C',
                backgroundColor: 'rgba(234,88,12,0.08)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#EA580C',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('chartRespEstado'), {
        type: 'doughnut',
        data: {
            labels: ['Sin calificar', 'Calificada', 'En revisión'],
            datasets: [{
                data: @json($chartData['resp_estados']),
                backgroundColor: ['#CBD5E1', '#F97316', '#475569'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
});
</script>
@endpush
@endif

@endsection
