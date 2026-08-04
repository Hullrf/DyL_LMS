@extends('layouts.app')
@section('title', 'Redactar mensaje - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nuevo mensaje</h1>

    <form method="POST" action="{{ route('mensajes.store') }}" class="bg-white rounded-lg shadow p-6"
          x-data="{
            enviarATodos: true,
            buscando: '',
            sugerencias: [],
            seleccionados: [],
            cursoId: '{{ old('curso_id', $cursoId) }}',
            async buscar() {
                if (this.buscando.length < 2) { this.sugerencias = []; return; }
                if (!this.cursoId) { alert('Selecciona un curso primero'); return; }
                const res = await fetch('{{ route('mensajes.buscar') }}?curso_id=' + this.cursoId + '&q=' + encodeURIComponent(this.buscando));
                this.sugerencias = await res.json();
            },
            seleccionar(user) {
                if (!this.seleccionados.find(s => s.id === user.id)) {
                    this.seleccionados.push(user);
                }
                this.buscando = '';
                this.sugerencias = [];
            },
            remover(user) {
                this.seleccionados = this.seleccionados.filter(s => s.id !== user.id);
            }
          }">
        @csrf
        @if(request('padre_id'))
            <input type="hidden" name="padre_id" value="{{ request('padre_id') }}">
        @endif

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
            <select name="curso_id" x-model="cursoId"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                <option value="">Seleccionar curso</option>
                @foreach($cursos as $c)
                    <option value="{{ $c->id }}" {{ old('curso_id', $cursoId) == $c->id ? 'selected' : '' }}>{{ $c->titulo }}</option>
                @endforeach
            </select>
            @error('curso_id')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Destinatarios</label>

            <label class="flex items-center gap-2 mb-3 cursor-pointer">
                <input type="checkbox" x-model="enviarATodos" class="w-4 h-4 rounded text-dyl-orange-600">
                <span class="text-sm font-medium text-gray-700">Enviar a todos los estudiantes del curso</span>
            </label>

            <div x-show="!enviarATodos" class="space-y-2" x-cloak>
                {{-- Chips de seleccionados --}}
                <div class="flex flex-wrap gap-1.5 min-h-[36px] p-2 border border-gray-300 rounded-lg bg-white">
                    <template x-for="user in seleccionados" :key="user.id">
                        <span class="inline-flex items-center gap-1 bg-dyl-orange-100 text-dyl-orange-800 text-sm px-2.5 py-1 rounded-full">
                            <span x-text="user.name"></span>
                            <button type="button" @click="remover(user)" class="text-dyl-orange-500 hover:text-dyl-orange-700">&times;</button>
                            <input type="hidden" name="destinatarios[]" :value="user.id">
                        </span>
                    </template>
                    <input type="text" x-model="buscando" @input.debounce.300ms="buscar()"
                           placeholder="Escribe un nombre para buscar..."
                           class="flex-1 min-w-[140px] border-0 outline-none text-sm py-0.5"
                           @keydown.escape="sugerencias = []">
                </div>

                {{-- Dropdown de sugerencias --}}
                <div x-show="sugerencias.length > 0" @click.outside="sugerencias = []"
                     class="border border-gray-200 rounded-lg shadow-lg bg-white max-h-48 overflow-y-auto z-10">
                    <template x-for="user in sugerencias" :key="user.id">
                        <button type="button" @click="seleccionar(user)"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-dyl-orange-50 flex items-center justify-between">
                            <span>
                                <span class="font-medium text-gray-900" x-text="user.name"></span>
                                <span class="text-gray-400 ml-1.5 text-xs" x-text="user.email"></span>
                            </span>
                            <span x-show="seleccionados.find(s => s.id === user.id)" class="text-xs text-dyl-orange-600">Seleccionado</span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Hidden inputs para el caso de enviarATodos (sin destinatarios especificos) --}}
            <template x-if="enviarATodos">
                <input type="hidden" name="destinatarios" value="">
            </template>

            @error('destinatarios')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
            @error('destinatarios.*')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Asunto</label>
            <input type="text" name="asunto" value="{{ old('asunto') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            @error('asunto')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
            <textarea name="mensaje" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>{{ old('mensaje') }}</textarea>
            @error('mensaje')<p class="text-dyl-graphite-900 font-semibold text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-between">
            <a href="{{ route('mensajes.bandeja') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Enviar</button>
        </div>
    </form>
</div>
@endsection
