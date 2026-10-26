@extends('layouts.app')
@section('title', $mensaje->asunto . ' - D&L Quality Campus')
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('mensajes.bandeja') }}" class="text-dyl-orange-700 hover:text-dyl-orange-800 text-sm mb-4 inline-block">&larr; Volver a la bandeja</a>

    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h1 class="font-display text-2xl font-semibold text-gray-900">{{ $mensaje->asunto }}</h1>
                <p class="mt-1 flex flex-wrap gap-x-4 text-sm text-gray-500">
                    <span class="font-medium text-gray-900">{{ $mensaje->remitente->name }}</span>
                    <span>{{ $mensaje->created_at->format('d/m/Y H:i') }}</span>
                    <span>Curso: <span class="font-medium text-dyl-orange-700">{{ $mensaje->curso->titulo }}</span></span>
                </p>
            </div>
            <a href="{{ route('mensajes.create', ['curso_id' => $mensaje->curso_id, 'padre_id' => $mensaje->id]) }}"
               class="btn-outline btn-sm shrink-0">Responder</a>
        </div>
        <div class="text-gray-700 leading-relaxed border-t border-gray-100 pt-4">
            {!! nl2br(e($mensaje->mensaje)) !!}
        </div>
    </div>

    @foreach($mensaje->respuestas as $r)
        <div class="card p-4 mb-3 ml-4 border-l-4 border-dyl-graphite-300">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900">{{ $r->remitente->name }}</span>
                    <span class="text-xs text-gray-500">{{ $r->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            <p class="text-sm text-gray-700">{!! nl2br(e($r->mensaje)) !!}</p>
        </div>
    @endforeach
</div>
@endsection
