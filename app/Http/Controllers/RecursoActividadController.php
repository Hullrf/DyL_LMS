<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\RecursoActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecursoActividadController extends Controller
{
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $tipo = $request->input('tipo');

        $rules = [
            'tipo'        => 'required|in:documento,video,texto,enlace',
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:500',
        ];

        // Validaciones por tipo
        match($tipo) {
            'documento' => $rules['archivo'] = 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip|max:51200',
            'video'     => $rules['url'] = 'required|url|max:2048',
            'texto'     => $rules['contenido'] = 'required|string',
            'enlace'    => $rules['url'] = 'required|url|max:2048',
            default     => null,
        };

        $validated = $request->validate($rules);

        $data = [
            'actividad_id' => $actividad->id,
            'tipo'         => $tipo,
            'titulo'       => $validated['titulo'],
            'descripcion'  => $validated['descripcion'] ?? null,
            'orden'        => $actividad->recursos()->max('orden') + 1,
        ];

        if ($tipo === 'documento' && $request->hasFile('archivo')) {
            $slug = Str::slug($actividad->leccion->modulo->curso->titulo);
            $path = $request->file('archivo')
                ->store("cursos/{$slug}/recursos/{$actividad->id}", 'public');
            $data['archivo_path'] = $path;
        } elseif (in_array($tipo, ['video', 'enlace'])) {
            $data['url'] = $validated['url'];
        } elseif ($tipo === 'texto') {
            $data['contenido'] = $validated['contenido'];
        }

        RecursoActividad::create($data);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Recurso agregado correctamente.');
    }

    public function destroy(RecursoActividad $recurso)
    {
        $actividad = $recurso->actividad;
        $this->authorize('update', $actividad->leccion->modulo->curso);

        // Eliminar archivo físico si es documento
        if ($recurso->tipo === 'documento' && $recurso->archivo_path) {
            Storage::disk('public')->delete($recurso->archivo_path);
        }

        $recurso->delete();

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Recurso eliminado.');
    }
}
