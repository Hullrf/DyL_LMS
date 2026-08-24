@extends('layouts.app')
@section('title', 'Todos los anuncios - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    @include('partials.tabs-comunicacion')

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Todos los anuncios</h1>

    @forelse($anuncios as $a)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-3">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <span class="text-xs text-dyl-orange-600 font-medium">{{ $a->curso->titulo }}</span>
                    <h3 class="font-semibold text-gray-900 mt-0.5">{{ $a->titulo }}</h3>
                </div>
                <span class="text-xs text-gray-400">{{ $a->created_at->diffForHumans() }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $a->contenido }}</p>
            <p class="text-xs text-gray-400 mt-2">Por {{ $a->creador->name }}</p>
        </div>
    @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-400">
            No hay anuncios disponibles.
        </div>
    @endforelse

    <div class="mt-4">{{ $anuncios->links() }}</div>
</div>
@endsection
