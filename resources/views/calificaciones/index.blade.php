@extends('layouts.app')
@section('title', 'Calificaciones - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('calificaciones.index') }}@endsection
@section('content')

<header class="mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Calificaciones</h1>
    <p class="mt-1 text-gray-600">Elige un curso para revisar y calificar las entregas.</p>
</header>

<section class="card overflow-hidden mb-8" aria-labelledby="cursos-titulo">
    <h2 id="cursos-titulo" class="sr-only">Cursos</h2>
    {{-- relative: el <span class="sr-only"> del encabezado es absoluto y, sin esto, ensancharía la página en móvil --}}
    <div class="relative overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500">Curso</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Inscritos</th>
                    <th scope="col" class="px-6 py-3 text-center text-sm font-medium text-gray-500">Pendientes</th>
                    <th scope="col" class="px-6 py-3 text-left text-sm font-medium text-gray-500"><span class="sr-only">Acción</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cursos as $curso)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-display text-lg font-semibold text-gray-900">{{ $curso->titulo }}</p>
                        <p class="text-xs text-gray-500">{{ $curso->duracion_horas }} h</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $curso->inscripciones_count }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($curso->pendientes_count > 0)
                            <span class="badge badge-yellow">{{ $curso->pendientes_count }}</span>
                        @else
                            <span class="text-gray-500">0</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('calificaciones.curso', $curso) }}"
                           class="{{ $curso->pendientes_count > 0 ? 'btn-primary' : 'btn-outline' }} btn-sm whitespace-nowrap">
                            {{ $curso->pendientes_count > 0 ? 'Calificar' : 'Ver calificaciones' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-16 text-center text-gray-500">Aún no tienes cursos.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
