<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class CertificadoService
{
    public function __construct(private CalificacionService $calificacionService)
    {
    }

    /**
     * Genera (o recupera) el certificado de un usuario para un curso.
     * Solo procede si la inscripción está en estado 'completado'.
     * Retorna el Certificado o null si no corresponde.
     */
    public function generarSiCorresponde(User $usuario, Curso $curso): ?Certificado
    {
        // Ya tiene certificado
        $existente = Certificado::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        // Verificar que el curso esté completado
        $inscripcion = Inscripcion::where('user_id', $usuario->id)
            ->where('curso_id', $curso->id)
            ->where('estado', 'completado')
            ->first();

        if (!$inscripcion) {
            return null;
        }

        // La carta de diplomado necesita el número de documento del estudiante.
        if ($curso->tipo_certificado === 'diplomado' && !$usuario->numero_documento) {
            Notificacion::crear(
                $usuario->id,
                'certificado',
                'Completa tu perfil para tu certificado',
                "Necesitamos tu número de documento para emitir tu certificado de «{$curso->titulo}». Complétalo en tu perfil.",
                route('profile.edit')
            );

            return null;
        }

        // Calcular calificación final (promedio de actividades calificadas)
        $calificacionFinal = $this->calcularCalificacionFinal($usuario, $curso);

        // Crear registro
        $certificado = Certificado::create([
            'user_id'             => $usuario->id,
            'curso_id'            => $curso->id,
            'fecha_emision'       => now()->toDateString(),
            'numero_certificado'  => $this->generarNumero(),
            'calificacion_final'  => $calificacionFinal,
        ]);

        // Generar PDF y guardar ruta
        $rutaPdf = $this->generarPdf($certificado);
        $certificado->update(['archivo_pdf' => $rutaPdf]);

        return $certificado;
    }

    /**
     * Genera el PDF del certificado y lo guarda en storage/app/public/certificados/.
     * Retorna la ruta relativa desde storage/app/public.
     */
    public function generarPdf(Certificado $certificado): string
    {
        $certificado->load(['usuario', 'curso.creador']);

        $esDiplomado = $certificado->curso->tipo_certificado === 'diplomado';

        if ($esDiplomado) {
            $inscripcion = Inscripcion::where('user_id', $certificado->user_id)
                ->where('curso_id', $certificado->curso_id)
                ->first();

            $html = view('certificados.plantilla-carta', compact('certificado', 'inscripcion'))->render();
            $mpdfConfig = [
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'P',
                'margin_top'    => 20,
                'margin_right'  => 20,
                'margin_bottom' => 25,
                'margin_left'   => 20,
                'tempDir'       => storage_path('app/tmp'),
            ];
        } else {
            $html = view('certificados.plantilla-pdf', compact('certificado'))->render();
            $mpdfConfig = [
                'mode'          => 'utf-8',
                'format'        => 'A4-L',
                'orientation'   => 'L',
                'margin_top'    => 0,
                'margin_right'  => 0,
                'margin_bottom' => 0,
                'margin_left'   => 0,
                'tempDir'       => storage_path('app/tmp'),
            ];
        }

        $mpdf = new Mpdf($mpdfConfig);
        $mpdf->WriteHTML($html);

        $año           = date('Y');
        $nombreArchivo = 'certificado-' . $certificado->numero_certificado . '.pdf';
        $directorio    = storage_path("app/public/certificados/{$año}");

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $mpdf->Output("{$directorio}/{$nombreArchivo}", 'F');

        return "certificados/{$año}/{$nombreArchivo}";
    }

    /**
     * Genera un número de certificado único del estilo: CERT-2026-XXXX.
     */
    private function generarNumero(): string
    {
        do {
            $numero = 'CERT-' . date('Y') . '-' . strtoupper(Str::random(8));
        } while (Certificado::where('numero_certificado', $numero)->exists());

        return $numero;
    }

    /**
     * Promedio ponderado de calificaciones del estudiante en el curso.
     */
    private function calcularCalificacionFinal(User $usuario, Curso $curso): int
    {
        $respuestas = $usuario->respuestas()
            ->where('estado', 'calificada')
            ->whereHas('actividad.leccion.modulo', fn($q) => $q->where('curso_id', $curso->id))
            ->with('actividad')
            ->get();

        $respuestasOficiales = $this->calificacionService->respuestasOficiales($respuestas);

        if ($respuestasOficiales->isEmpty()) {
            return 100; // Si no hay actividades, aprobado por completar lecciones
        }

        $total    = $respuestasOficiales->sum(fn($r) => $r->actividad->puntaje_maximo);
        $obtenido = $respuestasOficiales->sum('calificacion');

        if ($total === 0) {
            return 100;
        }

        return (int) round(($obtenido / $total) * 100);
    }
}
