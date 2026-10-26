@extends('layouts.app')
@section('title', 'Editar: ' . $curso->titulo . ' - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.edit', $curso) }}@endsection
@section('content')
@include('cursos.partials.aviso-ponderacion')
@if(count($faltan = $curso->fichaFaltantes()))
    <div class="alert alert-warning mb-5" role="status">
        Ficha académica incompleta: falta {{ implode(', ', $faltan) }}.
        <a href="{{ route('cursos.ficha.edit', $curso) }}" class="font-medium underline">Completar ficha</a>
    </div>
@else
    <p class="mb-5 text-sm"><a href="{{ route('cursos.ficha.edit', $curso) }}" class="text-dyl-orange-700 hover:underline">Ficha académica</a></p>
@endif
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="font-display text-3xl font-semibold text-gray-900">Editar curso</h1>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <a href="{{ route('cursos.ponderaciones.edit', $curso) }}"
           class="btn-outline btn-sm">
            Ponderaciones
        </a>
        <a href="{{ route('cursos.competencias.index', $curso) }}"
           class="btn-outline btn-sm">
            Competencias
        </a>
        <a href="{{ route('cursos.exportar', $curso) }}"
           title="Descarga el curso (estructura, configuración y archivos) para respaldarlo o cargarlo en otra instalación"
           class="btn-outline btn-sm">
            Exportar curso
        </a>
        <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm">Ver curso &rarr;</a>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Datos del curso --}}
    <div class="lg:col-span-1">
        <div class="card p-6">
            <h2 class="font-display text-2xl font-semibold text-gray-900 mb-4">Datos del curso</h2>
            <form action="{{ route('cursos.update', $curso) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" value="{{ old('titulo', $curso->titulo) }}"
                           class="form-input" required>
                    @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4 grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" value="{{ old('codigo', $curso->codigo) }}" maxlength="50"
                               class="form-input">
                        @error('codigo')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Grupo</label>
                        <input type="text" name="grupo" value="{{ old('grupo', $curso->grupo) }}" maxlength="50"
                               class="form-input">
                        @error('grupo')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <p class="col-span-2 text-xs text-gray-500">Opcional. Se muestra bajo el título del curso.</p>
                </div>
                <div class="mb-4">
                    <label class="form-label">Descripción</label>
                    <div id="quill-descripcion" class="h-48 border border-gray-300 rounded-lg"></div>
                    <input type="hidden" name="descripcion" id="descripcion" value="{{ old('descripcion', $curso->descripcion) }}">
                    @error('descripcion')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label">Duración (horas)</label>
                    <input type="number" name="duracion_horas" value="{{ old('duracion_horas', $curso->duracion_horas) }}" min="1"
                           class="form-input" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="borrador" @selected(old('estado', $curso->estado) === 'borrador')>Borrador</option>
                        <option value="publicado" @selected(old('estado', $curso->estado) === 'publicado')>Publicado</option>
                        <option value="archivado" @selected(old('estado', $curso->estado) === 'archivado')>Archivado</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Categoría</label>
                    <x-categoria-selector :categorias="$categorias" :selected-id="old('categoria_id', $curso->categoria_id)" />
                </div>
                <div class="mb-4">
                    <label class="form-label">Tipo de certificado</label>
                    <select name="tipo_certificado" class="form-select">
                        <option value="diploma" @selected(old('tipo_certificado', $curso->tipo_certificado) === 'diploma')>Diploma (horizontal)</option>
                        <option value="diplomado" @selected(old('tipo_certificado', $curso->tipo_certificado) === 'diplomado')>Diplomado — carta formal (vertical)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Nota mínima para aprobar (%)</label>
                    <input type="number" name="nota_aprobatoria" min="0" max="100"
                           value="{{ old('nota_aprobatoria', $curso->nota_aprobatoria) }}"
                           class="form-input">
                </div>
                <div class="mb-4" x-data="{ errorPortada: '' }">
                    <label class="form-label">Imagen de portada</label>
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
                    <p class="text-xs text-gray-500 mt-1">JPG o PNG — máx. 5 MB</p>
                    <p x-show="errorPortada" x-text="errorPortada" class="form-error"></p>
                </div>
                <button type="submit" class="btn-primary w-full">
                    Guardar Cambios
                </button>
            </form>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <form action="{{ route('cursos.destroy', $curso) }}" method="POST"
                      onsubmit="return confirm('Eliminar este curso y todo su contenido?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger w-full">
                        Eliminar Curso
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Módulos y lecciones --}}
    <div class="lg:col-span-2">
        <div class="card p-6 mb-4">
            <h2 class="font-display text-2xl font-semibold text-gray-900 mb-4">Agregar módulo</h2>
            <form action="{{ route('modulos.store', $curso) }}" method="POST" class="flex gap-3">
                @csrf
                <input aria-label="Nombre del módulo" type="text" name="titulo" placeholder="Nombre del módulo"
                       class="form-input flex-1" required>
                <button type="submit" class="btn-primary">
                    + Módulo
                </button>
            </form>
        </div>

        @forelse($modulos as $modulo)
        <div class="card mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-medium text-xs text-gray-500">{{ $modulo->orden + 1 }}.</span>
                    <h3 class="font-display text-lg font-semibold text-gray-900">{{ $modulo->titulo }}</h3>
                    <span class="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded">{{ $modulo->lecciones->count() }} {{ $modulo->lecciones->count() === 1 ? 'lección' : 'lecciones' }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('modulos.edit', $modulo) }}"
                       class="btn-ghost btn-sm">Editar</a>
                    <form action="{{ route('modulos.destroy', $modulo) }}" method="POST" class="inline"
                          onsubmit="return confirm('Eliminar este modulo y todas sus lecciones?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($modulo->lecciones as $leccion)
                {{-- Fila de lección --}}
                <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 hover:bg-gray-50">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-gray-800">{{ $leccion->titulo }}</span>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ $leccion->tipo }}</span>
                        <span class="text-xs text-gray-500">{{ $leccion->duracion_minutos }} min</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('lecciones.edit', $leccion) }}"
                           class="btn-ghost btn-sm text-dyl-orange-700">Editar</a>
                        <a href="{{ route('actividades.create', $leccion) }}"
                           class="btn-ghost btn-sm text-dyl-orange-700">+ Actividad</a>
                        <form action="{{ route('lecciones.destroy', $leccion) }}" method="POST" class="inline"
                              onsubmit="return confirm('Eliminar esta leccion?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </div>

                {{-- Sub-filas de actividades --}}
                <div class="actividades-lista" data-leccion-id="{{ $leccion->id }}">
                @foreach($leccion->actividades as $actividad)
                <div class="flex items-center justify-between pl-12 pr-6 py-2 bg-dyl-graphite-100 border-t border-dyl-graphite-200/60 hover:bg-dyl-graphite-200"
                     data-actividad-id="{{ $actividad->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <svg class="drag-handle w-3.5 h-3.5 text-dyl-graphite-400 shrink-0 cursor-grab active:cursor-grabbing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-sm text-gray-700">{{ $actividad->tituloCompleto() }}</span>
                        <span class="text-xs bg-dyl-graphite-100 text-dyl-graphite-600 px-2 py-0.5 rounded">{{ ucfirst($actividad->tipo) }}</span>
                        <span class="text-xs text-gray-500">{{ $actividad->puntaje_maximo }} pts</span>
                    </div>
                    <a href="{{ route('actividades.edit', $actividad) }}"
                       class="btn-ghost btn-sm text-dyl-orange-700">
                        Editar
                    </a>
                </div>
                @endforeach
                </div>
                @endforeach
                <div class="px-6 py-3 bg-gray-50 rounded-b-lg">
                    <div class="flex gap-4">
                        <a href="{{ route('lecciones.create', $modulo) }}"
                           class="text-sm text-dyl-orange-700 hover:text-dyl-orange-800 font-medium">
                            + Agregar lección
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="card p-8 text-center text-gray-500">
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