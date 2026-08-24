@extends('layouts.app')
@section('title', 'Revisar Cuestionario - LMS DyL')
@section('content')

<div class="max-w-3xl mx-auto">

    <div class="mb-5">
        <a href="{{ route('calificaciones.curso', $actividad->leccion->modulo->curso) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Volver a calificaciones</a>
    </div>

    {{-- Encabezado --}}
    <div class="card card-body mb-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Revisión de cuestionario</p>
                <h1 class="text-xl font-bold text-gray-900">{{ $actividad->titulo }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $respuesta->usuario->name }} — {{ $respuesta->usuario->email }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    Enviado {{ $respuesta->fecha_envio->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-2xl font-bold text-gray-900">{{ $actividad->puntaje_maximo }}</p>
                <p class="text-xs text-gray-400">pts totales</p>
            </div>
        </div>
    </div>

    <form action="{{ route('calificaciones.publicar', $respuesta) }}" method="POST" class="space-y-4">
        @csrf

        @foreach($preguntas as $index => $pregunta)
        @php
            $respuestaEstudiante = $respuestas[$pregunta->id] ?? null;
            $esCorta = $pregunta->tipo === 'respuesta_corta';
        @endphp

        <div class="card overflow-hidden {{ $esCorta ? 'ring-2 ring-dyl-orange-600/30' : '' }}">

            {{-- Cabecera de pregunta --}}
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-semibold text-gray-400 shrink-0">{{ $index + 1 }}</span>
                    <span class="text-sm font-medium text-gray-800">{{ $pregunta->pregunta_texto }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($esCorta)
                        <span class="badge badge-blue text-[10px]">Revisión manual</span>
                    @endif
                    <span class="text-xs text-gray-400">{{ $pregunta->puntaje }} pts</span>
                </div>
            </div>

            {{-- Imagen de apoyo --}}
            @if($pregunta->imagen_path)
            <div class="px-5 pt-3">
                <img src="{{ $pregunta->imagenUrl() }}"
                     class="w-full h-56 object-contain rounded-lg border border-gray-200 bg-gray-50">
            </div>
            @endif

            <div class="px-5 py-4">

                {{-- ===== RESPUESTA CORTA: requiere decisión ===== --}}
                @if($esCorta)
                    <div class="mb-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Respuesta del estudiante</p>
                        <div class="bg-dyl-graphite-50 border border-dyl-graphite-200 rounded-lg px-4 py-3 text-sm text-gray-800">
                            {{ $respuestaEstudiante ?? '— Sin respuesta —' }}
                        </div>
                    </div>

                    <div x-data="{ decision: '' }" class="flex items-center gap-3">
                        <p class="text-sm font-medium text-gray-700 mr-1">¿Es correcta?</p>

                        <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm font-medium transition-colors"
                               :class="decision === '1' ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-700 ring-1 ring-dyl-orange-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                            <input type="radio" name="decisiones[{{ $pregunta->id }}]" value="1"
                                   x-model="decision" class="sr-only">
                            <span :class="decision === '1' ? 'text-dyl-orange-600' : 'text-gray-300'">✓</span>
                            Correcto
                            <span class="text-xs font-normal opacity-70">({{ $pregunta->puntaje }} pts)</span>
                        </label>

                        <label class="flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm font-medium transition-colors"
                               :class="decision === '0' ? 'border-dyl-graphite-600 bg-dyl-graphite-100 text-dyl-graphite-900 font-semibold ring-1 ring-dyl-graphite-400' : 'border-gray-200 text-gray-500 hover:border-gray-300'">
                            <input type="radio" name="decisiones[{{ $pregunta->id }}]" value="0"
                                   x-model="decision" class="sr-only">
                            <span :class="decision === '0' ? 'text-dyl-graphite-600' : 'text-gray-300'">✗</span>
                            Incorrecto
                            <span class="text-xs font-normal opacity-70">(0 pts)</span>
                        </label>
                    </div>

                {{-- ===== OPCIÓN MÚLTIPLE / V-F: auto-calificado, solo lectura ===== --}}
                @else
                    @php
                        $opcionCorrecta   = $pregunta->opciones->firstWhere('es_correcta', true);
                        $idsCorrectas     = $pregunta->opciones->where('es_correcta', true)->pluck('id')->map(fn($id) => (string)$id);
                        $seleccionadas    = collect(is_array($respuestaEstudiante) ? $respuestaEstudiante : [$respuestaEstudiante])->map(fn($id) => (string)$id);

                        if ($pregunta->seleccion_multiple) {
                            $acertadas     = $seleccionadas->intersect($idsCorrectas)->count();
                            $nCorrectas    = $idsCorrectas->count();
                            $ptsObtenidos  = $nCorrectas > 0 ? round(($acertadas / $nCorrectas) * $pregunta->puntaje, 1) : 0;
                        } else {
                            $ptsObtenidos = ($opcionCorrecta && (string)$opcionCorrecta->id === (string)$respuestaEstudiante)
                                ? $pregunta->puntaje : 0;
                        }
                    @endphp

                    <div class="space-y-1.5">
                        @foreach($pregunta->opciones as $opcion)
                        @php
                            $esCorrecta  = $opcion->es_correcta;
                            $fueElegida  = $seleccionadas->contains((string)$opcion->id);
                        @endphp
                        <div class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
                            @if($esCorrecta && $fueElegida) bg-dyl-orange-50 border border-dyl-orange-200
                            @elseif(!$esCorrecta && $fueElegida) bg-dyl-graphite-100 border border-dyl-graphite-300
                            @elseif($esCorrecta) bg-dyl-orange-50/50 border border-dyl-orange-100
                            @else bg-gray-50 border border-gray-100 @endif">
                            <span class="w-4 text-center font-bold shrink-0
                                @if($esCorrecta) text-dyl-orange-600
                                @elseif($fueElegida) text-dyl-graphite-600
                                @else text-gray-300 @endif">
                                @if($esCorrecta) ✓ @elseif($fueElegida) ✗ @else ○ @endif
                            </span>
                            <span class="{{ $fueElegida ? 'font-medium' : 'text-gray-600' }}">{{ $opcion->texto }}</span>
                            @if($fueElegida && !$esCorrecta)
                                <span class="ml-auto text-xs text-dyl-graphite-600 font-semibold">Elegida incorrectamente</span>
                            @elseif($esCorrecta && $fueElegida)
                                <span class="ml-auto text-xs text-dyl-orange-600">Correcta ✓</span>
                            @elseif($esCorrecta)
                                <span class="ml-auto text-xs text-dyl-orange-500 opacity-60">Correcta (no elegida)</span>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3 text-right text-sm">
                        <span class="{{ $ptsObtenidos > 0 ? 'text-dyl-orange-600 font-semibold' : 'text-gray-400' }}">
                            {{ $ptsObtenidos }} / {{ $pregunta->puntaje }} pts
                        </span>
                        <span class="text-gray-300 ml-1 text-xs">(auto-calificado)</span>
                    </div>
                @endif

            </div>
        </div>
        @endforeach

        {{-- Feedback y botón de publicar --}}
        <div class="card card-body">
            <label class="form-label">Comentario / Retroalimentación <span class="text-gray-400 font-normal">(opcional)</span></label>
            <textarea name="feedback" rows="3" class="form-textarea"
                      placeholder="Observaciones para el estudiante..."></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('calificaciones.curso', $actividad->leccion->modulo->curso) }}" class="btn-outline">Cancelar</a>
            <button type="submit" class="btn-primary">
                Publicar calificación
            </button>
        </div>

    </form>
</div>
@endsection
