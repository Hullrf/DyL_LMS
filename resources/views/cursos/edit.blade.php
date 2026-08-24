@extends('layouts.app')
@section('title', 'Editar: ' . $curso->titulo . ' - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.edit', $curso) }}@endsection
@section('content')
<div class="flex items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Curso</h1>
    <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">Ver curso &rarr;</a>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Datos del curso --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Datos del Curso</h2>
            <form action="{{ route('cursos.update', $curso) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                    <input type="text" name="titulo" value="{{ old('titulo', $curso->titulo) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
                    <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion', $curso->descripcion) }}">
                    @error('descripcion')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duración (horas)</label>
                    <input type="number" name="duracion_horas" value="{{ old('duracion_horas', $curso->duracion_horas) }}" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="borrador" @selected(old('estado', $curso->estado) === 'borrador')>Borrador</option>
                        <option value="publicado" @selected(old('estado', $curso->estado) === 'publicado')>Publicado</option>
                        <option value="archivado" @selected(old('estado', $curso->estado) === 'archivado')>Archivado</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id', $curso->categoria_id)" />
                </div>
                <div class="mb-4" x-data="{ errorPortada: '' }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Imagen de portada</label>
                    @if($curso->imagen_portada)
                        <img src="{{ asset('storage/' . $curso->imagen_portada) }}" class="w-full h-24 object-cover rounded mb-2">
                    @endif
                    <input type="file" name="imagen_portada" accept="image/*"
                           class="w-full text-sm text-gray-600"
                           x-on:change="
                               errorPortada = '';
                               const f = $event.target.files[0];
                               if (f && f.size > 5 * 1024 * 1024) {
                                   errorPortada = 'La imagen supera el límite de 5 MB.';
                                   $event.target.value = '';
                               }
                           ">
                    <p class="text-xs text-gray-400 mt-1">JPG o PNG — máx. 5 MB</p>
                    <p x-show="errorPortada" x-text="errorPortada" class="form-error"></p>
                </div>
                <button type="submit" class="w-full bg-dyl-orange-600 text-white py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                    Guardar Cambios
                </button>
            </form>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <form action="{{ route('cursos.destroy', $curso) }}" method="POST"
                      onsubmit="return confirm('Eliminar este curso y todo su contenido?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full bg-dyl-graphite-900 text-white py-2 rounded-lg hover:bg-dyl-graphite-800 text-sm">
                        Eliminar Curso
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Módulos y lecciones --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Agregar Módulo</h2>
            <form action="{{ route('modulos.store', $curso) }}" method="POST" class="flex gap-3">
                @csrf
                <input type="text" name="titulo" placeholder="Nombre del módulo"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                <button type="submit" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 text-sm font-medium">
                    + Módulo
                </button>
            </form>
        </div>

        @forelse($modulos as $modulo)
        <div class="bg-white rounded-lg shadow mb-4">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-3">
                    <span class="font-medium text-xs text-gray-400">{{ $modulo->orden + 1 }}.</span>
                    <h3 class="font-semibold text-gray-900">{{ $modulo->titulo }}</h3>
                    <span class="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded">{{ $modulo->lecciones->count() }} lecciones</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('modulos.edit', $modulo) }}"
                       class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300">Editar</a>
                    <form action="{{ route('modulos.destroy', $modulo) }}" method="POST" class="inline"
                          onsubmit="return confirm('Eliminar este modulo y todas sus lecciones?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs bg-dyl-graphite-900 text-white px-3 py-1 rounded hover:bg-dyl-graphite-800">Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($modulo->lecciones as $leccion)
                {{-- Fila de lección --}}
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-800">{{ $leccion->titulo }}</span>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ $leccion->tipo }}</span>
                        <span class="text-xs text-gray-400">{{ $leccion->duracion_minutos }} min</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('lecciones.edit', $leccion) }}"
                           class="text-xs bg-dyl-orange-50 text-dyl-orange-600 px-3 py-1 rounded hover:bg-dyl-orange-100">Editar</a>
                        <a href="{{ route('actividades.create', $leccion) }}"
                           class="text-xs bg-dyl-orange-50 text-dyl-orange-600 px-3 py-1 rounded hover:bg-dyl-orange-100">+ Actividad</a>
                        <form action="{{ route('lecciones.destroy', $leccion) }}" method="POST" class="inline"
                              onsubmit="return confirm('Eliminar esta leccion?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs bg-dyl-graphite-900 text-white px-3 py-1 rounded hover:bg-dyl-graphite-800">Eliminar</button>
                        </form>
                    </div>
                </div>

                {{-- Sub-filas de actividades --}}
                <div class="actividades-lista" data-leccion-id="{{ $leccion->id }}">
                @foreach($leccion->actividades as $actividad)
                <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-dyl-graphite-100 border-t border-dyl-graphite-200/60 hover:bg-dyl-graphite-200"
                     data-actividad-id="{{ $actividad->id }}">
                    <div class="flex items-center gap-2">
                        <svg class="drag-handle w-3.5 h-3.5 text-dyl-graphite-400 shrink-0 cursor-grab active:cursor-grabbing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-700">{{ $actividad->titulo }}</span>
                        <span class="text-xs bg-dyl-graphite-100 text-dyl-graphite-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
                        <span class="text-xs text-gray-400">{{ $actividad->puntaje_maximo }} pts</span>
                    </div>
                    <a href="{{ route('actividades.edit', $actividad) }}"
                       class="text-xs bg-dyl-orange-100 text-dyl-orange-700 px-3 py-1 rounded hover:bg-dyl-orange-200">
                        Editar
                    </a>
                </div>
                @endforeach
                </div>
                @endforeach
                <div class="px-6 py-3 bg-gray-50 rounded-b-lg">
                    <div class="flex gap-4">
                        <a href="{{ route('lecciones.create', $modulo) }}"
                           class="text-sm text-dyl-orange-600 hover:text-dyl-orange-700 font-medium">
                            + Agregar lección
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No hay módulos aún. Agrega el primero arriba.
        </div>
        @endforelse
    </div>
</div>
@endsection

@include('components.quill-init')

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function() {
    const csrfMeta = document.querySelector('meta[name=csrf-token]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';
    const urlMover = @json(route('actividades.mover', $curso));

    function idsDe(lista) {
        return Array.from(lista.children).map(el => parseInt(el.dataset.actividadId, 10));
    }

    async function guardarMovimiento(destino, origen) {
        const body = {
            leccion_destino_id: parseInt(destino.dataset.leccionId, 10),
            orden_destino: idsDe(destino),
        };
        if (origen && origen !== destino) {
            body.leccion_origen_id = parseInt(origen.dataset.leccionId, 10);
            body.orden_origen = idsDe(origen);
        }

        try {
            const res = await fetch(urlMover, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (!res.ok) {
                alert('No se pudo guardar el nuevo orden. Se recargará la página.');
                location.reload();
            }
        } catch (e) {
            alert('No se pudo conectar con el servidor. Se recargará la página.');
            location.reload();
        }
    }

    document.querySelectorAll('.actividades-lista').forEach(function(lista) {
        new Sortable(lista, {
            group: 'actividades',
            handle: '.drag-handle',
            animation: 150,
            onEnd: function(evt) {
                guardarMovimiento(evt.to, evt.from);
            },
        });
    });
})();
</script>
@endpush