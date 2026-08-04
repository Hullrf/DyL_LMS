@extends('layouts.app')
@section('title', 'Mensajes - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Mensajes</h1>
        <a href="{{ route('mensajes.create') }}" class="bg-dyl-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-dyl-orange-700">+ Nuevo mensaje</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    @forelse($recibidos as $m)
        <a href="{{ route('mensajes.conversacion', $m) }}"
           class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $m->leido ? '' : 'border-l-4 border-l-dyl-orange-500' }}">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-medium text-sm text-gray-900">{{ $m->remitente->name }}</span>
                        <span class="text-xs text-gray-400">en</span>
                        <span class="text-xs text-dyl-orange-600 font-medium">{{ $m->curso->titulo }}</span>
                        @if(!$m->leido)
                            <span class="w-2 h-2 bg-dyl-orange-500 rounded-full"></span>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-gray-800 truncate">{{ $m->asunto }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ Str::limit($m->mensaje, 100) }}</p>
                </div>
                <span class="text-xs text-gray-400 ml-3 shrink-0">{{ $m->created_at->diffForHumans() }}</span>
            </div>
        </a>
    @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-400">
            No tienes mensajes.
        </div>
    @endforelse

    <div class="mt-4">{{ $recibidos->links() }}</div>
</div>
@endsection
