<?php

namespace App\Http\Controllers;

use App\Models\RespuestaEstudiante;
use App\Models\Notificacion;
use App\Services\CalificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalificacionController extends Controller
{
    public function __construct(private CalificacionService $calificacionService)
    {
    }

    /**
     * Instructor: lista de respuestas pendientes de calificación.
     * Incluye ensayo/tarea/practica (sin_calificar) y cuestionarios (en_revision).
     */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $estado = $request->get('estado', 'pendiente');

        $query = RespuestaEstudiante::with(['usuario', 'actividad.leccion.modulo.curso']);

        if (!$user->esAdmin()) {
            $query->whereHas('actividad.leccion.modulo.curso', fn($q) => $q->where('created_by', $user->id));
        }

        match ($estado) {
            'pendiente' => $query->where(function ($q) {
                $q->where('estado', 'sin_calificar')
                  ->orWhere('estado', 'en_revision');
            }),
            'calificada' => $query->where('estado', 'calificada'),
            default      => null, // todas
        };

        $respuestas = $query->orderBy('fecha_envio')->paginate(20);

        return view('calificaciones.index', compact('respuestas', 'estado'));
    }

    /**
     * Instructor: ver y calificar una respuesta manual (ensayo/tarea/practica).
     */
    public function show(RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);
        $respuesta->load(['usuario', 'actividad.leccion.modulo.curso', 'seleccionesRubrica']);
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

        return redirect()->route('calificaciones.index')->with('success', 'Calificación guardada correctamente.');
    }

    /**
     * Instructor: formulario de revisión para cuestionarios con respuestas cortas.
     */
    public function revisarCuestionario(RespuestaEstudiante $respuesta)
    {
        $this->verificarAcceso($respuesta);

        if ($respuesta->estado !== 'en_revision') {
            return redirect()->route('calificaciones.index')
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

        return redirect()->route('calificaciones.index')
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

        return redirect()->route('calificaciones.index')
            ->with('success', "Calificación guardada: {$calificacion} / {$actividad->puntaje_maximo} pts.");
    }

    /**
     * Estudiante: ver todas sus calificaciones.
     */
    public function misCalificaciones()
    {
        $respuestas = RespuestaEstudiante::with(['actividad.leccion.modulo.curso'])
            ->where('user_id', Auth::id())
            ->orderBy('fecha_envio', 'desc')
            ->get();

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

        return view('calificaciones.mis-calificaciones', compact('respuestas', 'porCurso'));
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
}
