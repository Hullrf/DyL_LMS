<?php

namespace App\Exports;

use App\Models\Curso;
use App\Models\ProgresoLeccion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CursoReporteExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private Curso $curso) {}

    public function collection()
    {
        $leccionIds     = $this->curso->lecciones->pluck('id');
        $totalLecciones = $leccionIds->count();

        return $this->curso->inscripciones()->with('usuario')->get()
            ->map(function ($insc) use ($leccionIds, $totalLecciones) {
                $completadas = ProgresoLeccion::where('user_id', $insc->user_id)
                    ->whereIn('leccion_id', $leccionIds)
                    ->where('completado', true)
                    ->count();

                $progreso = $totalLecciones > 0
                    ? round(($completadas / $totalLecciones) * 100) . '%'
                    : '0%';

                return [
                    $insc->usuario->name,
                    $insc->usuario->email,
                    $insc->usuario->empresa ?? '—',
                    $insc->fecha_inicio,
                    ucfirst($insc->estado),
                    $progreso,
                    "{$completadas} / {$totalLecciones}",
                ];
            });
    }

    public function headings(): array
    {
        return ['Estudiante', 'Email', 'Empresa', 'Fecha Inscripción', 'Estado', 'Progreso', 'Lecciones Completadas'];
    }

    public function title(): string
    {
        return 'Reporte Curso';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
            ],
        ];
    }
}
