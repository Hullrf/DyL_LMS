@extends('layouts.app')
@section('title', 'Nuevo anuncio - ' . $curso->titulo . ' - D&L Quality Campus')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="font-display text-3xl font-semibold text-gray-900 mb-6">Nuevo anuncio en {{ $curso->titulo }}</h1>
    <form method="POST" action="{{ route('anuncios.store', $curso) }}" class="card p-6">
        @csrf
        <div class="mb-4">
            <label class="form-label">Título</label>
            <input type="text" name="titulo" value="{{ old('titulo') }}" class="form-input" required>
            @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label">Contenido</label>
            <textarea name="contenido" rows="5" class="form-input" required>{{ old('contenido') }}</textarea>
            @error('contenido')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-between">
            <a href="{{ route('anuncios.index', $curso) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-primary">Publicar anuncio</button>
        </div>
    </form>
</div>
@endsection
