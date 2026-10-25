@extends('layouts.app')
@section('title', $curso->titulo . ' - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.show', $curso) }}@endsection
@section('content')

{{-- Hero del curso --}}
<header class="card overflow-hidden mb-8">
    <div class="h-40 sm:h-56 relative">
        @if($curso->imagen_portada)
            <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
        @else
            <x-portada-curso :curso="$curso" class="absolute inset-0"/>
        @endif
    </div>

    <div class="p-6 sm:p-8">
        @if($curso->estado !== 'publicado')
            <span class="{{ $curso->estado === 'borrador' ? 'badge-warning' : 'badge-info' }} mb-3">{{ ucfirst($curso->estado) }}</span>
        @endif
        <h1 class="font-display text-3xl sm:text-4xl font-semibold text-gray-900 mb-3 break-words">{{ $curso->titulo }}</h1>
        {{-- Descripción larga: se recorta para que el avance y "Continuar" no queden fuera de la vista --}}
        <div class="max-w-3xl" x-data="{ abierta: false, larga: false }" x-init="$nextTick(() => larga = $refs.desc.scrollHeight > 170)">
            <div x-ref="desc" :class="larga && !abierta ? 'max-h-40 overflow-hidden' : ''">
                @include('components.descripcion-render', ['slot' => $curso->descripcion])
            </div>
            <button type="button" x-show="larga" x-cloak @click="abierta = !abierta" :aria-expanded="abierta"
                    class="mt-2 text-sm font-medium text-dyl-orange-700 hover:underline"
                    x-text="abierta ? 'Ver menos' : 'Ver descripción completa'"></button>
        </div>
        <dl class="flex flex-wrap gap-x-10 gap-y-3 mt-5 text-sm">
            <div><dt class="text-gray-500">Duración</dt><dd class="font-medium text-gray-900">{{ $curso->duracion_horas }} horas</dd></div>
            <div><dt class="text-gray-500">Instructor</dt><dd class="font-medium text-gray-900">{{ $curso->creador->name }}</dd></div>
            <div><dt class="text-gray-500">Contenido</dt><dd class="font-medium text-gray-900">{{ $totalLecciones }} {{ $totalLecciones === 1 ? 'lección' : 'lecciones' }}</dd></div>
        </dl>
    </div>
</header>

{{-- Contenido del curso --}}
@php
    $puedeEditar = auth()->user()->can('update', $curso);
    $modoEdicion = $puedeEditar && session('modo_edicion', false);
@endphp

<div class="grid lg:grid-cols-3 gap-8 items-start">

{{-- Panel de acción: primero en móvil, columna fija a la derecha en escritorio --}}
<aside class="lg:col-span-1 lg:order-2 lg:sticky lg:top-6 min-w-0 space-y-4" aria-label="Tu avance en el curso">
    <div class="card p-5">
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            {{-- Instructor: botón editar --}}
            <a href="{{ route('cursos.edit', $curso) }}" class="btn-primary w-full mb-2">Editar curso</a>
            <a href="{{ route('cursos.inscripcion-masiva', $curso) }}" class="btn-outline w-full">Inscribir estudiantes</a>
        @elseif($estaInscrito)
            {{-- Inscrito: progreso y continuar --}}
            <p class="text-sm text-gray-500">Tu progreso</p>
            <p class="font-display text-4xl font-semibold tabular-nums {{ $porcentaje === 100 ? 'text-dyl-orange-700' : 'text-gray-900' }}">{{ $porcentaje }}%</p>
            <div class="progress-bar my-2" role="progressbar" aria-valuenow="{{ $porcentaje }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso del curso">
                <div class="progress-blue" style="width: {{ $porcentaje }}%"></div>
            </div>
            <p class="text-sm text-gray-600 mb-4">{{ $completadasIds->count() }}/{{ $totalLecciones }} lecciones</p>
            @php
                $primeraLeccionSinCompletar = $modulos->flatMap->lecciones->first(
                    fn($l) => !$completadasIds->contains($l->id)
                );
            @endphp
            @if($primeraLeccionSinCompletar)
                <a href="{{ route('lecciones.show', $primeraLeccionSinCompletar) }}" class="btn-primary w-full">Continuar &rarr;</a>
            @else
                <div class="text-center text-dyl-orange-700 font-medium text-sm mb-3">
                    &#10003; Curso completado
                </div>
                @php
                    $certExistente = \App\Models\Certificado::where('user_id', auth()->id())
                        ->where('curso_id', $curso->id)->first();
                @endphp
                @if($certExistente)
                    <a href="{{ route('certificados.show', $certExistente) }}" class="btn-primary w-full">
                        <x-icon name="certificado" class="w-4 h-4"/>Ver Certificado
                    </a>
                @else
                    <div class="text-center text-gray-600 text-sm px-4 py-3 bg-gray-50 rounded-lg">
                        Tu certificado está pendiente de revisión del instructor.
                    </div>
                @endif
            @endif
        @else
            {{-- No inscrito: el acceso lo otorga un administrador o el instructor del curso --}}
            <p class="text-sm text-gray-600">Aún no tienes acceso a este curso. Contacta a tu administrador o instructor para que te inscriba.</p>
        @endif
    </div>

    <div class="flex gap-3">
        <a href="{{ route('anuncios.index', $curso) }}" class="btn-outline flex-1">Anuncios</a>
        <a href="{{ route('foros.index', $curso) }}" class="btn-outline flex-1">Foros</a>
    </div>
