<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\IntentoEnProgreso;
use App\Models\Notificacion;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use App\Services\CalificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RespuestaEstudianteController extends Controller
{
    public function __construct(private CalificacionService $calificacionService)
    {
    }

    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('verContenido', $actividad->leccion->modulo->curso);
        abort_if(!$actividad->tieneCalificacion(), 403, 'Esta actividad no admite respuestas.');

        if ($actividad->tipo === 'cuestionario') {
            $intentoEnProgreso = IntentoEnProgreso::where('user_id', Auth::id())
                ->where('actividad_id', $actividad->id)
                ->first();

            if ($intentoEnProgreso && $actividad->duracion_minutos) {
                $segundosRestantes = $actividad->duracion_minutos * 60
                    - $intentoEnProgreso->fecha_inicio->diffInSeconds(now());

                if ($segundosRestantes <= 0) {
                    $this->calificacionService->registrarIntentoExpirado($actividad, Auth::id());
                    $intentoEnProgreso->delete();

                    return redirect()
                        ->route('actividades.show', $actividad)
                        ->with('error', 'El tiempo para este intento ya se agotó. Se registró como vencido.');
                }
            }

            if ($actividad->intentosUsadosPor(Auth::id()) >= $actividad->intentos_permitidos) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
            }

            if ($actividad->tieneIntentoEnRevisionPara(Auth::id())) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
            }
        } else {
            $yaRespondio = RespuestaEstudiante::where('user_id', Auth::id())
                ->where('actividad_id', $actividad->id)
                ->exists();

            if ($yaRespondio) {
                return redirect()
                    ->route('actividades.show', $actividad)
                    ->with('error', 'Ya has respondido esta actividad.');
            }
        }

        // Verificar ventana de tiempo
        $estado = $actividad->estadoPlazo();
        if ($estado === 'pendiente') {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Esta actividad aún no está disponible. Abre el ' . $actividad->fecha_apertura->format('d/m/Y \a \l\a\s H:i') . '.');
        }
        if ($estado === 'cerrada') {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'El plazo de entrega venció el ' . $actividad->fecha_cierre->format('d/m/Y \a \l\a\s H:i') . '.');
        }

        if ($actividad->tipo === 'cuestionario') {
            $request->validate(['respuesta' => 'required|string|min:2']);
        } else {
            $request->validate([
                'respuesta'       => 'nullable|string',
                'archivo_adjunto' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,mp4,mov,avi,webm,zip|max:51200',
            ]);
            if (!$request->filled('respuesta') && !$request->hasFile('archivo_adjunto')) {
                return back()->withErrors(['respuesta' => 'Debes escribir una respuesta o adjuntar un archivo.']);
            }
        }

        $calificacion   = null;
        $estado         = 'sin_calificar';
        $archivoPath    = null;

        if ($request->hasFile('archivo_adjunto')) {
            $slug        = Str::slug($actividad->leccion->modulo->curso->titulo);
            $userId      = Auth::id();
            $archivoPath = $request->file('archivo_adjunto')
                ->store("cursos/{$slug}/respuestas/{$userId}/{$actividad->id}", 'public');
        }

        if ($actividad->tipo === 'cuestionario') {
            if ($this->calificacionService->tienePreguntasCortas($actividad)) {
                $estado = 'en_revision';
            } else {
                $calificacion = $this->calificacionService->calcularCuestionario($actividad, $request->respuesta);
                $estado       = 'calificada';
            }
        }

        RespuestaEstudiante::create([
            'user_id'         => Auth::id(),
            'actividad_id'    => $actividad->id,
            'respuesta'       => $request->respuesta ?? '',
            'archivo_adjunto' => $archivoPath,
            'calificacion'    => $calificacion,
            'estado'          => $estado,
            'fecha_envio'     => now(),
        ]);

        IntentoEnProgreso::where('user_id', Auth::id())
            ->where('actividad_id', $actividad->id)
            ->delete();

        $actividad->completarPara(Auth::id());

        $curso     = $actividad->leccion->modulo->curso;
        $estudiante = Auth::user();

        Notificacion::crear(
            $curso->created_by,
            'entrega',
            'Nueva entrega recibida',
            "{$estudiante->name} entregó «{$actividad->titulo}» en el curso {$curso->titulo}.",
            route('calificaciones.index')
        );

        if ($actividad->tipo === 'cuestionario' && $estado === 'en_revision') {
            $mensaje = 'Respuesta enviada. El instructor revisará las preguntas de respuesta corta antes de publicar tu calificación.';
        } elseif ($actividad->tipo === 'cuestionario') {
            $mensaje = "Respuesta enviada. Calificación: {$calificacion}/{$actividad->puntaje_maximo}";
        } else {
            $mensaje = 'Respuesta enviada. Será calificada por el instructor.';
        }

        return redirect()
            ->route('actividades.show', $actividad)
            ->with('success', $mensaje);
    }
}
