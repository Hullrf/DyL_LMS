@extends('layouts.app')
@section('title', 'Mis certificados - D&L Quality Campus')

@section('content')
<header class="mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Mis certificados</h1>
</header>

@forelse($certificados as $cert)
    <article class="card-hover mb-4 p-5 sm:p-6 flex flex-col lg:flex-row gap-5 lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h2 class="font-display text-2xl font-semibold text-gray-900 break-words">{{ $cert->curso->titulo }}</h2>
            <dl class="flex flex-wrap gap-x-8 gap-y-2 mt-3 text-sm">
                <div>
                    <dt class="text-gray-500">Emitido</dt>
                    <dd class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($cert->fecha_emision)->locale('es')->isoFormat('D MMM YYYY') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Calificación</dt>
                    <dd class="font-medium text-dyl-orange-700 tabular-nums">{{ $cert->calificacion_final }}%</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Número</dt>
                    <dd class="font-mono text-gray-900">{{ $cert->numero_certificado }}</dd>
                </div>
            </dl>
        </div>
        <div class="flex gap-2 shrink-0">
            <a href="{{ route('certificados.show', $cert) }}" class="btn-outline">Ver</a>
            <a href="{{ route('certificados.descargar', $cert) }}" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Descargar PDF
            </a>
        </div>
    </article>
@empty
    <div class="card text-center py-16 px-6">
        <h2 class="font-display text-2xl font-semibold text-gray-900 mb-2">Aún no tienes certificados</h2>
        <p class="text-gray-600 mb-6">Cuando completes un curso y tu instructor lo revise, tu certificado aparecerá aquí.</p>
        <a href="{{ route('cursos.index') }}" class="btn-primary">Ver cursos disponibles</a>
    </div>
@endforelse
@endsection
