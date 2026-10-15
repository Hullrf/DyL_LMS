@if($curso->ponderacionIncompleta())
    <div class="alert alert-warning mb-5" role="status">
        Las ponderaciones no suman 100 % o faltan actividades: hoy la nota se calcula por puntaje. Revisa las ponderaciones.
        <a href="{{ route('cursos.ponderaciones.edit', $curso) }}" class="font-medium underline">Ir a ponderaciones</a>
    </div>
@endif
