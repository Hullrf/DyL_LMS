<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoEnProgreso;
use App\Models\Leccion;
use App\Models\ProgresoActividad;
use App\Models\ProgresoLeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActividadController extends Controller
{
    public function create(Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);
        return view('actividades.create', compact('leccion'));
    }

    public function store(Request $request, Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'tipo'             => 'required|in:cuestionario,ensayo,tarea,practica,ejercicio,lectura,encuesta,reflexion',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => [
                Rule::requiredIf(fn() => !in_array($request->tipo, Actividad::TIPOS_SIN_NOTA)),
                'nullable',
                'decimal:0,2',
                'min:0.01',
                'max:999.99',
            ],
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
            'permitir_descarga_adjuntos' => 'nullable|in:0,1,leccion',
            'intentos_permitidos'            => 'nullable|integer|min:1|max:20',
            'criterio_calificacion_intentos'  => 'nullable|in:mas_alto,ultimo',
            'mostrar_historial_intentos'      => 'boolean',
            'max_archivos_adjuntos'          => 'nullable|integer|min:1|max:10',
        ]);

        $orden = $leccion->actividades()->max('orden') + 1;

        $descargaRaw = $validated['permitir_descarga_adjuntos'] ?? null;

        $actividad = $leccion->actividades()->create([
            ...$validated,
            'puntaje_maximo'  => in_array($request->tipo, Actividad::TIPOS_SIN_NOTA) ? null : ($validated['puntaje_maximo'] ?? null),
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'permitir_descarga_adjuntos' => in_array($descargaRaw, ['leccion', null], true) ? null : (bool) $descargaRaw,
            'orden'           => $orden,
            'intentos_permitidos'            => $validated['intentos_permitidos'] ?? 1,
            'criterio_calificacion_intentos' => $validated['criterio_calificacion_intentos'] ?? 'mas_alto',
            'mostrar_historial_intentos'     => $request->boolean('mostrar_historial_intentos', true),
            'max_archivos_adjuntos'          => $validated['max_archivos_adjuntos'] ?? 1,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad creada. Ahora configura su contenido.');
    }

    public function show(Actividad $actividad)
    {
        $this->authorize('verContenido', $actividad->leccion->modulo->curso);

        $intentoEnProgreso = null;
        $segundosRestantes = null;

        if ($actividad->tipo === 'cuestionario') {
            $intentoEnProgreso = IntentoEnProgreso::where('user_id', auth()->id())
                ->where('actividad_id', $actividad->id)
                ->first();

            if ($intentoEnProgreso && $actividad->duracion_minutos) {
                $segundosRestantes = (int) floor(
                    $actividad->duracion_minutos * 60 - $intentoEnProgreso->fecha_inicio->diffInSeconds(now())
                );

                if ($segundosRestantes <= 0) {
                    app(\App\Services\CalificacionService::class)
                        ->registrarIntentoExpirado($actividad, auth()->id());
                    $intentoEnProgreso->delete();

                    return redirect()->route('actividades.show', $actividad);
                }
            }
        }

        $intentos = $actividad->respuestas()
            ->where('user_id', auth()->id())
            ->with('seleccionesRubrica')
            ->orderBy('fecha_envio')
            ->get();
        $intentos->each(fn($r) => $r->setRelation('actividad', $actividad));

        $respuestaOficial = $intentos->isNotEmpty()
            ? app(\App\Services\CalificacionService::class)->respuestasOficiales($intentos)->first()
            : null;

        $respuesta = $actividad->tipo === 'cuestionario' ? $respuestaOficial : $intentos->last();

        $intentosUsados         = $intentos->count();
        $intentosRestantes      = max(0, $actividad->intentosPermitidosPara(auth()->id()) - $intentosUsados);
        $tieneIntentoEnRevision = $intentos->contains('estado', 'en_revision');

        $actividadCompletada = ProgresoActividad::where('user_id', auth()->id())
            ->where('actividad_id', $actividad->id)
            ->where('completado', true)
            ->exists();

        $criteriosRubrica = $actividad->usa_rubrica
            ? $actividad->criteriosRubrica()->with('niveles')->get()
            : collect();

        $seleccionesMap = $respuesta
            ? $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id')
            : collect();

        $vista = view('actividades.show', compact(
            'actividad', 'respuesta', 'actividadCompletada', 'criteriosRubrica', 'seleccionesMap',
            'intentos', 'intentosUsados', 'intentosRestantes', 'tieneIntentoEnRevision',
            'intentoEnProgreso', 'segundosRestantes'
        ));

        if ($intentoEnProgreso) {
            // Un intento de cuestionario está en curso: evitamos que el navegador sirva
            // una copia cacheada de esta página (p. ej. al restaurar una pestaña suspendida
            // por inactividad), lo cual mostraría el tiempo restante desactualizado y daría
            // la impresión de que el temporizador se reinició.
            return response($vista)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
            ]);
        }

        return $vista;
    }

    public function edit(Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);
        $preguntas        = $actividad->preguntas()->with('opciones')->get();
        $criteriosRubrica = $actividad->criteriosRubrica()->with('niveles')->get();
        return view('actividades.edit', compact('actividad', 'preguntas', 'criteriosRubrica'));
    }

    public function update(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => [
                Rule::requiredIf(fn() => $actividad->tieneCalificacion()),
                'nullable',
                'decimal:0,2',
                'min:0.01',
                'max:999.99',
            ],
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
            'fecha_apertura'   => 'nullable|date',
            'fecha_cierre'     => 'nullable|date|after_or_equal:fecha_apertura',
            'permitir_descarga_adjuntos' => 'nullable|in:0,1,leccion',
            'intentos_permitidos'            => 'nullable|integer|min:1|max:20',
            'criterio_calificacion_intentos'  => 'nullable|in:mas_alto,ultimo',
            'mostrar_historial_intentos'      => 'boolean',
            'max_archivos_adjuntos'          => 'nullable|integer|min:1|max:10',
        ]);

        $descargaRaw = $validated['permitir_descarga_adjuntos'] ?? null;

        $actividad->update([
            ...$validated,
            'es_obligatoria'  => $request->boolean('es_obligatoria', true),
            'permitir_descarga_adjuntos' => in_array($descargaRaw, ['leccion', null], true) ? null : (bool) $descargaRaw,
            'fecha_apertura'  => $request->filled('fecha_apertura') ? $request->fecha_apertura : null,
            'fecha_cierre'    => $request->filled('fecha_cierre')   ? $request->fecha_cierre   : null,
            'intentos_permitidos'            => $validated['intentos_permitidos'] ?? $actividad->intentos_permitidos,
            'criterio_calificacion_intentos' => $validated['criterio_calificacion_intentos'] ?? $actividad->criterio_calificacion_intentos,
            'mostrar_historial_intentos'     => $request->boolean('mostrar_historial_intentos', $actividad->mostrar_historial_intentos),
            'max_archivos_adjuntos'          => $validated['max_archivos_adjuntos'] ?? $actividad->max_archivos_adjuntos,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad actualizada correctamente');
    }

    public function completar(Actividad $actividad)
    {
        $this->authorize('verContenido', $actividad->leccion->modulo->curso);
        $actividad->completarPara(auth()->id());
        return redirect()
            ->route('actividades.show', $actividad)
            ->with('success', 'Actividad marcada como completada.');
    }

    public function iniciarIntento(Actividad $actividad)
    {
        $this->authorize('verContenido', $actividad->leccion->modulo->curso);
        abort_unless($actividad->tipo === 'cuestionario', 403);

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

        if ($actividad->intentosUsadosPor(auth()->id()) >= $actividad->intentosPermitidosPara(auth()->id())) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Ya usaste todos los intentos permitidos para este cuestionario.');
        }

        if ($actividad->tieneIntentoEnRevisionPara(auth()->id())) {
            return redirect()
                ->route('actividades.show', $actividad)
                ->with('error', 'Tienes un intento pendiente de revisión. Espera a que el instructor lo califique antes de reintentar.');
        }

        IntentoEnProgreso::firstOrCreate(
            ['user_id' => auth()->id(), 'actividad_id' => $actividad->id],
            ['fecha_inicio' => now()]
        );

        return redirect()->route('actividades.show', $actividad);
    }

    public function destroy(Actividad $actividad)
    {
        $curso = $actividad->leccion->modulo->curso;
        $this->authorize('update', $curso);
        $actividad->delete();

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', 'Actividad eliminada');
    }

    public function mover(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $validated = $request->validate([
            'leccion_destino_id'  => 'required|exists:lecciones,id',
            'orden_destino'       => 'required|array|min:1',
            'orden_destino.*'     => 'integer|exists:actividades,id',
            'leccion_origen_id'   => 'nullable|exists:lecciones,id',
            'orden_origen'        => 'nullable|array',
            'orden_origen.*'      => 'integer|exists:actividades,id',
        ]);

        $leccionIds = array_filter([$validated['leccion_destino_id'], $validated['leccion_origen_id'] ?? null]);
        $leccionesValidas = Leccion::whereIn('id', $leccionIds)
            ->whereHas('modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->count();
        abort_unless($leccionesValidas === count($leccionIds), 422);

        $actividadIds = array_unique(array_merge($validated['orden_destino'], $validated['orden_origen'] ?? []));
        $actividadesValidas = Actividad::whereIn('id', $actividadIds)
            ->whereHas('leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->count();
        abort_unless($actividadesValidas === count($actividadIds), 422);

        DB::transaction(function () use ($validated) {
            foreach ($validated['orden_destino'] as $index => $actividadId) {
                Actividad::where('id', $actividadId)->update([
                    'leccion_id' => $validated['leccion_destino_id'],
                    'orden'      => $index,
                ]);
            }
            foreach ($validated['orden_origen'] ?? [] as $index => $actividadId) {
                Actividad::where('id', $actividadId)->update(['orden' => $index]);
            }
        });

        return response()->json(['ok' => true]);
    }
}