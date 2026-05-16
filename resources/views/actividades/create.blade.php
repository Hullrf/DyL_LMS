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

        <form action="{{ route('actividades.store', $leccion) }}" method="POST" x-data="{ tipo: 'cuestionario' }">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de actividad</label>
                <select name="tipo" x-model="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <optgroup label="Con calificación">
                        <option value="cuestionario">Cuestionario (calificación automática)</option>
                        <option value="ensayo">Ensayo (calificación manual)</option>
                        <option value="tarea">Tarea (entrega de archivo, rúbrica disponible)</option>
                        <option value="practica">Práctica (calificación manual)</option>
                    </optgroup>
                    <optgroup label="Sin calificación">
                        <option value="ejercicio">Ejercicio (consulta, sin nota)</option>
                        <option value="lectura">Lectura / Recurso (consulta, sin nota)</option>
                        <option value="encuesta">Encuesta / Sondeo (sin nota)</option>
                        <option value="reflexion">Reflexión (consulta, sin nota)</option>
                    </optgroup>
                </select>
                <p x-show="tipo === 'tarea'" x-cloak
                   class="mt-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Las actividades de tipo <strong>Tarea</strong> permiten configurar una rúbrica de evaluación por criterios (0–5.0). Podrás crearla en la página de edición después de guardar.
                </p>
                <p x-show="['ejercicio','lectura','encuesta','reflexion'].includes(tipo)" x-cloak
                   class="mt-2 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Esta actividad es de <strong>consulta</strong>: solo muestra descripción y recursos. Los estudiantes no envían respuestas ni reciben nota.
                </p>
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
            <div class="grid grid-cols-2 gap-4 mb-4"
                 x-show="!['ejercicio','lectura','encuesta','reflexion'].includes(tipo)"
                 x-cloak>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Puntaje máximo</label>
                    <input type="number" name="puntaje_maximo" value="{{ old('puntaje_maximo', 5.00) }}"
                           min="0.01" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
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