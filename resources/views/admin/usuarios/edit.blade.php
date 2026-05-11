@extends('layouts.app')
@section('title', 'Editar Usuario — ' . $usuario->name)
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-900">Editar: {{ $usuario->name }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="form-label">Nombre completo</label>
            <input type="text" name="name" value="{{ old('name', $usuario->name) }}" class="form-input" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-input" required>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">
                Nueva contraseña
                <span class="text-gray-400 font-normal">(dejar vacío para no cambiar)</span>
            </label>
            <input type="password" name="password" class="form-input">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" name="password_confirmation" class="form-input">
        </div>
        <div>
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" value="{{ old('empresa', $usuario->empresa) }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select name="estado" class="form-input">
                <option value="activo"   @selected(old('estado', $usuario->estado) === 'activo')>Activo</option>
                <option value="inactivo" @selected(old('estado', $usuario->estado) === 'inactivo')>Inactivo</option>
            </select>
        </div>
        <div>
            <label class="form-label">Roles</label>
            <div class="space-y-2 mt-1">
            @foreach($roles as $rol)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
                           @checked(in_array($rol->id, old('roles', $rolesActivos)))
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
            <button type="submit" class="btn-primary">Guardar Cambios</button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
