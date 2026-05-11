@extends('layouts.app')
@section('title', 'Auditoría del Sistema — LMS DyL')
@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Auditoría del Sistema</h1>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
        <select name="modelo" class="form-input w-44">
            <option value="">Todos los modelos</option>
            @foreach($modelos as $modelo)
                <option value="{{ $modelo }}" @selected(request('modelo') === $modelo)>{{ $modelo }}</option>
            @endforeach
        </select>
        <select name="accion" class="form-input w-36">
            <option value="">Todas las acciones</option>
            @foreach($acciones as $accion)
                <option value="{{ $accion }}" @selected(request('accion') === $accion)>{{ ucfirst($accion) }}</option>
            @endforeach
        </select>
        <select name="usuario_id" class="form-input w-52">
            <option value="">Todos los usuarios</option>
            @foreach($usuarios as $id => $nombre)
                <option value="{{ $id }}" @selected(request('usuario_id') == $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="{{ route('admin.auditoria.index') }}" class="btn-outline">Limpiar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="tbl-th">Fecha</th>
                    <th class="tbl-th">Usuario</th>
                    <th class="tbl-th">Acción</th>
                    <th class="tbl-th">Modelo</th>
                    <th class="tbl-th">ID</th>
                    <th class="tbl-th">IP</th>
                    <th class="tbl-th">Campos afectados</th>
                </tr>
            </thead>
            <tbody>
            @forelse($audits as $audit)
                <tr class="tbl-row">
                    <td class="tbl-td text-gray-500 text-xs whitespace-nowrap">
                        {{ $audit->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="tbl-td text-sm font-medium">{{ $audit->user?->name ?? 'Sistema' }}</td>
                    <td class="tbl-td">
                        <span class="badge {{ match($audit->event) {
                            'created' => 'badge-green',
                            'deleted' => 'badge-red',
                            default   => 'badge-blue',
                        } }}">{{ ucfirst($audit->event) }}</span>
                    </td>
                    <td class="tbl-td text-sm font-medium">{{ class_basename($audit->auditable_type) }}</td>
                    <td class="tbl-td text-gray-400 text-xs">#{{ $audit->auditable_id }}</td>
                    <td class="tbl-td text-gray-400 text-xs">{{ $audit->ip_address ?? '—' }}</td>
                    <td class="tbl-td">
                        @if($audit->new_values)
                        <details class="text-xs cursor-pointer">
                            <summary class="text-blue-600 hover:text-blue-800 select-none">
                                {{ count($audit->new_values) }} campo(s)
                            </summary>
                            <pre class="bg-gray-50 rounded p-2 mt-1 text-xs overflow-x-auto max-w-xs whitespace-pre-wrap">{{ implode(', ', array_keys($audit->new_values)) }}</pre>
                        </details>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="tbl-td text-center text-gray-400 py-10">No hay registros de auditoría aún.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $audits->links() }}</div>
</div>
@endsection
