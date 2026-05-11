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
    <div class="card border border-red-200">
        <div class="card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>

</div>
@endsection
