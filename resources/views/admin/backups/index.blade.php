@extends('layouts.app')
@section('title', 'Backups — D&L Quality Campus')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.backups.index') }}@endsection
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="font-display text-3xl font-semibold text-gray-900 mb-2">Backups de la base de datos</h1>
    <p class="text-gray-600 text-sm mb-6">
        Cada día a las 3:00 a. m. (hora de Colombia) se guarda una copia comprimida de la base de datos.
        Se conservan las últimas {{ $conservar }}; las más antiguas se eliminan solas.
    </p>

    @foreach (['backup', 'archivo'] as $campo)
        @error($campo)
            <div class="alert alert-error mb-6" role="alert">{{ $message }}</div>
        @enderror
    @endforeach

    {{-- Backups guardados --}}
    <div class="card mb-6">
        <div class="p-6 pb-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-semibold text-gray-900">Backups guardados</h2>
                <p class="text-sm text-gray-500 mt-1">{{ count($backups) }} de {{ $conservar }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.backups.guardar') }}">
                    @csrf
                    <button type="submit" class="btn-primary">Crear backup ahora</button>
                </form>
                <form method="POST" action="{{ route('admin.backups.crear') }}">
                    @csrf
                    <button type="submit" class="btn-outline"
                            title="Se descarga a tu computadora sin guardar copia en el servidor">
                        Descargar sin guardar
                    </button>
                </form>
            </div>
        </div>

        @if (count($backups) === 0)
            <p class="px-6 pb-6 text-sm text-gray-600">
                Todavía no hay backups guardados. Crea el primero con “Crear backup ahora”.
            </p>
        @else
            <ul class="border-t border-dyl-graphite-100">
                @foreach ($backups as $b)
                    <li class="px-6 py-4 border-b border-dyl-graphite-100 last:border-b-0" x-data="{ modo: null, confirmacion: '' }">
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">
                                    {{ $b['fecha']->copy()->setTimezone('America/Bogota')->format('d/m/Y, H:i') }}
                                </p>
                                <p class="text-xs text-gray-500 break-all">
                                    {{ $b['tamano'] >= 1048576 ? number_format($b['tamano'] / 1048576, 1) . ' MB' : max(1, round($b['tamano'] / 1024)) . ' KB' }} · {{ $b['nombre'] }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.backups.descargar', $b['nombre']) }}" class="btn-outline btn-sm">Descargar</a>
                                <button type="button" class="btn-outline btn-sm"
                                        @click="modo = modo === 'restaurar' ? null : 'restaurar'"
                                        :aria-expanded="modo === 'restaurar'">Restaurar</button>
                                <button type="button" class="btn-ghost btn-sm"
                                        @click="modo = modo === 'eliminar' ? null : 'eliminar'"
                                        :aria-expanded="modo === 'eliminar'">Eliminar</button>
                            </div>
                        </div>

                        {{-- Confirmar restauración --}}
                        <form x-show="modo === 'restaurar'" x-cloak method="POST"
                              action="{{ route('admin.backups.restaurar-guardado', $b['nombre']) }}"
                              class="mt-4 p-4 rounded-xl border-2 border-dyl-orange-300 bg-dyl-graphite-50">
                            @csrf
                            <p class="text-sm text-gray-800 mb-3">
                                Esto reemplaza <strong>todo</strong> el contenido actual de la base de datos por el de este backup.
                                Antes se guarda un backup del estado actual para que puedas volver atrás.
                            </p>
                            <label class="form-label" for="conf-{{ $loop->index }}">Escribe <strong>RESTAURAR</strong> para confirmar</label>
                            <div class="flex flex-wrap gap-2">
                                <input id="conf-{{ $loop->index }}" type="text" name="confirmacion" x-model="confirmacion"
                                       placeholder="RESTAURAR" autocomplete="off" class="form-input flex-1 min-w-[10rem]">
                                <button type="submit" class="btn text-white"
                                        :disabled="confirmacion !== 'RESTAURAR'"
                                        :class="confirmacion !== 'RESTAURAR' ? 'bg-gray-300 cursor-not-allowed' : 'bg-dyl-orange-600 hover:bg-dyl-orange-700'">
                                    Restaurar este backup
                                </button>
                            </div>
                        </form>

                        {{-- Confirmar eliminación --}}
                        <form x-show="modo === 'eliminar'" x-cloak method="POST"
                              action="{{ route('admin.backups.eliminar', $b['nombre']) }}"
                              class="mt-4 flex flex-wrap items-center gap-3">
                            @csrf
                            @method('DELETE')
                            <p class="text-sm text-gray-800">¿Eliminar este backup? No se puede recuperar.</p>
                            <button type="submit" class="btn-danger btn-sm">Sí, eliminar</button>
                            <button type="button" class="btn-ghost btn-sm" @click="modo = null">Cancelar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Restaurar desde un archivo --}}
    <details class="card" @if($errors->has('archivo')) open @endif
             x-data="{ confirmacion: '', archivo: null }">
        <summary class="p-6 cursor-pointer font-display text-2xl font-semibold text-gray-900">
            Restaurar desde un archivo
        </summary>
        <div class="px-6 pb-6">
            <p class="text-sm text-gray-600 mb-4">
                Sube un backup <code>.sql</code> o <code>.sql.gz</code> descargado antes. Reemplaza por completo el
                contenido actual de la base de datos y <strong>no se puede deshacer</strong>; antes se guarda
                automáticamente un backup del estado actual.
            </p>
            <form method="POST" action="{{ route('admin.backups.restaurar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="archivo-backup">Archivo de backup</label>
                    <input id="archivo-backup" type="file" name="archivo" accept=".sql,.gz" required class="form-input"
                           @change="archivo = $event.target.files[0]?.name ?? null">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="conf-archivo">Escribe <strong>RESTAURAR</strong> para confirmar</label>
                    <input id="conf-archivo" type="text" name="confirmacion" x-model="confirmacion"
                           placeholder="RESTAURAR" autocomplete="off" class="form-input">
                    @error('confirmacion')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-lg text-white"
                        :disabled="confirmacion !== 'RESTAURAR' || !archivo"
                        :class="(confirmacion !== 'RESTAURAR' || !archivo) ? 'bg-gray-300 cursor-not-allowed' : 'bg-dyl-orange-600 hover:bg-dyl-orange-700'">
                    Restaurar base de datos
                </button>
            </form>
        </div>
    </details>
</div>
@endsection
