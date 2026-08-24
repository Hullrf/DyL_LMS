@extends('layouts.app')
@section('title', $curso->titulo . ' - Calificaciones - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('calificaciones.curso', $curso) }}@endsection
@section('content')

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $curso->titulo }}</h1>
        <p class="text-sm text-gray-500">Calificaciones</p>
    </div>
    <a href="{{ route('calificaciones.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Cursos</a>
</div>

@if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-error mb-6">{{ session('error') }}</div>
@endif

{{-- Filtros --}}
<form method="GET" action="{{ route('calificaciones.curso', $curso) }}"
      class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="buscar" value="{{ request('buscar') }}"
           placeholder="Buscar estudiante..." class="px-3 py-2 border border-gray-300 rounded-lg text-sm flex-1 min-w-[200px]">
    <select name="estado" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">Todos los estudiantes</option>
        <option value="pendientes" @selected(request('estado') === 'pendientes')>Con pendientes</option>
        <option value="completos" @selected(request('estado') === 'completos')>Completos</option>
    </select>
    @if($modulos->count() > 1)
    <select name="modulo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <option value="">Todos los módulos</option>
        @foreach($modulos as $mod)
            <option value="{{ $mod->id }}" @selected(request('modulo') == $mod->id)>{{ $mod->titulo }}</option>
        @endforeach
    </select>
    @endif
    <button type="submit" class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Filtrar</button>
    @if(request()->anyFilled(['buscar', 'estado', 'modulo']))
        <a href="{{ route('calificaciones.curso', $curso) }}" class="text-sm text-gray-500 hover:text-gray-700">Limpiar filtros</a>
    @endif
</form>

@if($actividades->isEmpty())
    <div class="bg-white rounded-xl shadow p-16 text-center text-gray-400">
        Este curso no tiene actividades calificables todavía.
    </div>
@else
<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Estudiante</th>
                    @foreach($actividades as $act)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                            {{ $act->titulo }}
                            <span class="block normal-case text-gray-400 font-normal">/ {{ number_format($act->puntaje_maximo ?? 0, 0) }} pts</span>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Promedio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($filas as $fila)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <p class="font-medium text-gray-900">{{ $fila->estudiante->name }}</p>
                        <p class="text-xs text-gray-400">{{ $fila->estudiante->email }}</p>
                    </td>
                    @foreach($fila->celdas as $respuesta)
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if(!$respuesta)
                                <span class="text-gray-300">—</span>
                            @elseif($respuesta->estado === 'calificada')
                                <a href="{{ route('calificaciones.show', $respuesta) }}"
                                   class="text-dyl-orange-600 hover:text-dyl-orange-700 font-semibold">
                                    {{ number_format($respuesta->calificacion, 2) }}
                                </a>
                            @elseif($respuesta->estado === 'en_revision')
                                <a href="{{ route('calificaciones.revisar', $respuesta) }}" class="badge badge-blue">Revisar</a>
                            @else
                                <a href="{{ route('calificaciones.show', $respuesta) }}" class="badge badge-yellow">Pendiente</a>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-3 text-center font-bold text-gray-700 whitespace-nowrap">
                        {{ $fila->promedio !== null ? $fila->promedio . '%' : '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $actividades->count() + 2 }}" class="px-6 py-10 text-center text-gray-400">
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
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endif
@endsection
