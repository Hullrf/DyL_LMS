@extends('layouts.app')
@section('title', 'Reporte: ' . $reporte['usuario']->name)

@section('content')
@php $usuario = $reporte['usuario']; @endphp

<div class="flex justify-between items-center mb-8">
    <div>
        <a href="{{ route('reportes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Reportes</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $usuario->name }}</h1>
        <p class="text-sm text-gray-500">{{ $usuario->email }}</p>
    </div>
</div>

{{-- KPIs del estudiante --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow p-5 text-center">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Cursos inscritos</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['total_cursos'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5 text-center">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Completados</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['completados'] }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5 text-center">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Certificados</p>
        <p class="text-3xl font-bold text-dyl-orange-600 mt-1">
            {{ $reporte['cursos']->where('tiene_certificado', true)->count() }}
        </p>
    </div>
    <div class="bg-white rounded-xl shadow p-5 text-center">
        <p class="text-xs text-gray-400 uppercase tracking-wide">Actividades enviadas</p>
        <p class="text-3xl font-bold text-dyl-graphite-700 mt-1">{{ $reporte['respuestas_historial']->count() }}</p>
    </div>
</div>

{{-- Cursos del estudiante --}}
<div class="bg-white rounded-xl shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-900">Progreso por Curso</h2>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($reporte['cursos'] as $cd)
        <div class="px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-sm font-bold text-gray-900">{{ $cd['curso']->titulo }}</h3>
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                            @if($cd['estado'] === 'completado') bg-dyl-orange-100 text-dyl-orange-700
                            @elseif($cd['estado'] === 'en_progreso') bg-dyl-graphite-100 text-dyl-graphite-600
                            @else bg-gray-100 text-gray-500 @endif">
                            {{ ucfirst(str_replace('_', ' ', $cd['estado'])) }}
                        </span>
                        @if($cd['tiene_certificado'])
                            <span class="text-dyl-orange-500" title="Certificado emitido">&#127941;</span>
                        @endif
                    </div>

                    {{-- Barra de progreso --}}
                    <div class="flex items-center gap-3 mt-2">
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden max-w-xs">
                            <div class="h-full rounded-full {{ $cd['progreso_pct'] === 100 ? 'bg-dyl-orange-600' : 'bg-dyl-graphite-400' }}"
                                 style="width: {{ $cd['progreso_pct'] }}%"></div>
                        </div>
                        <span class="text-xs font-medium text-gray-600">{{ $cd['progreso_pct'] }}%</span>
                        <span class="text-xs text-gray-400">{{ $cd['lecciones_ok'] }}/{{ $cd['total_lecciones'] }} lecciones</span>
                    </div>
                </div>

                <div class="text-right flex-shrink-0">
                    @if($cd['promedio'] !== null)
                        <p class="text-2xl font-bold {{ $cd['promedio'] >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
                            {{ $cd['promedio'] }}%
                        </p>
                        <p class="text-xs text-gray-400">Calificación</p>
                    @else
                        <p class="text-sm text-gray-400">Sin actividades calificadas</p>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="px-6 py-10 text-center text-gray-400">No está inscrito en ningún curso.</div>
        @endforelse
    </div>
</div>

{{-- Historial de actividades --}}
@if($reporte['respuestas_historial']->isNotEmpty())
<div class="bg-white rounded-xl shadow">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-900">Últimas Actividades</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-50">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actividad</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Calificación</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($reporte['respuestas_historial'] as $resp)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <p class="text-sm font-medium text-gray-800">{{ $resp->actividad->titulo ?? 'Actividad eliminada' }}</p>
                        <p class="text-xs text-gray-400">{{ $resp->actividad ? ucfirst($resp->actividad->tipo) : '—' }}</p>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">
                        {{ $resp->actividad->leccion->modulo->curso->titulo ?? '—' }}
                    </td>
                    <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap">
                        {{ $resp->fecha_envio->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($resp->estado === 'calificada')
                            @php $pct = ($resp->actividad?->puntaje_maximo ?? 0) > 0
                                ? round(($resp->calificacion / $resp->actividad->puntaje_maximo) * 100) : 0; @endphp
                            <span class="text-sm font-bold {{ $pct >= 60 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">
                                {{ $resp->calificacion }}{{ $resp->actividad ? '/' . $resp->actividad->puntaje_maximo : '' }}
                            </span>
                        @else
                            <span class="text-xs text-dyl-graphite-900 font-semibold bg-dyl-graphite-100 px-2 py-0.5 rounded-full">Pendiente</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
