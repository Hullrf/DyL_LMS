@extends('layouts.app')
@section('title', $mensaje->asunto . ' - LMS DyL')
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('mensajes.bandeja') }}" class="text-dyl-orange-600 hover:text-dyl-orange-700 text-sm mb-4 inline-block">&larr; Volver a la bandeja</a>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ $mensaje->asunto }}</h1>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $mensaje->remitente->name }} ·
                    {{ $mensaje->created_at->format('d/m/Y H:i') }} ·
                    Curso: <span class="font-medium text-dyl-orange-600">{{ $mensaje->curso->titulo }}</span>
                </p>
            </div>
            <a href="{{ route('mensajes.create', ['curso_id' => $mensaje->curso_id, 'padre_id' => $mensaje->id]) }}"
               class="text-sm text-dyl-orange-600 hover:text-dyl-orange-700">Responder</a>
        </div>
        <div class="prose prose-sm max-w-none text-gray-700 border-t pt-3">
            {!! nl2br(e($mensaje->mensaje)) !!}
        </div>
    </div>

    @foreach($mensaje->respuestas as $r)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-3 ml-4 border-l-4 border-l-gray-300">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900">{{ $r->remitente->name }}</span>
                    <span class="text-xs text-gray-400">{{ $r->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            <p class="text-sm text-gray-700">{!! nl2br(e($r->mensaje)) !!}</p>
        </div>
    @endforeach
</div>
@endsection
