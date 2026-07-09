@extends('layouts.app')
@section('title', $leccion->titulo . ' - ' . $curso->titulo)
@php $fullWidth = true; @endphp

@section('content')
<div x-data="{ sidebarOpen: false }" class="flex min-h-[calc(100vh-4rem)]">

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
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="lg:translate-x-0 lg:relative lg:z-auto flex-col w-72 xl:w-80 bg-white border-r border-gray-200 flex-shrink-0
                  fixed inset-y-0 left-0 z-50 lg:sticky lg:top-16 h-[calc(100vh-4rem)] overflow-y-auto
                  transition-transform duration-300 flex">

        {{-- Cabecera sidebar --}}
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <a href="{{ route('cursos.show', $curso) }}" class="text-xs text-blue-600 hover:underline">&larr; Ver curso</a>
            <h2 class="text-sm font-bold text-gray-900 mt-1 line-clamp-2">{{ $curso->titulo }}</h2>

            {{-- Mini barra de progreso --}}
            @php $totalSidebar = $modulos->flatMap->lecciones->count(); @endphp
            @if($totalSidebar > 0)
                @php $pctSidebar = (int) round(($completadasIds->count() / $totalSidebar) * 100); @endphp
                <div class="mt-2">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $completadasIds->count() }}/{{ $totalSidebar }} lecciones</span>
                        <span>{{ $pctSidebar }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full transition-all" style="width: {{ $pctSidebar }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Lista de módulos y lecciones --}}
        <nav class="flex-1 overflow-y-auto py-2">
            @foreach($modulos as $modulo)
                <div class="mb-1">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50 border-b border-gray-100">
                        {{ $modulo->orden + 1 }}. {{ $modulo->titulo }}
                    </div>
                    @foreach($modulo->lecciones as $lis)
                        @php $isActual = $lis->id === $leccion->id; @endphp
                        @php $isDone   = $completadasIds->contains($lis->id); @endphp
                        <a href="{{ route('lecciones.show', $lis) }}"
                           class="flex items-center px-4 py-2.5 text-sm transition-colors
                                  {{ $isActual ? 'bg-blue-50 text-blue-700 font-medium border-l-2 border-blue-500' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{-- Icono estado --}}
                            <span class="mr-2.5 flex-shrink-0">
                                @if($isDone)
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                @elseif($isActual)
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2-4.5l5-3-5-3v6z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </span>
                            <span class="line-clamp-2 text-xs leading-snug">{{ $lis->titulo }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- ============================================================
         CONTENIDO PRINCIPAL
    ============================================================ --}}
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-6 max-w-4xl">

        {{-- Mensajes flash --}}
        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Breadcrumb móvil --}}
        <div class="lg:hidden mb-4 flex items-center gap-2 text-sm text-gray-500">
            <button @click="sidebarOpen = true" class="p-1.5 -ml-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" aria-label="Abrir índice del curso">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            </button>
            <a href="{{ route('cursos.show', $curso) }}" class="text-blue-600 hover:underline truncate">{{ $curso->titulo }}</a>
            <span class="text-gray-300">/</span>
            <span class="truncate">{{ $leccion->titulo }}</span>
        </div>

        {{-- Cabecera de lección --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                <span>{{ $leccion->modulo->titulo }}</span>
                <span>·</span>
                <span>{{ $leccion->duracion_minutos }} min</span>
                <span>·</span>
                <span>{{ ucfirst($leccion->tipo) }}</span>
                @if($estaCompletada)
                    <span class="ml-1 px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">Completada</span>
                    @if($tiempoReal)
                        <span class="text-gray-400">· Estudiada {{ $tiempoReal }} min</span>
                    @endif
                @endif
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $leccion->titulo }}</h1>
        </div>

        {{-- Player de video --}}
        @if($leccion->video_url)
            @php $embedUrl = $leccion->embedUrl(); @endphp
            <div class="mb-8 bg-black rounded-xl overflow-hidden shadow-lg">
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
            <div class="leccion-contenido prose prose-lg max-w-none bg-white rounded-xl shadow-sm p-6 sm:p-8 mb-8 leading-relaxed
                        prose-headings:text-gray-900 prose-p:text-gray-700 prose-a:text-blue-600">
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
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8 text-center text-yellow-700">
                Esta lección aún no tiene contenido.
            </div>
        @endif

        {{-- Actividades de la lección --}}
        @if($actividades->isNotEmpty())
            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 mb-3">Actividades de esta lección</h2>
                <div class="space-y-3">
                    @foreach($actividades as $actividad)
                        @php $completada = $actividadesCompletadasIds->contains($actividad->id); @endphp
                        <a href="{{ route('actividades.show', $actividad) }}"
                           class="flex items-center justify-between bg-white rounded-lg border border-gray-200 px-5 py-4 hover:border-blue-300 hover:shadow-sm transition-all group">
                            <div class="flex items-center gap-3">
                                {{-- Icono por tipo --}}
                                <span class="text-xl">
                                    @if($actividad->tipo === 'cuestionario') &#10067;
                                    @elseif($actividad->tipo === 'ensayo')    &#9997;
                                    @elseif($actividad->tipo === 'tarea')     &#128203;
                                    @else                                     &#127919;
                                    @endif
                                </span>
                                <div>
                                    <p class="font-medium text-gray-900 group-hover:text-blue-700">{{ $actividad->titulo }}</p>
                                    <p class="text-xs text-gray-400">{{ ucfirst($actividad->tipo) }} · {{ $actividad->puntaje_maximo }} pts</p>
                                </div>
                            </div>
                            @if($completada)
                                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium">Completada</span>
                            @else
                                <span class="text-gray-400 text-sm group-hover:text-blue-600">&rarr;</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Estado de la lección --}}
        @if(!$estaCompletada)
            <div class="mb-8 px-5 py-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700 flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Completa todas las actividades de esta lección para marcarla como finalizada.</span>
            </div>
        @endif

        {{-- Navegación anterior / siguiente --}}
        <div class="flex justify-between items-center pt-6 border-t border-gray-200">
            <div>
                @if($prevLeccion)
                    <a href="{{ route('lecciones.show', $prevLeccion) }}"
                       class="flex items-center gap-2 text-gray-600 hover:text-blue-600 group">
                        <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <div class="text-left">
                            <p class="text-xs text-gray-400">Anterior</p>
                            <p class="text-sm font-medium line-clamp-1">{{ $prevLeccion->titulo }}</p>
                        </div>
                    </a>
                @endif
            </div>
            <div class="text-right">
                @if($nextLeccion)
                    <a href="{{ route('lecciones.show', $nextLeccion) }}"
                       class="flex items-center gap-2 text-gray-600 hover:text-blue-600 group">
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Siguiente</p>
                            <p class="text-sm font-medium line-clamp-1">{{ $nextLeccion->titulo }}</p>
                        </div>
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('cursos.show', $curso) }}"
                       class="flex items-center gap-2 text-green-600 hover:text-green-700 font-medium group">
                        <div class="text-right">
                            <p class="text-xs text-green-500">Has terminado el módulo</p>
                            <p class="text-sm font-medium">Ver resumen del curso</p>
                        </div>
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Controles del instructor --}}
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <div class="mt-6 pt-4 border-t border-gray-100 flex gap-3">
                <a href="{{ route('lecciones.edit', $leccion) }}"
                   class="text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                    Editar lección
                </a>
                <a href="{{ route('actividades.create', $leccion) }}"
                   class="text-sm bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200">
                    + Agregar actividad
                </a>
            </div>
        @endif

    </main>
</div>
@endsection
