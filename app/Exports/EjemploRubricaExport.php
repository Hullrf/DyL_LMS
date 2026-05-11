<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EjemploRubricaSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function array(): array
    {
        return [
            ['Criterio', 'Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'],
            [
                'Planteamiento del Problema y Justificación',
                "El problema no está claramente descrito, o no se relaciona con una necesidad real. La justificación es inexistente o carece de argumentos sólidos sobre la relevancia del estudio.\n\n0 puntos",
                "El problema se describe de forma general, pero le falta claridad y delimitación. La justificación es básica y no articula de manera convincente la pertinencia del estudio en el contexto local o nacional.\n\n0.33 puntos",
                "El problema está claramente descrito y se evidencia su relevancia. La justificación presenta argumentos sólidos que conectan la investigación con necesidades reales.\n\n0.8 puntos",
                "El problema está planteado con total claridad y delimitación, demostrando una comprensión profunda de la problemática. La justificación es convincente y articula de forma excepcional la pertinencia social, económica o académica del estudio.\n\n1.0 puntos",
            ],
            [
                'Formulación de Pregunta y Objetivos',
                "La pregunta de investigación es vaga o inexistente. Los objetivos no están definidos o no se relacionan con el problema planteado.\n\n0 puntos",
                "La pregunta existe pero es ambigua o muy amplia. Los objetivos son generales y poco medibles. La alineación entre pregunta, objetivos y problema es parcial.\n\n0.25 puntos",
                "La pregunta es clara y delimita el problema de investigación. Los objetivos son específicos, alcanzables y están alineados con la pregunta y el problema planteado.\n\n0.5 puntos",
                "La pregunta es precisa, específica y verificable. Los objetivos están perfectamente articulados: son medibles, alcanzables, relevantes y temporalmente definidos. La coherencia interna es total.\n\n1.0 puntos",
            ],
        ];
    }

    public function title(): string
    {
        return 'Rúbrica';
    }

    public function styles(Worksheet $sheet): void
    {
        // Encabezados
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        // Wrap text en celdas de contenido
        foreach (range(2, 3) as $row) {
            foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                $sheet->getStyle("{$col}{$row}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            }
        }

        $sheet->getStyle('A2:A3')->getFont()->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(150);
        $sheet->getRowDimension(3)->setRowHeight(150);
        $sheet->getColumnDimension('A')->setWidth(35);
    }
}

class EjemploInstruccionesSheet implements FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['INSTRUCCIONES PARA USAR ESTA PLANTILLA DE RÚBRICA'],
            [''],
            ['ESTRUCTURA DEL ARCHIVO:'],
            ['- Columna A: Nombre del criterio de evaluación'],
            ['- Columnas B en adelante: Un nivel por columna (de peor a mejor rendimiento)'],
            ['- En cada celda de nivel: escribe la descripción y en la ÚLTIMA LÍNEA el puntaje así:'],
            ['  "0.8 puntos"  o  "0.8 pts"  o simplemente  "0.8"'],
            [''],
            ['REGLAS:'],
            ['- Puedes agregar tantas columnas de nivel como necesites (mínimo 1, máximo recomendado 5)'],
            ['- Los puntos deben ir de menor a mayor de izquierda a derecha'],
            ['- La suma de los puntajes máximos de todos los criterios será la nota máxima del trabajo'],
            ['- No cambies el nombre de la pestaña "Rúbrica" — el sistema la busca con ese nombre'],
            ['- Primera fila = encabezados (Criterio, Nivel 1, Nivel 2...) — no la elimines'],
            [''],
            ['EJEMPLO DE CELDA DE NIVEL:'],
            ['------------------------------------------------------'],
            ['El problema está claramente descrito y se evidencia su'],
            ['relevancia. La justificación presenta argumentos sólidos'],
            ['que conectan la investigación con necesidades reales.'],
            [''],
            ['0.8 puntos'],
            ['------------------------------------------------------'],
            [''],
            ['El sistema extraerá "0.8" como los puntos de ese nivel.'],
            ['El resto del texto es la descripción que verá el estudiante.'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}

class EjemploRubricaExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new EjemploRubricaSheet(),
            new EjemploInstruccionesSheet(),
        ];
    }
}
