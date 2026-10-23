@extends('layouts.app')
@section('title', 'Seguimiento - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('reportes.seguimiento', $curso) }}@endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Seguimiento</h1>
        <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
    </div>
    @if(!$error && $datos && $datos['filas']->isNotEmpty())
        <a href="{{ route('reportes.seguimiento.csv', $curso) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Exportar CSV</a>
    @endif
</div>

@if($error)
    <div class="alert alert-error" role="alert">
        No se pudo cargar el seguimiento.
        <a href="{{ route('reportes.seguimiento', $curso) }}" class="font-medium underline">Reintentar</a>
    </div>
@else
    <div class="mb-6 grid grid-cols-3 gap-4">
        @foreach(['riesgo' => 'text-red-700', 'atencion' => 'text-dyl-orange-600', 'ok' => 'text-dyl-graphite-700'] as $nivel => $color)
            <div class="rounded-xl bg-white p-4 text-center shadow">
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ $niveles[$nivel] }}</p>
                <p class="mt-1 text-3xl font-bold {{ $color }}">{{ $datos['resumen'][$nivel] }}</p>
            </div>
        @endforeach
    </div>
    <p class="mb-6 -mt-3 text-xs text-gray-500">La última conexión se registra desde la actualización de octubre de 2026; los estudiantes sin registro aparecerán como «Atención» hasta su próxima visita.</p>

    <form method="POST" action="{{ route('reportes.seguimiento.umbrales', $curso) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow text-sm">
        @csrf @method('PUT')
        <label class="text-gray-700">Días sin ingresar
            <input type="number" min="1" max="90" name="seguimiento_dias_sin_acceso" value="{{ old('seguimiento_dias_sin_acceso', $datos['umbrales']['dias_sin_acceso']) }}" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-gray-700">Entregas atrasadas (mínimo)
            <input type="number" min="1" max="20" name="seguimiento_max_atrasadas" value="{{ old('seguimiento_max_atrasadas', $datos['umbrales']['max_atrasadas']) }}" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm">
        </label>
        <button class="rounded-lg bg-gray-100 px-4 py-2 hover:bg-gray-200">Guardar umbrales</button>
        @error('seguimiento_dias_sin_acceso')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        @error('seguimiento_max_atrasadas')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
    </form>

    @if($datos['filas']->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow">Aún no hay estudiantes inscritos.</div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Estudiante</th><th scope="col" class="px-4 py-3">Nivel</th><th scope="col" class="px-4 py-3">Progreso</th>
                        <th scope="col" class="px-4 py-3">Promedio</th><th scope="col" class="px-4 py-3">Atrasadas</th><th scope="col" class="px-4 py-3">Último acceso</th><th scope="col" class="px-4 py-3">Señales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($datos['filas'] as $f)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $f->usuario->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $f->nivel === 'riesgo' ? 'bg-red-100 text-red-800' : ($f->nivel === 'atencion' ? 'bg-dyl-orange-100 text-dyl-orange-800' : 'bg-dyl-graphite-100 text-dyl-graphite-700') }}">
                                    {{ $niveles[$f->nivel] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $f->progreso_pct === null ? '—' : $f->progreso_pct . '%' }}</td>
                            <td class="px-4 py-3">{{ $f->promedio === null ? '—' : $f->promedio . '%' }}</td>
                            <td class="px-4 py-3">{{ $f->atrasadas }}</td>
                            <td class="px-4 py-3">{{ $f->ultimo_acceso ? $f->ultimo_acceso->format('d/m/Y H:i') : 'Sin registro de ingreso' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @forelse($f->senales as $s){{ $senales[$s] }}@if(!$loop->last) · @endif @empty — @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
@endsection
