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

{{-- Filtros --}}
<div class="flex gap-2 mb-6">
    <a href="{{ route('calificaciones.index', ['estado' => 'pendiente']) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ $estado === 'pendiente' ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}">
        Pendientes
    </a>
    <a href="{{ route('calificaciones.index', ['estado' => 'calificada']) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ $estado === 'calificada' ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}">
        Calificadas
    </a>
    <a href="{{ route('calificaciones.index', ['estado' => 'todas']) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ $estado === 'todas' ? 'bg-dyl-orange-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50' }}">
        Todas
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estudiante</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actividad</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Curso</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($respuestas as $respuesta)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-gray-900">{{ $respuesta->usuario->name }}</p>
                    <p class="text-xs text-gray-500">{{ $respuesta->usuario->email }}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-gray-900">{{ $respuesta->actividad->titulo }}</p>
                    <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">
                        {{ ucfirst($respuesta->actividad->tipo) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    {{ $respuesta->actividad->leccion->modulo->curso->titulo }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                    {{ $respuesta->fecha_envio->format('d/m/Y H:i') }}
                </td>
                <td class="px-6 py-4">
                    @if($respuesta->estado === 'calificada')
                        <span class="badge badge-green">
                            {{ number_format($respuesta->calificacion, 2) }} / {{ number_format($respuesta->actividad->puntaje_maximo, 2) }} pts
                        </span>
                    @elseif($respuesta->estado === 'en_revision')
                        <span class="badge badge-blue">
                            Revisión pendiente
                        </span>
                    @else
                        <span class="badge badge-yellow">Sin calificar</span>
                    @endif
                </td>
                <td class="px-6 py-4">
                    @if($respuesta->estado === 'en_revision')
                        <a href="{{ route('calificaciones.revisar', $respuesta) }}"
                           class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
                            Revisar &rarr;
                        </a>
                    @else
                        <a href="{{ route('calificaciones.show', $respuesta) }}"
                           class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm font-medium">
                            {{ $respuesta->estado === 'calificada' ? 'Ver / Editar' : 'Calificar' }} &rarr;
                        </a>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                    @if($estado === 'pendiente')
                        No hay respuestas pendientes de calificación.
                    @elseif($estado === 'calificada')
                        No hay respuestas calificadas todavía.
                    @else
                        No hay respuestas enviadas.
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($respuestas->hasPages())
    <div class="px-6 py-4 border-t">
        {{ $respuestas->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
