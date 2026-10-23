@extends('layouts.app')
@section('title', 'Ficha académica - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.ficha', $curso) }}@endsection
@section('content')
@php $f = $curso->ficha; @endphp
<h1 class="text-3xl font-bold text-gray-900 mb-6">Ficha académica</h1>

<form method="POST" action="{{ route('cursos.ficha.update', $curso) }}" class="space-y-6 max-w-3xl"
      x-data="{ filas: {{ Js::from(old('bibliografia', $curso->bibliografias->map(fn($b) => ['autor' => $b->autor, 'anio' => $b->anio, 'titulo' => $b->titulo, 'editorial' => $b->editorial])->all())) }} }">
    @csrf @method('PUT')

    @foreach([
        ['justificacion', 'Justificación', 'textarea', $f?->justificacion],
        ['resultado_aprendizaje', 'Resultado esperado de aprendizaje', 'textarea', $f?->resultado_aprendizaje],
        ['prerrequisitos', 'Prerrequisitos', 'textarea', $f?->prerrequisitos],
    ] as [$campo, $etiqueta, $tipo, $valor])
        <label class="block text-sm font-medium text-gray-700">{{ $etiqueta }}
            <textarea name="{{ $campo }}" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old($campo, $valor) }}</textarea>
            @error($campo)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
    @endforeach

    <label class="block text-sm font-medium text-gray-700">Resultados específicos <span class="font-normal text-gray-500">(uno por línea)</span>
        <textarea name="resultados_especificos" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old('resultados_especificos', implode("\n", $f?->resultados_especificos ?? [])) }}</textarea>
    </label>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach([['creditos', 'Créditos', 'number', $f?->creditos], ['semestre', 'Semestre', 'number', $f?->semestre], ['programa', 'Programa', 'text', $f?->programa], ['facultad', 'Facultad', 'text', $f?->facultad]] as [$campo, $etiqueta, $tipo, $valor])
            <label class="block text-sm font-medium text-gray-700">{{ $etiqueta }}
                <input type="{{ $tipo }}" name="{{ $campo }}" value="{{ old($campo, $valor) }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                @error($campo)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        @endforeach
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-gray-700 mb-2">Bibliografía</legend>
        <template x-for="(fila, i) in filas" :key="i">
            <div class="mb-2 grid grid-cols-12 gap-2">
                <input aria-label="Autor" class="col-span-3 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][autor]`" x-model="fila.autor" placeholder="Autor" required>
                <input aria-label="Año" class="col-span-1 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][anio]`" x-model="fila.anio" placeholder="Año" type="number">
                <input aria-label="Título" class="col-span-4 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][titulo]`" x-model="fila.titulo" placeholder="Título" required>
                <input aria-label="Editorial" class="col-span-3 rounded-lg border-gray-300 text-sm" :name="`bibliografia[${i}][editorial]`" x-model="fila.editorial" placeholder="Editorial">
                <button type="button" class="col-span-1 text-red-600" @click="filas.splice(i, 1)" aria-label="Quitar">×</button>
            </div>
        </template>
        <button type="button" class="text-sm text-dyl-orange-700 hover:underline" @click="filas.push({autor: '', anio: '', titulo: '', editorial: ''})">+ Agregar referencia</button>
        @error('bibliografia.*.autor')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('bibliografia.*.titulo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <button class="rounded-lg bg-dyl-orange-700 px-5 py-2 text-sm font-medium text-white hover:bg-dyl-orange-800">Guardar ficha</button>
</form>
@endsection
