@extends('layouts.app')
@section('title', 'Editar Módulo - D&L Quality Campus')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm">&larr; Volver al curso</a>
    </div>
    <div class="card p-8">
        <h1 class="font-display text-3xl font-semibold text-gray-900 mb-6">Editar módulo</h1>
        <form action="{{ route('modulos.update', $modulo) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" value="{{ old('titulo', $modulo->titulo) }}"
                       class="form-input" required>
                @error('titulo')<p class="form-error text-sm">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">Descripción</label>
                <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
                <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion', $modulo->descripcion) }}">
            </div>
            <div class="mb-6">
                <label class="form-label">Duración estimada (horas)</label>
                <input type="number" name="duracion_horas" value="{{ old('duracion_horas', $modulo->duracion_horas) }}" min="0"
                       class="form-input">
            </div>
            <div class="flex gap-4">
                <button type="submit" class="btn-primary">Guardar</button>
                <a href="{{ route('cursos.edit', $modulo->curso) }}" class="btn-ghost">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('components.quill-init')
