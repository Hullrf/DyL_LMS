@extends('layouts.app')
@section('title', 'Dashboard Admin - LMS DyL')

@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-8">Dashboard Administrador</h1>

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-6 rounded-xl shadow">
        <p class="text-gray-400 text-xs uppercase tracking-wide">Total Cursos</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['total_cursos'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow">
        <p class="text-gray-400 text-xs uppercase tracking-wide">Publicados</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['cursos_publicados'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow">
        <p class="text-gray-400 text-xs uppercase tracking-wide">Total Usuarios</p>
        <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['total_usuarios'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow">
        <p class="text-gray-400 text-xs uppercase tracking-wide">Instructores</p>
        <p class="text-3xl font-bold text-orange-500 mt-1">{{ $stats['total_instructores'] }}</p>
    </div>
</div>

{{-- Accesos rápidos --}}
<div class="flex gap-3 mb-8">
    <a href="{{ route('reportes.index') }}"
       class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium text-sm">
        Ver Reportes
    </a>
    <a href="{{ route('calificaciones.index') }}"
       class="bg-white border border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-50 text-sm">
        Calificaciones
    </a>
    <a href="{{ route('cursos.create') }}"
       class="bg-white border border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-50 text-sm">
        + Nuevo Curso
    </a>
</div>

{{-- Cursos recientes --}}
<div class="bg-white rounded-xl shadow">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
        <h2 class="text-lg font-bold text-gray-900">Cursos Recientes</h2>
        <a href="{{ route('reportes.index') }}" class="text-sm text-blue-600 hover:underline">Ver todos los reportes</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cursos_recientes as $curso)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900 text-sm">{{ $curso->titulo }}</td>
                        <td class="px-6 py-4 text-gray-500 text-sm">{{ $curso->creador->name }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                @if($curso->estado === 'publicado') bg-green-100 text-green-700
                                @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-700
                                @else bg-gray-100 text-gray-500 @endif">
                                {{ ucfirst($curso->estado) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 flex gap-3">
                            <a href="{{ route('reportes.curso', $curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">Reporte</a>
                            <a href="{{ route('cursos.edit', $curso) }}" class="text-gray-600 hover:text-gray-900 text-sm">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No hay cursos aún</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
