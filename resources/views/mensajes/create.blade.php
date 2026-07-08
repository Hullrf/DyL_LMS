@extends('layouts.app')
@section('title', 'Redactar mensaje - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nuevo mensaje</h1>

    <form method="POST" action="{{ route('mensajes.store') }}" class="bg-white rounded-lg shadow p-6">
        @csrf
        @if(request('padre_id'))
            <input type="hidden" name="padre_id" value="{{ request('padre_id') }}">
        @endif

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
            <select name="curso_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                <option value="">Seleccionar curso</option>
                @foreach($cursos as $c)
                    <option value="{{ $c->id }}" {{ old('curso_id', $cursoId) == $c->id ? 'selected' : '' }}>{{ $c->titulo }}</option>
                @endforeach
            </select>
            @error('curso_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Para</label>
            <select name="destinatario_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                <option value="">Seleccionar destinatario</option>
                @foreach($destinatarios as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
            @error('destinatario_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Asunto</label>
            <input type="text" name="asunto" value="{{ old('asunto') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            @error('asunto')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
            <textarea name="mensaje" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>{{ old('mensaje') }}</textarea>
            @error('mensaje')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-between">
            <a href="{{ route('mensajes.bandeja') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Enviar</button>
        </div>
    </form>
</div>
@endsection
