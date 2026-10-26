@extends('layouts.app')
@section('title', 'Reportes - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('reportes.index') }}@endsection

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Reportes y Analytics</h1>
    
</div>

{{-- KPIs globales (solo admin) --}}
@if($kpis)
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card p-5">
        <p class="text-sm text-gray-500">Estudiantes</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-graphite-700 mt-1">{{ $kpis['total_estudiantes'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Inscripciones</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-graphite-700 mt-1">{{ $kpis['total_inscripciones'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Tasa de completitud</p>
        <p class="font-display text-4xl font-semibold tabular-nums mt-1 {{ $kpis['tasa_completitud'] >= 70 ? 'text-dyl-orange-700' : 'text-dyl-graphite-500' }}">
            {{ $kpis['tasa_completitud'] }}%
        </p>
        <div class="mt-2 h-1.5 bg-gray-200 rounded-full">
            <div class="h-full rounded-full {{ $kpis['tasa_completitud'] >= 70 ? 'bg-dyl-orange-500' : 'bg-dyl-graphite-400' }}"
                 style="width: {{ min($kpis['tasa_completitud'], 100) }}%"></div>
        </div>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Certificados emitidos</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-orange-700 mt-1">{{ $kpis['total_certificados'] }}</p>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card p-5">
        <p class="text-sm text-gray-500">Total cursos</p>
        <p class="font-display text-3xl font-semibold tabular-nums text-gray-800 mt-1">{{ $kpis['total_cursos'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Publicados</p>
        <p class="font-display text-3xl font-semibold tabular-nums text-dyl-orange-700 mt-1">{{ $kpis['cursos_publicados'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Completaron cursos</p>
        <p class="font-display text-3xl font-semibold tabular-nums text-dyl-graphite-700 mt-1">{{ $kpis['completados'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-sm text-gray-500">Promedio calificación</p>
        <p class="font-display text-3xl font-semibold tabular-nums mt-1 {{ ($kpis['promedio_calificacion'] ?? 0) >= 60 ? 'text-dyl-orange-700' : 'text-gray-500' }}">
            {{ $kpis['promedio_calificacion'] !== null ? $kpis['promedio_calificacion'] . '%' : '—' }}
        </p>
    </div>
</div>
@endif

{{-- Tabla de cursos --}}
<div class="card mb-8">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-display text-2xl font-semibold text-gray-900 mb-4">Reportes por Curso</h2>
        <form method="GET" class="flex flex-wrap gap-3">
            @foreach(['usuario_buscar', 'usuarios_page'] as $preservar)
                @if(request()->filled($preservar))
                    <input type="hidden" name="{{ $preservar }}" value="{{ request($preservar) }}">
                @endif
            @endforeach
            <input aria-label="Buscar curso..." type="text" name="curso_buscar" value="{{ request('curso_buscar') }}"
                   placeholder="Buscar curso..." class="form-input flex-1 min-w-[200px]">
            <select name="curso_estado" class="form-select w-auto max-w-full">
                <option value="">Todos los estados</option>
                <option value="publicado" @selected(request('curso_estado') === 'publicado')>Publicado</option>
                <option value="borrador"  @selected(request('curso_estado') === 'borrador')>Borrador</option>
                <option value="archivado" @selected(request('curso_estado') === 'archivado')>Archivado</option>
            </select>
            <button type="submit" class="btn-primary">Filtrar</button>
            @if(request()->anyFilled(['curso_buscar', 'curso_estado']))
                <a href="{{ route('reportes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 self-center">Limpiar</a>
            @endif
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Curso</th>
                    @if($kpis)
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Instructor</th>
                    @endif
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Estado</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Inscritos</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($cursos as $curso)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $curso->titulo }}</p>
                        <p class="text-xs text-gray-500">{{ $curso->duracion_horas }} h</p>
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
                           class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm font-medium">
                            Ver reporte &rarr;
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">Sin cursos</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cursos->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $cursos->links() }}
    </div>
    @endif
</div>

{{-- Tabla de usuarios (solo admin) --}}
@if($kpis)
<div class="card">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-display text-2xl font-semibold text-gray-900 mb-4">Actividad de Estudiantes</h2>
        <form method="GET" class="flex flex-wrap gap-3">
            @foreach(['curso_buscar', 'curso_estado', 'cursos_page'] as $preservar)
                @if(request()->filled($preservar))
                    <input type="hidden" name="{{ $preservar }}" value="{{ request($preservar) }}">
                @endif
            @endforeach
            <input aria-label="Buscar por nombre o email..." type="text" name="usuario_buscar" value="{{ request('usuario_buscar') }}"
                   placeholder="Buscar por nombre o email..." class="form-input flex-1 min-w-[200px]">
            <button type="submit" class="btn-primary">Filtrar</button>
            @if(request()->filled('usuario_buscar'))
                <a href="{{ route('reportes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 self-center">Limpiar</a>
            @endif
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Estudiante</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Email</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Cursos inscritos</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Ver reporte</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($usuarios as $usr)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $usr->name }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $usr->email }}</td>
                    <td class="px-6 py-3 text-center text-sm font-bold text-gray-700">{{ $usr->cursos_count }}</td>
                    <td class="px-6 py-3">
                        <a href="{{ route('reportes.estudiante', $usr) }}"
                           class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm">Ver &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">Ningún estudiante coincide con la búsqueda.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($usuarios->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $usuarios->links() }}
    </div>
    @endif
</div>
@endif

{{-- Gráficos (solo admin ve $chartData completo) --}}
@if(isset($chartData))
<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-6">
        <h3 class="font-display text-xl font-semibold text-gray-900 mb-4">Inscripciones por mes (últimos 6 meses)</h3>
        <div style="height: 220px;">
            <canvas id="chartInscMes" role="img" aria-label="Gráfico de barras con las inscripciones de cada uno de los últimos seis meses"></canvas>
        </div>
    </div>
    <div class="card p-6">
        <h3 class="font-display text-xl font-semibold text-gray-900 mb-4">Estado de Respuestas</h3>
        <div style="height: 220px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartRespEstado" role="img" aria-label="Gráfico con el estado de las respuestas de los estudiantes"></canvas>
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
                backgroundColor: ['#94A3B8', '#F97316', '#64748B'],
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
