@extends('layouts.app')
@section('title', 'Crear Usuario — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.usuarios.create') }}@endsection
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-900">Crear Usuario</h1>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.store') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 space-y-5">
        @csrf

        <div>
            <label class="form-label">Nombre completo</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-input" required>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-input" required>
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" value="{{ old('empresa') }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select name="estado" class="form-input">
                <option value="activo"   @selected(old('estado', 'activo') === 'activo')>Activo</option>
                <option value="inactivo" @selected(old('estado') === 'inactivo')>Inactivo</option>
            </select>
        </div>
        <div>
            <label class="form-label">Roles</label>
            <div class="space-y-2 mt-1">
            @foreach($roles as $rol)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
                           @checked(in_array($rol->id, old('roles', [])))
                           class="rounded text-blue-600">
                    <span class="text-sm text-gray-700 font-medium">{{ $rol->nombre }}</span>
                    @if($rol->descripcion)
                        <span class="text-xs text-gray-400">— {{ $rol->descripcion }}</span>
                    @endif
                </label>
            @endforeach
            </div>
            @error('roles')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Crear Usuario</button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
