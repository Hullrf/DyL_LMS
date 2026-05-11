<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Support\Collection;

class ReporteService
{
    // ---------------------------------------------------------------
    // KPIs globales (para admin)
    // ---------------------------------------------------------------

    public function kpiGenerales(): array
    {
        $totalEstudiantes = User::whereHas('roles', fn($q) => $q->where('nombre', 'Estudiante'))
            ->orWhereDoesntHave('roles')
            ->count();

        $totalCursos        = Curso::count();
        $cursosPublicados   = Curso::where('estado', 'publicado')->count();
        $totalInscripciones = Inscripcion::count();
        $completados        = Inscripcion::where('estado', 'completado')->count();
        $totalCertificados  = Certificado::count();

        $tasaCompletitud = $totalInscripciones > 0
            ? round(($completados / $totalInscripciones) * 100)
            : 0;

        // Promedio de calificaciones de cuestionarios (calificadas)
        $promedioCalificacion = RespuestaEstudiante::where('estado', 'calificada')
            ->whereHas('actividad', fn($q) => $q->where('tipo', 'cuestionario'))
            ->selectRaw('AVG(calificacion) as avg_cal')
            ->value('avg_cal');

        return [
            'total_estudiantes'   => $totalEstudiantes,
            'total_cursos'        => $totalCursos,
            'cursos_publicados'   => $cursosPublicados,
            'total_inscripciones' => $totalInscripciones,
            'completados'         => $completados,
            'tasa_completitud'    => $tasaCompletitud,
            'total_certificados'  => $totalCertificados,
            'promedio_calificacion' => $promedioCalificacion ? round($promedioCalificacion) : null,
        ];
    }

    // ---------------------------------------------------------------
    // Reporte por curso
    // ---------------------------------------------------------------

    public function reportePorCurso(Curso $curso): array
    {
        $inscripciones = Inscripcion::with('usuario')
            ->where('curso_id', $curso->id)
            ->get();

        $totalInscritos   = $inscripciones->count();
        $completados      = $inscripciones->where('estado', 'completado')->count();
        $enProgreso       = $inscripciones->where('estado', 'en_progreso')->count();

        $totalLecciones   = $curso->lecciones()->count();

        // Datos por estudiante
        $estudiantesData = $inscripciones->map(function ($insc) use ($curso, $totalLecciones) {
            $usuario = $insc->usuario;

            $leccionesIds = $curso->lecciones()->pluck('lecciones.id');

            $completadasCount = $usuario->progresoLecciones()
                ->whereIn('leccion_id', $leccionesIds)
                ->where('completado', true)
                ->count();

            $progresoPct = $totalLecciones > 0
                ? (int) round(($completadasCount / $totalLecciones) * 100)
                : 0;

            // Promedio de calificaciones en este curso
            $respuestas = $usuario->respuestas()
                ->where('estado', 'calificada')
                ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
                ->with('actividad')
                ->get();

            $promedio = null;
            if ($respuestas->isNotEmpty()) {
                $totalPts    = $respuestas->sum(fn($r) => $r->actividad->puntaje_maximo);
                $obtenidoPts = $respuestas->sum('calificacion');
                $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;
            }

            $certificado = Certificado::where('user_id', $usuario->id)
                ->where('curso_id', $curso->id)
                ->first();

            return [
                'usuario'          => $usuario,
                'estado'           => $insc->estado,
                'fecha_inicio'     => $insc->fecha_inicio,
                'fecha_fin'        => $insc->fecha_fin,
                'progreso_pct'     => $progresoPct,
                'lecciones_ok'     => $completadasCount,
                'promedio'         => $promedio,
                'tiene_certificado'=> $certificado !== null,
            ];
        })->sortByDesc('progreso_pct')->values();

        // Promedio global del curso
        $promedioGlobal = $estudiantesData->whereNotNull('promedio')->avg('promedio');

        return [
            'curso'           => $curso,
            'total_inscritos' => $totalInscritos,
            'completados'     => $completados,
            'en_progreso'     => $enProgreso,
            'total_lecciones' => $totalLecciones,
            'promedio_global' => $promedioGlobal ? (int) round($promedioGlobal) : null,
            'estudiantes'     => $estudiantesData,
        ];
    }

