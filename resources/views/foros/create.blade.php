@extends('layouts.app')
@section('title', 'Crear foro - ' . $curso->titulo . ' - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nuevo foro en {{ $curso->titulo }}</h1>
    <form method="POST" action="{{ route('foros.store', $curso) }}" class="bg-white rounded-lg shadow p-6">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
            <input type="text" name="titulo" value="{{ old('titulo') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            @error('titulo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
            <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion') }}">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Lección asociada (opcional)</label>
            <select name="leccion_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Sin lección específica</option>
                @foreach($lecciones as $lec)
                    <option value="{{ $lec->id }}" {{ old('leccion_id') == $lec->id ? 'selected' : '' }}>{{ $lec->titulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex justify-between">
            <a href="{{ route('foros.index', $curso) }}" class="px-4 py-2 text-sm text-gray-500">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Crear foro</button>
        </div>
    </form>
</div>
@endsection

@include('components.quill-init')
