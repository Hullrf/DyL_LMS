@extends('layouts.app')
@section('title', 'Gestión de Categorías — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.categorias.index') }}@endsection
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Categorías</h1>
        <a href="{{ route('admin.categorias.create') }}" class="btn-primary">+ Nueva Categoría</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
        <input type="text" name="buscar" value="{{ request('buscar') }}"
               placeholder="Buscar por nombre..." class="form-input flex-1 min-w-48">
        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="{{ route('admin.categorias.index') }}" class="btn-outline">Limpiar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="tbl-th">Categoría</th>
                    <th class="tbl-th">Cursos</th>
                    <th class="tbl-th">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($categorias as $categoria)
                <tr class="tbl-row">
                    <td class="tbl-td">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full inline-block" style="background-color: {{ $categoria->color }}"></span>
                            <span class="font-medium text-gray-900">{{ $categoria->nombre }}</span>
                        </span>
                    </td>
                    <td class="tbl-td text-gray-600">{{ $categoria->cursos_count }}</td>
                    <td class="tbl-td">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.categorias.edit', $categoria) }}" class="btn-outline btn-sm">Editar</a>
                            <form method="POST" action="{{ route('admin.categorias.destroy', $categoria) }}"
                                  onsubmit="return confirm('¿Eliminar \'{{ addslashes($categoria->nombre) }}\'?{{ $categoria->cursos_count > 0 ? ' Los ' . $categoria->cursos_count . ' curso(s) que la usan quedarán sin categoría.' : '' }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="tbl-td text-center text-gray-400 py-10">No se encontraron categorías.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $categorias->links() }}</div>
</div>
@endsection
