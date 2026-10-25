@extends('layouts.app')
@section('title', 'Mi Aprendizaje - D&L Quality Campus')

@section('content')
@php
    $progresoDe = fn($c) => $resumen[$c->id]['porcentaje'] ?? null;
    $destacado = $cursos_inscritos->first(fn($c) => $progresoDe($c) !== null && $progresoDe($c) > 0 && $progresoDe($c) < 100)
        ?? $cursos_inscritos->first();
    $otros = $cursos_inscritos->reject(fn($c) => $destacado && $c->id === $destacado->id);
    $pctDestacado = $destacado ? ($progresoDe($destacado) ?? 0) : 0;
@endphp

<header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3 mb-8">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    <dl class="flex gap-8 text-sm text-gray-500">
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['cursos_activos'] }}</dd><dt>en curso</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['completados'] }}</dd><dt>completados</dt></div>
        <div><dd class="font-display text-2xl font-semibold text-gray-900 tabular-nums">{{ $stats['progreso_general'] }}%</dd><dt>de avance total</dt></div>
    </dl>
</header>

@if($destacado)
<section aria-labelledby="continuar-titulo" class="card overflow-hidden grid md:grid-cols-5 mb-10">
    <div class="md:col-span-2 h-40 md:h-auto md:min-h-[16rem] relative">
        @if($destacado->imagen_portada)
            <img src="{{ asset('storage/' . $destacado->imagen_portada) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
        @else
            <x-portada-curso :curso="$destacado" class="absolute inset-0"/>
        @endif
    </div>
    <div class="md:col-span-3 p-6 sm:p-8 flex flex-col justify-center">
        <p class="text-sm text-gray-500 mb-1">{{ $pctDestacado > 0 ? 'Retoma donde lo dejaste' : 'Empieza tu primer curso' }}</p>
        <h2 id="continuar-titulo" class="font-display text-3xl font-semibold text-gray-900 mb-5 break-words">{{ $destacado->titulo }}</h2>
        <div class="flex items-center gap-3 mb-6" role="progressbar" aria-valuenow="{{ $pctDestacado }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso del curso">
            <div class="progress-bar flex-1"><div class="progress-blue" style="width: {{ $pctDestacado }}%"></div></div>
            <span class="text-sm font-medium text-gray-700 tabular-nums">{{ $pctDestacado }}%</span>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('cursos.show', $destacado) }}" class="btn-primary btn-lg">{{ $pctDestacado > 0 ? 'Continuar curso' : 'Empezar curso' }}</a>
            <span class="text-sm text-gray-500">{{ $destacado->duracion_horas }} horas</span>
        </div>
    </div>
</section>
@endif

<div class="grid lg:grid-cols-3 gap-10">
    <section class="lg:col-span-2 min-w-0" aria-labelledby="cursos-titulo">
        <h2 id="cursos-titulo" class="font-display text-2xl font-semibold text-gray-900 mb-4">{{ $destacado ? 'Tus otros cursos' : 'Mis cursos' }}</h2>
        @if($cursos_inscritos->isEmpty())
            <div class="card p-8 text-center text-gray-500">
                Aún no estás inscrito en ningún curso.
                <a href="{{ route('cursos.index') }}" class="text-dyl-orange-700 font-medium hover:underline ml-1">Explorar el catálogo</a>
            </div>
        @elseif($otros->isEmpty())
            <p class="text-sm text-gray-500">Este es tu único curso por ahora.
                <a href="{{ route('cursos.index') }}" class="text-dyl-orange-700 font-medium hover:underline">Explorar el catálogo</a></p>
        @else
            <div class="grid sm:grid-cols-2 gap-5">
                @foreach($otros as $curso)
                    <a href="{{ route('cursos.show', $curso) }}" class="card-hover overflow-hidden block group">
                        <div class="h-32">
                            @if($curso->imagen_portada)
                                <img src="{{ asset('storage/' . $curso->imagen_portada) }}" alt="" class="w-full h-full object-cover">
                            @else
                                <x-portada-curso :curso="$curso"/>
                            @endif
                        </div>
                        <div class="p-5">
                            <h3 class="font-display text-lg font-semibold text-gray-900 mb-1 group-hover:text-dyl-orange-700">{{ $curso->titulo }}</h3>
                            @include('cursos._resumen', ['curso' => $curso])
                            <p class="text-xs text-gray-500">{{ $curso->duracion_horas }} horas</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <aside class="min-w-0" aria-labelledby="entregas-titulo">
        <div class="flex items-baseline justify-between mb-4">
            <h2 id="entregas-titulo" class="font-display text-2xl font-semibold text-gray-900">Próximas entregas</h2>
            <a href="{{ route('linea-de-tiempo.index') }}" class="text-sm text-dyl-orange-700 hover:underline">Ver todo</a>
        </div>
        @include('partials.linea-de-tiempo', ['lineaDeTiempo' => $lineaDeTiempo, 'compacto' => true])
    </aside>
</div>
@endsection
