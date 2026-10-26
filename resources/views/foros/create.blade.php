@extends('layouts.app')
@section('title', 'Crear foro - ' . $curso->titulo . ' - D&L Quality Campus')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="font-display text-3xl font-semibold text-gray-900 mb-6">Nuevo foro en {{ $curso->titulo }}</h1>
    <form method="POST" action="{{ route('foros.store', $curso) }}" class="card p-6">
        @csrf
        <div class="mb-4">
            <label class="form-label">Título</label>
            <input type="text" name="titulo" value="{{ old('titulo') }}" class="form-input" required>
            @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label">Descripción</label>
            <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
            <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion') }}">
        </div>
        <div class="mb-4">
            <label class="form-label">Lección asociada (opcional)</label>
            <select name="leccion_id" class="form-select">
                <option value="">Sin lección específica</option>
                @foreach($lecciones as $lec)
                    <option value="{{ $lec->id }}" {{ old('leccion_id') == $lec->id ? 'selected' : '' }}>{{ $lec->titulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex justify-between">
            <a href="{{ route('foros.index', $curso) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-primary">Crear foro</button>
        </div>
    </form>
</div>
@endsection

@include('components.quill-init')
