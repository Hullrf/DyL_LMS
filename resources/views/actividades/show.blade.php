@extends('layouts.app')
@section('title', $actividad->titulo . ' - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('cursos.show', $actividad->leccion->modulo->curso) }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; Volver al curso</a>
    </div>

    {{-- Encabezado de la actividad --}}
    <div class="bg-white rounded-lg shadow p-8 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <span class="text-xs font-medium uppercase text-gray-500">{{ $actividad->tipo }}</span>
                <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $actividad->titulo }}</h1>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-blue-600">{{ $actividad->puntaje_maximo }}</p>
                <p class="text-xs text-gray-500">puntos</p>
            </div>
        </div>
        @if($actividad->descripcion)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-gray-700 text-sm">
            {!! nl2br(e($actividad->descripcion)) !!}
        </div>
        @endif
        @if($actividad->duracion_minutos)
        <p class="text-sm text-gray-500 mt-3">Tiempo límite: {{ $actividad->duracion_minutos }} minutos</p>
        @endif
    </div>

    {{-- Indicador de plazo --}}
    @php $estadoPlazo = $actividad->estadoPlazo(); @endphp
    @if($estadoPlazo !== 'sin_plazo')
    <div class="mb-6 flex items-start gap-3 px-5 py-4 rounded-xl border
        @if($estadoPlazo === 'abierta')   bg-green-50  border-green-200
        @elseif($estadoPlazo === 'pendiente') bg-yellow-50 border-yellow-200
        @else bg-red-50 border-red-200 @endif">
        {{-- Icono --}}
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0
            @if($estadoPlazo === 'abierta') text-green-500
            @elseif($estadoPlazo === 'pendiente') text-yellow-500
            @else text-red-500 @endif"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1 text-sm">
            @if($estadoPlazo === 'abierta')
                <span class="font-semibold text-green-800">Actividad abierta</span>
                @if($actividad->fecha_apertura)
                    <span class="text-green-700"> — disponible desde el {{ $actividad->fecha_apertura->format('d/m/Y H:i') }}</span>
                @endif
                @if($actividad->fecha_cierre)
                    <span class="text-green-700"> · Cierra el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
                @endif
            @elseif($estadoPlazo === 'pendiente')
                <span class="font-semibold text-yellow-800">Aún no disponible</span>
                <span class="text-yellow-700"> — abre el <strong>{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</strong></span>
            @else
                <span class="font-semibold text-red-800">Plazo vencido</span>
                <span class="text-red-700"> — la entrega cerró el <strong>{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</strong></span>
            @endif
        </div>
    </div>
    @endif

    {{-- Recursos de la actividad --}}
    @php $recursos = $actividad->recursos; @endphp
    @if($recursos->isNotEmpty())
    <div class="mb-6">
        <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-dyl-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            Materiales de apoyo
        </h2>
        <div class="space-y-3">
        @foreach($recursos as $recurso)

            {{-- DOCUMENTO --}}
            @if($recurso->tipo === 'documento')
            <div class="flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-red-300 hover:shadow-sm transition-all">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900">{{ $recurso->titulo }}</p>
                    @if($recurso->descripcion)<p class="text-xs text-gray-500 mt-0.5">{{ $recurso->descripcion }}</p>@endif
                    <p class="text-xs text-gray-400 mt-1">{{ $recurso->archivoNombre() }}</p>
                </div>
                <a href="{{ $recurso->archivoUrl() }}" target="_blank" download
                   class="btn-outline btn-sm flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar
                </a>
            </div>

            {{-- VIDEO --}}
            @elseif($recurso->tipo === 'video')
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="p-4 flex items-center gap-3 border-b border-gray-100">
                    <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $recurso->titulo }}</p>
                        @if($recurso->descripcion)<p class="text-xs text-gray-500">{{ $recurso->descripcion }}</p>@endif
                    </div>
                </div>
                @php $embed = $recurso->embedUrl(); @endphp
                @if($embed && (str_contains($embed, 'youtube.com/embed') || str_contains($embed, 'player.vimeo.com')))
                    <div class="aspect-video">
                        <iframe src="{{ $embed }}" class="w-full h-full" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                @else
                    <div class="p-4">
                        <video src="{{ $embed }}" controls class="w-full rounded-lg"></video>
                    </div>
                @endif
            </div>

            {{-- TEXTO --}}
            @elseif($recurso->tipo === 'texto')
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                    <span class="font-medium text-blue-800 text-sm">{{ $recurso->titulo }}</span>
                    @if($recurso->descripcion)<span class="text-xs text-blue-500 ml-1">— {{ $recurso->descripcion }}</span>@endif
                </div>
                <div class="px-5 py-4 prose prose-sm max-w-none text-gray-700 leading-relaxed">
                    {!! nl2br(e($recurso->contenido)) !!}
                </div>
            </div>

            {{-- ENLACE EXTERNO --}}
            @elseif($recurso->tipo === 'enlace')
            <a href="{{ $recurso->url }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl p-4 hover:border-green-400 hover:shadow-sm transition-all group">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0 group-hover:bg-green-100 transition-colors">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $recurso->iconoTipo() }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 group-hover:text-green-700 transition-colors">{{ $recurso->titulo }}</p>
                    @if($recurso->descripcion)<p class="text-xs text-gray-500 mt-0.5">{{ $recurso->descripcion }}</p>@endif
                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $recurso->url }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-green-500 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
            @endif

        @endforeach
        </div>
    </div>
    @endif

    {{-- Resultado si ya respondió --}}
    @if($respuesta)
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <h2 class="font-bold text-green-800 mb-2">Ya respondiste esta actividad</h2>
        @if($respuesta->calificacion !== null)
            <p class="text-2xl font-bold text-green-700">{{ $respuesta->calificacion }}/{{ $actividad->puntaje_maximo }} puntos</p>
        @else
            <p class="text-gray-600">Tu respuesta está pendiente de calificación.</p>
        @endif
        @if($respuesta->feedback)
            <div class="mt-3 pt-3 border-t border-green-200">
                <p class="text-sm font-medium text-gray-700 mb-1">Retroalimentación:</p>
                <p class="text-sm text-gray-600">{{ $respuesta->feedback }}</p>
            </div>
        @endif
    </div>

    @elseif(!$actividad->estaAbierta())
    {{-- Actividad cerrada o pendiente: no se puede responder --}}
    <div class="bg-white rounded-lg shadow p-10 text-center">
        @if($estadoPlazo === 'pendiente')
            <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-700 font-medium">La actividad estará disponible el</p>
            <p class="text-xl font-bold text-yellow-600 mt-1">{{ $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') }}</p>
        @else
            <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5V7m0 0V5m0 2h2M12 7H10m10 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-700 font-medium">El plazo de entrega venció el</p>
            <p class="text-xl font-bold text-red-600 mt-1">{{ $actividad->fecha_cierre->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    @else
    {{-- Formulario de respuesta --}}
    <form id="form-respuesta" action="{{ route('respuestas.store', $actividad) }}" method="POST" enctype="multipart/form-data">
        @csrf

        @if($actividad->tipo === 'cuestionario')
            @php
                $preguntas  = $actividad->preguntas()->with('opciones')->orderBy('orden')->get();
                $oldAnswers = old('respuesta') ? (json_decode(old('respuesta'), true) ?? []) : [];
            @endphp
            <div class="space-y-6">
                @foreach($preguntas as $index => $pregunta)
                @php $oldVal = $oldAnswers[$pregunta->id] ?? null; @endphp
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="font-medium text-gray-900 mb-1">
                        {{ $index + 1 }}. {{ $pregunta->pregunta_texto }}
                        <span class="text-xs text-gray-400 ml-2">({{ $pregunta->puntaje }} pts)</span>
                    </p>

                    @if($pregunta->imagen_path)
                    <img src="{{ $pregunta->imagenUrl() }}"
                         alt="Imagen de apoyo"
                         class="my-4 w-full h-64 object-contain rounded-lg border border-gray-200 bg-gray-50">
                    @endif

                    @if($pregunta->tipo === 'respuesta_corta')
                        <input type="text" name="respuesta_{{ $pregunta->id }}"
                               value="{{ old('respuesta_' . $pregunta->id) }}"
                               class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>

                    @elseif($pregunta->seleccion_multiple)
                        <p class="mt-2 text-xs text-blue-600 font-medium">
                            Selecciona todas las respuestas correctas.
                        </p>
                        <div class="mt-2 space-y-2">
                            @foreach($pregunta->opciones as $opcion)
                            @php $checked = is_array($oldVal) && in_array((string)$opcion->id, array_map('strval', $oldVal)); @endphp
                            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                <input type="checkbox"
                                       name="respuesta_{{ $pregunta->id }}[]"
                                       value="{{ $opcion->id }}"
                                       {{ $checked ? 'checked' : '' }}
                                       class="w-4 h-4 rounded text-blue-600">
                                <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                            </label>
                            @endforeach
                        </div>

                    @else
                        <div class="mt-3 space-y-2">
                            @foreach($pregunta->opciones as $opcion)
                            @php $checked = $oldVal !== null && (string)$oldVal === (string)$opcion->id; @endphp
                            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="respuesta_{{ $pregunta->id }}" value="{{ $opcion->id }}"
                                       {{ $checked ? 'checked' : '' }}
                                       class="text-blue-600">
                                <span class="text-sm text-gray-800">{{ $opcion->texto }}</span>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Serializar respuestas en JSON --}}
            <input type="hidden" name="respuesta" id="respuesta-json">
            <script>
            document.getElementById('form-respuesta').addEventListener('submit', function(e) {
                const data = {};

                // Radios y texto (una sola respuesta)
                this.querySelectorAll('[name^="respuesta_"]:not([type=checkbox])').forEach(function(el) {
                    if (el.type === 'radio' && !el.checked) return;
                    if (!el.value) return;
                    const id = el.name.replace('respuesta_', '');
                    data[id] = el.value;
                });

                // Checkboxes (selección múltiple) — agrupados por pregunta_id
                this.querySelectorAll('[name^="respuesta_"][type=checkbox]:checked').forEach(function(el) {
                    const id = el.name.replace('respuesta_', '').replace('[]', '');
                    if (!data[id]) data[id] = [];
                    data[id].push(el.value);
                });

                document.getElementById('respuesta-json').value = JSON.stringify(data);
            });
            </script>

        @else
            <div class="bg-white rounded-lg shadow p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tu respuesta</label>
                <textarea name="respuesta" rows="8"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Escribe tu respuesta aquí...">{{ old('respuesta') }}</textarea>
                @error('respuesta')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Adjunto opcional --}}
            <div class="bg-white rounded-lg shadow p-6" x-data="{ nombre: null }">
                <p class="text-sm font-medium text-gray-700 mb-3">
                    Adjuntar archivo
                    <span class="text-gray-400 font-normal">(opcional)</span>
                </p>
                <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
                       :class="nombre
                           ? 'border-green-400 bg-green-50/40 hover:bg-green-50'
                           : 'border-gray-300 bg-gray-50/40 hover:border-blue-400 hover:bg-blue-50/30'">
                    <div x-show="!nombre" class="flex flex-col items-center gap-1.5 text-gray-400 pointer-events-none">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm">Haz clic para seleccionar</p>
                        <p class="text-xs">Imagen, PDF, Word, video — máx. 50 MB</p>
                    </div>
                    <div x-show="nombre" class="flex items-center gap-2 px-4 text-green-700 pointer-events-none">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-medium truncate max-w-xs" x-text="nombre"></span>
                    </div>
                    <input type="file" name="archivo_adjunto" class="sr-only"
                           accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                           @change="nombre = $event.target.files[0]?.name ?? null">
                </label>
                @error('archivo_adjunto')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-medium">
                Enviar respuesta
            </button>
        </div>
    </form>
    @endif
</div>
@endsection