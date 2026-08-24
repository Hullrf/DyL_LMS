@extends('layouts.app')
@section('title', 'Calificaciones - LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('calificaciones.index') }}@endsection
@section('content')

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Calificaciones</h1>
    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 text-sm">&#8592; Dashboard</a>
</div>

@if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-error mb-6">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl shadow mb-8">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-900">Elige un curso para calificar</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Inscritos</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pendientes</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($cursos as $curso)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $curso->titulo }}</p>
                        <p class="text-xs text-gray-400">{{ $curso->duracion_horas }} h</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-lg font-bold text-gray-800">{{ $curso->inscripciones_count }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($curso->pendientes_count > 0)
                            <span class="badge badge-yellow">{{ $curso->pendientes_count }}</span>
                        @else
                            <span class="text-gray-300">0</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('calificaciones.curso', $curso) }}"
                           class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
                            Ver calificaciones &rarr;
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-16 text-center text-gray-400">No tienes cursos todavía.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
