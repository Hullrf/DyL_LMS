@extends('layouts.app')
@section('title', 'Crear Curso - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.create') }}@endsection
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Crear Nuevo Curso</h1>
    <form action="{{ route('cursos.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-8">
        @csrf
        <div class="mb-6">
            <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título del Curso</label>
            <input type="text" name="titulo" id="titulo" value="{{ old('titulo') }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            @error('titulo')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
            <textarea name="descripcion" id="descripcion" rows="4"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>{{ old('descripcion') }}</textarea>
            @error('descripcion')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="duracion_horas" class="block text-sm font-medium text-gray-700 mb-2">Duración (horas)</label>
            <input type="number" name="duracion_horas" id="duracion_horas" value="{{ old('duracion_horas', 1) }}" min="1" max="500"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            @error('duracion_horas')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="imagen_portada" class="block text-sm font-medium text-gray-700 mb-2">Imagen de portada (opcional)</label>
            <input type="file" name="imagen_portada" id="imagen_portada" accept="image/*"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            @error('imagen_portada')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-between">
            <a href="{{ route('cursos.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Crear Curso</button>
        </div>
    </form>
</div>
@endsection
