@extends('layouts.app')
@section('title', 'Foros - ' . $curso->titulo . ' - D&L Quality Campus')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="font-display text-3xl font-semibold text-gray-900">Foros de discusión</h1>
            <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
        </div>
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <a href="{{ route('foros.create', $curso) }}" class="btn-primary">+ Nuevo foro</a>
        @endif
    </div>

    @forelse($foros as $foro)
        <a href="{{ route('foros.show', $foro) }}" class="card-hover block p-5 mb-3">
            <h2 class="font-display text-xl font-semibold text-gray-900 mb-1">{{ $foro->titulo }}</h2>
            @if($foro->descripcion)<p class="text-sm text-gray-500 mb-2">{{ Str::limit(strip_tags($foro->descripcion), 120) }}</p>@endif
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                <span>{{ $foro->creador->name }}</span>
                <span>{{ $foro->created_at->diffForHumans() }}</span>
                <span>{{ $foro->comentarios->count() }} {{ $foro->comentarios->count() === 1 ? 'comentario' : 'comentarios' }}</span>
                @if($foro->leccion)
                    <span class="text-dyl-graphite-500">Lección: {{ $foro->leccion->titulo }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="card p-12 text-center text-gray-500">
            No hay foros en este curso todavía.
        </div>
    @endforelse
</div>
@endsection
