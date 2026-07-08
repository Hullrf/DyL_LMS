@extends('layouts.app')
@section('title', 'Anuncios - ' . $curso->titulo . ' - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Anuncios</h1>
            <p class="text-sm text-gray-500">{{ $curso->titulo }}</p>
        </div>
        @if(auth()->user()->esAdmin() || auth()->id() === $curso->created_by)
            <a href="{{ route('anuncios.create', $curso) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">+ Nuevo anuncio</a>
        @endif
    </div>

    @foreach($anuncios as $a)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-3">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold text-gray-900">{{ $a->titulo }}</h3>
                <span class="text-xs text-gray-400">{{ $a->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $a->contenido }}</p>
            <p class="text-xs text-gray-400 mt-2">Por {{ $a->creador->name }}</p>
        </div>
    @endforeach

    <div class="mt-4">{{ $anuncios->links() }}</div>
</div>
@endsection
