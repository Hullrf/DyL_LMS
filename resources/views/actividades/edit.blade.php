@extends('layouts.app')
@section('title', 'Editar Actividad - LMS DyL')
@section('content')
<div class="mb-4">
    <a href="{{ route('cursos.edit', $actividad->leccion->modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
</div>

@if(session('success'))
    <div class="mb-4 alert-success">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ===== COLUMNA IZQUIERDA: datos de la actividad ===== --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Formulario de datos --}}
        <div class="card p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-1">{{ $actividad->titulo }}</h2>
            <span class="badge mb-4
                @if($actividad->tipo === 'cuestionario') badge-blue
                @elseif($actividad->tipo === 'ensayo') bg-purple-100 text-purple-800
                @elseif($actividad->tipo === 'tarea') badge-yellow
                @else badge-green @endif">
                {{ ucfirst($actividad->tipo) }}
            </span>

            <form action="{{ route('actividades.update', $actividad) }}" method="POST">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" value="{{ old('titulo', $actividad->titulo) }}"
                           class="form-input" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción / Instrucciones</label>
                    <textarea name="descripcion" rows="3" class="form-textarea">{{ old('descripcion', $actividad->descripcion) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Puntaje máximo</label>
                    <input type="number" name="puntaje_maximo"
                           value="{{ old('puntaje_maximo', number_format($actividad->puntaje_maximo, 2, '.', '')) }}"
                           min="0.01" step="0.01"
                           class="form-input" required>
                </div>
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="es_obligatoria" value="1" @checked($actividad->es_obligatoria) class="rounded">
                        <span class="text-sm text-gray-700">Obligatoria</span>
                    </label>
                </div>

                {{-- Plazo de entrega --}}
                <div class="mb-4 pt-4 border-t border-gray-100"
                     x-data="{ conPlazo: {{ ($actividad->fecha_apertura || $actividad->fecha_cierre) ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2 cursor-pointer mb-3">
                        <input type="checkbox" x-model="conPlazo" class="rounded text-dyl-orange">
                        <span class="text-sm font-medium text-gray-700">Configurar plazo de entrega</span>
                    </label>
                    <div x-show="conPlazo" x-cloak class="space-y-3">
                        <div>
                            <label class="form-label">Apertura</label>
                            <input type="datetime-local" name="fecha_apertura"
                                   value="{{ old('fecha_apertura', $actividad->fecha_apertura?->format('Y-m-d\TH:i')) }}"
                                   class="form-input text-sm">
                            <p class="form-hint">Dejar vacío = disponible de inmediato</p>
                        </div>
                        <div>
                            <label class="form-label">Cierre</label>
                            <input type="datetime-local" name="fecha_cierre"
                                   value="{{ old('fecha_cierre', $actividad->fecha_cierre?->format('Y-m-d\TH:i')) }}"
                                   class="form-input text-sm">
                            <p class="form-hint">Dejar vacío = sin fecha límite</p>
                            @error('fecha_cierre')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        {{-- Indicador del estado actual --}}
                        @php $estadoPlazo = $actividad->estadoPlazo(); @endphp
                        @if($estadoPlazo !== 'sin_plazo')
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium
                            @if($estadoPlazo === 'abierta')  bg-green-50 text-green-700 border border-green-200
                            @elseif($estadoPlazo === 'pendiente') bg-yellow-50 text-yellow-700 border border-yellow-200
                            @else bg-red-50 text-red-700 border border-red-200 @endif">
                            <span class="w-2 h-2 rounded-full flex-shrink-0
                                @if($estadoPlazo === 'abierta') bg-green-500
                                @elseif($estadoPlazo === 'pendiente') bg-yellow-500
                                @else bg-red-500 @endif"></span>
                            @if($estadoPlazo === 'abierta')   Abierta ahora
                            @elseif($estadoPlazo === 'pendiente') Aún no disponible
                            @else Plazo vencido @endif
                        </div>
                        @endif
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full">Guardar cambios</button>
            </form>

            <div class="mt-3 pt-3 border-t border-gray-200">
                <form action="{{ route('actividades.destroy', $actividad) }}" method="POST"
                      onsubmit="return confirm('¿Eliminar esta actividad?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger btn-sm w-full">Eliminar actividad</button>
                </form>
            </div>
        </div>

        {{-- ===== PANEL DE RECURSOS ===== --}}
        <div class="card p-6" x-data="{ tipoRecurso: 'documento' }">
            <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Agregar recurso
            </h3>

            <form action="{{ route('recursos.store', $actividad) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                @csrf

                {{-- Selector de tipo --}}
                <div>
                    <label class="form-label">Tipo de recurso</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['documento' => ['📄','Documento'], 'video' => ['▶️','Video'], 'texto' => ['📝','Texto'], 'enlace' => ['🔗','Enlace']] as $val => [$emoji, $label])
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm transition-colors"
                               :class="tipoRecurso === '{{ $val }}' ? 'border-dyl-blue bg-blue-50 text-dyl-blue font-medium' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                            <input type="radio" name="tipo" value="{{ $val }}" x-model="tipoRecurso" class="sr-only">
                            <span>{{ $emoji }}</span> {{ $label }}
                        </label>
                        @endforeach
                    </div>
                    @error('tipo')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Título --}}
                <div>
                    <label class="form-label">Título del recurso</label>
                    <input type="text" name="titulo" value="{{ old('titulo') }}" class="form-input" required
                           placeholder="Ej: Guía ISO 9001 v2015">
                    @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Descripción corta (opcional) --}}
                <div>
                    <label class="form-label">Descripción breve <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="descripcion" value="{{ old('descripcion') }}" class="form-input"
                           placeholder="Para qué sirve este recurso">
                </div>

                {{-- Campo dinámico según tipo --}}
                {{-- Documento --}}
                <div x-show="tipoRecurso === 'documento'" x-cloak>
                    <label class="form-label">Archivo</label>
                    <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    <p class="form-hint">PDF, Word, PowerPoint, Excel o ZIP — máx. 20 MB</p>
                    @error('archivo')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Video --}}
                <div x-show="tipoRecurso === 'video'" x-cloak>
                    <label class="form-label">URL del video</label>
                    <input type="url" name="url" value="{{ old('url') }}" class="form-input"
                           placeholder="https://youtube.com/watch?v=...">
                    <p class="form-hint">YouTube, Vimeo o enlace directo a .mp4</p>
                    @error('url')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Texto --}}
                <div x-show="tipoRecurso === 'texto'" x-cloak>
                    <label class="form-label">Contenido</label>
                    <textarea name="contenido" rows="5" class="form-textarea"
                              placeholder="Escribe el contenido del recurso...">{{ old('contenido') }}</textarea>
                    @error('contenido')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                {{-- Enlace externo --}}
                <div x-show="tipoRecurso === 'enlace'" x-cloak>
                    <label class="form-label">URL del enlace</label>
                    <input type="url" name="url" value="{{ old('url') }}" class="form-input"
                           placeholder="https://...">
                    <p class="form-hint">Se abrirá en nueva pestaña</p>
                    @error('url')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn-primary w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar recurso
                </button>
            </form>

            {{-- Lista de recursos existentes --}}
            @php $recursos = $actividad->recursos; @endphp
            @if($recursos->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-gray-100 space-y-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Recursos agregados ({{ $recursos->count() }})</p>
                    @foreach($recursos as $recurso)
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg group">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0
                            @if($recurso->tipo === 'documento') text-red-500
                            @elseif($recurso->tipo === 'video') text-purple-500
                            @elseif($recurso->tipo === 'texto') text-blue-500
                            @else text-green-500 @endif"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $recurso->titulo }}</p>
                            <p class="text-xs text-gray-400 capitalize">{{ $recurso->tipo }}</p>
                        </div>
                        <form action="{{ route('recursos.destroy', $recurso) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar este recurso?');" class="shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors"
                                    title="Eliminar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ===== COLUMNA DERECHA: preguntas / instrucciones ===== --}}
    <div class="lg:col-span-2">
        @if($actividad->tipo === 'cuestionario')
            {{-- Resumen de distribución de puntaje --}}
            @php $nPreguntas = $preguntas->count(); @endphp
            @if($nPreguntas > 0)
            <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-100 rounded-xl text-sm">
                <svg class="w-4 h-4 text-dyl-blue shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-gray-700">
                    <strong>{{ $nPreguntas }} preguntas</strong> —
                    el puntaje de <strong>{{ $actividad->puntaje_maximo }} pts</strong> se distribuye automáticamente.
                    Cada pregunta vale aprox. <strong>{{ round($actividad->puntaje_maximo / $nPreguntas, 1) }} pts</strong>
                    (al agregar o quitar preguntas se recalcula).
                </span>
            </div>
            @endif

            {{-- Agregar pregunta --}}
            <div class="card p-6 mb-4">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Agregar Pregunta</h3>
                <form action="{{ route('preguntas.store', $actividad) }}" method="POST" enctype="multipart/form-data"
                      x-data="{ tipo: 'opcion_multiple', preview: null, correctaVF: '' }">
                    @csrf

                    {{-- Enunciado --}}
                    <div class="mb-3">
                        <textarea name="pregunta_texto" placeholder="Texto de la pregunta" rows="2"
                                  class="form-textarea" required></textarea>
                    </div>

                    {{-- Imagen opcional --}}
                    <div class="mb-3">
                        <label class="form-label">
                            Imagen de apoyo
                            <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input type="file" name="imagen" accept="image/*"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-orange-50 file:text-dyl-orange hover:file:bg-orange-100 cursor-pointer"
                               x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                        <p class="form-hint">JPG, PNG, WebP — máx. 4 MB · Resolución sugerida: <strong>1280 × 720 px</strong> (16:9)</p>
                        <img x-show="preview" x-cloak :src="preview"
                             class="mt-2 w-full h-48 object-contain rounded-lg border border-gray-200 bg-gray-50">
                    </div>

                    {{-- Tipo + Botón --}}
                    <div class="flex gap-3 flex-wrap mb-3 items-center">
                        <select name="tipo" x-model="tipo" class="form-select flex-1 min-w-[180px]">
                            <option value="opcion_multiple">Opción múltiple</option>
                            <option value="verdadero_falso">Verdadero / Falso</option>
                            <option value="respuesta_corta">Respuesta corta</option>
                        </select>
                        <button type="submit" class="btn bg-green-600 text-white hover:bg-green-700">
                            + Pregunta
                        </button>
                    </div>

                    {{-- Respuesta correcta: Verdadero / Falso --}}
                    <div x-show="tipo === 'verdadero_falso'" x-cloak class="mt-1">
                        <label class="form-label">Respuesta correcta</label>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors"
                                   :class="correctaVF === 'verdadero'
                                       ? 'border-green-500 bg-green-50 text-green-700 font-semibold ring-1 ring-green-400'
                                       : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                                <input type="radio" name="correcta_vf" value="verdadero"
                                       x-model="correctaVF" class="sr-only">
                                <span class="text-base" :class="correctaVF === 'verdadero' ? 'text-green-600' : 'text-gray-300'">✓</span>
                                Verdadero
                            </label>
                            <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm transition-colors"
                                   :class="correctaVF === 'falso'
                                       ? 'border-red-500 bg-red-50 text-red-700 font-semibold ring-1 ring-red-400'
                                       : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                                <input type="radio" name="correcta_vf" value="falso"
                                       x-model="correctaVF" class="sr-only">
                                <span class="text-base" :class="correctaVF === 'falso' ? 'text-red-500' : 'text-gray-300'">✗</span>
                                Falso
                            </label>
                        </div>
                        @error('correcta_vf')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Permite múltiples respuestas correctas (solo opción múltiple) --}}
                    <div x-show="tipo === 'opcion_multiple'" x-cloak>
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" name="seleccion_multiple" value="1"
                                   class="w-4 h-4 rounded text-dyl-blue">
                            <span class="text-sm text-gray-700">
                                Permite <strong>más de una respuesta correcta</strong>
                                <span class="text-gray-400">(puntaje parcial por cada acierto)</span>
                            </span>
                        </label>
                    </div>

                </form>
            </div>

            {{-- Lista de preguntas --}}
            @forelse($preguntas as $pregunta)
            <div class="card mb-4">
                <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <span class="text-xs font-medium text-gray-500 uppercase">
                                {{ str_replace('_', ' ', $pregunta->tipo) }}
                            </span>
                            <span class="ml-1 badge badge-gold text-[10px]">{{ $pregunta->puntaje }} pts</span>
                            @if($pregunta->seleccion_multiple)
                                <span class="ml-2 badge badge-blue text-[10px]">selección múltiple</span>
                            @endif
                            <p class="font-medium text-gray-900 mt-1">{{ $pregunta->pregunta_texto }}</p>

                            @if($pregunta->imagen_path)
                            <div class="mt-3">
                                <img src="{{ $pregunta->imagenUrl() }}"
                                     alt="Imagen de la pregunta"
                                     class="w-full h-48 object-contain rounded-lg border border-gray-200 bg-gray-50 mb-1.5">
                                <form action="{{ route('preguntas.imagen.destroy', $pregunta) }}" method="POST"
                                      onsubmit="return confirm('¿Quitar la imagen de esta pregunta?');">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs text-red-400 hover:text-red-600 transition-colors flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Quitar imagen
                                    </button>
                                </form>
                            </div>
                            @endif
                        </div>
                        <form action="{{ route('preguntas.destroy', $pregunta) }}" method="POST" class="shrink-0"
                              onsubmit="return confirm('¿Eliminar pregunta?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </div>

                <div class="p-4">
                    @if($pregunta->tipo === 'verdadero_falso')
                        {{-- Selector de respuesta correcta para V/F --}}
                        @php $correctaVF = $pregunta->opciones->firstWhere('es_correcta', true)?->texto; @endphp
                        <form action="{{ route('preguntas.update', $pregunta) }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" name="pregunta_texto" value="{{ $pregunta->pregunta_texto }}">
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500">Respuesta correcta:</span>
                                <label class="flex items-center gap-2 px-3 py-1.5 rounded-lg border cursor-pointer text-sm transition-colors
                                    {{ $correctaVF === 'Verdadero' ? 'border-green-400 bg-green-50 text-green-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                                    <input type="radio" name="correcta_vf" value="verdadero"
                                           {{ $correctaVF === 'Verdadero' ? 'checked' : '' }} class="sr-only">
                                    ✓ Verdadero
                                </label>
                                <label class="flex items-center gap-2 px-3 py-1.5 rounded-lg border cursor-pointer text-sm transition-colors
                                    {{ $correctaVF === 'Falso' ? 'border-red-400 bg-red-50 text-red-700 font-medium' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                                    <input type="radio" name="correcta_vf" value="falso"
                                           {{ $correctaVF === 'Falso' ? 'checked' : '' }} class="sr-only">
                                    ✗ Falso
                                </label>
                                <button type="submit" class="btn-primary btn-sm ml-1">Guardar</button>
                            </div>
                        </form>

                    @elseif($pregunta->tipo === 'opcion_multiple')
                        <div class="space-y-2 mb-4">
                            @foreach($pregunta->opciones as $opcion)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg {{ $opcion->es_correcta ? 'bg-green-50 border border-green-200' : 'bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    @if($opcion->es_correcta)
                                        <span class="text-green-600 font-bold">✓</span>
                                    @else
                                        <span class="text-gray-300">○</span>
                                    @endif
                                    <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                                </div>
                                <form action="{{ route('opciones.destroy', $opcion) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar opción?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                                </form>
                            </div>
                            @endforeach
                        </div>
                        <form action="{{ route('opciones.store', $pregunta) }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="text" name="texto" placeholder="Texto de la opción"
                                   class="form-input flex-1" required>
                            <label class="flex items-center gap-1 text-sm text-gray-600 whitespace-nowrap">
                                <input type="checkbox" name="es_correcta" value="1" class="rounded">
                                Correcta
                            </label>
                            <button type="submit" class="btn-primary btn-sm">+ Opción</button>
                        </form>

                    @else
                        <p class="text-sm text-gray-500 italic">Respuesta abierta — el estudiante escribirá su respuesta.</p>
                    @endif
                </div>
            </div>
            @empty
            <div class="card p-8 text-center text-gray-500">
                No hay preguntas. Agrega la primera arriba.
            </div>
            @endforelse

        @else
            {{-- Para ensayo, tarea, practica --}}
            <div class="space-y-6">

                {{-- Instrucciones y respuestas --}}
                <div class="card p-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Instrucciones para estudiantes</h3>
                    <p class="text-gray-600 mb-4">
                        Esta actividad de tipo <strong>{{ $actividad->tipo }}</strong> requiere calificación manual por parte del instructor.
                    </p>
                    @if($actividad->descripcion)
                    <div class="bg-gray-50 rounded-lg p-4 text-gray-700 text-sm">
                        {{ $actividad->descripcion }}
                    </div>
                    @endif
                    <div class="mt-6 border-t pt-4">
                        <h4 class="font-medium text-gray-900 mb-2">Respuestas recibidas</h4>
                        @php $respuestas = $actividad->respuestas()->with('usuario')->latest()->get(); @endphp
                        @forelse($respuestas as $resp)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-medium text-gray-800">{{ $resp->usuario->name }}</span>
                                <span class="badge
                                    @if($resp->estado === 'calificada') badge-green
                                    @elseif($resp->estado === 'en_revision') badge-yellow
                                    @else badge-gray @endif">
                                    {{ str_replace('_', ' ', $resp->estado) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 line-clamp-3">{{ $resp->respuesta }}</p>
                            @if($resp->calificacion !== null)
                                <p class="text-sm font-medium text-green-600 mt-1">
                                    Calificación: {{ number_format($resp->calificacion, 2) }} / {{ number_format($actividad->puntaje_maximo, 2) }}
                                </p>
                            @endif
                        </div>
                        @empty
                            <p class="text-gray-500 text-sm">Aún no hay respuestas.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Aviso para ensayo/practica --}}
                @if(in_array($actividad->tipo, ['ensayo', 'practica']))
                <div class="flex items-start gap-3 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-500">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    La rúbrica de evaluación por criterios está disponible únicamente para actividades de tipo <strong>Tarea</strong> (entrega de archivo).
                </div>
                @endif

                {{-- ===== CONSTRUCTOR DE RÚBRICA (solo para tarea) ===== --}}
                @if($actividad->tipo === 'tarea')
                @php
                    $criteriosIniciales = $criteriosRubrica->map(fn($c) => [
                        'nombre'  => $c->nombre,
                        'niveles' => $c->niveles->map(fn($n) => [
                            'descripcion' => $n->descripcion,
                            'puntos'      => (string) $n->puntos,
                        ])->values()->toArray(),
                    ])->values()->toArray();
                @endphp

                <div class="card p-6"
                     x-data="{
                        usaRubrica: {{ $actividad->usa_rubrica ? 'true' : 'false' }},
                        criterios: {{ json_encode($criteriosIniciales) }},
                        importError: '',
                        importando: false,
                        modalImport: false,

                        get totalPuntos() {
                            return this.criterios.reduce((sum, c) => {
                                const puntos = c.niveles.map(n => parseFloat(n.puntos) || 0);
                                return sum + (puntos.length ? Math.max(...puntos) : 0);
                            }, 0).toFixed(2);
                        },

                        agregarCriterio() {
                            this.criterios.push({ nombre: '', niveles: [{ descripcion: '', puntos: '' }] });
                            this.$nextTick(() => this.$el.querySelector('.criterio-ultimo input')?.focus());
                        },

                        eliminarCriterio(i) { this.criterios.splice(i, 1); },
                        agregarNivel(ci) { this.criterios[ci].niveles.push({ descripcion: '', puntos: '' }); },
                        eliminarNivel(ci, ni) {
                            if (this.criterios[ci].niveles.length > 1) this.criterios[ci].niveles.splice(ni, 1);
                        },

                        async importarArchivo(event) {
                            const file = event.target.files[0];
                            if (!file) return;
                            this.importando  = true;
                            this.importError = '';
                            try {
                                const fd = new FormData();
                                fd.append('archivo', file);
                                const csrfMeta = document.querySelector('meta[name=csrf-token]');
                                fd.append('_token', csrfMeta ? csrfMeta.content : '');

                                const res = await fetch('{{ route('rubrica.importar', $actividad) }}', { method: 'POST', body: fd });

                                let data;
                                try { data = await res.json(); }
                                catch (_) {
                                    this.importError = 'Respuesta inesperada del servidor (código ' + res.status + '). Recarga la página e intenta de nuevo.';
                                    return;
                                }

                                if (!res.ok) {
                                    this.importError = data.error || ('Error del servidor: código ' + res.status);
                                } else {
                                    this.criterios   = data.criterios;
                                    this.usaRubrica  = true;
                                    this.modalImport = false;
                                }
                            } catch (e) {
                                this.importError = 'Error de red: ' + e.message;
                            } finally {
                                this.importando = false;
                                event.target.value = '';
                            }
                        }
                     }">

                    {{-- Encabezado con toggle --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Rúbrica de evaluación
                        </h3>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <span class="text-sm text-gray-600" x-text="usaRubrica ? 'Activa' : 'Inactiva'"></span>
                            <div class="relative">
                                <input type="checkbox" x-model="usaRubrica" class="sr-only peer">
                                <div class="w-10 h-6 bg-gray-200 peer-checked:bg-dyl-blue rounded-full transition-colors"></div>
                                <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                            </div>
                        </label>
                    </div>

                    <div x-show="usaRubrica" x-cloak class="space-y-4">
                        {{-- Acciones --}}
                        <div class="flex gap-3 flex-wrap">
                            <button type="button" @click="agregarCriterio()"
                                    class="btn-outline btn-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Agregar criterio
                            </button>
                            <button type="button" @click="modalImport = true"
                                    class="btn-outline btn-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Importar desde Excel
                            </button>
                        </div>

                        {{-- Lista de criterios --}}
                        <div class="space-y-4">
                            <template x-for="(criterio, ci) in criterios" :key="ci">
                                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50" :class="'criterio-' + (ci === criterios.length-1 ? 'ultimo' : ci)">
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="flex-1">
                                            <label class="form-label text-xs">Criterio <span x-text="ci+1"></span></label>
                                            <input type="text" x-model="criterio.nombre"
                                                   placeholder="Ej: Planteamiento del Problema"
                                                   class="form-input">
                                        </div>
                                        <button type="button" @click="eliminarCriterio(ci)"
                                                class="mt-5 text-red-400 hover:text-red-600 transition-colors shrink-0" title="Eliminar criterio">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="space-y-2 mb-3">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Niveles (menor → mayor rendimiento)</p>
                                        <template x-for="(nivel, ni) in criterio.niveles" :key="ni">
                                            <div class="flex gap-2 items-start bg-white border border-gray-200 rounded-lg p-3">
                                                <span class="text-xs text-gray-400 mt-2 w-5 shrink-0" x-text="ni+1+'.'"></span>
                                                <textarea x-model="nivel.descripcion"
                                                          placeholder="Descripción del nivel..."
                                                          rows="2"
                                                          class="form-input flex-1 text-sm resize-none"></textarea>
                                                <div class="w-24 shrink-0">
                                                    <label class="text-xs text-gray-400 block mb-1">Puntos</label>
                                                    <input type="number" x-model="nivel.puntos"
                                                           step="0.01" min="0" max="99.99"
                                                           placeholder="0.00"
                                                           class="form-input text-sm text-center">
                                                </div>
                                                <button type="button" @click="eliminarNivel(ci, ni)"
                                                        x-show="criterio.niveles.length > 1"
                                                        class="mt-1 text-gray-300 hover:text-red-400 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" @click="agregarNivel(ci)"
                                            class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        + Nivel
                                    </button>
                                </div>
                            </template>

                            <div x-show="criterios.length === 0"
                                 class="text-center py-6 text-gray-400 text-sm border border-dashed border-gray-300 rounded-xl">
                                Sin criterios. Agrega uno o importa desde Excel.
                            </div>
                        </div>

                        {{-- Total en tiempo real --}}
                        <div class="flex items-center justify-between px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl">
                            <span class="text-sm font-medium text-blue-800">Puntaje máximo calculado:</span>
                            <span class="text-xl font-bold text-blue-700" x-text="totalPuntos + ' pts'"></span>
                        </div>

                        {{-- Formulario para guardar rúbrica --}}
                        <form action="{{ route('rubrica.store', $actividad) }}" method="POST" id="form-rubrica">
                            @csrf
                            <input type="hidden" name="usa_rubrica" :value="usaRubrica ? '1' : '0'">
                            <input type="hidden" name="criterios_json" id="criterios-json-input">
                            <button type="button"
                                    @click="
                                        document.getElementById('criterios-json-input').value = JSON.stringify(criterios);
                                        document.getElementById('form-rubrica').submit();
                                    "
                                    class="btn-primary w-full">
                                Guardar rúbrica
                            </button>
                        </form>
                    </div>

                    {{-- Botón para desactivar si ya estaba activa --}}
                    <div x-show="!usaRubrica && {{ $actividad->usa_rubrica ? 'true' : 'false' }}" x-cloak class="mt-3">
                        <form action="{{ route('rubrica.store', $actividad) }}" method="POST">
                            @csrf
                            <input type="hidden" name="usa_rubrica" value="0">
                            <input type="hidden" name="criterios_json" value="[]">
                            <button type="submit" class="btn-outline btn-sm w-full text-gray-500">
                                Confirmar desactivación de rúbrica
                            </button>
                        </form>
                    </div>

                    {{-- Modal de importación --}}
                    <div x-show="modalImport" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                         @click.self="modalImport = false">
                        <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Importar rúbrica desde Excel</h3>

                            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800 space-y-1">
                                <p class="font-semibold">Formato del archivo:</p>
                                <p>• Columna A: nombre del criterio</p>
                                <p>• Columnas B, C, D...: niveles (de peor a mejor)</p>
                                <p>• Última línea de cada celda: el puntaje (ej: <strong>0.8 puntos</strong>)</p>
                            </div>

                            <a href="{{ route('rubrica.ejemplo') }}" target="_blank"
                               class="btn-outline w-full mb-4 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Descargar archivo de ejemplo
                            </a>

                            <div class="mb-3">
                                <label class="form-label">Subir mi archivo (.xlsx)</label>
                                <input type="file" accept=".xlsx,.xls,.csv"
                                       @change="importarArchivo($event)"
                                       :disabled="importando"
                                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 cursor-pointer">
                            </div>

                            <p x-show="importando" class="text-sm text-blue-600 mt-1 flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Procesando archivo...
                            </p>
                            <p x-show="importError" x-text="importError" class="text-sm text-red-600 mt-1"></p>

                            <button type="button" @click="modalImport = false; importError = ''"
                                    class="btn-outline w-full mt-4">Cancelar</button>
                        </div>
                    </div>

                </div>
                @endif

            </div>
        @endif
    </div>

</div>
@endsection
