@extends('layouts.app')
@section('title', 'Gestión de Usuarios — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.usuarios.index') }}@endsection
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
        <a href="{{ route('admin.usuarios.create') }}" class="btn-primary">+ Nuevo Usuario</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
        <input type="text" name="buscar" value="{{ request('buscar') }}"
               placeholder="Nombre o email..." class="form-input flex-1 min-w-48">
        <select name="rol" class="form-input w-44">
            <option value="">Todos los roles</option>
            @foreach($roles as $rol)
                <option value="{{ $rol->id }}" @selected(request('rol') == $rol->id)>{{ $rol->nombre }}</option>
            @endforeach
        </select>
        <select name="estado" class="form-input w-36">
            <option value="">Todos los estados</option>
            <option value="activo"   @selected(request('estado') === 'activo')>Activo</option>
            <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
        </select>
        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Limpiar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="tbl-th">Nombre</th>
                    <th class="tbl-th">Email</th>
                    <th class="tbl-th">Empresa</th>
                    <th class="tbl-th">Rol</th>
                    <th class="tbl-th">Estado</th>
                    <th class="tbl-th">Registro</th>
                    <th class="tbl-th">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($usuarios as $usuario)
                <tr class="tbl-row">
                    <td class="tbl-td font-medium text-gray-900">{{ $usuario->name }}</td>
                    <td class="tbl-td text-gray-600">{{ $usuario->email }}</td>
                    <td class="tbl-td text-gray-600">{{ $usuario->empresa ?? '—' }}</td>
                    <td class="tbl-td">
                        @foreach($usuario->roles as $rol)
                            <span class="badge badge-blue">{{ $rol->nombre }}</span>
                        @endforeach
                    </td>
                    <td class="tbl-td">
                        @if($usuario->estado === 'activo')
                            <span class="badge badge-green">Activo</span>
                        @else
                            <span class="badge badge-red">Inactivo</span>
                        @endif
                    </td>
                    <td class="tbl-td text-gray-500 text-sm">{{ $usuario->created_at->format('d/m/Y') }}</td>
                    <td class="tbl-td">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn-outline btn-sm">Editar</a>
                            @if($usuario->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                  onsubmit="return confirm('¿Eliminar a {{ addslashes($usuario->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="tbl-td text-center text-gray-400 py-10">No se encontraron usuarios.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $usuarios->links() }}</div>
</div>
@endsection
