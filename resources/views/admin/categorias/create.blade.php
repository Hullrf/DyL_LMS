@extends('layouts.app')
@section('title', 'Crear Categoría — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.categorias.create') }}@endsection
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.categorias.index') }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">← Categorías</a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-900">Crear Categoría</h1>
    </div>

    <form method="POST" action="{{ route('admin.categorias.store') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 space-y-5">
        @csrf

        <div>
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" class="form-input" required autofocus>
            @error('nombre')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Color</label>
            <div class="flex items-center gap-3">
                <input type="color" name="color" value="{{ old('color', '#4F46E5') }}"
                       class="h-10 w-14 rounded border border-gray-300 cursor-pointer">
                <span class="text-xs text-gray-400">Se usa como acento en el catálogo de cursos.</span>
            </div>
            @error('color')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Crear Categoría</button>
            <a href="{{ route('admin.categorias.index') }}" class="btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
