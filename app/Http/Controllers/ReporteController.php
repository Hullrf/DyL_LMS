<?php

namespace App\Http\Controllers;

use App\Exports\CursoReporteExport;
use App\Exports\UsuariosReporteExport;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
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

        $chartData = $this->buildChartData();

        return view('reportes.index', compact('kpis', 'cursos', 'usuarios', 'chartData'));
    }

    // ---------------------------------------------------------------
    // Exportar Excel
    // ---------------------------------------------------------------

    public function exportarExcelCurso(Curso $curso)
    {
        $user = Auth::user();

        if (!$user->esAdmin() && $curso->created_by !== $user->id) {
            abort(403);
        }

        $nombre = 'reporte-' . \Str::slug($curso->titulo) . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new CursoReporteExport($curso), $nombre);
    }

    public function exportarExcelUsuarios()
    {
        return Excel::download(new UsuariosReporteExport(), 'usuarios-' . now()->format('Y-m-d') . '.xlsx');
    }

    // ---------------------------------------------------------------
    // Chart data helper
    // ---------------------------------------------------------------

    private function mesRaw(): string
    {
        return \DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at) as mes"
            : "DATE_FORMAT(created_at, '%Y-%m') as mes";
    }

    private function buildChartData(): array
    {
        $inscripciones = Inscripcion::selectRaw($this->mesRaw() . ', COUNT(*) as total')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $meses = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $meses[$key] = $inscripciones[$key] ?? 0;
        }

        $respEstados = RespuestaEstudiante::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'meses_labels' => $meses->keys()
                ->map(fn($m) => Carbon::parse($m . '-01')->locale('es')->isoFormat('MMM YY'))
                ->values(),
            'meses_data'   => $meses->values(),
            'resp_estados' => [
                $respEstados['sin_calificar'] ?? 0,
                $respEstados['calificada']    ?? 0,
                $respEstados['en_revision']   ?? 0,
            ],
        ];
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

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

        $mpdf = new Mpdf([
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'margin_top'   => 15,
            'margin_right' => 15,
            'margin_bottom'=> 15,
            'margin_left'  => 15,
            'tempDir'      => storage_path('app/tmp'),
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
