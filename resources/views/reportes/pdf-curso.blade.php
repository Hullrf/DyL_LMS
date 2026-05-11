<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; }
        h1 { font-size: 16pt; color: #1e3a5f; margin-bottom: 4px; }
        h2 { font-size: 12pt; color: #1e3a5f; margin: 16px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .header { background: #1e3a5f; color: white; padding: 16px 20px; margin-bottom: 20px; }
        .header p { margin: 2px 0; font-size: 9pt; color: #a5b4cc; }
        .kpis { display: table; width: 100%; margin-bottom: 20px; border-spacing: 8px; }
        .kpi { display: table-cell; background: #f3f4f6; border-radius: 6px; padding: 10px 14px; text-align: center; }
        .kpi-val { font-size: 20pt; font-weight: bold; color: #1e3a5f; display: block; }
        .kpi-lbl { font-size: 7pt; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.data th { background: #f9fafb; color: #6b7280; text-transform: uppercase; font-size: 7.5pt;
                        padding: 6px 10px; text-align: left; border-bottom: 2px solid #e5e7eb; }
        table.data td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        table.data tr:last-child td { border-bottom: none; }
        .badge-completado { color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: 12px; font-size: 8pt; }
        .badge-progreso   { color: #1d4ed8; background: #dbeafe; padding: 2px 8px; border-radius: 12px; font-size: 8pt; }
        .ok { color: #15803d; font-weight: bold; }
        .fail { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 8pt; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>

<div class="header">
    <h1 style="color:white; margin:0 0 4px;">Reporte del Curso</h1>
    <p>{{ $reporte['curso']->titulo }}</p>
    <p>Instructor: {{ $reporte['curso']->creador->name }} &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

{{-- KPIs --}}
<div class="kpis">
    <div class="kpi"><span class="kpi-val">{{ $reporte['total_inscritos'] }}</span><span class="kpi-lbl">Inscritos</span></div>
    <div class="kpi"><span class="kpi-val" style="color:#15803d">{{ $reporte['completados'] }}</span><span class="kpi-lbl">Completados</span></div>
    <div class="kpi"><span class="kpi-val" style="color:#d97706">{{ $reporte['en_progreso'] }}</span><span class="kpi-lbl">En Progreso</span></div>
    <div class="kpi"><span class="kpi-val">{{ $reporte['total_lecciones'] }}</span><span class="kpi-lbl">Lecciones</span></div>
    <div class="kpi">
        <span class="kpi-val {{ ($reporte['promedio_global'] ?? 0) >= 60 ? 'ok' : '' }}">
            {{ $reporte['promedio_global'] !== null ? $reporte['promedio_global'] . '%' : '—' }}
        </span>
        <span class="kpi-lbl">Promedio</span>
    </div>
</div>

<h2>Detalle por Estudiante</h2>

<table class="data">
    <thead>
        <tr>
            <th>Estudiante</th>
            <th>Estado</th>
            <th style="text-align:center">Progreso</th>
            <th style="text-align:center">Calificación</th>
            <th style="text-align:center">Certificado</th>
            <th>Inicio</th>
        </tr>
    </thead>
    <tbody>
        @forelse($reporte['estudiantes'] as $e)
        <tr>
            <td>
                <strong>{{ $e['usuario']->name }}</strong><br>
                <span style="color:#9ca3af;font-size:8pt">{{ $e['usuario']->email }}</span>
            </td>
            <td>
                @if($e['estado'] === 'completado')
                    <span class="badge-completado">Completado</span>
                @else
                    <span class="badge-progreso">En progreso</span>
                @endif
            </td>
            <td style="text-align:center">
                {{ $e['progreso_pct'] }}%<br>
                <span style="font-size:8pt;color:#9ca3af">{{ $e['lecciones_ok'] }}/{{ $reporte['total_lecciones'] }}</span>
            </td>
            <td style="text-align:center">
                @if($e['promedio'] !== null)
                    <span class="{{ $e['promedio'] >= 60 ? 'ok' : 'fail' }}">{{ $e['promedio'] }}%</span>
                @else
                    <span style="color:#9ca3af">—</span>
                @endif
            </td>
            <td style="text-align:center">{{ $e['tiene_certificado'] ? '✓' : '—' }}</td>
            <td style="font-size:8.5pt">
                {{ $e['fecha_inicio'] ? \Carbon\Carbon::parse($e['fecha_inicio'])->format('d/m/Y') : '—' }}
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:20px">Sin estudiantes inscritos</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    DyL Quality Consulting &nbsp;·&nbsp; {{ url('/reportes') }} &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}
</div>

</body>
</html>
