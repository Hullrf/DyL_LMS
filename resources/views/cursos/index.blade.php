@extends('layouts.app')
@section('title', 'Cursos - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.index') }}@endsection
@section('content')

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Cursos</h1>
    @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
        <a href="{{ route('cursos.create') }}"
           class="bg-dyl-orange text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
            + Nuevo Curso
        </a>
    @endif
</div>

{{-- ============================================================
     SECCIÓN: MIS CURSOS
     ============================================================ --}}
@if($inscripciones->isNotEmpty())
<section class="mb-12">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-1 h-6 bg-dyl-orange rounded-full"></div>
        <h2 class="text-xl font-bold text-gray-900">Mis Cursos</h2>
        <span class="text-sm text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
            {{ $inscripciones->count() }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($inscripciones as $inscripcion)
        @php $curso = $inscripcion->curso; @endphp
        <div class="bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden flex flex-col">

            {{-- Imagen --}}
            @if($curso->imagen_portada)
                <img src="{{ asset('storage/' . $curso->imagen_portada) }}"
                     alt="{{ $curso->titulo }}" class="w-full h-44 object-cover">
            @else
                <div class="w-full h-44 bg-gradient-to-br from-dyl-orange to-orange-700 flex items-center justify-center">
                    <span class="text-5xl">📚</span>
                </div>
            @endif

            <div class="p-5 flex flex-col flex-1">
                {{-- Estado de inscripción --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium
                        @if($inscripcion->estado === 'completado') text-green-600 bg-green-100
                        @else text-dyl-orange bg-orange-100 @endif
                        px-2.5 py-0.5 rounded-full">
                        @if($inscripcion->estado === 'completado')
                            ✓ Completado
                        @else
                            En progreso
                        @endif
                    </span>
                    <span class="text-xs text-gray-400">{{ $curso->duracion_horas }} h</span>
                </div>

                <h3 class="text-base font-bold text-gray-900 line-clamp-2 mb-2">{{ $curso->titulo }}</h3>

                <p class="text-gray-500 text-sm line-clamp-2 flex-1 mb-4">
                    {{ Str::limit($curso->descripcion, 90) }}
                </p>

                <div class="mt-auto space-y-2">
                    <a href="{{ route('cursos.show', $curso) }}"
                       class="block w-full text-center py-2 rounded-lg font-medium text-sm bg-dyl-orange text-white hover:bg-dyl-orange-700 transition-colors">
                        Continuar →
                    </a>
                    @if($curso->created_by === auth()->id() || auth()->user()->esAdmin())
                        <a href="{{ route('cursos.edit', $curso) }}"
                           class="block w-full text-center bg-gray-100 text-gray-600 py-2 rounded-lg hover:bg-gray-200 text-sm">
                            Editar
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- ============================================================
     SECCIÓN: CATÁLOGO
     ============================================================ --}}
<section>
    <div class="flex items-center gap-3 mb-5">
        <div class="w-1 h-6 bg-gray-300 rounded-full"></div>
        <h2 class="text-xl font-bold text-gray-900">
            @if($inscripciones->isNotEmpty()) Más cursos disponibles @else Catálogo de Cursos @endif
        </h2>
        @if($catalogo->total() > 0)
        <span class="text-sm text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
            {{ $catalogo->total() }}
        </span>
        @endif
    </div>

    @if($catalogo->isEmpty())
        <div class="bg-white rounded-xl shadow p-16 text-center text-gray-400">
            @if($inscripciones->isNotEmpty())
                Ya estás inscrito en todos los cursos disponibles.
            @else
                No hay cursos disponibles por el momento.
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($catalogo as $curso)
            <div class="bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden flex flex-col">

                {{-- Imagen --}}
                @if($curso->imagen_portada)
                    <img src="{{ asset('storage/' . $curso->imagen_portada) }}"
                         alt="{{ $curso->titulo }}" class="w-full h-44 object-cover">
                @else
                    <div class="w-full h-44 bg-gradient-to-br from-blue-400 to-blue-700 flex items-center justify-center">
                        <span class="text-5xl">📚</span>
                    </div>
                @endif

                <div class="p-5 flex flex-col flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium
                            @if($curso->estado === 'publicado') bg-green-100 text-green-700
                            @elseif($curso->estado === 'borrador') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-500 @endif">
                            {{ ucfirst($curso->estado) }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $curso->duracion_horas }} h</span>
                    </div>

                    <h3 class="text-base font-bold text-gray-900 line-clamp-2 mb-2">{{ $curso->titulo }}</h3>

                    <p class="text-gray-500 text-sm line-clamp-2 flex-1 mb-4">
                        {{ Str::limit($curso->descripcion, 90) }}
                    </p>

                    <p class="text-xs text-gray-400 mb-4">Por {{ $curso->creador->name }}</p>

                    <div class="mt-auto space-y-2">
                        <a href="{{ route('cursos.show', $curso) }}"
                           class="block w-full text-center py-2 rounded-lg font-medium text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                            Ver Curso
                        </a>
                        @if($curso->created_by === auth()->id() || auth()->user()->esAdmin())
                            <a href="{{ route('cursos.edit', $curso) }}"
                               class="block w-full text-center bg-blue-50 text-blue-700 py-2 rounded-lg hover:bg-blue-100 text-sm">
                                Editar
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($catalogo->hasPages())
        <div class="mt-8">{{ $catalogo->links() }}</div>
        @endif
    @endif
</section>

@endsection
