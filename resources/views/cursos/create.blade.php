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
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent" required>
            @error('titulo')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
            <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
            <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion') }}">
            @error('descripcion')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="duracion_horas" class="block text-sm font-medium text-gray-700 mb-2">Duración (horas)</label>
            <input type="number" name="duracion_horas" id="duracion_horas" value="{{ old('duracion_horas', 1) }}" min="1" max="500"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent" required>
            @error('duracion_horas')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
            <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id')" />
        </div>
        <div class="mb-6">
            <label for="tipo_certificado" class="block text-sm font-medium text-gray-700 mb-2">Tipo de certificado</label>
            <select name="tipo_certificado" id="tipo_certificado"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent">
                <option value="diploma" {{ old('tipo_certificado', 'diploma') === 'diploma' ? 'selected' : '' }}>Diploma (horizontal)</option>
                <option value="diplomado" {{ old('tipo_certificado') === 'diplomado' ? 'selected' : '' }}>Diplomado — carta formal (vertical)</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Define qué diseño de certificado recibe el estudiante al completar el curso.</p>
        </div>
        <div class="mb-6">
            <label for="nota_aprobatoria" class="block text-sm font-medium text-gray-700 mb-2">Nota mínima para aprobar (%)</label>
            <input type="number" name="nota_aprobatoria" id="nota_aprobatoria" min="0" max="100"
                   value="{{ old('nota_aprobatoria', 80) }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-dyl-orange-600 focus:border-transparent">
            <p class="text-xs text-gray-400 mt-1">Guía para el instructor al aprobar certificados — no bloquea la aprobación, solo advierte si la nota del estudiante queda por debajo.</p>
        </div>
        <div class="mb-6" x-data="{ errorPortada: '' }">
            <label for="imagen_portada" class="block text-sm font-medium text-gray-700 mb-2">Imagen de portada (opcional)</label>
            <input type="file" name="imagen_portada" id="imagen_portada" accept="image/*"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                   x-on:change="
                       errorPortada = '';
                       const f = $event.target.files[0];
                       if (f && f.size > 5 * 1024 * 1024) {
                           errorPortada = 'La imagen supera el límite de 5 MB.';
                           $event.target.value = '';
                       }
                   ">
            <p class="text-xs text-gray-400 mt-1">JPG o PNG — máx. 5 MB</p>
            <p x-show="errorPortada" x-text="errorPortada" class="form-error"></p>
            @error('imagen_portada')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-between">
            <a href="{{ route('cursos.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg hover:bg-dyl-orange-700">Crear Curso</button>
        </div>
    </form>
</div>
@endsection

@include('components.quill-init')
