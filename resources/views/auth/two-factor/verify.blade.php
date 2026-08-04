@extends('layouts.guest')
@section('title', 'Verificación 2FA — LMS DyL')

<div class="max-w-sm mx-auto mt-16 px-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex justify-center mb-4">
            <div class="w-12 h-12 rounded-full bg-dyl-orange-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-dyl-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        </div>
        <h1 class="text-xl font-bold text-gray-900 text-center mb-2">Verificación de seguridad</h1>
        <p class="text-gray-500 text-sm text-center mb-6">
            Ingresa el código de 6 dígitos de tu app de autenticación.
        </p>
        <form method="POST" action="{{ route('2fa.check') }}">
            @csrf
            <input type="text" name="code"
                   class="form-input text-center text-2xl tracking-widest w-full"
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                   required autofocus placeholder="000000">
            @error('code')<p class="form-error text-center mt-2">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full mt-4">Verificar</button>
        </form>
    </div>
</div>
