@extends('layouts.app')
@section('title', 'Todos los anuncios - D&L Quality Campus')
@section('content')
<div class="max-w-3xl mx-auto">
    @include('partials.tabs-comunicacion')

    <h1 class="font-display text-3xl font-semibold text-gray-900 mb-6">Todos los anuncios</h1>

    @forelse($anuncios as $a)
        <article class="card p-5 mb-3">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <span class="text-xs text-dyl-orange-700 font-medium">{{ $a->curso->titulo }}</span>
                    <h2 class="font-display text-xl font-semibold text-gray-900 mt-0.5">{{ $a->titulo }}</h2>
                </div>
                <span class="text-xs text-gray-500">{{ $a->created_at->diffForHumans() }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $a->contenido }}</p>
            <p class="text-xs text-gray-500 mt-2">Por {{ $a->creador->name }}</p>
        </article>
    @empty
        <div class="card p-12 text-center text-gray-500">
            No hay anuncios disponibles.
        </div>
    @endforelse

    <div class="mt-4">{{ $anuncios->links() }}</div>
</div>
@endsection
