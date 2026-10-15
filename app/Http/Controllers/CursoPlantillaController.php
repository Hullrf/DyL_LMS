<?php

namespace App\Http\Controllers;

use App\Exceptions\CursoImportException;
use App\Models\Curso;
use App\Services\CursoExportService;
use App\Services\CursoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CursoPlantillaController extends Controller
{
    public function exportar(Curso $curso, CursoExportService $servicio): BinaryFileResponse
    {
        $this->authorize('update', $curso);

        $ruta = $servicio->exportar($curso);

        return response()
            ->download($ruta, 'curso-' . Str::slug($curso->titulo) . '.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function importar(Request $request, CursoImportService $servicio): RedirectResponse
    {
        $this->authorize('create', Curso::class);

        $validador = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:zip|max:102400',
        ], [
            'archivo.required' => 'Selecciona el archivo ZIP del curso.',
            'archivo.uploaded' => 'No se pudo subir el archivo. Puede superar el tamaño máximo permitido por el servidor.',
            'archivo.file'     => 'No se pudo subir el archivo. Puede superar el tamaño máximo permitido por el servidor.',
            'archivo.mimes'    => 'El archivo debe ser un ZIP exportado desde la plataforma.',
            'archivo.max'      => 'El archivo supera el tamaño máximo de 100 MB.',
        ]);
        if ($validador->fails()) {
            return back()->with('error', $validador->errors()->first());
        }

        try {
            $curso = $servicio->importar($request->file('archivo')->getRealPath(), $request->user());
        } catch (CursoImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cursos.edit', $curso)
            ->with('success', "Curso importado como borrador: {$curso->titulo}. Revísalo antes de publicarlo.");
    }
}
