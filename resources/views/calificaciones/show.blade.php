@extends('layouts.app')
@section('title', 'Calificar Respuesta - LMS DyL')

@section('content')
@php
    $archivosAdjuntos = $respuesta->archivos;
    // Con 1 solo archivo se conserva la previsualización rica de siempre
    // (imagen grande / video / visor de PDF). Con 2+ se usa la lista simple
    // de abajo — repetir el visor grande por cada archivo satura la pantalla.
    $adjunto  = $archivosAdjuntos->count() === 1 ? $archivosAdjuntos->first() : null;
    $ext      = $adjunto ? strtolower(pathinfo($adjunto->path, PATHINFO_EXTENSION)) : null;
    $urlAdj   = $adjunto ? asset('storage/' . $adjunto->path) : null;
    $esImagen = $adjunto && in_array($ext, ['jpg','jpeg','png','gif','webp']);
    $esVideo  = $adjunto && in_array($ext, ['mp4','mov','avi','webm']);
    $esPdf    = $adjunto && $ext === 'pdf';
    $esOtro   = $adjunto && !$esImagen && !$esVideo && !$esPdf;
@endphp

<div class="{{ $esPdf ? 'max-w-6xl' : 'max-w-4xl' }} mx-auto">

    {{-- Encabezado --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('calificaciones.curso', $respuesta->actividad->leccion->modulo->curso) }}" class="text-gray-500 hover:text-gray-800 text-xl">&larr;</a>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Calificando actividad</p>
            <h1 class="text-2xl font-bold text-gray-900">{{ $respuesta->actividad->titulo }}</h1>
        </div>
    </div>

    {{-- Tarjetas informativas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow text-center">
            <p class="text-xs text-gray-500 uppercase mb-1">Estudiante</p>
            <p class="font-semibold text-gray-900 text-sm">{{ $respuesta->usuario->name }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow text-center">
            <p class="text-xs text-gray-500 uppercase mb-1">Tipo</p>
            <p class="font-semibold text-gray-900 text-sm">{{ ucfirst($respuesta->actividad->tipo) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow text-center">
            <p class="text-xs text-gray-500 uppercase mb-1">Puntaje máximo</p>
            <p class="font-semibold text-gray-900 text-sm">{{ $respuesta->actividad->puntaje_maximo }} pts</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow text-center">
            <p class="text-xs text-gray-500 uppercase mb-1">Enviado</p>
            <p class="font-semibold text-gray-900 text-sm">{{ $respuesta->fecha_envio->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    {{-- Fila principal: calificación (izq) + respuesta (der) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Formulario de calificación --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Calificación</h2>

            @if($errors->any())
                <div class="alert alert-error mb-4">
                    <div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                </div>
            @endif

            @if($respuesta->actividad->usa_rubrica && $criteriosRubrica->isNotEmpty())
            {{-- ===== CALIFICACIÓN CON RÚBRICA ===== --}}
            @php
                $nivelPuntos = $criteriosRubrica->flatMap(fn($c) => $c->niveles)->pluck('puntos', 'id')->map(fn($p) => (float) $p);
            @endphp

            <form action="{{ route('calificaciones.rubrica', $respuesta) }}" method="POST"
                  x-data="{
                    selecciones: {{ $seleccionesActuales->toJson() }},
                    nivelPuntos: {{ $nivelPuntos->toJson() }},
                    totalCriterios: {{ $criteriosRubrica->count() }},
                    get totalSeleccionado() {
                        return Object.values(this.selecciones)
                            .reduce((sum, id) => sum + (parseFloat(this.nivelPuntos[id]) || 0), 0)
                            .toFixed(2);
                    },
                    get todosSeleccionados() {
                        return Object.keys(this.selecciones).length >= this.totalCriterios;
                    }
                  }">
                @csrf

                <div class="space-y-3 mb-5">
                    @foreach($criteriosRubrica as $criterio)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                            <p class="font-semibold text-gray-800 text-sm">{{ $criterio->nombre }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-{{ min($criterio->niveles->count(), 4) }} divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                            @foreach($criterio->niveles->sortBy('orden') as $nivel)
                            <label class="cursor-pointer p-3 hover:bg-dyl-orange-50 transition-colors"
                                   :class="selecciones[{{ $criterio->id }}] == {{ $nivel->id }} ? 'bg-dyl-orange-50 ring-2 ring-inset ring-dyl-orange-600' : ''">
                                <input type="radio"
                                       name="selecciones[{{ $criterio->id }}]"
                                       value="{{ $nivel->id }}"
                                       x-model="selecciones[{{ $criterio->id }}]"
                                       class="sr-only">
                                <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $nivel->descripcion }}</p>
                                <p class="text-sm font-bold text-dyl-orange-600">{{ number_format($nivel->puntos, 2) }} pts</p>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Contador en tiempo real --}}
                <div class="flex items-center justify-between px-4 py-3 bg-dyl-orange-50 border border-dyl-orange-200 rounded-xl mb-4">
                    <span class="text-sm font-medium text-dyl-orange-800">Calificación actual:</span>
                    <span class="text-2xl font-bold text-dyl-orange-700">
                        <span x-text="totalSeleccionado"></span>
                        <span class="text-base font-normal text-dyl-orange-500"> / {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
                    </span>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Retroalimentación <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="feedback" rows="5"
                              placeholder="Comentarios para el estudiante..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-dyl-orange-600 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
                </div>

                <button type="submit"
                        :disabled="!todosSeleccionados"
                        :class="todosSeleccionados ? 'bg-dyl-orange-600 hover:bg-dyl-orange-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                        class="w-full text-white py-2.5 rounded-lg font-medium transition-colors">
                    <span x-show="todosSeleccionados">Guardar Calificación</span>
                    <span x-show="!todosSeleccionados">Selecciona un nivel por criterio para continuar</span>
                </button>
            </form>

            @else
            {{-- ===== CALIFICACIÓN MANUAL (sin rúbrica) ===== --}}
            <form action="{{ route('calificaciones.update', $respuesta) }}" method="POST" class="space-y-5">
                @csrf @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Calificación (0 – {{ number_format($respuesta->actividad->puntaje_maximo, 2) }})
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="number"
                               name="calificacion"
                               min="0"
                               max="{{ $respuesta->actividad->puntaje_maximo }}"
                               step="0.01"
                               value="{{ old('calificacion', $respuesta->calificacion) }}"
                               class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-center text-2xl font-bold focus:ring-2 focus:ring-dyl-orange-600 focus:border-dyl-orange-600"
                               required>
                        <span class="text-gray-400 text-lg">/ {{ number_format($respuesta->actividad->puntaje_maximo, 2) }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Retroalimentación <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="feedback" rows="7"
                              placeholder="Escribe comentarios para el estudiante..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-dyl-orange-600 resize-none">{{ old('feedback', $respuesta->feedback) }}</textarea>
                </div>

                <button type="submit"
                        class="w-full bg-dyl-orange-600 text-white py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium transition-colors">
                    Guardar Calificación
                </button>
            </form>
            @endif

            @if($respuesta->fecha_calificacion)
                <p class="text-xs text-gray-400 text-center mt-3">
                    Última calificación: {{ $respuesta->fecha_calificacion->format('d/m/Y H:i') }}
                </p>
            @endif
        </div>

        {{-- Respuesta del estudiante (texto + adjuntos no-PDF) --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Respuesta del Estudiante</h2>

            @if($respuesta->actividad->descripcion)
                <div class="bg-dyl-graphite-50 rounded p-3 mb-4 text-sm text-dyl-graphite-700">
                    <span class="font-medium">Enunciado:</span>
                    @include('components.descripcion-render', ['slot' => $respuesta->actividad->descripcion])
                </div>
            @endif

            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap min-h-32 leading-relaxed">
                {{ $respuesta->respuesta ?: '— Sin texto —' }}
            </div>

            {{-- Adjuntos que no son PDF (imagen, video, otros) --}}
            @if($adjunto && !$esPdf)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Archivo adjunto</p>
                    <a href="{{ $urlAdj }}" target="_blank" download
                       class="inline-flex items-center gap-1.5 text-dyl-orange-600 hover:text-dyl-orange-700 text-xs font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Descargar
                    </a>
                </div>

                @if($esImagen)
                    <img src="{{ $urlAdj }}" alt="Adjunto"
                         class="max-w-full max-h-72 rounded-lg border border-gray-200 object-contain">
                @elseif($esVideo)
                    <video src="{{ $urlAdj }}" controls class="w-full max-h-56 rounded-lg"></video>
                @elseif($esOtro)
                    <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                        <svg class="w-8 h-8 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $adjunto->nombre_original ?: basename($adjunto->path) }}</p>
                            <p class="text-xs text-gray-500 uppercase">{{ $ext }}</p>
                        </div>
                    </div>
                @endif
            </div>
            @endif

            @if($esPdf)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    El PDF se muestra en el visor de abajo.
                </p>
            </div>
            @endif

            {{-- 2+ archivos: lista simple con descarga en vez del visor rico --}}
            @if($archivosAdjuntos->count() > 1)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500 uppercase tracking-wide mb-2">
                    Archivos adjuntos ({{ $archivosAdjuntos->count() }})
                </p>
                <ul class="space-y-2">
                    @foreach($archivosAdjuntos as $arch)
                        @php
                            $extArch  = strtolower(pathinfo($arch->path, PATHINFO_EXTENSION));
                            $urlArch  = asset('storage/' . $arch->path);
                            $esImgArch = in_array($extArch, ['jpg','jpeg','png','gif','webp']);
                        @endphp
                        <li class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                            @if($esImgArch)
                                <img src="{{ $urlArch }}" alt="" class="w-10 h-10 rounded object-cover flex-shrink-0 border border-gray-200">
                            @else
                                <svg class="w-8 h-8 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $arch->nombre_original ?: basename($arch->path) }}</p>
                                <p class="text-xs text-gray-500 uppercase">
                                    {{ $extArch }}
                                    @if($arch->tamano) · {{ number_format($arch->tamano / 1024 / 1024, 2) }} MB @endif
                                </p>
                            </div>
                            <a href="{{ $urlArch }}" target="_blank" download
                               class="inline-flex items-center gap-1 text-dyl-orange-600 hover:text-dyl-orange-700 text-xs font-medium flex-shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Descargar
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>

    {{-- Visor de PDF a ancho completo --}}
    @if($esPdf)
    <div class="bg-white rounded-lg shadow p-5" x-data="{ alto: 75 }">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-dyl-graphite-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h2 class="text-base font-semibold text-gray-800">Documento adjunto</h2>
            </div>
            <div class="flex items-center gap-3">
                {{-- Control de altura --}}
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <button type="button" @click="alto = Math.max(40, alto - 15)"
                            class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">－</button>
                    <span x-text="alto + 'vh'"></span>
                    <button type="button" @click="alto = Math.min(95, alto + 15)"
                            class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">＋</button>
                </div>
                <a href="{{ $urlAdj }}" target="_blank" download
                   class="inline-flex items-center gap-1.5 text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar PDF
                </a>
            </div>
        </div>
        <iframe src="{{ $urlAdj }}"
                class="w-full rounded-lg border border-gray-200 block"
                :style="'height:' + alto + 'vh'"
                title="Documento PDF adjunto"></iframe>
    </div>
    @endif

</div>
@endsection
