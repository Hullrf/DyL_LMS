@extends('layouts.app')
@section('title', 'Certificado - ' . $certificado->curso->titulo)

@section('content')
<div class="max-w-3xl mx-auto">

    <div class="flex flex-wrap justify-between items-end gap-4 mb-8">
        <div>
            <a href="{{ route('certificados.mis') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Mis certificados</a>
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-gray-900 mt-1">Certificado de finalización</h1>
            <p class="text-gray-600 mt-1">{{ $certificado->curso->titulo }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('certificados.descargar', $certificado) }}" class="btn-primary btn-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Descargar PDF
            </a>
            @if(auth()->user()->esAdmin() || $certificado->curso->created_by === auth()->id())
                <form method="POST" action="{{ route('certificados.regenerar', $certificado) }}"
                      onsubmit="return confirm('¿Regenerar el PDF con la plantilla actual?')">
                    @csrf
                    <button type="submit" class="btn-outline btn-lg">Regenerar PDF</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Previsualización: el PDF real, no un mockup aparte --}}
    @if($certificado->archivo_pdf && Storage::disk('public')->exists($certificado->archivo_pdf))
        <div class="card overflow-hidden mb-6">
            <iframe
                src="{{ route('certificados.previsualizar', $certificado) }}?v={{ $certificado->updated_at?->timestamp }}"
                class="w-full"
                style="height: 80vh; border: none;"
                title="Certificado {{ $certificado->numero_certificado }}"
            ></iframe>
        </div>
    @else
        <div class="rounded-2xl p-6 mb-6 border-2 border-dashed border-gray-300 text-center text-gray-500">
            El certificado se está generando o no está disponible en este momento.
        </div>
    @endif

    {{-- Datos de verificación --}}
    <dl class="card p-5 flex flex-col gap-4">
        <div>
            <dt class="text-sm text-gray-500 mb-1">Número de certificado</dt>
            <dd class="font-mono font-semibold text-gray-900">{{ $certificado->numero_certificado }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-sm text-gray-500 mb-1">Enlace de verificación</dt>
            <dd>
                <a href="{{ route('certificados.verificar', $certificado->numero_certificado) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="text-dyl-orange-700 hover:underline text-sm font-mono break-all">
                    {{ url('/verificar-certificado/' . $certificado->numero_certificado) }}
                </a>
            </dd>
        </div>
    </dl>

</div>
@endsection
