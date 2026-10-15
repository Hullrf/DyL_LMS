@extends('layouts.app')
@section('title', 'Ponderaciones - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.ponderaciones', $curso) }}@endsection
@section('content')
<h1 class="text-3xl font-bold text-gray-900 mb-2">Ponderaciones</h1>
<p class="text-sm text-gray-600 mb-6 max-w-2xl">
    Define cuánto pesa cada actividad con nota en la nota final de «{{ $curso->titulo }}».
    Deben sumar 100 %. Si las dejas todas vacías, el peso se reparte según el puntaje máximo de cada actividad.
    Cambiar las ponderaciones recalcula la nota de todos los estudiantes.
</p>
<p class="text-sm text-gray-600 mb-4">Modo actual: <strong>{{ $curso->ponderacionIncompleta() || $actividades->whereNotNull('ponderacion')->isEmpty() ? 'por puntaje' : 'por ponderación' }}</strong></p>
@include('cursos.partials.aviso-ponderacion')

@if($actividades->isEmpty())
    <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">Este curso aún no tiene actividades con nota.</div>
@else
<form method="POST" action="{{ route('cursos.ponderaciones.update', $curso) }}"
      x-data="{
          vals: {{ Js::from($actividades->mapWithKeys(fn($a) => [$a->id => (string) old('ponderaciones.' . $a->id, $a->ponderacion !== null ? rtrim(rtrim(number_format($a->ponderacion, 2, '.', ''), '0'), '.') : '')])) }},
          get suma() { return Object.values(this.vals).reduce((s, v) => s + (parseFloat(v) || 0), 0); },
          limpiar() { Object.keys(this.vals).forEach(k => this.vals[k] = ''); }
      }">
    @csrf @method('PUT')
    @error('ponderaciones')<div class="alert alert-error mb-4" role="alert">{{ $message }}</div>@enderror

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actividad</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Puntaje máx.</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Peso (%)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($actividades as $a)
                <tr>
                    <td class="px-6 py-3 text-sm text-gray-900">{{ $a->tituloCompleto() }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ ucfirst($a->tipo) }}</td>
                    <td class="px-6 py-3 text-sm text-right text-gray-700">{{ number_format($a->puntaje_maximo, 2) }}</td>
                    <td class="px-6 py-3 text-right">
                        <input type="number" step="0.01" min="0" max="100" name="ponderaciones[{{ $a->id }}]"
                               x-model="vals[{{ $a->id }}]" aria-label="Peso de {{ $a->titulo }}"
                               class="w-24 rounded-lg border-gray-300 text-right text-sm">
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Suma</td>
                    <td class="px-6 py-3 text-right text-sm font-bold"
                        :class="Math.abs(suma - 100) < 0.01 || suma === 0 ? 'text-dyl-orange-700' : 'text-red-600'"
                        x-text="suma.toFixed(2) + ' %'"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-4 flex gap-3">
        <button type="submit" class="rounded-lg bg-dyl-orange-600 px-5 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Guardar</button>
        <button type="button" @click="limpiar()" class="rounded-lg border border-gray-300 px-5 py-2 text-sm text-gray-700 hover:bg-gray-50">Repartir por puntaje</button>
        <a href="{{ route('calificaciones.curso', $curso) }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Volver a calificaciones</a>
    </div>
</form>
@endif
@endsection
