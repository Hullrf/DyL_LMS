@extends('layouts.app')
@section('title', 'Reporte: ' . $reporte['curso']->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('reportes.curso', $reporte['curso']) }}@endsection
@section('content')
@php $curso = $reporte['curso']; @endphp

<div class="flex flex-wrap justify-between items-center gap-3 mb-8">
    <div>
        <a href="{{ route('reportes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Reportes</a>
        <h1 class="font-display text-3xl font-semibold text-gray-900 mt-1 break-words">{{ $curso->titulo }}</h1>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reportes.seguimiento', $curso) }}"
           class="btn-outline">
            Seguimiento
        </a>
        <a href="{{ route('reportes.csv', $curso) }}"
           class="btn-outline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Exportar CSV
        </a>
        <a href="{{ route('reportes.pdf', $curso) }}"
           class="btn-outline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Exportar PDF
        </a>
        <a href="{{ route('reportes.excel.curso', $curso) }}"
           class="btn-outline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Excel
        </a>
    </div>
</div>

{{-- KPIs del curso --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="card p-5 text-center">
        <p class="text-sm text-gray-500">Inscritos</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-graphite-700 mt-1">{{ $reporte['total_inscritos'] }}</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-sm text-gray-500">Completados</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-orange-700 mt-1">{{ $reporte['completados'] }}</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-sm text-gray-500">En progreso</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-graphite-500 mt-1">{{ $reporte['en_progreso'] }}</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-sm text-gray-500">Lecciones</p>
        <p class="font-display text-4xl font-semibold tabular-nums text-dyl-graphite-700 mt-1">{{ $reporte['total_lecciones'] }}</p>
    </div>
    <div class="card p-5 text-center">
        <p class="text-sm text-gray-500">Promedio global</p>
        <p class="font-display text-4xl font-semibold tabular-nums mt-1 {{ ($reporte['promedio_global'] ?? 0) >= 60 ? 'text-dyl-orange-700' : 'text-gray-500' }}">
            {{ $reporte['promedio_global'] !== null ? $reporte['promedio_global'] . '%' : '—' }}
        </p>
    </div>
</div>

{{-- Tasa de completitud visual --}}
@if($reporte['total_inscritos'] > 0)
@php $tasaCurso = (int) round(($reporte['completados'] / $reporte['total_inscritos']) * 100); @endphp
<div class="card p-5 mb-8">
    <div class="flex justify-between text-sm font-medium text-gray-700 mb-2">
        <span>Tasa de completitud del curso</span>
        <span class="{{ $tasaCurso >= 70 ? 'text-dyl-orange-700' : 'text-dyl-graphite-500' }}">{{ $tasaCurso }}%</span>
    </div>
    <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
        <div class="h-full rounded-full {{ $tasaCurso >= 70 ? 'bg-dyl-orange-500' : 'bg-dyl-graphite-400' }}"
             style="width: {{ $tasaCurso }}%"></div>
    </div>
</div>
@endif

{{-- Tabla de estudiantes --}}
<div class="card">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-display text-2xl font-semibold text-gray-900">Detalle por Estudiante</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Estudiante</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Estado</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Progreso</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Calificación</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Certificado</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Inicio</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Ver</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($reporte['estudiantes'] as $e)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $e['usuario']->name }}</p>
                        <p class="text-xs text-gray-500">{{ $e['usuario']->email }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-medium
                            @if($e['estado'] === 'completado') bg-dyl-orange-100 text-dyl-orange-700
                            @elseif($e['estado'] === 'en_progreso') bg-dyl-graphite-100 text-dyl-graphite-600
                            @else bg-gray-100 text-gray-500 @endif">
                            {{ ucfirst(str_replace('_', ' ', $e['estado'])) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 min-w-[160px]">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $e['progreso_pct'] === 100 ? 'bg-dyl-orange-600' : 'bg-dyl-graphite-400' }}"
                                     style="width: {{ $e['progreso_pct'] }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-600 w-10 text-right">
                                {{ $e['progreso_pct'] }}%
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $e['lecciones_ok'] }}/{{ $reporte['total_lecciones'] }} lecciones</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($e['promedio'] !== null)
                            <span class="text-sm font-bold {{ $e['promedio'] >= 60 ? 'text-dyl-orange-700' : 'text-dyl-graphite-500' }}">
                                {{ $e['promedio'] }}%
                            </span>
                        @else
                            <span class="text-xs text-gray-500">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($e['tiene_certificado'])
                            <span class="inline-flex text-dyl-orange-700" title="Certificado emitido" role="img" aria-label="Certificado emitido"><x-icon name="certificado" class="w-5 h-5"/></span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">
                        {{ $e['fecha_inicio'] ? \Carbon\Carbon::parse($e['fecha_inicio'])->format('d/m/Y') : '—' }}
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('reportes.estudiante', $e['usuario']) }}"
                           class="text-dyl-orange-700 hover:text-dyl-orange-800 text-xs">Ver &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                        No hay estudiantes inscritos en este curso.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
