@extends('layouts.app')
@section('title', 'Crear curso - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.create') }}@endsection
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="font-display text-4xl font-semibold text-gray-900 mb-8">Crear nuevo curso</h1>
    <form action="{{ route('cursos.store') }}" method="POST" enctype="multipart/form-data" class="card p-8">
        @csrf
        <div class="mb-6">
            <label for="titulo" class="form-label">Título del Curso</label>
            <input type="text" name="titulo" id="titulo" value="{{ old('titulo') }}"
                   class="form-input" required>
            @error('titulo')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="codigo" class="form-label">Código</label>
                <input type="text" name="codigo" id="codigo" value="{{ old('codigo') }}" maxlength="50"
                       class="form-input">
                @error('codigo')<p class="form-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="grupo" class="form-label">Grupo</label>
                <input type="text" name="grupo" id="grupo" value="{{ old('grupo') }}" maxlength="50"
                       class="form-input">
                @error('grupo')<p class="form-error text-sm">{{ $message }}</p>@enderror
            </div>
            <p class="sm:col-span-2 text-xs text-gray-500 -mt-2">Opcional. Se muestra bajo el título del curso.</p>
        </div>
        <div class="mb-6">
            <label class="form-label">Descripción</label>
            <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
            <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion') }}">
            @error('descripcion')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="duracion_horas" class="form-label">Duración (horas)</label>
            <input type="number" name="duracion_horas" id="duracion_horas" value="{{ old('duracion_horas', 1) }}" min="1" max="500"
                   class="form-input" required>
            @error('duracion_horas')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label for="categoria_id" class="form-label">Categoría</label>
            <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id')" />
        </div>
        <div class="mb-6">
            <label for="tipo_certificado" class="form-label">Tipo de certificado</label>
            <select name="tipo_certificado" id="tipo_certificado"
                    class="form-select">
                <option value="diploma" {{ old('tipo_certificado', 'diploma') === 'diploma' ? 'selected' : '' }}>Diploma (horizontal)</option>
                <option value="diplomado" {{ old('tipo_certificado') === 'diplomado' ? 'selected' : '' }}>Diplomado — carta formal (vertical)</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Define qué diseño de certificado recibe el estudiante al completar el curso.</p>
        </div>
        <div class="mb-6">
            <label for="nota_aprobatoria" class="form-label">Nota mínima para aprobar (%)</label>
            <input type="number" name="nota_aprobatoria" id="nota_aprobatoria" min="0" max="100"
                   value="{{ old('nota_aprobatoria', 80) }}"
                   class="form-input">
            <p class="text-xs text-gray-500 mt-1">Guía para el instructor al aprobar certificados — no bloquea la aprobación, solo advierte si la nota del estudiante queda por debajo.</p>
        </div>
        <div class="mb-6" x-data="{ errorPortada: '' }">
            <label for="imagen_portada" class="form-label">Imagen de portada (opcional)</label>
            <input type="file" name="imagen_portada" id="imagen_portada" accept="image/*"
                   class="form-input"
                   x-on:change="
                       errorPortada = '';
                       const f = $event.target.files[0];
                       if (f && f.size > 5 * 1024 * 1024) {
                           errorPortada = 'La imagen supera el límite de 5 MB.';
                           $event.target.value = '';
                       }
                   ">
            <p class="text-xs text-gray-500 mt-1">JPG o PNG — máx. 5 MB</p>
            <p x-show="errorPortada" x-text="errorPortada" class="form-error"></p>
            @error('imagen_portada')<p class="form-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-between">
            <a href="{{ route('cursos.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-primary">Crear curso</button>
        </div>
    </form>
</div>
@endsection

@include('components.quill-init')
