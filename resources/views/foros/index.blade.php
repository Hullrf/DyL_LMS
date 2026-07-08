@extends('layouts.app')
@section('title', 'Foros - ' . $curso->titulo . ' - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Foros de discusión</h1>
            <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
        </div>
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <a href="{{ route('foros.create', $curso) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Nuevo foro</a>
        @endif
    </div>

    @forelse($foros as $foro)
        <a href="{{ route('foros.show', $foro) }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-3 hover:shadow-md transition-shadow">
            <h3 class="font-semibold text-gray-900 mb-1">{{ $foro->titulo }}</h3>
            @if($foro->descripcion)<p class="text-sm text-gray-500 mb-2">{{ Str::limit($foro->descripcion, 120) }}</p>@endif
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span>{{ $foro->creador->name }}</span>
                <span>{{ $foro->created_at->diffForHumans() }}</span>
                <span>{{ $foro->comentarios->count() }} comentarios</span>
                @if($foro->leccion)
                    <span class="text-blue-500">Lección: {{ $foro->leccion->titulo }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-400">
            No hay foros en este curso todavía.
        </div>
    @endforelse
</div>
@endsection
