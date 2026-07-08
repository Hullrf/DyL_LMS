@extends('layouts.app')
@section('title', 'Redactar mensaje - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nuevo mensaje</h1>

    <form method="POST" action="{{ route('mensajes.store') }}" class="bg-white rounded-lg shadow p-6" x-data="{ enviarATodos: true }">
        @csrf
        @if(request('padre_id'))
            <input type="hidden" name="padre_id" value="{{ request('padre_id') }}">
        @endif

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
            <select name="curso_id" id="cursoSelect"
                    onchange="if(this.value) window.location.href='{{ route('mensajes.create') }}?curso_id=' + this.value;"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                <option value="">Seleccionar curso</option>
                @foreach($cursos as $c)
                    <option value="{{ $c->id }}" {{ old('curso_id', $cursoId) == $c->id ? 'selected' : '' }}>{{ $c->titulo }}</option>
                @endforeach
            </select>
            @error('curso_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Destinatarios</label>

            @if($cursoId && $destinatarios->isNotEmpty())
                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                    <input type="checkbox" x-model="enviarATodos" class="w-4 h-4 rounded text-blue-600">
                    <span class="text-sm font-medium text-gray-700">Enviar a todos los estudiantes del curso</span>
                    <span class="text-xs text-gray-400">({{ $destinatarios->count() }} estudiantes)</span>
                </label>

                <div x-show="!enviarATodos" class="ml-2 mb-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                    @foreach($destinatarios as $d)
                        <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="destinatarios[]" value="{{ $d->id }}" class="w-4 h-4 rounded text-blue-600">
                            <div>
                                <span class="text-sm text-gray-800">{{ $d->name }}</span>
                                <span class="text-xs text-gray-400 ml-1">{{ $d->email }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <input type="hidden" name="destinatarios" value="" x-bind:disabled="enviarATodos">

                @error('destinatarios')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                @error('destinatarios.*')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            @elseif($cursoId)
                <p class="text-sm text-gray-400 bg-gray-50 rounded-lg p-3">
                    No hay estudiantes inscritos en este curso. El mensaje se enviará de todas formas.
                </p>
            @else
                <p class="text-sm text-gray-400 bg-gray-50 rounded-lg p-3">
                    Selecciona un curso primero para elegir los destinatarios.
                </p>
            @endif
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
