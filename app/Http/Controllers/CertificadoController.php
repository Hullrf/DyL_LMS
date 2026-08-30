<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Notificacion;
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

        if ($certificado->wasRecentlyCreated) {
            Notificacion::crear(
                $usuario->id,
                'certificado',
                'Certificado generado',
                "¡Felicitaciones! Obtuviste el certificado del curso «{$curso->titulo}».",
                route('certificados.show', $certificado)
            );
        }

        return redirect()->route('certificados.show', $certificado);
    }

    /**
     * Vista del certificado: previsualización + botón de descarga.
     */
    public function show(Certificado $certificado)
    {
        $this->autorizar($certificado);

        return view('certificados.show', compact('certificado'));
    }

    /**
     * Descarga el PDF del certificado.
     */
    public function descargar(Certificado $certificado)
    {
        $this->autorizar($certificado);

        try {
            $this->asegurarArchivoGenerado($certificado);
        } catch (\RuntimeException $e) {
            return $this->redirigirPorFaltaDeDatos($certificado);
        }

        $rutaAbsoluta = storage_path('app/public/' . $certificado->archivo_pdf);
        $nombre = 'Certificado-' . str_replace(' ', '-', $certificado->curso->titulo) . '.pdf';

        return response()->download($rutaAbsoluta, $nombre);
    }

    /**
     * Sirve el PDF del certificado en línea (para el iframe de previsualización),
     * sin exponerlo por una URL pública del disco de storage.
     */
    public function previsualizar(Certificado $certificado)
    {
        $this->autorizar($certificado);

        try {
            $this->asegurarArchivoGenerado($certificado);
        } catch (\RuntimeException $e) {
            return $this->redirigirPorFaltaDeDatos($certificado);
        }

        return response()->file(storage_path('app/public/' . $certificado->archivo_pdf));
    }

    /**
     * Solo el dueño del certificado o un admin puede verlo, descargarlo o previsualizarlo.
     */
    private function autorizar(Certificado $certificado): void
    {
        if (Auth::id() !== $certificado->user_id && !Auth::user()->esAdmin()) {
            abort(403);
        }
    }

    /**
     * Regenera el PDF del certificado si el archivo no existe todavía.
     *
     * @throws \RuntimeException si el certificado es de diplomado y falta el
     *                           número de documento del estudiante.
     */
    private function asegurarArchivoGenerado(Certificado $certificado): void
    {
        if (!$certificado->archivo_pdf || !Storage::disk('public')->exists($certificado->archivo_pdf)) {
            $ruta = $this->certificadoService->generarPdf($certificado);
            $certificado->update(['archivo_pdf' => $ruta]);
        }
    }

    /**
     * Redirige a la vista del certificado con un aviso de que falta completar
     * el perfil del estudiante para poder generar la carta de diplomado.
     */
    private function redirigirPorFaltaDeDatos(Certificado $certificado)
    {
        return redirect()
            ->route('certificados.show', $certificado)
            ->with('error', 'No pudimos generar tu certificado: falta tu número de documento. Complétalo en tu perfil (' . route('profile.edit') . ') e inténtalo de nuevo.');
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