</aside>

<div class="lg:col-span-2 lg:order-1 min-w-0">

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-2xl font-semibold text-gray-900">Contenido del curso</h2>
    @if($puedeEditar)
        <form method="POST" action="{{ route('cursos.modo-edicion', $curso) }}">
            @csrf
            <button class="rounded-lg border px-4 py-2 text-sm font-medium {{ $modoEdicion ? 'border-dyl-orange-600 bg-dyl-orange-50 text-dyl-orange-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                {{ $modoEdicion ? 'Salir del modo de edición' : 'Activar modo de edición' }}
            </button>
        </form>
    @endif
</div>

@if($modoEdicion)
    <div class="alert alert-warning mb-4" role="status">
        Modo de edición activo: ves los controles para editar el curso. Los estudiantes ven la vista normal.
        <span class="ml-2 inline-flex flex-wrap gap-3 text-sm font-medium">
            <a class="underline" href="{{ route('cursos.edit', $curso) }}">Editar curso</a>
            <a class="underline" href="{{ route('cursos.ponderaciones.edit', $curso) }}">Ponderaciones</a>
            <a class="underline" href="{{ route('cursos.ficha.edit', $curso) }}">Ficha académica</a>
            <a class="underline" href="{{ route('cursos.competencias.index', $curso) }}">Competencias</a>
            <a class="underline" href="{{ route('reportes.seguimiento', $curso) }}">Seguimiento</a>
        </span>
    </div>
@endif

@forelse($modulos as $modulo)
    <div class="card mb-4 overflow-hidden" x-data="{ open: true }">

        {{-- Cabecera del módulo --}}
        <button @click="open = !open" :aria-expanded="open"
                class="w-full flex justify-between items-center p-5 text-left hover:bg-gray-50 transition-colors">
            <div class="min-w-0">
                <h3 class="font-display text-lg font-semibold text-gray-900">
                    {{ $modulo->orden + 1 }}. {{ $modulo->titulo }}
                </h3>
                @if($modulo->descripcion)
                    <div class="text-sm text-gray-500 mt-0.5">
                        @include('components.descripcion-render', ['slot' => $modulo->descripcion])
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3 ml-4 shrink-0">
                <span class="text-xs text-gray-500">{{ $modulo->lecciones->count() }} {{ $modulo->lecciones->count() === 1 ? 'lección' : 'lecciones' }}</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        @if($modoEdicion)
            <div class="flex flex-wrap gap-3 border-t border-gray-100 bg-dyl-orange-50 px-5 py-2 text-xs font-medium">
                <a class="text-dyl-orange-800 hover:underline" href="{{ route('modulos.edit', $modulo) }}">Editar módulo</a>
                <a class="text-dyl-orange-800 hover:underline" href="{{ route('lecciones.create', $modulo) }}">+ Lección</a>
            </div>
        @endif

        {{-- Lista de lecciones --}}
        <div x-show="open" x-cloak class="border-t border-gray-100">
            @forelse($modulo->lecciones as $leccion)
                @php $done = $completadasIds->contains($leccion->id); @endphp
                <div class="flex items-center px-5 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 group">
                    {{-- Icono completado --}}
                    <span class="mr-3 flex-shrink-0">
                        @if($done)
                            <svg class="w-5 h-5 text-dyl-orange-700" fill="currentColor" viewBox="0 0 20 20" role="img" aria-label="Completada">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                    </span>

                    <div class="flex-1 min-w-0">
                        @if($estaInscrito || auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
                            <a href="{{ route('lecciones.show', $leccion) }}"
                               class="text-sm font-medium {{ $done ? 'text-gray-500 line-through' : 'text-gray-800 hover:text-dyl-orange-700' }}">
                                {{ $leccion->titulo }}
                            </a>
                        @else
                            <span class="text-sm font-medium text-gray-500">{{ $leccion->titulo }}</span>
                        @endif

                        {{-- Actividades de la lección --}}
                        @if($leccion->actividades->isNotEmpty())
                            <span class="ml-2 text-xs text-gray-500">
                                {{ $leccion->actividades->count() }} actividad(es)
                            </span>
                        @endif
                    </div>

                    @if($modoEdicion)
                        <div class="ml-3 flex flex-shrink-0 flex-wrap items-center gap-2 text-xs font-medium">
                            <a class="text-dyl-orange-800 hover:underline" href="{{ route('lecciones.edit', $leccion) }}">Editar</a>
                            <a class="text-dyl-orange-800 hover:underline" href="{{ route('actividades.create', $leccion) }}">+ Actividad</a>
                            @foreach($leccion->actividades as $act)
                                <a class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 hover:bg-gray-200" href="{{ route('actividades.edit', $act) }}">{{ $act->tituloCompleto() }}</a>
                            @endforeach
                        </div>
                    @endif

                    <span class="text-xs text-gray-500 ml-3 flex-shrink-0 tabular-nums">{{ $leccion->duracion_minutos }} min</span>
                </div>
            @empty
                <p class="px-5 py-3 text-sm text-gray-500">Sin lecciones en este módulo</p>
            @endforelse
        </div>
    </div>
@empty
    <div class="card text-center py-16 text-gray-500">Este curso no tiene módulos aún.</div>
@endforelse

</div>
</div>

@include('cursos.partials.competencias', ['curso' => $curso])
@include('cursos.partials.ficha', ['curso' => $curso->loadMissing('ficha', 'bibliografias', 'creador')])

@endsection

