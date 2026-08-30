<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoExtra;
use App\Models\RespuestaEstudiante;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\CalificacionService;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalificacionController extends Controller
{
    public function __construct(
        private CalificacionService $calificacionService,
        private CertificadoService $certificadoService,
    ) {
    }

    /**
     * Instructor: lista de cursos donde puede calificar, con conteo de
     * respuestas pendientes por curso. Punto de entrada hacia la matriz de
     * calificaciones de cada curso (ver curso()).
     */
    public function index()
    {
        $user = Auth::user();

        $cursos = Curso::withCount('inscripciones')
            ->when(!$user->esAdmin(), fn($q) => $q->where('created_by', $user->id))
            ->orderBy('titulo')
            ->get()
            ->map(function (Curso $curso) {
                $curso->pendientes_count = RespuestaEstudiante::whereIn('estado', ['sin_calificar', 'en_revision'])
                    ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
                    ->count();
                return $curso;
            });

        return view('calificaciones.index', compact('cursos'));
    }

    /**
     * Instructor: matriz de calificaciones de un curso — filas = estudiantes
     * inscritos, columnas = actividades calificables del curso. Cada celda
     * enlaza a la pantalla de calificación/revisión que ya existe para esa
     * actividad; esta vista es solo el mapa de navegación, no reimplementa
     * la lógica de calificar.
     */
    public function curso(Request $request, Curso $curso)
    {
        $this->verificarAccesoCurso($curso);

        $curso->load(['modulos' => fn($q) => $q->when(
            $request->filled('modulo'),
            fn($q2) => $q2->where('id', $request->modulo)
        )->with(['lecciones.actividades' => fn($q2) => $q2->whereNotIn('tipo', Actividad::TIPOS_SIN_NOTA)])]);

        $actividades = $curso->modulos->flatMap->lecciones->flatMap->actividades->values();

        $inscripciones = Inscripcion::with('usuario')
            ->where('curso_id', $curso->id)
            ->when($request->filled('buscar'), fn($q) => $q->whereHas('usuario', fn($q2) =>
                $q2->where('name', 'like', "%{$request->buscar}%")
                   ->orWhere('email', 'like', "%{$request->buscar}%")
            ))
            ->get()
            ->sortBy(fn($i) => $i->usuario->name)
            ->values();

        $actividadesIds = $actividades->pluck('id');
        $estudiantesIds = $inscripciones->pluck('user_id');

        $respuestasRaw = RespuestaEstudiante::whereIn('actividad_id', $actividadesIds)
            ->whereIn('user_id', $estudiantesIds)
            ->with('actividad')
            ->get();

        $respuestasPorCelda = $this->calificacionService
            ->respuestasOficiales($respuestasRaw)
            ->keyBy(fn($r) => "{$r->user_id}-{$r->actividad_id}");

        // Info de intentos (usados/permitidos/extra) por celda, solo para cuestionarios:
        // habilita el control de "otorgar intento extra" en la matriz.
        $intentosUsadosPorCelda = $respuestasRaw->groupBy(fn($r) => "{$r->user_id}-{$r->actividad_id}");
        $intentosExtraPorCelda  = IntentoExtra::whereIn('actividad_id', $actividadesIds)
            ->whereIn('user_id', $estudiantesIds)
            ->get()
            ->keyBy(fn($e) => "{$e->user_id}-{$e->actividad_id}");

        $intentosPorCelda = [];
        foreach ($actividades->where('tipo', 'cuestionario') as $act) {
            foreach ($estudiantesIds as $uid) {
                $key   = "{$uid}-{$act->id}";
                $extra = $intentosExtraPorCelda->get($key)->cantidad ?? 0;
                $intentosPorCelda[$key] = [
                    'usados'     => $intentosUsadosPorCelda->get($key, collect())->count(),
                    'permitidos' => $act->intentos_permitidos + $extra,
                    'extra'      => $extra,
                ];
            }
        }

        // Fila por estudiante: celdas + promedio ponderado del curso (mismo
        // criterio que ReporteService::reportePorCurso).
        $filas = $inscripciones->map(function ($insc) use ($actividades, $respuestasPorCelda) {
            $celdas = $actividades->map(fn($act) => $respuestasPorCelda->get("{$insc->user_id}-{$act->id}"));

            $calificadas = $celdas->filter(fn($r) => $r && $r->estado === 'calificada');
            $tienePendientes = $actividades->count() > $calificadas->count();

            $totalPts    = $calificadas->sum(fn($r) => $r->actividad->puntaje_maximo);
            $obtenidoPts = $calificadas->sum('calificacion');
            $promedio    = $totalPts > 0 ? (int) round(($obtenidoPts / $totalPts) * 100) : null;

            return (object) [
                'estudiante'      => $insc->usuario,
                'celdas'          => $celdas,
                'promedio'        => $promedio,
                'tiene_pendientes'=> $tienePendientes,
            ];
        });

        if ($request->get('estado') === 'pendientes') {
            $filas = $filas->where('tiene_pendientes', true)->values();
        } elseif ($request->get('estado') === 'completos') {
            $filas = $filas->where('tiene_pendientes', false)->values();
        }

        // Promedio general por actividad (fila inferior de la tabla).
        $promediosPorActividad = $actividades->map(function ($act) use ($respuestasPorCelda, $inscripciones) {
            $notas = $inscripciones
                ->map(fn($i) => $respuestasPorCelda->get("{$i->user_id}-{$act->id}"))
                ->filter(fn($r) => $r && $r->estado === 'calificada')
                ->pluck('calificacion');

            return $notas->isNotEmpty() ? round($notas->avg(), 2) : null;
        });

        // Lista completa (sin el filtro ?modulo=) para poblar el <select> del filtro.
        $modulos = $curso->modulos()->get();

        return view('calificaciones.curso', compact(
            'curso', 'actividades', 'filas', 'promediosPorActividad', 'modulos', 'intentosPorCelda'
        ));
    }

    /**
     * Instructor: otorga intentos extra a un estudiante puntual para un cuestionario,
     * por encima del límite global de la actividad. Acumulativo entre otorgamientos.
     */
    public function otorgarIntentoExtra(Request $request, Actividad $actividad, User $estudiante)
    {
        $this->verificarAccesoCurso($actividad->leccion->modulo->curso);
        abort_if($actividad->tipo !== 'cuestionario', 403, 'Solo los cuestionarios admiten intentos extra.');

        $validated = $request->validate([
            'cantidad' => 'required|integer|min:1|max:5',
        ]);

        $intentoExtra = IntentoExtra::firstOrNew([
            'user_id'      => $estudiante->id,
            'actividad_id' => $actividad->id,
        ]);
        $intentoExtra->cantidad     = ($intentoExtra->cantidad ?? 0) + $validated['cantidad'];
        $intentoExtra->otorgado_por = Auth::id();
        $intentoExtra->save();

        Notificacion::crear(
            $estudiante->id,
            'intento_extra',
            'Intento extra habilitado',
            "Se te habilitaron {$validated['cantidad']} intento(s) extra para «{$actividad->titulo}».",
            route('actividades.show', $actividad)
        );

        return redirect()->route('calificaciones.curso', $actividad->leccion->modulo->curso)
            ->with('success', "Se otorgaron {$validated['cantidad']} intento(s) extra a {$estudiante->name}.");
    }

    /**
     * Instructor/admin: aprueba y genera el certificado de un estudiante que
     * completó el curso. La nota mínima del curso (Curso::nota_aprobatoria)
     * es solo informativa en la matriz — este método no la valida, la
     * decisión de aprobar por debajo del mínimo es del instructor.
     */
    public function aprobarCertificado(Curso $curso, User $estudiante)
    {
        $this->verificarAccesoCurso($curso);

        $completado = Inscripcion::where('user_id', $estudiante->id)
            ->where('curso_id', $curso->id)
            ->where('estado', 'completado')
            ->exists();

        if (!$completado) {
            return redirect()->route('calificaciones.curso', $curso)
                ->with('error', "{$estudiante->name} no ha completado el curso todavía.");
        }

        $certificado = $this->certificadoService->generarSiCorresponde($estudiante, $curso, Auth::user());

        if (!$certificado) {
            return redirect()->route('calificaciones.curso', $curso)
                ->with('error', "No se pudo generar: a {$estudiante->name} le falta el número de documento — se le notificó para que lo complete.");
        }

        return redirect()->route('calificaciones.curso', $curso)
            ->with('success', "Certificado aprobado y generado para {$estudiante->name}.");
    }

    /**
     * Instructor: ver y calificar una respuesta manual (ensayo/tarea/practica).
     */
    public function show(RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);
        $respuesta->load(['usuario', 'actividad.leccion.modulo.curso', 'seleccionesRubrica']);
        if (!$respuesta->actividad) abort(404, 'Actividad no encontrada.');
        $criteriosRubrica    = $respuesta->actividad->usa_rubrica
            ? $respuesta->actividad->criteriosRubrica()->with('niveles')->get()
            : collect();
        $seleccionesActuales = $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id');
        return view('calificaciones.show', compact('respuesta', 'criteriosRubrica', 'seleccionesActuales'));
    }

    /**
     * Instructor: guardar calificación manual (ensayo/tarea/practica).
     */
    public function update(Request $request, RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);

        $actividad = $respuesta->actividad;
        $validated = $request->validate([
            'calificacion' => "required|decimal:0,2|min:0|max:{$actividad->puntaje_maximo}",
            'feedback'     => 'nullable|string|max:2000',
        ]);

        $this->calificacionService->calificarManual(
            $respuesta,
            (float) $validated['calificacion'],
            $validated['feedback'] ?? null
        );

        $actividad = $respuesta->actividad;
        Notificacion::crear(
            $respuesta->user_id,
            'calificacion',
            'Actividad calificada',
            "Tu entrega de «{$actividad->titulo}» recibió {$validated['calificacion']}/{$actividad->puntaje_maximo} pts.",
            route('calificaciones.mis')
        );

        return redirect()->route('calificaciones.curso', $respuesta->actividad->leccion->modulo->curso)
            ->with('success', 'Calificación guardada correctamente.');
    }

    /**
     * Instructor: formulario de revisión para cuestionarios con respuestas cortas.
     */
    public function revisarCuestionario(RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);

        if ($respuesta->estado !== 'en_revision') {
            return redirect()->route('calificaciones.curso', $respuesta->actividad->leccion->modulo->curso)
                ->with('error', 'Este cuestionario no está pendiente de revisión.');
        }

        $respuesta->load(['usuario', 'actividad.preguntas.opciones']);
        $actividad  = $respuesta->actividad;
        $preguntas  = $actividad->preguntas()->with('opciones')->orderBy('orden')->get();
        $respuestas = json_decode($respuesta->respuesta, true) ?? [];

        return view('calificaciones.revisar-cuestionario', compact(
            'respuesta', 'actividad', 'preguntas', 'respuestas'
        ));
    }

    /**
     * Instructor: publicar calificación de un cuestionario con respuestas cortas.
     * $request->decisiones = [ pregunta_id => "1"|"0" ]
     */
    public function publicarCuestionario(Request $request, RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);

        $request->validate([
            'decisiones'   => 'nullable|array',
            'decisiones.*' => 'in:0,1',
            'feedback'     => 'nullable|string|max:2000',
        ]);

        // Convertir a bool keyed por pregunta_id
        $decisionesCortas = collect($request->input('decisiones', []))
            ->map(fn($v) => (bool)(int)$v)
            ->all();

        $calificacion = $this->calificacionService->calcularCuestionario(
            $respuesta->actividad,
            $respuesta->respuesta,
            $decisionesCortas
        );

        $respuesta->update([
            'calificacion'       => $calificacion,
            'feedback'           => $request->feedback,
            'estado'             => 'calificada',
            'fecha_calificacion' => now(),
        ]);

        $actividadPub = $respuesta->actividad;
        Notificacion::crear(
            $respuesta->user_id,
            'calificacion',
            'Cuestionario calificado',
            "Tu cuestionario «{$actividadPub->titulo}» fue calificado: {$calificacion}/{$actividadPub->puntaje_maximo} pts.",
            route('calificaciones.mis')
        );

        return redirect()->route('calificaciones.curso', $respuesta->actividad->leccion->modulo->curso)
            ->with('success', "Calificación publicada: {$calificacion}/{$respuesta->actividad->puntaje_maximo} pts.");
    }

    /**
     * Instructor: califica una tarea usando rúbrica.
     */
    public function guardarRubrica(\Illuminate\Http\Request $request, RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);

        $actividad   = $respuesta->actividad->load('criteriosRubrica');
        $criterioIds = $actividad->criteriosRubrica->pluck('id')->toArray();

        $request->validate([
            'selecciones'   => 'required|array',
            'selecciones.*' => 'required|integer|exists:niveles_criterio,id',
            'feedback'      => 'nullable|string|max:2000',
        ]);

        foreach ($criterioIds as $criterioId) {
            if (!array_key_exists($criterioId, $request->selecciones)) {
                return back()->withErrors([
                    'selecciones' => 'Debes seleccionar un nivel para cada criterio de la rúbrica.',
                ]);
            }
        }

        $calificacion = $this->calificacionService->calificarConRubrica(
            $respuesta,
            $request->selecciones,
            $request->feedback
        );

        Notificacion::crear(
            $respuesta->user_id,
            'calificacion',
            'Actividad calificada con rúbrica',
            "Tu entrega de «{$actividad->titulo}» fue evaluada: {$calificacion}/{$actividad->puntaje_maximo} pts.",
            route('calificaciones.mis')
        );

        return redirect()->route('calificaciones.curso', $actividad->leccion->modulo->curso)
            ->with('success', "Calificación guardada: {$calificacion} / {$actividad->puntaje_maximo} pts.");
    }

    /**
     * Estudiante: ver todas sus calificaciones.
     */
    public function misCalificaciones()
    {
        $respuestas = RespuestaEstudiante::with(['actividad.leccion.modulo.curso'])
            ->where('user_id', Auth::id())
            ->whereHas('actividad')
            ->orderBy('fecha_envio', 'desc')
            ->get();

        $oficialesIds = $this->calificacionService
            ->respuestasOficiales($respuestas->where('estado', 'calificada'))
            ->pluck('id')
            ->all();

        // Agrupar por curso, manteniendo el orden cronológico inverso
        $porCurso = $respuestas
            ->groupBy(fn($r) => $r->actividad->leccion->modulo->curso->id)
            ->map(fn($grupo) => (object)[
                'curso'         => $grupo->first()->actividad->leccion->modulo->curso,
                'respuestas'    => $grupo->sortByDesc('fecha_envio')->values(),
                'ultima_envio'  => $grupo->max('fecha_envio'),
            ])
            ->sortByDesc('ultima_envio')
            ->values();

        return view('calificaciones.mis-calificaciones', compact('respuestas', 'porCurso', 'oficialesIds'));
    }

    private function verificarAcceso(RespuestaEstudiante $respuesta): void
    {
        $user = Auth::user();
        if ($user->esAdmin()) return;

        $creador = $respuesta->actividad->leccion->modulo->curso->created_by;
        if ($creador !== $user->id) {
            abort(403, 'No tienes permiso para calificar esta respuesta.');
        }
    }

    private function verificarAccesoCurso(Curso $curso): void
    {
        $user = Auth::user();
        if ($user->esAdmin()) return;

        if ($curso->created_by !== $user->id) {
            abort(403, 'No tienes permiso para ver las calificaciones de este curso.');
        }
    }
}
