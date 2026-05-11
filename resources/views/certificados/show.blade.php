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
           class="flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Descargar PDF
        </a>
    </div>

    {{-- Tarjeta previsualización del certificado --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border-2 border-yellow-300">

        {{-- Franja superior decorativa --}}
        <div class="h-3 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-400"></div>

        <div class="px-10 py-12 text-center">

            <p class="text-xs font-bold tracking-[4px] text-blue-900 uppercase mb-1">DyL Quality Consulting</p>
            <div class="w-24 h-0.5 bg-yellow-400 mx-auto mb-6"></div>

            <h2 class="text-4xl font-serif tracking-[6px] text-yellow-500 uppercase mb-2">Certificado</h2>
            <p class="text-sm tracking-[3px] text-gray-500 uppercase mb-8">De Finalización</p>

            <p class="text-gray-500 mb-2 text-sm">Este certificado se otorga a</p>

            <p class="text-4xl font-serif italic font-bold text-blue-900 border-b border-yellow-400 pb-3 inline-block px-8 mb-6">
                {{ $certificado->usuario->name }}
            </p>

            <p class="text-gray-500 mb-2 text-sm">por haber completado satisfactoriamente el curso</p>

            <p class="text-2xl font-bold text-blue-900 mb-4">{{ $certificado->curso->titulo }}</p>

            <div class="inline-flex gap-6 text-sm text-gray-500 border border-yellow-300 rounded-lg px-6 py-2 mb-8">
                <span>Calificación: <strong class="text-blue-900">{{ $certificado->calificacion_final }}%</strong></span>
                <span>·</span>
                <span>Duración: <strong class="text-blue-900">{{ $certificado->curso->duracion_horas }} h</strong></span>
                <span>·</span>
                <span>Fecha: <strong class="text-blue-900">{{ \Carbon\Carbon::parse($certificado->fecha_emision)->locale('es')->isoFormat('D MMM YYYY') }}</strong></span>
            </div>

            <div class="flex justify-around mt-2">
                <div class="text-center">
                    <div class="border-t border-gray-400 w-40 mb-1 mx-auto"></div>
                    <p class="text-sm font-semibold text-gray-800">{{ $certificado->curso->creador->name }}</p>
                    <p class="text-xs text-gray-500">Instructor del Curso</p>
                </div>
                <div class="text-center">
                    <div class="border-t border-gray-400 w-40 mb-1 mx-auto"></div>
                    <p class="text-sm font-semibold text-gray-800">DyL Quality Consulting</p>
                    <p class="text-xs text-gray-500">Dirección Académica</p>
                </div>
            </div>
        </div>

        <div class="h-3 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-400"></div>
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
               class="text-blue-600 hover:underline text-sm font-mono break-all">
                {{ url('/verificar-certificado/' . $certificado->numero_certificado) }}
            </a>
        </div>
    </div>

</div>
@endsection
