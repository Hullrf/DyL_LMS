@extends('layouts.app')
@section('title', $curso->titulo . ' - Calificaciones - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('calificaciones.curso', $curso) }}@endsection
@section('content')

<div class="flex flex-wrap justify-between items-center gap-3 mb-6">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-900 break-words">{{ $curso->titulo }}</h1>
        <p class="text-sm text-gray-500">Calificaciones</p>
    </div>
    <div class="flex items-center gap-4">
        <a href="{{ route('cursos.ponderaciones.edit', $curso) }}" class="btn-outline btn-sm">Ponderaciones</a>
        <a href="{{ route('calificaciones.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Cursos</a>
    </div>
</div>

@include('cursos.partials.aviso-ponderacion')

{{-- Filtros --}}
<form method="GET" action="{{ route('calificaciones.curso', $curso) }}"
      class="card p-4 mb-6 flex flex-wrap items-center gap-3">
    <input aria-label="Buscar estudiante..." type="text" name="buscar" value="{{ request('buscar') }}"
           placeholder="Buscar estudiante..." class="form-input flex-1 min-w-[200px]">
    <select name="estado" class="form-select w-auto max-w-full">
        <option value="">Todos los estudiantes</option>
        <option value="pendientes" @selected(request('estado') === 'pendientes')>Con pendientes</option>
        <option value="completos" @selected(request('estado') === 'completos')>Completos</option>
    </select>
    @if($modulos->count() > 1)
    <select name="modulo" class="form-select w-auto max-w-full">
        <option value="">Todos los módulos</option>
        @foreach($modulos as $mod)
            <option value="{{ $mod->id }}" @selected(request('modulo') == $mod->id)>{{ $mod->titulo }}</option>
        @endforeach
    </select>
    @endif
    <button type="submit" class="btn-primary">Filtrar</button>
    @if(request()->anyFilled(['buscar', 'estado', 'modulo']))
        <a href="{{ route('calificaciones.curso', $curso) }}" class="text-sm text-gray-500 hover:text-gray-700">Limpiar filtros</a>
    @endif
</form>

@if($actividades->isEmpty())
    <div class="card p-4 mb-6 text-center text-gray-500 text-sm">
        Este curso no tiene actividades calificables todavía. Los estudiantes que completen el curso
        (por ejemplo, terminando todas las lecciones) igual pueden recibir su certificado abajo.
    </div>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-500 whitespace-nowrap">Estudiante</th>
                        <th scope="col" class="px-4 py-3 text-center text-sm font-medium text-gray-500 whitespace-nowrap">Certificado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($filas as $fila)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <p class="font-medium text-gray-900">{{ $fila->estudiante->name }}</p>
                            <p class="text-xs text-gray-500">{{ $fila->estudiante->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @include('calificaciones.partials.celda-certificado', ['curso' => $curso, 'fila' => $fila])
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-6 py-10 text-center text-gray-500">
                            Ningún estudiante coincide con el filtro.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@else
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-500 whitespace-nowrap">Estudiante</th>
                    @foreach($actividades as $act)
                        <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-500 whitespace-nowrap">
                            {{ $act->tituloCompleto() }}
                            <span class="block text-xs text-gray-500 font-normal">/ {{ number_format($act->puntaje_maximo ?? 0, 0) }} pts</span>
                        </th>
                    @endforeach
                    <th scope="col" class="px-4 py-3 text-center text-sm font-medium text-gray-500 whitespace-nowrap">Promedio</th>
                    <th scope="col" class="px-4 py-3 text-center text-sm font-medium text-gray-500 whitespace-nowrap">Certificado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($filas as $fila)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <p class="font-medium text-gray-900">{{ $fila->estudiante->name }}</p>
                        <p class="text-xs text-gray-500">{{ $fila->estudiante->email }}</p>
                    </td>
                    @foreach($actividades as $i => $act)
                        @php($respuesta = $fila->celdas[$i])
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if(!$respuesta)
                                <span class="text-gray-300">—</span>
                            @elseif($respuesta->estado === 'calificada')
                                <a href="{{ route('calificaciones.show', $respuesta) }}"
                                   class="text-dyl-orange-700 hover:text-dyl-orange-800 font-semibold">
                                    {{ number_format($respuesta->calificacion, 2) }}
                                </a>
                            @elseif($respuesta->estado === 'en_revision')
                                <a href="{{ route('calificaciones.revisar', $respuesta) }}" class="badge badge-blue">Revisar</a>
                            @else
                                <a href="{{ route('calificaciones.show', $respuesta) }}" class="badge badge-yellow">Pendiente</a>
                            @endif

                            @if($act->tipo === 'cuestionario')
                                @php($info = $intentosPorCelda["{$fila->estudiante->id}-{$act->id}"] ?? ['usados' => 0, 'permitidos' => $act->intentos_permitidos, 'extra' => 0])
                                <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                                    <span>{{ $info['usados'] }}/{{ $info['permitidos'] }} intentos</span>
                                    @if($info['extra'] > 0)
                                        <span class="badge badge-green" title="Intentos extra otorgados">+{{ $info['extra'] }}</span>
                                    @endif
                                    <details class="relative inline-block">
                                        <summary class="cursor-pointer text-dyl-orange-700 hover:text-dyl-orange-800 list-none">+ intento extra</summary>
                                        <form method="POST"
                                              action="{{ route('calificaciones.intentos-extra', [$act, $fila->estudiante]) }}"
                                              class="absolute z-10 mt-1 p-2 bg-white border border-gray-200 rounded-lg shadow-lg flex items-center gap-1 whitespace-nowrap">
                                            @csrf
                                            <input type="number" name="cantidad" value="1" min="1" max="5"
                                                   class="form-input w-16 py-1 text-xs">
                                            <button type="submit"
                                                    class="btn-primary btn-sm">
                                                Otorgar
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-3 text-center font-bold text-gray-700 whitespace-nowrap">
                        {{ $fila->promedio !== null ? $fila->promedio . '%' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        @include('calificaciones.partials.celda-certificado', ['curso' => $curso, 'fila' => $fila])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $actividades->count() + 3 }}" class="px-6 py-10 text-center text-gray-500">
                        Ningún estudiante coincide con el filtro.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($filas->isNotEmpty())
            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                <tr>
                    <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">Promedio general</td>
                    @foreach($promediosPorActividad as $prom)
                        <td class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap">{{ $prom !== null ? number_format($prom, 2) : '—' }}</td>
                    @endforeach
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endif
@endsection
