@extends('layouts.app')
@section('title', 'Notificaciones - LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Notificaciones</h1>
        @if($notificaciones->where('leido', false)->count() > 0)
            <form method="POST" action="{{ route('notificaciones.marcar-todas') }}">
                @csrf
                <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    @forelse($notificaciones as $n)
        <a href="{{ route('notificaciones.marcar', $n) }}"
           class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 hover:shadow-md transition-shadow {{ $n->leido ? '' : 'border-l-4 border-l-blue-500 bg-blue-50/30' }}">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 shrink-0">
                    @if($n->tipo === 'calificacion')
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @elseif($n->tipo === 'entrega')
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    @elseif($n->tipo === 'certificado')
                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    @else
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $n->titulo }}</h3>
                        <span class="text-xs text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{{ $n->mensaje }}</p>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <p class="text-gray-500">No tienes notificaciones</p>
        </div>
    @endforelse

    <div class="mt-4">
        {{ $notificaciones->links() }}
    </div>
</div>
@endsection
