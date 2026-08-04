@extends('layouts.app')
@section('title', 'Mis Certificados - LMS DyL')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Mis Certificados</h1>
    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dashboard</a>
</div>

@if(session('success'))
    <div class="alert alert-success mb-6">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-error mb-6">{{ session('error') }}</div>
@endif

@forelse($certificados as $cert)
    <div class="bg-white rounded-xl shadow hover:shadow-md transition-shadow mb-4 overflow-hidden border-l-4 border-dyl-orange-400">
        <div class="p-6 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-dyl-orange-50 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-7 h-7 text-dyl-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6 3a1 1 0 011-1h.01a1 1 0 010 2H7a1 1 0 01-1-1zm2 3a1 1 0 00-2 0v1a2 2 0 00-2 2v1a2 2 0 00-2 2v.683a3.7 3.7 0 011.055.485 1.704 1.704 0 001.89 0 3.704 3.704 0 014.11 0 1.704 1.704 0 001.89 0 3.704 3.704 0 014.11 0 1.704 1.704 0 001.89 0A3.7 3.7 0 0118 12.683V12a2 2 0 00-2-2V9a2 2 0 00-2-2V6a1 1 0 10-2 0v1h-1V6a1 1 0 10-2 0v1H8V6zm10 8.868a3.704 3.704 0 01-4.055-.036 1.704 1.704 0 00-1.89 0 3.704 3.704 0 01-4.11 0 1.704 1.704 0 00-1.89 0A3.7 3.7 0 012 14.868V17a1 1 0 001 1h14a1 1 0 001-1v-2.132zM9 3a1 1 0 011-1h.01a1 1 0 010 2H10a1 1 0 01-1-1zm3 0a1 1 0 011-1h.01a1 1 0 010 2H13a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">{{ $cert->curso->titulo }}</h3>
                    <div class="flex gap-3 text-xs text-gray-500 mt-1">
                        <span>Emitido: {{ \Carbon\Carbon::parse($cert->fecha_emision)->locale('es')->isoFormat('D MMM YYYY') }}</span>
                        <span>·</span>
                        <span>Calificación: <strong class="text-dyl-orange-600">{{ $cert->calificacion_final }}%</strong></span>
                        <span>·</span>
                        <span class="font-mono">{{ $cert->numero_certificado }}</span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('certificados.show', $cert) }}"
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                    Ver
                </a>
                <a href="{{ route('certificados.descargar', $cert) }}"
                   class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg hover:bg-dyl-orange-700 text-sm font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    PDF
                </a>
            </div>
        </div>
    </div>
@empty
    <div class="text-center py-20 bg-white rounded-xl shadow">
        <div class="w-20 h-20 bg-dyl-orange-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-dyl-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Aún no tienes certificados</h3>
        <p class="text-gray-500 mb-6">Completa un curso para obtener tu certificado.</p>
        <a href="{{ route('cursos.index') }}" class="bg-dyl-orange-600 text-white px-6 py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium">
            Ver cursos disponibles
        </a>
    </div>
@endforelse
@endsection
