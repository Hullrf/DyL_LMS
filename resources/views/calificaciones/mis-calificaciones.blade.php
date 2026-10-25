@extends('layouts.app')
@section('title', 'Mis Calificaciones - D&L Quality Campus')

@section('content')
<header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Mis calificaciones</h1>

    {{-- Resumen rápido --}}
    @if($respuestas->count() > 0)
    @php
        $calificadas = $respuestas->where('estado', 'calificada')->whereIn('id', $oficialesIds);
        $promedio    = $calificadas->count() > 0
            ? round($calificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
                ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
            : 0;
    @endphp
    <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-500">
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $respuestas->count() }}</dd><dt>enviadas</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $calificadas->count() }}</dd><dt>calificadas</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $porCurso->count() }}</dd><dt>{{ $porCurso->count() === 1 ? 'curso' : 'cursos' }}</dt></div>
        <div><dd class="font-display text-2xl font-semibold tabular-nums {{ $promedio >= 60 ? 'text-dyl-orange-700' : 'text-gray-900' }}">{{ $promedio }}%</dd><dt>promedio</dt></div>
    </dl>
    @endif
</header>

{{-- Secciones por curso --}}
@forelse($porCurso as $grupo)
@php
    $grupoCalificadas = $grupo->respuestas->where('estado', 'calificada')->whereIn('id', $oficialesIds);
    $promedioCurso    = $grupoCalificadas->count() > 0
        ? round($grupoCalificadas->avg(fn($r) => $r->actividad->puntaje_maximo > 0
            ? ($r->calificacion / $r->actividad->puntaje_maximo) * 100 : 0))
        : null;
@endphp
<section class="card overflow-hidden mb-8" aria-labelledby="curso-{{ $grupo->curso->id }}">
    {{-- Encabezado del curso --}}
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 p-5 sm:p-6">
        <div class="min-w-0">
            <h2 id="curso-{{ $grupo->curso->id }}" class="font-display text-2xl font-semibold text-gray-900">{{ $grupo->curso->titulo }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $grupo->respuestas->count() }} {{ Str::plural('actividad', $grupo->respuestas->count()) }}
            </p>
        </div>
        @if($promedioCurso !== null)
        <p class="text-right">
            <span class="block font-display text-4xl font-semibold tabular-nums {{ $promedioCurso >= 60 ? 'text-dyl-orange-700' : 'text-gray-900' }}">{{ $promedioCurso }}%</span>
            <span class="text-sm text-gray-500">promedio</span>
        </p>
        @endif
    </div>

    @isset($notasPorCurso[$grupo->curso->id])
        @include('calificaciones.partials.resumen-nota-curso', ['nota' => $notasPorCurso[$grupo->curso->id]])
    @endisset

    <div class="overflow-x-auto border-t border-gray-100">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Actividad</th>
                    <th scope="col" class="hidden sm:table-cell px-6 py-3 text-left text-sm font-medium text-gray-500">Enviado</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Calificación</th>
                    <th scope="col" class="hidden md:table-cell px-6 py-3 text-left text-sm font-medium text-gray-500">Retroalimentación</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($grupo->respuestas as $respuesta)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $respuesta->actividad->tituloCompleto() }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span>{{ ucfirst($respuesta->actividad->tipo) }}</span>
                            @if(in_array($respuesta->id, $oficialesIds) && $grupo->respuestas->where('actividad_id', $respuesta->actividad_id)->count() > 1)
                                <span class="badge-success">Cuenta para tu nota</span>
                            @endif
                        </p>
                        {{-- En pantallas chicas la fecha y el comentario viven aquí (sus columnas se ocultan) --}}
                        <p class="sm:hidden mt-1 text-xs text-gray-500">{{ $respuesta->fecha_envio->format('d/m/Y H:i') }}</p>
                        @if($respuesta->feedback)
                            <details class="md:hidden mt-2">
                                <summary class="cursor-pointer text-dyl-orange-700 text-xs">Ver comentario</summary>
                                <p class="mt-2 text-gray-700 bg-dyl-graphite-50 p-3 rounded text-xs whitespace-pre-wrap leading-relaxed">{{ $respuesta->feedback }}</p>
                            </details>
                        @endif
                    </td>
                    <td class="hidden sm:table-cell px-6 py-4 whitespace-nowrap">
                        <p class="text-sm text-gray-700">{{ $respuesta->fecha_envio->format('d/m/Y') }}</p>
                        <p class="text-xs text-gray-500">{{ $respuesta->fecha_envio->format('H:i') }}</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($respuesta->estado === 'calificada')
                            @php
                                $max = $respuesta->actividad->puntaje_maximo;
                                $pct = $max > 0 ? round(($respuesta->calificacion / $max) * 100) : 0;
                            @endphp
                            <div class="flex flex-col items-center">
                                <span class="font-display text-2xl font-semibold tabular-nums {{ $pct >= 60 ? 'text-dyl-orange-700' : 'text-gray-900' }}">
                                    {{ number_format($respuesta->calificacion, 2) }}
                                    <span class="text-sm text-gray-500 font-sans font-normal">/ {{ number_format($max, 2) }}</span>
                                </span>
                                <span class="text-xs text-gray-500 tabular-nums">{{ $pct }}%</span>
                            </div>
                        @elseif($respuesta->estado === 'en_revision')
                            <span class="badge-info">En revisión</span>
                        @else
                            <span class="badge-warning">Pendiente</span>
                        @endif
                    </td>
                    <td class="hidden md:table-cell px-6 py-4 text-sm text-gray-700 max-w-xs">
                        @if($respuesta->feedback)
                            <details class="cursor-pointer">
                                <summary class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm">Ver comentario</summary>
                                <p class="mt-2 text-gray-700 bg-dyl-graphite-50 p-3 rounded text-xs whitespace-pre-wrap leading-relaxed">{{ $respuesta->feedback }}</p>
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
</section>
@empty
<div class="card p-16 text-center text-gray-500">
    Todavía no has enviado ninguna actividad.
    <a href="{{ route('cursos.index') }}" class="text-dyl-orange-700 font-medium hover:underline ml-1">Ver cursos disponibles</a>
</div>
@endforelse
@endsection
