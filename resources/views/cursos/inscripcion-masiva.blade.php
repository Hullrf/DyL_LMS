@extends('layouts.app')
@section('title', 'Inscribir Estudiantes - ' . $curso->titulo)
@section('breadcrumbs'){{ Breadcrumbs::render('cursos.edit', $curso) }}@endsection
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inscribir Estudiantes</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $curso->titulo }}</p>
        </div>
        <a href="{{ route('cursos.edit', $curso) }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm">&larr; Volver al curso</a>
    </div>

    {{-- Formulario de búsqueda --}}
    <form method="GET" class="mb-4 flex gap-3">
        <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o email..."
               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-dyl-orange-600">
        <button type="submit" class="px-4 py-2 bg-dyl-orange-600 text-white rounded-lg text-sm hover:bg-dyl-orange-700">Buscar</button>
        @if(request('buscar'))
            <a href="{{ route('cursos.inscripcion-masiva', $curso) }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
        @endif
    </form>

    <form method="POST" action="{{ route('cursos.inscripcion-masiva', $curso) }}">
        @csrf
        <div class="bg-white rounded-lg shadow overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Usuarios disponibles</span>
                <button type="submit" class="px-4 py-1.5 bg-dyl-orange-600 text-white text-sm rounded-lg hover:bg-dyl-orange-700">Inscribir seleccionados</button>
            </div>

            @if($usuarios->isEmpty())
                <div class="p-12 text-center text-gray-400 text-sm">No se encontraron usuarios disponibles para inscribir.</div>
            @else
                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    @foreach($usuarios as $usuario)
                        <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="usuarios[]" value="{{ $usuario->id }}" class="w-4 h-4 rounded text-dyl-orange-600">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $usuario->name }}</p>
                                <p class="text-xs text-gray-500">{{ $usuario->email }}
                                    @if($usuario->empresa) · {{ $usuario->empresa }} @endif
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">
                {{ $inscritosIds ? count($inscritosIds) . ' ya inscritos' : '' }}
            </span>
            <button type="submit" class="px-6 py-2 bg-dyl-orange-600 text-white rounded-lg hover:bg-dyl-orange-700 font-medium text-sm">
                Inscribir seleccionados
            </button>
        </div>
        @error('usuarios')<p class="form-error text-sm mt-2">{{ $message }}</p>@enderror
    </form>

    <div class="mt-4">{{ $usuarios->links() }}</div>
</div>
@endsection
