@extends('layouts.app')
@section('title', 'Línea de tiempo - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('linea-de-tiempo.index') }}@endsection
@section('content')
<h1 class="font-display text-4xl font-semibold text-gray-900 mb-6">Línea de tiempo</h1>

<form method="GET" class="card p-4 flex flex-wrap items-end gap-4 mb-8">
    <label class="text-sm text-gray-700">Ventana
        <select name="dias" class="form-select mt-1 block w-auto" onchange="this.form.submit()">
            @foreach([7 => '7 días', 30 => '30 días', 180 => '6 meses'] as $v => $t)
                <option value="{{ $v }}" @selected($dias === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Curso
        <select name="curso" class="form-select mt-1 block w-auto max-w-xs" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach($cursos as $c)
                <option value="{{ $c->id }}" @selected($filtros['curso'] === $c->id)>{{ $c->titulo }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm text-gray-700">Ronda
        <input type="number" name="ronda" min="1" max="9" value="{{ $filtros['ronda'] }}" class="form-input mt-1 block w-20">
    </label>
    <label class="text-sm text-gray-700">Buscar
        <input type="search" name="buscar" value="{{ $filtros['buscar'] }}" placeholder="Nombre o tipo" class="form-input mt-1 block">
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700 pb-2">
        <input type="checkbox" name="entregadas" value="1" @checked($filtros['entregadas']) class="rounded" onchange="this.form.submit()">
        Mostrar entregadas
    </label>
    <button class="btn-primary">Filtrar</button>
</form>

@include('partials.linea-de-tiempo', ['lineaDeTiempo' => $lineaDeTiempo])
@endsection
