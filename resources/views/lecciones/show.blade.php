@extends('layouts.app')
@section('title', $leccion->titulo . ' - ' . $curso->titulo)
@php $fullWidth = true; @endphp

@section('content')
<div x-data="{ sidebarOpen: false }" class="flex min-h-[calc(100vh-3.5rem)]">

    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="lg:hidden fixed inset-0 bg-black/50 z-40"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    {{-- ============================================================
         SIDEBAR — índice del curso
    ============================================================ --}}
    {{-- Nace cerrado en móvil (-translate-x-full estático) para que no parpadee antes de que Alpine cargue --}}
    <aside :class="sidebarOpen && '!translate-x-0'"
           class="-translate-x-full lg:translate-x-0 lg:relative lg:z-auto flex-col w-72 xl:w-80 bg-white border-r border-gray-200 flex-shrink-0
                  fixed inset-y-0 left-0 z-50 lg:sticky lg:top-14 h-[calc(100vh-3.5rem)] overflow-y-auto
                  transition-transform duration-300 flex">

        {{-- Cabecera sidebar --}}
        <div class="p-4 border-b border-gray-200">
            <a href="{{ route('cursos.show', $curso) }}" class="text-xs text-dyl-orange-700 hover:underline">&larr; Ver curso</a>
            <h2 class="font-display text-lg font-semibold text-gray-900 mt-1 leading-snug line-clamp-2">{{ $curso->titulo }}</h2>

            {{-- Mini barra de progreso --}}
            @php $totalSidebar = $modulos->flatMap->lecciones->count(); @endphp
            @if($totalSidebar > 0)
                @php $pctSidebar = (int) round(($completadasIds->count() / $totalSidebar) * 100); @endphp
                <div class="mt-3" role="progressbar" aria-valuenow="{{ $pctSidebar }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso del curso">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $completadasIds->count() }}/{{ $totalSidebar }} {{ $totalSidebar === 1 ? 'lección' : 'lecciones' }}</span>
                        <span class="tabular-nums">{{ $pctSidebar }}%</span>
                    </div>
                    <div class="progress-bar h-1.5"><div class="progress-blue" style="width: {{ $pctSidebar }}%"></div></div>
                </div>
            @endif
        </div>

        {{-- Lista de módulos y lecciones --}}
        <nav class="flex-1 overflow-y-auto py-2" aria-label="Índice del curso">
            @foreach($modulos as $modulo)
                <div class="mb-1">
                    <div class="px-4 pt-3 pb-1.5 text-sm font-semibold text-gray-900">
                        {{ $modulo->orden + 1 }}. {{ $modulo->titulo }}
                    </div>
                    @foreach($modulo->lecciones as $lis)
                        @php $isActual = $lis->id === $leccion->id; @endphp
                        @php $isDone   = $completadasIds->contains($lis->id); @endphp
                        <a href="{{ route('lecciones.show', $lis) }}" @if($isActual) aria-current="page" @endif
                           class="flex items-center px-4 py-2.5 text-sm transition-colors
                                  {{ $isActual ? 'bg-dyl-orange-50 text-dyl-orange-700 font-medium border-l-2 border-dyl-orange-600' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{-- Icono estado --}}
                            <span class="mr-2.5 flex-shrink-0">
                                @if($isDone)
                                    <svg class="w-4 h-4 text-dyl-orange-700" fill="currentColor" viewBox="0 0 20 20" role="img" aria-label="Completada">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                @elseif($isActual)
                                    <svg class="w-4 h-4 text-dyl-orange-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-dyl-graphite-300" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </span>
                            <span class="line-clamp-2 text-sm leading-snug">{{ $lis->titulo }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- ============================================================
         CONTENIDO PRINCIPAL
    ============================================================ --}}
    <div class="flex-1 min-w-0 px-4 sm:px-6 lg:px-10 py-8">
      <div class="max-w-3xl mx-auto">

        {{-- Breadcrumb móvil --}}
        <div class="lg:hidden mb-4 flex items-center gap-2 text-sm text-gray-500">
            <button @click="sidebarOpen = true" class="p-1.5 -ml-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" aria-label="Abrir índice del curso">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            </button>
            <a href="{{ route('cursos.show', $curso) }}" class="text-dyl-orange-700 hover:underline truncate">{{ $curso->titulo }}</a>
        </div>

        {{-- Cabecera de lección --}}
        <header class="mb-6">
            <p class="text-sm text-gray-500 mb-2">{{ $leccion->modulo->titulo }}</p>
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-gray-900 break-words">{{ $leccion->titulo }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 mt-3">
                <span>{{ $leccion->duracion_minutos }} min</span>
                <span>{{ ucfirst($leccion->tipo) }}</span>
                @if($estaCompletada)
                    <span class="badge-success">Completada</span>
                    @if($tiempoReal)
                        <span>Estudiada {{ $tiempoReal }} min</span>
                    @endif
                @endif
            </div>
        </header>

        {{-- Player de video --}}
        @if($leccion->video_url)
            @php $embedUrl = $leccion->embedUrl(); @endphp
            <div class="mb-8 bg-black rounded-2xl overflow-hidden shadow-lg">
                @if(str_contains($embedUrl, 'youtube.com/embed') || str_contains($embedUrl, 'player.vimeo.com'))
                    <div class="relative w-full" style="padding-top: 56.25%">
                        <iframe
                            class="absolute inset-0 w-full h-full"
                            src="{{ $embedUrl }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            title="{{ $leccion->titulo }}"
                        ></iframe>
                    </div>
                @else
                    <video controls class="w-full max-h-[500px]" src="{{ $embedUrl }}">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                @endif
            </div>
        @endif

        {{-- Contenido HTML de la lección --}}
        @if($leccion->contenido_html)
            <div class="leccion-contenido card p-6 sm:p-8 mb-8">
                {!! $leccion->contenido_html !!}
            </div>
            <style>
                .leccion-contenido img {
                    display: block;
                    width: 100%;
                    height: 400px;
                    object-fit: contain;
                    background-color: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 0.5rem;
                    margin: 1.25rem auto;
                }
            </style>
        @elseif(!$leccion->video_url)
            <div class="bg-dyl-graphite-50 border-2 border-dyl-orange-300 rounded-xl p-6 mb-8 text-center text-dyl-graphite-900 font-medium">
                Esta lección aún no tiene contenido.
            </div>
        @endif

        {{-- Actividades de la lección --}}
        @if($actividades->isNotEmpty())
            <section class="mb-8" aria-labelledby="actividades-titulo">
                <h2 id="actividades-titulo" class="font-display text-2xl font-semibold text-gray-900 mb-3">Actividades de esta lección</h2>
                <div class="space-y-3">
                    @foreach($actividades as $actividad)
                        @php $completada = $actividadesCompletadasIds->contains($actividad->id); @endphp
                        <a href="{{ route('actividades.show', $actividad) }}"
                           class="card-hover flex items-center justify-between gap-4 px-5 py-4 group">
                            <div class="flex items-center gap-3 min-w-0">
                                {{-- Icono por tipo --}}
                                <span class="text-dyl-graphite-500" aria-hidden="true">
                                    <x-icon :name="match($actividad->tipo) { 'cuestionario' => 'pregunta', 'ensayo' => 'lapiz', 'tarea' => 'tarea', default => 'encuesta' }" class="w-6 h-6"/>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 group-hover:text-dyl-orange-700">{{ $actividad->tituloCompleto() }}</p>
                                    <p class="text-xs text-gray-500">{{ ucfirst($actividad->tipo) }}@if($actividad->puntaje_maximo) &middot; {{ $actividad->puntaje_maximo }} pts @endif</p>
                                </div>
                            </div>
                            @if($completada)
                                <span class="badge-success shrink-0">Completada</span>
                            @else
                                <span class="text-gray-400 text-sm group-hover:text-dyl-orange-700" aria-hidden="true">&rarr;</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Estado de la lección --}}
        @if(!$estaCompletada)
            <div class="mb-8 alert alert-info">
                <svg class="w-5 h-5 text-dyl-graphite-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Completa todas las actividades de esta lección para marcarla como finalizada.</span>
            </div>
        @endif

        {{-- Navegación anterior / siguiente --}}
        <nav class="grid sm:grid-cols-2 gap-3 pt-6 border-t border-gray-200" aria-label="Lecciones">
            @if($prevLeccion)
                <a href="{{ route('lecciones.show', $prevLeccion) }}"
                   class="card-hover flex items-center gap-3 px-4 py-3 group">
                    <svg class="w-5 h-5 shrink-0 text-gray-400 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">Anterior</p>
                        <p class="text-sm font-medium text-gray-900 line-clamp-1">{{ $prevLeccion->titulo }}</p>
                    </div>
                </a>
            @else
                <span class="hidden sm:block"></span>
            @endif
            @if($nextLeccion)
                <a href="{{ route('lecciones.show', $nextLeccion) }}"
                   class="card-hover flex items-center justify-end gap-3 px-4 py-3 group text-right">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">Siguiente</p>
                        <p class="text-sm font-medium text-gray-900 line-clamp-1">{{ $nextLeccion->titulo }}</p>
                    </div>
                    <svg class="w-5 h-5 shrink-0 text-gray-400 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @else
                <a href="{{ route('cursos.show', $curso) }}"
                   class="card-hover flex items-center justify-end gap-3 px-4 py-3 group text-right">
                    <div class="min-w-0">
                        <p class="text-xs text-dyl-orange-700">Última lección del curso</p>
                        <p class="text-sm font-medium text-gray-900">Ver resumen del curso</p>
                    </div>
                    <svg class="w-5 h-5 shrink-0 text-dyl-orange-700 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endif
        </nav>

        {{-- Controles del instructor --}}
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <div class="mt-6 pt-4 border-t border-gray-100 flex flex-wrap gap-3">
                <a href="{{ route('lecciones.edit', $leccion) }}" class="btn-ghost">Editar lección</a>
                <a href="{{ route('actividades.create', $leccion) }}" class="btn-outline">+ Agregar actividad</a>
            </div>
        @endif

      </div>
    </div>
</div>
@endsection
