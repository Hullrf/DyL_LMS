@extends('layouts.app')
@section('title', $foro->titulo . ' - D&L Quality Campus')
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('foros.index', $foro->curso) }}" class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm mb-4 inline-block">&larr; Volver a foros</a>

    <div class="card p-6 mb-6">
        <h1 class="font-display text-3xl font-semibold text-gray-900 mb-2 break-words">{{ $foro->titulo }}</h1>
        @if($foro->descripcion)<div class="text-gray-600 text-sm mb-3">@include('components.descripcion-render', ['slot' => $foro->descripcion])</div>@endif
        <p class="text-xs text-gray-500">{{ $foro->creador->name }} · {{ $foro->created_at->format('d/m/Y') }}
            @if($foro->leccion) · Lección: {{ $foro->leccion->titulo }} @endif
        </p>
    </div>

    @forelse($foro->comentarios as $c)
        <div class="card p-4 mb-3">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-sm font-semibold text-gray-900">{{ $c->usuario->name }}</span>
                <span class="text-xs text-gray-500">{{ $c->created_at->diffForHumans() }}</span>
            </div>
            <p class="text-sm text-gray-700">{{ $c->contenido }}</p>
            <div class="mt-2">
                <button onclick="document.getElementById('reply-{{ $c->id }}').classList.toggle('hidden')" class="text-xs text-dyl-orange-700 hover:underline">Responder</button>
                <form id="reply-{{ $c->id }}" method="POST" action="{{ route('foros.comentar', $foro) }}" class="hidden mt-2 flex gap-2">
                    @csrf
                    <input type="hidden" name="padre_id" value="{{ $c->id }}">
                    <input aria-label="Escribe una respuesta..." type="text" name="contenido" class="form-input flex-1" placeholder="Escribe una respuesta..." required>
                    <button type="submit" class="btn-primary btn-sm">Enviar</button>
                </form>
            </div>

            @foreach($c->respuestas as $r)
                <div class="ml-6 mt-3 pl-4 border-l-2 border-dyl-graphite-200">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-medium text-gray-900">{{ $r->usuario->name }}</span>
                        <span class="text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-700">{{ $r->contenido }}</p>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card p-8 text-center text-gray-500 text-sm">
            No hay comentarios todavía. ¡Sé el primero en comentar!
        </div>
    @endforelse

    <form method="POST" action="{{ route('foros.comentar', $foro) }}" class="mt-4 flex gap-2">
        @csrf
        <input aria-label="Escribe un comentario..." type="text" name="contenido" class="form-input flex-1" placeholder="Escribe un comentario..." required>
        <button type="submit" class="btn-primary">Comentar</button>
    </form>
</div>
@endsection
