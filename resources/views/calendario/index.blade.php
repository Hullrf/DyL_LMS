@extends('layouts.app')
@section('title', 'Calendario - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('calendario.index') }}@endsection
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="font-display text-4xl font-semibold text-gray-900">Calendario</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('calendario.index', array_filter(['mes' => $anterior, 'curso' => $cursoId])) }}" class="btn-outline btn-sm" aria-label="Mes anterior">◄</a>
        <span class="w-40 text-center font-display text-xl font-semibold capitalize">{{ $mes->locale('es')->isoFormat('MMMM YYYY') }}</span>
        <a href="{{ route('calendario.index', array_filter(['mes' => $siguiente, 'curso' => $cursoId])) }}" class="btn-outline btn-sm" aria-label="Mes siguiente">►</a>
    </div>
</div>

<div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
        <label class="text-sm text-gray-700">Curso
            <select name="curso" class="form-select mt-1 block w-auto max-w-xs" onchange="this.form.submit()">
                <option value="">Todos los cursos</option>
                @foreach($cursos as $c)
                    <option value="{{ $c->id }}" @selected($cursoId === $c->id)>{{ $c->titulo }}</option>
                @endforeach
            </select>
        </label>
    </form>

    {{-- Leyenda: los colores de los eventos significan algo --}}
    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600" aria-label="Tipos de evento">
        <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-dyl-orange-100 border border-dyl-orange-300" aria-hidden="true"></span>Cierre de entrega</li>
        <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-dyl-graphite-100 border border-dyl-graphite-300" aria-hidden="true"></span>Apertura</li>
        <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-white border border-dyl-graphite-400" aria-hidden="true"></span>Evento personal</li>
    </ul>
</div>

<div class="overflow-x-auto">
<div class="grid min-w-[720px] grid-cols-7 gap-px rounded-2xl bg-gray-200 shadow-card overflow-hidden text-sm">
    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)
        <div class="bg-gray-50 px-2 py-1.5 text-center text-xs font-medium text-gray-500">{{ $d }}</div>
    @endforeach
    @foreach($semanas as $semana)
        @foreach($semana as $dia)
            @php $eventos = $porDia[$dia->format('Y-m-d')] ?? collect(); @endphp
            <div class="min-h-24 bg-white p-1.5 {{ $dia->month !== $mes->month ? 'opacity-50' : '' }}">
                @if($dia->isToday())
                    <p class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-dyl-orange-700 text-xs font-bold text-white" aria-label="Hoy, {{ $dia->day }}">{{ $dia->day }}</p>
                @else
                    <p class="text-xs text-gray-500">{{ $dia->day }}</p>
                @endif
                <ul class="mt-1 max-h-28 space-y-1 overflow-y-auto">
                    @foreach($eventos as $e)
                        <li class="rounded px-1.5 py-0.5 text-xs leading-tight
                            {{ $e->tipo === 'cierre' ? 'bg-dyl-orange-100 text-dyl-orange-800 border border-dyl-orange-300' : ($e->tipo === 'apertura' ? 'bg-dyl-graphite-100 text-dyl-graphite-700 border border-dyl-graphite-300' : 'bg-white text-gray-800 border border-dyl-graphite-400') }}">
                            <span class="font-medium tabular-nums">{{ $e->inicia->format('H:i') }}</span>
                            @if($e->url)
                                <a href="{{ $e->url }}" class="hover:underline" title="{{ $e->detalle }}">{{ $e->titulo }}</a>
                            @else
                                {{ $e->titulo }}
                                <form method="POST" action="{{ route('calendario.eventos.destroy', $e->id) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar este evento?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-1 text-gray-400 hover:text-gray-900" aria-label="Eliminar evento">×</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endforeach
</div>
</div>

<details class="card mt-6 p-4">
    <summary class="cursor-pointer text-sm font-medium text-dyl-orange-700">Nuevo evento personal</summary>
    <form method="POST" action="{{ route('calendario.eventos.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        <label class="text-sm text-gray-700 sm:col-span-2">Título
            <input type="text" name="titulo" maxlength="255" required value="{{ old('titulo') }}" class="form-input mt-1 block w-full">
        </label>
        <label class="text-sm text-gray-700">Inicia
            <input type="datetime-local" name="inicia_en" required value="{{ old('inicia_en') }}" class="form-input mt-1 block w-full">
        </label>
        <label class="text-sm text-gray-700">Termina (opcional)
            <input type="datetime-local" name="termina_en" value="{{ old('termina_en') }}" class="form-input mt-1 block w-full">
        </label>
        <label class="text-sm text-gray-700 sm:col-span-2">Descripción (opcional)
            <textarea name="descripcion" rows="2" maxlength="2000" class="form-textarea mt-1 block w-full">{{ old('descripcion') }}</textarea>
        </label>
        <div><button class="btn-primary">Agregar</button></div>
    </form>
</details>

<div class="card mt-6 p-4 text-sm">
    <p class="font-display text-lg font-semibold text-gray-900 mb-2">Llévalo a tu calendario</p>
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('calendario.exportar') }}" class="btn-outline btn-sm">Descargar .ics</a>
        @if(auth()->user()->calendario_token)
            <input type="text" readonly value="{{ route('calendario.feed', auth()->user()->calendario_token) }}"
                   onclick="this.select()" class="form-input min-w-0 flex-1 text-xs" aria-label="Enlace de suscripción">
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="btn-outline btn-sm">Regenerar</button></form>
            <form method="POST" action="{{ route('calendario.token.destroy') }}">@csrf @method('DELETE')
                <button class="btn-outline btn-sm">Revocar</button></form>
        @else
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="btn-outline btn-sm">Crear enlace de suscripción</button></form>
        @endif
    </div>
    <p class="mt-2 text-xs text-gray-500">Quien tenga el enlace de suscripción puede ver tus fechas; regenéralo o revócalo si lo compartiste por error.</p>
</div>
@endsection
