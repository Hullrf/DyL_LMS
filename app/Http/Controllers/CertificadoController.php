<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Models\Curso;
use App\Services\CertificadoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificadoController extends Controller
{
    public function __construct(private CertificadoService $certificadoService) {}

    /**
     * Genera (o recupera) el certificado del usuario autenticado para un curso.
     * Solo funciona si la inscripción está completada.
     */
    public function generar(Curso $curso)
    {
        $usuario = Auth::user();

        $certificado = $this->certificadoService->generarSiCorresponde($usuario, $curso);

        if (!$certificado) {
            return redirect()
                ->route('cursos.show', $curso)
                ->with('error', 'Debes completar todas las lecciones del curso para obtener el certificado.');
        }

        return redirect()->route('certificados.show', $certificado);
    }

    /**
     * Vista del certificado: previsualización + botón de descarga.
     */
    public function show(Certificado $certificado)
    {
        // Solo el dueño o un admin puede ver
        if (Auth::id() !== $certificado->user_id && !Auth::user()->esAdmin()) {
            abort(403);
        }

        return view('certificados.show', compact('certificado'));
    }

    /**
     * Descarga el PDF del certificado.
     */
    public function descargar(Certificado $certificado)
    {
        // Solo el dueño o admin
        if (Auth::id() !== $certificado->user_id && !Auth::user()->esAdmin()) {
            abort(403);
        }

        // Regenerar si no existe el archivo
        if (!$certificado->archivo_pdf || !Storage::disk('public')->exists($certificado->archivo_pdf)) {
            $ruta = $this->certificadoService->generarPdf($certificado);
            $certificado->update(['archivo_pdf' => $ruta]);
        }

        $rutaAbsoluta = storage_path('app/public/' . $certificado->archivo_pdf);
        $nombre = 'Certificado-' . str_replace(' ', '-', $certificado->curso->titulo) . '.pdf';

        return response()->download($rutaAbsoluta, $nombre);
    }

    /**
     * Página pública de verificación (sin autenticación).
     */
    public function verificar(string $numeroCertificado)
    {
        $certificado = Certificado::with(['usuario', 'curso'])
            ->where('numero_certificado', $numeroCertificado)
            ->first();

        return view('certificados.verificar', compact('certificado', 'numeroCertificado'));
    }

    /**
     * Lista de certificados del usuario autenticado.
     */
    public function misCertificados()
    {
        $certificados = Certificado::with(['curso'])
            ->where('user_id', Auth::id())
            ->orderBy('fecha_emision', 'desc')
            ->get();

        return view('certificados.mis-certificados', compact('certificados'));
    }
}
