@extends('layouts.app')
@section('title', $curso->titulo . ' - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.show', $curso) }}@endsection
@section('content')

{{-- Hero del curso --}}
<div class="bg-white rounded-xl shadow overflow-hidden mb-8">
    {{-- Imagen de portada o gradiente --}}
    <div class="h-40 sm:h-56 relative
        {{ $curso->imagen_portada ? '' : 'bg-gradient-to-br from-dyl-orange-500 to-dyl-orange-700' }}">
        @if($curso->imagen_portada)
            <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="{{ $curso->titulo }}"
                 class="w-full h-full object-cover">
        @endif
        <div class="absolute inset-0 bg-black/30 flex items-end p-6">
            <span class="px-3 py-1 rounded-full text-xs font-medium
                @if($curso->estado === 'publicado') bg-dyl-orange-600 text-white
                @elseif($curso->estado === 'borrador') bg-dyl-graphite-200 text-dyl-graphite-900
                @else bg-dyl-graphite-600 text-white @endif">
                {{ ucfirst($curso->estado) }}
            </span>
        </div>
    </div>

    <div class="p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div class="flex-1">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">{{ $curso->titulo }}</h1>
                                @include('components.descripcion-render', ['slot' => $curso->descripcion])
                <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $curso->duracion_horas }} horas
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $curso->creador->name }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        {{ $totalLecciones }} lecciones
                    </span>
                </div>
            </div>

            {{-- Panel de acción derecha --}}
            <div class="sm:w-56 flex-shrink-0">
                @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
                    {{-- Instructor: botón editar --}}
                    <a href="{{ route('cursos.edit', $curso) }}"
                       class="block w-full text-center bg-dyl-orange-600 text-white px-6 py-3 rounded-lg hover:bg-dyl-orange-700 font-medium mb-2">
                        Editar curso
                    </a>
                    <a href="{{ route('cursos.inscripcion-masiva', $curso) }}"
                       class="block w-full text-center bg-white border border-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-50 font-medium text-sm">
                        Inscribir estudiantes
                    </a>
                @elseif($estaInscrito)
                    {{-- Inscrito: mostrar progreso y continuar --}}
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 mb-1">Tu progreso</p>
                        <div class="flex justify-between text-sm font-medium mb-1">
                            <span>{{ $completadasIds->count() }}/{{ $totalLecciones }} lecciones</span>
                            <span class="{{ $porcentaje === 100 ? 'text-dyl-orange-600' : 'text-dyl-graphite-500' }}">{{ $porcentaje }}%</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden mb-3">
                            <div class="h-full rounded-full transition-all bg-dyl-orange-600"
                                 style="width: {{ $porcentaje }}%"></div>
                        </div>
                        @php
                            $primeraLeccionSinCompletar = $modulos->flatMap->lecciones->first(
                                fn($l) => !$completadasIds->contains($l->id)
                            );
                        @endphp
                        @if($primeraLeccionSinCompletar)
                            <a href="{{ route('lecciones.show', $primeraLeccionSinCompletar) }}"
                               class="block w-full text-center bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                                Continuar &rarr;
                            </a>
                        @else
                            <div class="text-center text-dyl-orange-700 font-medium text-sm mb-2">
                                &#10003; Curso completado
                            </div>
                            @php
                                $certExistente = \App\Models\Certificado::where('user_id', auth()->id())
                                    ->where('curso_id', $curso->id)->first();
                            @endphp
                            @if($certExistente)
                                <a href="{{ route('certificados.show', $certExistente) }}"
                                   class="block w-full text-center bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                                    &#127941; Ver Certificado
                                </a>
                            @else
                                <form action="{{ route('certificados.generar', $curso) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="w-full bg-dyl-orange-600 text-white px-4 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                                        &#127941; Obtener Certificado
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                @else
                    {{-- No inscrito: el acceso lo otorga un administrador o el instructor del curso --}}
                    <div class="w-full text-center bg-gray-100 text-gray-600 px-4 py-3 rounded-lg text-sm">
                        Aún no tienes acceso a este curso. Contacta a tu administrador o instructor para que te inscriba.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Contenido del curso --}}
<h2 class="text-xl font-bold text-gray-900 mb-4">Contenido del curso</h2>

@forelse($modulos as $modulo)
    <div class="bg-white rounded-xl shadow mb-4 overflow-hidden" x-data="{ open: true }">

        {{-- Cabecera del módulo --}}
        <button @click="open = !open"
                class="w-full flex justify-between items-center p-5 text-left hover:bg-gray-50 transition-colors">
            <div>
                <h3 class="text-base font-bold text-gray-900">
                    {{ $modulo->orden + 1 }}. {{ $modulo->titulo }}
                </h3>
                @if($modulo->descripcion)
                    <div class="text-sm text-gray-500 mt-0.5">
                        @include('components.descripcion-render', ['slot' => $modulo->descripcion])
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3 ml-4">
                <span class="text-xs text-gray-400">{{ $modulo->lecciones->count() }} lecciones</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        {{-- Lista de lecciones --}}
        <div x-show="open" x-cloak class="border-t border-gray-100">
            @forelse($modulo->lecciones as $leccion)
                @php $done = $completadasIds->contains($leccion->id); @endphp
                <div class="flex items-center px-5 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 group">
                    {{-- Icono completado --}}
                    <span class="mr-3 flex-shrink-0">
                        @if($done)
                            <svg class="w-5 h-5 text-dyl-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                    </span>

                    <div class="flex-1 min-w-0">
                        @if($estaInscrito || auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
                            <a href="{{ route('lecciones.show', $leccion) }}"
                               class="text-sm font-medium {{ $done ? 'text-gray-500 line-through' : 'text-gray-800 hover:text-dyl-orange-600' }}">
                                {{ $leccion->titulo }}
                            </a>
                        @else
                            <span class="text-sm font-medium text-gray-500">{{ $leccion->titulo }}</span>
                        @endif

                        {{-- Actividades de la lección --}}
                        @if($leccion->actividades->isNotEmpty())
                            <span class="ml-2 text-xs text-gray-400">
                                {{ $leccion->actividades->count() }} actividad(es)
                            </span>
                        @endif
                    </div>

                    <span class="text-xs text-gray-400 ml-3 flex-shrink-0">{{ $leccion->duracion_minutos }} min</span>
                </div>
            @empty
                <p class="px-5 py-3 text-sm text-gray-400">Sin lecciones en este módulo</p>
            @endforelse
        </div>
    </div>
@empty
    <div class="text-center py-16 text-gray-500">Este curso no tiene módulos aún.</div>
@endforelse

{{-- Foros del curso --}}
<div class="mt-6 flex flex-wrap gap-3 justify-center">
    <a href="{{ route('anuncios.index', $curso) }}"
       class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-50 text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
        Anuncios
    </a>
    <a href="{{ route('foros.index', $curso) }}"
       class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-50 text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
        Foros
    </a>
</div>

@endsection

