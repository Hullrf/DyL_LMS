@extends('layouts.app')
@section('title', 'Verificar Certificado - LMS DyL')

@section('content')
<div class="max-w-2xl mx-auto">

    <div class="text-center mb-10">
        <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Verificación de Certificado</h1>
        <p class="text-gray-500 mt-1">DyL Quality Consulting</p>
    </div>

    @if($certificado)
        {{-- Certificado válido --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-green-400 to-green-500"></div>

            <div class="p-8 text-center">
                <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Certificado Válido y Auténtico
                </div>

                <div class="space-y-4 text-left max-w-md mx-auto">
                    <div class="flex justify-between py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-500">Estudiante</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $certificado->usuario->name }}</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-500">Curso</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $certificado->curso->titulo }}</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-500">Calificación final</span>
                        <span class="text-sm font-semibold text-green-600">{{ $certificado->calificacion_final }}%</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-gray-100">
                        <span class="text-sm text-gray-500">Fecha de emisión</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ \Carbon\Carbon::parse($certificado->fecha_emision)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                        </span>
                    </div>
                    <div class="flex justify-between py-3">
                        <span class="text-sm text-gray-500">N° Certificado</span>
                        <span class="text-sm font-mono font-bold text-gray-800">{{ $certificado->numero_certificado }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-4 text-center">
                <p class="text-xs text-gray-400">
                    Este certificado fue emitido por DyL Quality Consulting y acredita la finalización exitosa del curso indicado.
                </p>
            </div>
        </div>

    @else
        {{-- Certificado no encontrado --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="h-2 bg-gradient-to-r from-red-400 to-red-500"></div>

            <div class="p-10 text-center">
                <div class="inline-flex items-center gap-2 bg-red-50 text-red-600 px-4 py-2 rounded-full text-sm font-medium mb-6">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Certificado No Encontrado
                </div>

                <p class="text-gray-600 mb-2">
                    No se encontró ningún certificado con el número:
                </p>
                <p class="font-mono font-bold text-gray-800 text-lg mb-6">{{ $numeroCertificado }}</p>
                <p class="text-sm text-gray-400">
                    Por favor verifica que el número sea correcto. Si el problema persiste,
                    contacta a DyL Quality Consulting.
                </p>
            </div>
        </div>
    @endif

</div>
@endsection
