<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\User;
use App\Services\CertificadoService;
use App\Services\ReporteService;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $reporteService) {}

    // ---------------------------------------------------------------
    // Dashboard principal de reportes
    // ---------------------------------------------------------------

    public function index()
    {
        $user = Auth::user();

        if (!$user->esAdmin() && !$user->esInstructor()) {
            abort(403);
        }

        if ($user->esAdmin()) {
            $kpis   = $this->reporteService->kpiGenerales();
            $cursos = Curso::with('creador')->withCount('inscripciones')->get();
            $usuarios = User::withCount('cursos')->orderBy('name')->get();
        } else {
            $kpis   = null;
            $cursos = $user->cursosCreados()->withCount('inscripciones')->get();
            $usuarios = collect();
        }

        return view('reportes.index', compact('kpis', 'cursos', 'usuarios'));
    }

    // ---------------------------------------------------------------
    // Reporte por curso (HTML)
    // ---------------------------------------------------------------

    public function curso(Curso $curso)
    {
        $user = Auth::user();

        if (!$user->esAdmin() && $curso->created_by !== $user->id) {
            abort(403);
        }

        $reporte = $this->reporteService->reportePorCurso($curso);

        return view('reportes.curso', compact('reporte'));
    }

    // ---------------------------------------------------------------
    // Exportar CSV de un curso
    // ---------------------------------------------------------------

    public function exportarCsvCurso(Curso $curso)
    {
        $user = Auth::user();

        if (!$user->esAdmin() && $curso->created_by !== $user->id) {
            abort(403);
        }

        $csv      = $this->reporteService->generarCsvCurso($curso);
        $filename = 'reporte-' . \Str::slug($curso->titulo) . '-' . now()->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ---------------------------------------------------------------
    // Exportar PDF de un curso
    // ---------------------------------------------------------------

    public function exportarPdfCurso(Curso $curso)
    {
        $user = Auth::user();

        if (!$user->esAdmin() && $curso->created_by !== $user->id) {
            abort(403);
        }

        $reporte = $this->reporteService->reportePorCurso($curso);

        $html = view('reportes.pdf-curso', compact('reporte'))->render();

        $mpdf = new Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A4',
            'margin_top'  => 15,
            'margin_right'  => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
        ]);

        $mpdf->SetTitle('Reporte - ' . $curso->titulo);
        $mpdf->WriteHTML($html);

        $filename = 'reporte-' . \Str::slug($curso->titulo) . '-' . now()->format('Ymd') . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ---------------------------------------------------------------
    // Reporte por estudiante
    // ---------------------------------------------------------------

    public function estudiante(User $usuario)
    {
        $user = Auth::user();

        // Solo admin puede ver el reporte de cualquier estudiante
        // El propio estudiante puede ver el suyo
        if (!$user->esAdmin() && $user->id !== $usuario->id) {
            abort(403);
        }

        $reporte = $this->reporteService->reportePorEstudiante($usuario);

        return view('reportes.estudiante', compact('reporte'));
    }
}
