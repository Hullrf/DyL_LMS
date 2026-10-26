@extends('layouts.app')
@section('title', 'Anuncios - ' . $curso->titulo . ' - D&L Quality Campus')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="font-display text-3xl font-semibold text-gray-900">Anuncios</h1>
            <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
        </div>
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <a href="{{ route('anuncios.create', $curso) }}" class="btn-primary">+ Nuevo anuncio</a>
        @endif
    </div>

    @foreach($anuncios as $a)
        <article class="card p-5 mb-3">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-display text-xl font-semibold text-gray-900">{{ $a->titulo }}</h2>
                <span class="text-xs text-gray-500">{{ $a->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $a->contenido }}</p>
            <p class="text-xs text-gray-500 mt-2">Por {{ $a->creador->name }}</p>
        </article>
    @endforeach

    <div class="mt-4">{{ $anuncios->links() }}</div>
</div>
@endsection
