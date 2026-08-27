@extends('layouts.app')
@section('title', 'Backups — LMS DyL')
@section('breadcrumbs'){{ Breadcrumbs::render('admin.backups.index') }}@endsection
@section('content')
<div class="max-w-3xl mx-auto"
     x-data="{ descargoSeguridad: false, confirmacion: '', archivoSeleccionado: null }">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Backups de la base de datos</h1>
    <p class="text-gray-500 text-sm mb-6">
        Backup y restauración manual de la base de datos de producción. No hay backups automáticos: esto es
        exclusivamente para vos.
    </p>

    @if(session('success'))
        <div class="alert alert-success mb-6">{{ session('success') }}</div>
    @endif
    @error('archivo')
        <div class="alert alert-error mb-6">{{ $message }}</div>
    @enderror

    {{-- Crear backup --}}
    <div class="card p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-2">Crear backup</h2>
        <p class="text-sm text-gray-600 mb-4">
            Genera un dump completo de la base de datos actual y lo descarga directo a tu computadora.
            No se guarda ninguna copia en el servidor.
        </p>
        <form method="POST" action="{{ route('admin.backups.crear') }}">
            @csrf
            <button type="submit" class="btn-primary">Descargar backup ahora</button>
        </form>
    </div>

    {{-- Restaurar backup --}}
    <div class="card p-6 border-2 border-dyl-graphite-300">
        <h2 class="text-lg font-bold text-gray-900 mb-2">Restaurar backup</h2>
        <p class="text-sm text-gray-600 mb-4">
            Sube un archivo <code>.sql</code> descargado previamente para reemplazar por completo el contenido
            actual de la base de datos. <strong>Esta acción no se puede deshacer.</strong>
        </p>

        <div class="mb-4 p-4 bg-dyl-graphite-50 rounded-xl border border-dyl-graphite-200">
            <p class="text-sm text-gray-700 mb-3">
                Antes de restaurar, descarga un backup de seguridad del estado actual — si el archivo que vas
                a subir resulta ser el equivocado, vas a necesitarlo para volver atrás.
            </p>
            <form method="POST" action="{{ route('admin.backups.crear') }}"
                  @submit="descargoSeguridad = true">
                @csrf
                <button type="submit" class="btn-outline">
                    Descargar backup de seguridad del estado actual
                </button>
            </form>
            <p x-show="descargoSeguridad" x-cloak class="text-xs text-dyl-orange-700 mt-2 font-medium">
                ✓ Backup de seguridad descargado en esta sesión.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.backups.restaurar') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Archivo de backup (.sql)</label>
                <input type="file" name="archivo" accept=".sql" required class="form-input"
                       @change="archivoSeleccionado = $event.target.files[0]?.name ?? null">
            </div>
            <div class="mb-4">
                <label class="form-label">
                    Escribe <strong>RESTAURAR</strong> para confirmar
                </label>
                <input type="text" name="confirmacion" x-model="confirmacion"
                       :disabled="!descargoSeguridad"
                       placeholder="RESTAURAR"
                       class="form-input">
                @error('confirmacion')<p class="text-dyl-graphite-900 font-semibold text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit"
                    :disabled="!descargoSeguridad || confirmacion !== 'RESTAURAR' || !archivoSeleccionado"
                    :class="(!descargoSeguridad || confirmacion !== 'RESTAURAR' || !archivoSeleccionado) ? 'bg-gray-300 cursor-not-allowed' : 'bg-dyl-orange-600 hover:bg-dyl-orange-700 cursor-pointer'"
                    class="text-white px-6 py-2 rounded-lg font-medium transition-colors">
                Restaurar base de datos
            </button>
        </form>
    </div>
</div>
@endsection
