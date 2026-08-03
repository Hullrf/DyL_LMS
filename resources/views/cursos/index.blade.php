@extends('layouts.app')
@section('title', 'Cursos - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.index') }}@endsection
@section('content')

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Cursos</h1>
    @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
        <a href="{{ route('cursos.create') }}"
           class="bg-dyl-orange-600 text-white px-5 py-2 rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
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
        <div class="w-1 h-6 bg-dyl-orange-600 rounded-full"></div>
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
                <div class="w-full h-44 bg-gradient-to-br from-dyl-orange-600 to-dyl-orange-700 flex items-center justify-center">
                    <span class="text-5xl">📚</span>
                </div>
            @endif

            <div class="p-5 flex flex-col flex-1">
                {{-- Estado de inscripción --}}
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium
                        @if($inscripcion->estado === 'completado') text-green-600 bg-green-100
                        @else text-dyl-orange-600 bg-dyl-orange-100 @endif
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
                    {{ Str::limit(strip_tags($curso->descripcion), 90) }}
                </p>

                <div class="mt-auto space-y-2">
                    <a href="{{ route('cursos.show', $curso) }}"
                       class="block w-full text-center py-2 rounded-lg font-medium text-sm bg-dyl-orange-600 text-white hover:bg-dyl-orange-700 transition-colors">
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
        {{-- Filtros --}}
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <form method="GET" action="{{ route('cursos.index') }}" class="flex flex-wrap items-center gap-3 w-full">
                @if($categorias->isNotEmpty())
                <select name="categoria" onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
                @endif
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar curso..."
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm flex-1 min-w-[200px] focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Buscar</button>
                @if(request()->anyFilled(['categoria', 'buscar']))
                    <a href="{{ route('cursos.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Limpiar filtros</a>
                @endif
            </form>
        </div>
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
                    @if($curso->categoria)
                        <span class="inline-block text-xs px-2 py-0.5 rounded font-medium mb-2" style="background-color: {{ $curso->categoria->color }}20; color: {{ $curso->categoria->color }}">
                            {{ $curso->categoria->nombre }}
                        </span>
                    @endif
                    <h3 class="text-base font-bold text-gray-900 line-clamp-2 mb-2">{{ $curso->titulo }}</h3>

                    <p class="text-gray-500 text-sm line-clamp-2 flex-1 mb-4">
                        {{ Str::limit(strip_tags($curso->descripcion), 90) }}
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
