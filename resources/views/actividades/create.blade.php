@extends('layouts.app')
@section('title', 'Nueva Actividad - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('cursos.edit', $leccion->modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
    </div>
    <div class="bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Nueva Actividad</h1>
        <p class="text-gray-500 text-sm mb-6">Lección: <strong>{{ $leccion->titulo }}</strong></p>

        <form action="{{ route('actividades.store', $leccion) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de actividad</label>
                <select name="tipo" id="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="cuestionario">Cuestionario (calificación automática)</option>
                    <option value="ensayo">Ensayo (calificación manual)</option>
                    <option value="tarea">Tarea (calificación manual)</option>
                    <option value="practica">Práctica (calificación manual)</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                <input type="text" name="titulo" value="{{ old('titulo') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                @error('titulo')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Descripción / Instrucciones</label>
                <textarea name="descripcion" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('descripcion') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Puntaje máximo</label>
                    <input type="number" name="puntaje_maximo" value="{{ old('puntaje_maximo', 100) }}" min="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tiempo límite (minutos)</label>
                    <input type="number" name="duracion_minutos" value="{{ old('duracion_minutos') }}" min="1" placeholder="Sin límite"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="es_obligatoria" value="1" checked class="rounded">
                    <span class="text-sm font-medium text-gray-700">Actividad obligatoria</span>
                </label>
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Crear y configurar
                </button>
                <a href="{{ route('cursos.edit', $leccion->modulo->curso) }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection