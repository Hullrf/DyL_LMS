@extends('layouts.app')
@section('title', 'Línea de tiempo - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('linea-de-tiempo.index') }}@endsection
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-6">Línea de tiempo</h1>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <label class="text-sm text-gray-700">Ventana
        <select name="dias" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            @foreach([7 => '7 días', 30 => '30 días', 180 => '6 meses'] as $v => $t)
                <option value="{{ $v }}" @selected($dias === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Curso
        <select name="curso" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach($cursos as $c)
                <option value="{{ $c->id }}" @selected($filtros['curso'] === $c->id)>{{ $c->titulo }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Ronda
        <input type="number" name="ronda" min="1" max="9" value="{{ $filtros['ronda'] }}" class="mt-1 block w-20 rounded-lg border-gray-300 text-sm">
    </label>
    <label class="text-sm text-gray-700">Buscar
        <input type="search" name="buscar" value="{{ $filtros['buscar'] }}" placeholder="Nombre o tipo" class="mt-1 block rounded-lg border-gray-300 text-sm">
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="entregadas" value="1" @checked($filtros['entregadas']) class="rounded" onchange="this.form.submit()">
        Mostrar entregadas
    </label>
    <button class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200">Filtrar</button>
</form>

@include('partials.linea-de-tiempo', ['lineaDeTiempo' => $lineaDeTiempo])
@endsection
