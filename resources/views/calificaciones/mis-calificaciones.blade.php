@extends('layouts.app')
@section('title', 'Mis Calificaciones - LMS DyL')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Mis Calificaciones</h1>
    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 text-sm">&larr; Dashboard</a>
</div>

@if(session('success'))
    <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
@endif

{{-- Resumen rápido --}}
@if($respuestas->count() > 0)
@php
    $calificadas = $respuestas->where('estado', 'calificada');
    $promedio    = $calificadas->count() > 0
        ? round($calificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
            ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
        : 0;
@endphp
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Enviadas</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ $respuestas->count() }}</p>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Calificadas</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ $calificadas->count() }}</p>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Cursos</p>
        <p class="text-3xl font-bold text-dyl-orange mt-1">{{ $porCurso->count() }}</p>
    </div>
    <div class="bg-white p-5 rounded-lg shadow text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Promedio</p>
        <p class="text-3xl font-bold mt-1 {{ $promedio >= 60 ? 'text-green-600' : 'text-red-500' }}">{{ $promedio }}%</p>
    </div>
</div>
@endif

{{-- Secciones por curso --}}
@forelse($porCurso as $grupo)
<div class="mb-8">
    {{-- Encabezado del curso --}}
    <div class="flex items-center gap-3 mb-3">
        <div class="w-2 h-6 bg-dyl-orange rounded-full flex-shrink-0"></div>
        <h2 class="text-lg font-bold text-gray-900">{{ $grupo->curso->titulo }}</h2>
        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
            {{ $grupo->respuestas->count() }} {{ Str::plural('actividad', $grupo->respuestas->count()) }}
        </span>
        @php
            $grupoCalificadas = $grupo->respuestas->where('estado', 'calificada');
            $promedioCurso    = $grupoCalificadas->count() > 0
                ? round($grupoCalificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
                    ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
                : null;
        @endphp
        @if($promedioCurso !== null)
        <span class="ml-auto text-sm font-semibold {{ $promedioCurso >= 60 ? 'text-green-600' : 'text-red-500' }}">
            Promedio: {{ $promedioCurso }}%
        </span>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Calificación</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Retroalimentación</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($grupo->respuestas as $respuesta)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $respuesta->actividad->titulo }}</p>
                        <span class="inline-block mt-1 px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded">
                            {{ ucfirst($respuesta->actividad->tipo) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <p class="text-sm text-gray-700">{{ $respuesta->fecha_envio->format('d/m/Y') }}</p>
                        <p class="text-xs text-gray-400">{{ $respuesta->fecha_envio->format('H:i') }}</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($respuesta->estado === 'calificada')
                            @php
                                $max = $respuesta->actividad->puntaje_maximo;
                                $pct = $max > 0 ? round(($respuesta->calificacion / $max) * 100) : 0;
                            @endphp
                            <div class="flex flex-col items-center">
                                <span class="text-xl font-bold {{ $pct >= 60 ? 'text-green-600' : 'text-red-500' }}">
                                    {{ $respuesta->calificacion }}
                                    <span class="text-sm text-gray-400 font-normal">/ {{ $max }}</span>
                                </span>
                                <span class="text-xs {{ $pct >= 60 ? 'text-green-600' : 'text-red-500' }}">{{ $pct }}%</span>
                            </div>
                        @elseif($respuesta->estado === 'en_revision')
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">En revisión</span>
                        @else
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full font-medium">Pendiente</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 max-w-xs">
                        @if($respuesta->feedback)
                            <details class="cursor-pointer">
                                <summary class="text-blue-600 hover:text-blue-800 text-sm">Ver comentario</summary>
                                <p class="mt-2 text-gray-700 bg-blue-50 p-3 rounded text-xs whitespace-pre-wrap leading-relaxed">{{ $respuesta->feedback }}</p>
                            </details>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-16 text-center text-gray-500">
    Todavía no has enviado ninguna actividad.
    <a href="{{ route('cursos.index') }}" class="text-blue-600 hover:underline ml-1">Ver cursos disponibles</a>
</div>
@endforelse
@endsection