    // ---------------------------------------------------------------
    // Reporte por estudiante
    // ---------------------------------------------------------------

    public function reportePorEstudiante(User $usuario): array
    {
        $inscripciones = Inscripcion::with('curso.modulos.lecciones')
            ->where('user_id', $usuario->id)
            ->get();

        $cursosData = $inscripciones->map(function ($insc) use ($usuario) {
            $curso          = $insc->curso;
            $totalLecciones = $curso->modulos->sum(fn($m) => $m->lecciones->count());

            $leccionesIds = $curso->lecciones()->pluck('lecciones.id');

            $completadasCount = $usuario->progresoLecciones()
                ->whereIn('leccion_id', $leccionesIds)
                ->where('completado', true)
                ->count();

            $progresoPct = $totalLecciones > 0
                ? (int) round(($completadasCount / $totalLecciones) * 100)
                : 0;

            $respuestas = $usuario->respuestas()
                ->where('estado', 'calificada')
                ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
                ->with('actividad')
                ->get();

            $promedio = null;
            if ($respuestas->isNotEmpty()) {
                $totalPts    = $respuestas->sum(fn($r) => $r->actividad->puntaje_maximo);
                $obtenidoPts = $respuestas->sum('calificacion');
                $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;
            }

            $certificado = Certificado::where('user_id', $usuario->id)
                ->where('curso_id', $curso->id)->first();

            return [
                'curso'            => $curso,
                'estado'           => $insc->estado,
                'fecha_inicio'     => $insc->fecha_inicio,
                'progreso_pct'     => $progresoPct,
                'lecciones_ok'     => $completadasCount,
                'total_lecciones'  => $totalLecciones,
                'promedio'         => $promedio,
                'tiene_certificado'=> $certificado !== null,
                'certificado'      => $certificado,
            ];
        });

        // Historial de actividades
        $respuestasHistorial = $usuario->respuestas()
            ->with(['actividad.leccion.modulo.curso'])
            ->orderBy('fecha_envio', 'desc')
            ->take(20)
            ->get();

        return [
            'usuario'              => $usuario,
            'cursos'               => $cursosData,
            'total_cursos'         => $cursosData->count(),
            'completados'          => $cursosData->where('estado', 'completado')->count(),
            'respuestas_historial' => $respuestasHistorial,
        ];
    }

    // ---------------------------------------------------------------
    // Generar CSV para un curso
    // ---------------------------------------------------------------

    public function generarCsvCurso(Curso $curso): string
    {
        $data = $this->reportePorCurso($curso);

        $rows   = [];
        $rows[] = ['Reporte del Curso: ' . $curso->titulo];
        $rows[] = ['Generado:', now()->format('d/m/Y H:i')];
        $rows[] = [];
        $rows[] = ['Resumen'];
        $rows[] = ['Total inscritos', $data['total_inscritos']];
        $rows[] = ['Completados', $data['completados']];
        $rows[] = ['En progreso', $data['en_progreso']];
        $rows[] = ['Promedio global', $data['promedio_global'] ? $data['promedio_global'] . '%' : 'N/A'];
        $rows[] = [];
        $rows[] = ['Estudiante', 'Email', 'Estado', 'Progreso', 'Lecciones completadas', 'Promedio', 'Certificado', 'Fecha inicio'];

        foreach ($data['estudiantes'] as $e) {
            $rows[] = [
                $e['usuario']->name,
                $e['usuario']->email,
                ucfirst($e['estado']),
                $e['progreso_pct'] . '%',
                $e['lecciones_ok'] . '/' . $data['total_lecciones'],
                $e['promedio'] !== null ? $e['promedio'] . '%' : 'Sin calificar',
                $e['tiene_certificado'] ? 'Sí' : 'No',
                $e['fecha_inicio'] ?? '',
            ];
        }

        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $csv;
    }
}
