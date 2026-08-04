@extends('layouts.app')
@section('title', 'Nuevo anuncio - ' . $curso->titulo . ' - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nuevo anuncio en {{ $curso->titulo }}</h1>
    <form method="POST" action="{{ route('anuncios.store', $curso) }}" class="bg-white rounded-lg shadow p-6">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
            <input type="text" name="titulo" value="{{ old('titulo') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Contenido</label>
            <textarea name="contenido" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>{{ old('contenido') }}</textarea>
            @error('contenido')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-between">
            <a href="{{ route('anuncios.index', $curso) }}" class="px-4 py-2 text-sm text-gray-500">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Publicar anuncio</button>
        </div>
    </form>
</div>
@endsection
