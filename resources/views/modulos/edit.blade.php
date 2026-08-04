@extends('layouts.app')
@section('title', 'Editar Módulo - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
    </div>
    <div class="bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Editar Módulo</h1>
        <form action="{{ route('modulos.update', $modulo) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                <input type="text" name="titulo" value="{{ old('titulo', $modulo->titulo) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600" required>
                @error('titulo')<p class="form-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
                <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion', $modulo->descripcion) }}">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Duración estimada (horas)</label>
                <input type="number" name="duracion_horas" value="{{ old('duracion_horas', $modulo->duracion_horas) }}" min="0"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600">
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-dyl-orange-600 text-white px-6 py-2 rounded-lg hover:bg-dyl-orange-700">Guardar</button>
                <a href="{{ route('cursos.edit', $modulo->curso) }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('components.quill-init')
