@extends('layouts.app')
@section('title', 'Configurar 2FA — LMS DyL')
@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-2">Autenticación de dos factores</h1>
        <p class="text-gray-500 text-sm mb-6">
            Escanea este código QR con Google Authenticator, Authy u otra app TOTP compatible.
        </p>

        <div class="flex justify-center mb-6">
            <img src="data:image/svg+xml;base64,{{ $qrSvg }}"
                 alt="QR Code 2FA"
                 class="w-48 h-48 border border-gray-200 rounded-xl p-2 bg-white">
        </div>

        <p class="text-xs text-center text-gray-500 mb-6">
            ¿No puedes escanear? Usa la clave manual:<br>
            <code class="bg-gray-100 px-2 py-1 rounded font-mono text-sm">{{ $user->two_factor_secret }}</code>
        </p>

        <form method="POST" action="{{ route('2fa.enable') }}">
            @csrf
            <label class="form-label">Código de verificación (6 dígitos)</label>
            <input type="text" name="code" class="form-input text-center tracking-widest text-lg"
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            @error('code')<p class="form-error text-center mt-1">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full mt-4">Activar 2FA</button>
        </form>
    </div>
</div>
@endsection
