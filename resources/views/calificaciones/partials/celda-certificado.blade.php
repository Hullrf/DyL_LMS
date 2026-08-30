{{-- Celda de la columna "Certificado" de la matriz de calificaciones.
     Recibe $curso y $fila. El gating (tiene_pendientes_total/promedio_total)
     SIEMPRE refleja el curso completo, sin importar el filtro ?modulo= —
     ver CalificacionController::curso(). --}}
@if($fila->certificado)
    <a href="{{ route('certificados.show', $fila->certificado) }}" class="badge badge-green" title="Aprobado por {{ $fila->certificado->aprobador->name ?? '—' }} el {{ $fila->certificado->created_at->format('d/m/Y') }}">
        Certificado emitido
    </a>
@elseif($fila->completado && !$fila->tiene_pendientes_total)
    @php($bajoMinimo = $fila->promedio_total !== null && $fila->promedio_total < $curso->nota_aprobatoria)
    <form method="POST" action="{{ route('calificaciones.aprobarCertificado', [$curso, $fila->estudiante]) }}"
          onclick="return confirm('{{ $bajoMinimo ? '¿Aprobar de todas formas? La nota del estudiante está por debajo del mínimo del curso.' : '¿Aprobar y generar el certificado?' }}')">
        @csrf
        @if($bajoMinimo)
            <button type="submit" class="btn btn-sm bg-dyl-graphite-700 text-white hover:bg-dyl-graphite-800">
                Aprobar de todas formas
            </button>
        @else
            <button type="submit" class="btn btn-primary btn-sm">
                Aprobar certificado
            </button>
        @endif
    </form>
@else
    <span class="text-gray-300">—</span>
@endif
