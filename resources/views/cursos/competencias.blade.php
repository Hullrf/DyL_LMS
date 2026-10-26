@extends('layouts.app')
@section('title', 'Competencias - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.competencias', $curso) }}@endsection
@section('content')
<h1 class="font-display text-4xl font-semibold text-gray-900 mb-2">Competencias</h1>
<p class="text-sm text-gray-600 mb-6 max-w-2xl">
    Define los resultados de aprendizaje de «{{ $curso->titulo }}» y enlaza las actividades que los evidencian.
    Tus estudiantes verán su avance en cada competencia dentro del curso.
</p>

@if($competencias->isEmpty())
    <div class="mb-6 rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
        Aún no hay competencias. Agrega la primera con el formulario de abajo.
    </div>
@endif

@foreach($competencias as $c)
    @php $enlazadas = $c->actividades->pluck('id')->all(); @endphp
    <div class="card mb-4 p-5">
        <form method="POST" action="{{ route('cursos.competencias.update', [$curso, $c]) }}">
            @csrf @method('PUT')
            <label class="block text-sm font-medium text-gray-700">Competencia
                <input type="text" name="descripcion" maxlength="500" required value="{{ $c->descripcion }}"
                       class="form-input mt-1 block">
            </label>
            @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => $enlazadas])
            <div class="mt-3 flex items-center gap-3">
                <button class="btn-primary">Guardar</button>
            </div>
        </form>
        <form method="POST" action="{{ route('cursos.competencias.destroy', [$curso, $c]) }}" class="mt-2"
              onsubmit="return confirm('¿Eliminar esta competencia?')">
            @csrf @method('DELETE')
            <button class="text-sm text-gray-700 hover:underline">Eliminar competencia</button>
        </form>
    </div>
@endforeach

<div class="card p-5">
    <h2 class="mb-3 font-display text-2xl font-semibold text-gray-900">Agregar competencia</h2>
    @error('descripcion')<div class="alert alert-error mb-3">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('cursos.competencias.store', $curso) }}">
        @csrf
        <label class="block text-sm font-medium text-gray-700">Competencia
            <input type="text" name="descripcion" maxlength="500" required value="{{ old('descripcion') }}"
                   class="form-input mt-1 block"
                   placeholder="Ej.: Aplicar los fundamentos de seguridad en sistemas y redes">
        </label>
        @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => []])
        <button class="mt-3 btn-primary">Agregar competencia</button>
    </form>
</div>
@endsection
