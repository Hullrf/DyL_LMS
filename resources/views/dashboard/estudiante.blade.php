@extends('layouts.app')
@section('title', 'Mi Aprendizaje - LMS DyL')

@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-8">Mi Aprendizaje</h1>

@if(session('success'))
    <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-4 mb-6 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Cursos Activos</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['cursos_activos'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Completados</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completados'] }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow col-span-2 md:col-span-1">
        <p class="text-gray-500 text-xs uppercase tracking-wide">Progreso General</p>
        <p class="text-3xl font-bold text-purple-600 mt-1">{{ $stats['progreso_general'] }}%</p>
    </div>
</div>

{{-- Accesos rápidos --}}
<div class="flex gap-3 mb-6">
    <a href="{{ route('calificaciones.mis') }}"
       class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium">
        Mis Calificaciones
    </a>
    <a href="{{ route('certificados.mis') }}"
       class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium">
        Mis Certificados
    </a>
    <a href="{{ route('cursos.index') }}"
       class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm font-medium">
        Ver Catálogo
    </a>
</div>

<h2 class="text-xl font-bold text-gray-900 mb-4">Mis Cursos</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($cursos_inscritos as $curso)
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="w-full h-32 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                <span class="text-5xl">&#128218;</span>
            </div>
            <div class="p-5">
                <h3 class="font-bold text-gray-900 mb-1">{{ $curso->titulo }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ $curso->duracion_horas }} horas</p>
                <a href="{{ route('cursos.show', $curso) }}"
                   class="block w-full bg-blue-600 text-white py-2 rounded text-center hover:bg-blue-700 text-sm font-medium">
                    Continuar
                </a>
            </div>
        </div>
    @empty
        <div class="col-span-3 text-center py-12 text-gray-500">
            No estás inscrito en ningún curso.
            <a href="{{ route('cursos.index') }}" class="text-blue-600 hover:underline ml-1">Ver cursos disponibles</a>
        </div>
    @endforelse
</div>
@endsection
