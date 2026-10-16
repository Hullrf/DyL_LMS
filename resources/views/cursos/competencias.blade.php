@extends('layouts.app')
@section('title', 'Competencias - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.competencias', $curso) }}@endsection
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-2">Competencias</h1>
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
    <div class="mb-4 rounded-xl bg-white p-5 shadow">
        <form method="POST" action="{{ route('cursos.competencias.update', [$curso, $c]) }}">
            @csrf @method('PUT')
            <label class="block text-sm font-medium text-gray-700">Competencia
                <input type="text" name="descripcion" maxlength="500" required value="{{ $c->descripcion }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
            </label>
            @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => $enlazadas])
            <div class="mt-3 flex items-center gap-3">
                <button class="rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Guardar</button>
            </div>
        </form>
        <form method="POST" action="{{ route('cursos.competencias.destroy', [$curso, $c]) }}" class="mt-2"
              onsubmit="return confirm('¿Eliminar esta competencia?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">Eliminar competencia</button>
        </form>
    </div>
@endforeach

<div class="rounded-xl bg-white p-5 shadow">
    <h2 class="mb-3 text-lg font-bold text-gray-900">Agregar competencia</h2>
    @error('descripcion')<div class="alert alert-error mb-3">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('cursos.competencias.store', $curso) }}">
        @csrf
        <label class="block text-sm font-medium text-gray-700">Competencia
            <input type="text" name="descripcion" maxlength="500" required value="{{ old('descripcion') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                   placeholder="Ej.: Aplicar los fundamentos de seguridad en sistemas y redes">
        </label>
        @include('cursos.partials.competencia-actividades', ['actividades' => $actividades, 'marcadas' => []])
        <button class="mt-3 rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Agregar competencia</button>
    </form>
</div>
@endsection
