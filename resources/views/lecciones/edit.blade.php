@extends('layouts.app')
@section('title', 'Editar Lección - LMS DyL')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('cursos.edit', $modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
    </div>
    <div class="bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Editar Lección</h1>
        <p class="text-gray-500 text-sm mb-6">Módulo: <strong>{{ $modulo->titulo }}</strong></p>

        <form id="form-leccion" action="{{ route('lecciones.update', $leccion) }}" method="POST"
              x-data="{ tipo: '{{ old('tipo', $leccion->tipo) }}' }">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Título de la lección</label>
                    <input type="text" name="titulo" value="{{ old('titulo', $leccion->titulo) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                    @error('titulo')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select name="tipo" x-model="tipo"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="texto">Texto</option>
                        <option value="video">Video</option>
                        <option value="mixto">Mixto (texto + video)</option>
                    </select>
                </div>
            </div>

            {{-- URL de video (visible cuando tipo = video o mixto) --}}
            <div class="mb-6" x-show="tipo === 'video' || tipo === 'mixto'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    URL del video
                    <span class="text-gray-400 font-normal">(YouTube, Vimeo o MP4 directo)</span>
                </label>
                <input type="url" name="video_url" value="{{ old('video_url', $leccion->video_url) }}"
                       placeholder="https://www.youtube.com/watch?v=..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                @error('video_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Duración estimada (minutos)</label>
                <input type="number" name="duracion_minutos" value="{{ old('duracion_minutos', $leccion->duracion_minutos) }}" min="1"
                       class="w-48 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
            </div>

            {{-- Toggle: permitir descarga de adjuntos (default para actividades de esta lección) --}}
            <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Permitir descarga de archivos adjuntos</p>
                        <p class="text-xs text-gray-500 mt-0.5">Valor por defecto para las actividades de esta lección. Puede sobrescribirse en cada actividad.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="hidden" name="permitir_descarga_adjuntos" value="0">
                        <input type="checkbox" name="permitir_descarga_adjuntos" value="1" class="sr-only peer"
                               {{ old('permitir_descarga_adjuntos', $leccion->permitir_descarga_adjuntos ?? true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-300 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            <div class="mb-6" x-show="tipo !== 'video'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contenido</label>
                <div id="quill-editor" class="h-64 border border-gray-300 rounded-lg"></div>
                <input type="hidden" name="contenido_html" id="contenido_html">
                <p class="mt-1.5 text-xs text-gray-400 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Las imágenes se mostrarán con altura fija (400 px). Resolución sugerida: <strong class="text-gray-500">1280 × 720 px</strong> o superior.
                </p>
            </div>

            <div class="flex gap-4">
                <button type="submit" id="btn-guardar" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Guardar Cambios
                </button>
                <a href="{{ route('cursos.edit', $modulo->curso) }}" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#quill-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'align': [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ]
    }
});

// Cargar contenido existente
quill.root.innerHTML = {!! json_encode($leccion->contenido_html ?? '') !!};

// Subida de imagen al servidor para insertarla en el editor
quill.getModule('toolbar').addHandler('image', function () {
    const input = document.createElement('input');
    input.type  = 'file';
    input.accept = 'image/*';
    input.click();
    input.addEventListener('change', async function () {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 4 * 1024 * 1024) {
            alert('La imagen supera el límite de 4 MB.');
            return;
        }
        const body = new FormData();
        body.append('imagen', file);
        body.append('_token', '{{ csrf_token() }}');
        try {
            const res  = await fetch('{{ route("upload.imagen-editor") }}', { method: 'POST', body });
            const data = await res.json();
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url, Quill.sources.USER);
            quill.setSelection(range.index + 1, Quill.sources.SILENT);
        } catch {
            alert('Error al subir la imagen. Intenta de nuevo.');
        }
    });
});

document.getElementById('form-leccion').addEventListener('submit', function () {
    document.getElementById('contenido_html').value = quill.root.innerHTML;
});
</script>
@endsection