@extends('layouts.app')
@section('title', 'Cursos - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.index') }}@endsection
@section('content')

<div class="flex flex-wrap justify-between items-end gap-4 mb-10">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Cursos</h1>
    @if(auth()->user()->esInstructor() || auth()->user()->esAdmin())
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('cursos.importar') }}" enctype="multipart/form-data">
                @csrf
                <label class="btn-outline cursor-pointer"
                       title="Carga un curso exportado (.zip). Se crea como borrador.">
                    Importar curso
                    <input type="file" name="archivo" accept=".zip,application/zip" class="hidden"
                           onchange="if (this.files.length) this.form.submit()">
                </label>
            </form>
            <a href="{{ route('cursos.create') }}" class="btn-primary">+ Nuevo curso</a>
        </div>
    @endif
</div>

{{-- Mis cursos --}}
@if($inscripciones->isNotEmpty())
<section class="mb-12" aria-labelledby="mis-cursos-titulo">
    <h2 id="mis-cursos-titulo" class="font-display text-2xl font-semibold text-gray-900 mb-4">
        Mis cursos <span class="text-base font-sans font-normal text-gray-500 tabular-nums">({{ $inscripciones->count() }})</span>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($inscripciones as $inscripcion)
        @php $curso = $inscripcion->curso; @endphp
        <article class="card-hover overflow-hidden flex flex-col">
            <a href="{{ route('cursos.show', $curso) }}" tabindex="-1" aria-hidden="true" class="block h-36">
                @if($curso->imagen_portada)
                    <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="" class="w-full h-full object-cover">
                @else
                    <x-portada-curso :curso="$curso"/>
                @endif
            </a>

            <div class="p-5 flex flex-col flex-1">
                <h3 class="font-display text-xl font-semibold text-gray-900 line-clamp-2 mb-2">
                    <a href="{{ route('cursos.show', $curso) }}" class="hover:text-dyl-orange-700">{{ $curso->titulo }}</a>
                </h3>
                @if($inscripcion->estado === 'completado')
                    <span class="badge-success self-start mb-2">Completado</span>
                @endif
                @include('cursos._resumen', ['curso' => $curso])
                <p class="text-xs text-gray-500 flex-1 mb-4">{{ $curso->duracion_horas }} h</p>

                <div class="flex items-center gap-2 mt-auto">
                    <a href="{{ route('cursos.show', $curso) }}" class="btn-primary flex-1">
                        {{ $inscripcion->estado === 'completado' ? 'Repasar' : 'Continuar' }}
                    </a>
                    @if($curso->created_by === auth()->id() || auth()->user()->esAdmin())
                        <a href="{{ route('cursos.edit', $curso) }}" class="btn-ghost">Editar</a>
                    @endif
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif

{{-- Catálogo --}}
<section aria-labelledby="catalogo-titulo">
    <h2 id="catalogo-titulo" class="font-display text-2xl font-semibold text-gray-900 mb-4">
        {{ $inscripciones->isNotEmpty() ? 'Más cursos disponibles' : 'Catálogo de cursos' }}
        @if($catalogo->total() > 0)
            <span class="text-base font-sans font-normal text-gray-500 tabular-nums">({{ $catalogo->total() }})</span>
        @endif
    </h2>

    @if($catalogo->isEmpty() && !request()->anyFilled(['categoria', 'buscar']))
        <div class="card p-12 text-center text-gray-500">
            @if($inscripciones->isNotEmpty())
                Ya estás inscrito en todos los cursos disponibles.
            @else
                Todavía no hay cursos publicados. Cuando haya uno, aparecerá aquí.
            @endif
        </div>
    @else
        <form method="GET" action="{{ route('cursos.index') }}" class="flex flex-wrap items-center gap-3 mb-6">
            <input aria-label="Buscar curso" type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por título"
                   class="form-input flex-1 min-w-[200px] sm:max-w-sm">
            @if($categorias->isNotEmpty())
                <select name="categoria" aria-label="Categoría" onchange="this.form.submit()" class="form-select w-auto">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn-primary">Buscar</button>
            @if(request()->anyFilled(['categoria', 'buscar']))
                <a href="{{ route('cursos.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Limpiar filtros</a>
            @endif
            @if(auth()->user()->esAdmin())
                <a href="{{ route('admin.categorias.index') }}" class="text-sm text-gray-500 hover:text-dyl-orange-700 ml-auto whitespace-nowrap">Gestionar categorías</a>
            @endif
        </form>

        @if($catalogo->isEmpty())
            <div class="card p-12 text-center text-gray-500">
                Ningún curso coincide con tu búsqueda.
                <a href="{{ route('cursos.index') }}" class="text-dyl-orange-700 font-medium hover:underline ml-1">Ver todos</a>
            </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($catalogo as $curso)
            <article class="card-hover overflow-hidden flex flex-col">
                <a href="{{ route('cursos.show', $curso) }}" tabindex="-1" aria-hidden="true" class="block h-36">
                    @if($curso->imagen_portada)
                        <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="" class="w-full h-full object-cover">
                    @else
                        <x-portada-curso :curso="$curso"/>
                    @endif
                </a>

                <div class="p-5 flex flex-col flex-1">
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-2">
                        @if($curso->categoria)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $curso->categoria->color }}" aria-hidden="true"></span>
                                {{ $curso->categoria->nombre }}
                            </span>
                        @endif
                        <span class="tabular-nums">{{ $curso->duracion_horas }} h</span>
                        @if($curso->estado !== 'publicado')
                            <span class="{{ $curso->estado === 'borrador' ? 'badge-warning' : 'badge-info' }}">{{ ucfirst($curso->estado) }}</span>
                        @endif
                    </div>

                    <h3 class="font-display text-xl font-semibold text-gray-900 line-clamp-2 mb-2">
                        <a href="{{ route('cursos.show', $curso) }}" class="hover:text-dyl-orange-700">{{ $curso->titulo }}</a>
                    </h3>

                    <p class="text-gray-600 text-sm line-clamp-3 flex-1 mb-4">
                        {{ Str::limit(strip_tags($curso->descripcion), 140) }}
                    </p>

                    <div class="flex items-center justify-between gap-3 mt-auto">
                        <p class="text-xs text-gray-500 truncate">Por {{ $curso->creador->name }}</p>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($curso->created_by === auth()->id() || auth()->user()->esAdmin())
                                <a href="{{ route('cursos.edit', $curso) }}" class="btn-ghost btn-sm">Editar</a>
                            @endif
                            <a href="{{ route('cursos.show', $curso) }}" class="btn-outline btn-sm">Ver curso</a>
                        </div>
                    </div>
                </div>
            </article>
            @endforeach
        </div>

        @if($catalogo->hasPages())
            <div class="mt-8">{{ $catalogo->links() }}</div>
        @endif
        @endif
    @endif
</section>

@endsection
