@extends('layouts.app')
@section('title', 'Calendario - D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('calendario.index') }}@endsection
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-3xl font-bold text-gray-900">Calendario</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('calendario.index', array_filter(['mes' => $anterior, 'curso' => $cursoId])) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" aria-label="Mes anterior">◄</a>
        <span class="w-40 text-center text-sm font-semibold capitalize">{{ $mes->locale('es')->isoFormat('MMMM YYYY') }}</span>
        <a href="{{ route('calendario.index', array_filter(['mes' => $siguiente, 'curso' => $cursoId])) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" aria-label="Mes siguiente">►</a>
    </div>
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
    <label class="text-sm text-gray-700">Curso
        <select name="curso" class="mt-1 block rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
            <option value="">Todos los cursos</option>
            @foreach($cursos as $c)
                <option value="{{ $c->id }}" @selected($cursoId === $c->id)>{{ $c->titulo }}</option>
            @endforeach
        </select>
    </label>
</form>

<div class="overflow-x-auto">
<div class="grid min-w-[720px] grid-cols-7 gap-px rounded-xl bg-gray-200 shadow overflow-hidden text-sm">
    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)
        <div class="bg-gray-50 px-2 py-1.5 text-center text-xs font-medium text-gray-500">{{ $d }}</div>
    @endforeach
    @foreach($semanas as $semana)
        @foreach($semana as $dia)
            @php $eventos = $porDia[$dia->format('Y-m-d')] ?? collect(); @endphp
            <div class="min-h-24 bg-white p-1.5 {{ $dia->month !== $mes->month ? 'opacity-50' : '' }}">
                <p class="text-xs {{ $dia->isToday() ? 'font-bold text-dyl-orange-700' : 'text-gray-500' }}">{{ $dia->day }}</p>
                <ul class="mt-1 max-h-28 space-y-1 overflow-y-auto">
                    @foreach($eventos as $e)
                        <li class="rounded px-1.5 py-0.5 text-xs leading-tight
                            {{ $e->tipo === 'cierre' ? 'bg-dyl-orange-100 text-dyl-orange-800' : ($e->tipo === 'apertura' ? 'bg-dyl-graphite-100 text-dyl-graphite-700' : 'bg-blue-50 text-blue-800') }}">
                            <span class="font-medium">{{ $e->inicia->format('H:i') }}</span>
                            @if($e->url)
                                <a href="{{ $e->url }}" class="hover:underline" title="{{ $e->detalle }}">{{ $e->titulo }}</a>
                            @else
                                {{ $e->titulo }}
                                <form method="POST" action="{{ route('calendario.eventos.destroy', $e->id) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar este evento?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-1 text-gray-400 hover:text-red-600" aria-label="Eliminar evento">×</button>
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

<details class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
    <summary class="cursor-pointer text-sm font-medium text-dyl-orange-700">Nuevo evento personal</summary>
    <form method="POST" action="{{ route('calendario.eventos.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        <label class="text-sm text-gray-700 sm:col-span-2">Título
            <input type="text" name="titulo" maxlength="255" required value="{{ old('titulo') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700">Inicia
            <input type="datetime-local" name="inicia_en" required value="{{ old('inicia_en') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700">Termina (opcional)
            <input type="datetime-local" name="termina_en" value="{{ old('termina_en') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm text-gray-700 sm:col-span-2">Descripción (opcional)
            <textarea name="descripcion" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">{{ old('descripcion') }}</textarea>
        </label>
        <div><button class="rounded-lg bg-dyl-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-dyl-orange-700">Agregar</button></div>
    </form>
</details>

<div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 text-sm">
    <p class="font-medium text-gray-900 mb-2">Llévalo a tu calendario</p>
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('calendario.exportar') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Descargar .ics</a>
        @if(auth()->user()->calendario_token)
            <input type="text" readonly value="{{ route('calendario.feed', auth()->user()->calendario_token) }}"
                   onclick="this.select()" class="min-w-0 flex-1 rounded-lg border-gray-300 text-xs" aria-label="Enlace de suscripción">
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Regenerar</button></form>
            <form method="POST" action="{{ route('calendario.token.destroy') }}">@csrf @method('DELETE')
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-red-600 hover:bg-red-50">Revocar</button></form>
        @else
            <form method="POST" action="{{ route('calendario.token.store') }}">@csrf
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Crear enlace de suscripción</button></form>
        @endif
    </div>
    <p class="mt-2 text-xs text-gray-500">Quien tenga el enlace de suscripción puede ver tus fechas; regenéralo o revócalo si lo compartiste por error.</p>
</div>
@endsection
