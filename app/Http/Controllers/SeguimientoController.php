<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Services\SeguimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeguimientoController extends Controller
{
    private const NIVELES = ['ok' => 'Al día', 'atencion' => 'Atención', 'riesgo' => 'En riesgo'];
    private const SENALES = ['sin_acceso' => 'Sin ingreso reciente', 'atrasadas' => 'Entregas atrasadas', 'nota_baja' => 'Nota por debajo de la mínima'];

    public function index(Curso $curso, SeguimientoService $servicio)
    {
        $this->authorize('update', $curso);

        try {
            $datos = $servicio->para($curso);
            $error = false;
        } catch (\Throwable $e) {
            report($e);
            $datos = null;
            $error = true;
        }

        return view('reportes.seguimiento', ['curso' => $curso, 'datos' => $datos, 'error' => $error,
            'niveles' => self::NIVELES, 'senales' => self::SENALES]);
    }

    public function umbrales(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $curso->update($request->validate([
            'seguimiento_dias_sin_acceso' => 'required|integer|min:1|max:90',
            'seguimiento_max_atrasadas'   => 'required|integer|min:1|max:20',
        ]));

        return redirect()->route('reportes.seguimiento', $curso)->with('success', 'Umbrales guardados.');
    }

    public function csv(Curso $curso, SeguimientoService $servicio)
    {
        $this->authorize('update', $curso);
        $filas = $servicio->para($curso)['filas'];

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Estudiante', 'Correo', 'Progreso %', 'Promedio %', 'Entregas atrasadas', 'Último acceso', 'Nivel', 'Señales']);
            foreach ($filas as $f) {
                fputcsv($out, array_map([$this, 'celda'], [
                    $f->usuario->name, $f->usuario->email, $f->progreso_pct, $f->promedio, $f->atrasadas,
                    $f->ultimo_acceso?->format('Y-m-d H:i'), self::NIVELES[$f->nivel],
                    implode('; ', array_map(fn($s) => self::SENALES[$s], $f->senales)),
                ]));
            }
            fclose($out);
        }, 'seguimiento-' . Str::slug($curso->titulo) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel/Sheets. */
    private function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'" . $texto : $texto;
    }
}
