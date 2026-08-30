@extends('layouts.app')
@section('title', 'Certificado - ' . $certificado->curso->titulo)

@section('content')
<div class="max-w-3xl mx-auto">

    <div class="flex justify-between items-center mb-8">
        <div>
            <a href="{{ route('certificados.mis') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Mis certificados</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Certificado de Finalización</h1>
        </div>
        <a href="{{ route('certificados.descargar', $certificado) }}"
           class="flex items-center gap-2 bg-dyl-orange-600 text-white px-5 py-2.5 rounded-lg hover:bg-dyl-orange-700 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Descargar PDF
        </a>
    </div>

    {{-- Previsualización: el PDF real, no un mockup aparte --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border-2 border-dyl-orange-300">
        <iframe
            src="{{ Storage::disk('public')->url($certificado->archivo_pdf) }}"
            class="w-full"
            style="height: 80vh; border: none;"
            title="Certificado {{ $certificado->numero_certificado }}"
        ></iframe>
    </div>

    {{-- Datos de verificación --}}
    <div class="bg-gray-50 rounded-xl p-5 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">N° de Certificado</p>
            <p class="font-mono font-bold text-gray-800">{{ $certificado->numero_certificado }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Enlace de verificación</p>
            <a href="{{ route('certificados.verificar', $certificado->numero_certificado) }}"
               target="_blank"
               class="text-dyl-orange-600 hover:underline text-sm font-mono break-all">
                {{ url('/verificar-certificado/' . $certificado->numero_certificado) }}
            </a>
        </div>
    </div>

</div>
@endsection
