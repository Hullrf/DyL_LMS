@extends('layouts.app')

@section('title', 'Mi Perfil - LMS DyL')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mi Perfil</h1>
        <p class="mt-1 text-sm text-gray-500">Administra tu información personal y configuración de cuenta.</p>
    </div>

    {{-- Información del perfil --}}
    <div class="card">
        <div class="card-body">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    {{-- Cambiar contraseña --}}
    <div class="card">
        <div class="card-body">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    {{-- Eliminar cuenta --}}
    <div class="card">
        <div class="card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>

    {{-- 2FA --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Autenticación de dos factores</h2>
        @if(auth()->user()->two_factor_enabled)
            <p class="text-dyl-orange-600 text-sm mb-4 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                2FA activo — tu cuenta tiene una capa extra de seguridad.
            </p>
            <form method="POST" action="{{ route('2fa.disable') }}"
                  onsubmit="return confirm('¿Desactivar la autenticación de dos factores?')">
                @csrf
                <button type="submit" class="btn-danger">Desactivar 2FA</button>
            </form>
        @else
            <p class="text-gray-500 text-sm mb-4">Añade seguridad extra a tu cuenta con una app de autenticación.</p>
            <a href="{{ route('2fa.setup') }}" class="btn-primary">Configurar 2FA</a>
        @endif
    </div>

</div>
@endsection
