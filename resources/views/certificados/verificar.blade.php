@extends('layouts.app')
@section('title', 'Verificar certificado - D&L Quality Campus')

@section('content')
<div class="max-w-2xl mx-auto">

    <header class="text-center mb-10">
        <h1 class="font-display text-3xl sm:text-4xl font-semibold text-gray-900">Verificación de certificado</h1>
        <p class="text-gray-600 mt-1">DyL Quality Consulting</p>
    </header>

    @if($certificado)
        {{-- Certificado válido --}}
        <div class="card overflow-hidden">
            <div class="h-1.5 bg-dyl-orange-600"></div>

            <div class="p-6 sm:p-8">
                <p class="inline-flex items-center gap-2 bg-dyl-orange-50 text-dyl-orange-700 px-4 py-2 rounded-full text-sm font-medium mb-6" role="status">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Certificado válido y auténtico
                </p>

                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-gray-500">Estudiante</dt>
                        <dd class="font-semibold text-gray-900">{{ $certificado->usuario->name }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-gray-500">Curso</dt>
                        <dd class="font-semibold text-gray-900 sm:text-right">{{ $certificado->curso->titulo }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-gray-500">Calificación final</dt>
                        <dd class="font-semibold text-dyl-orange-700 tabular-nums">{{ $certificado->calificacion_final }}%</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-gray-500">Fecha de emisión</dt>
                        <dd class="font-semibold text-gray-900">
                            {{ \Carbon\Carbon::parse($certificado->fecha_emision)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-gray-500">Número de certificado</dt>
                        <dd class="font-mono font-semibold text-gray-900">{{ $certificado->numero_certificado }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-gray-50 px-6 sm:px-8 py-4">
                <p class="text-xs text-gray-500">
                    Este certificado fue emitido por DyL Quality Consulting y acredita la finalización exitosa del curso indicado.
                </p>
            </div>
        </div>

    @else
        {{-- Certificado no encontrado --}}
        <div class="card overflow-hidden">
            <div class="h-1.5 bg-dyl-graphite-900"></div>

            <div class="p-8 sm:p-10 text-center">
                <p class="inline-flex items-center gap-2 bg-dyl-graphite-900 text-white px-4 py-2 rounded-full text-sm font-medium mb-6" role="status">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Certificado no encontrado
                </p>

                <p class="text-gray-600 mb-2">No se encontró ningún certificado con el número:</p>
                <p class="font-mono font-semibold text-gray-900 text-lg mb-6 break-all">{{ $numeroCertificado }}</p>
                <p class="text-sm text-gray-600">
                    Revisa que el número esté completo y sin espacios. Si el problema continúa,
                    contacta a DyL Quality Consulting.
                </p>
            </div>
        </div>
    @endif

</div>
@endsection
