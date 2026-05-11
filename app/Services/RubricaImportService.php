<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class RubricaImportReader implements ToArray
{
    private array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}

class RubricaImportService
{
    /**
     * Parsea un archivo Excel y retorna la estructura de criterios/niveles.
     *
     * Formato esperado:
     *   Fila 1: encabezados (ignorada)
     *   Fila 2+: Col A = nombre criterio, Col B+ = niveles
     *   Cada celda de nivel: descripción + última línea con puntos
     *
     * Retorna: [['nombre' => '...', 'niveles' => [['descripcion' => '...', 'puntos' => 0.8], ...]], ...]
     */
    public function parsear(UploadedFile $archivo): array
    {
        $reader = new RubricaImportReader();
        Excel::import($reader, $archivo);

        $rows     = $reader->getRows();
        $criterios = [];

        // Ignorar primera fila (encabezados)
        foreach (array_slice($rows, 1) as $row) {
            $nombre = trim((string) ($row[0] ?? ''));
            if ($nombre === '') continue;

            $niveles = [];
            for ($i = 1; $i < count($row); $i++) {
                $celda = trim((string) ($row[$i] ?? ''));
                if ($celda === '') continue;

                $puntos      = $this->extraerPuntos($celda);
                $descripcion = $this->extraerDescripcion($celda);

                if ($descripcion !== '' || $puntos > 0) {
                    $niveles[] = ['descripcion' => $descripcion, 'puntos' => $puntos];
                }
            }

            if (count($niveles) > 0) {
                $criterios[] = ['nombre' => $nombre, 'niveles' => $niveles];
            }
        }

        return $criterios;
    }

    private function extraerPuntos(string $celda): float
    {
        $lineas = array_filter(array_map('trim', explode("\n", $celda)));

        foreach (array_reverse(array_values($lineas)) as $linea) {
            if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:puntos?|pts?)?$/iu', $linea, $m)) {
                return round((float) str_replace(',', '.', $m[1]), 2);
            }
        }

        return 0.0;
    }

    private function extraerDescripcion(string $celda): string
    {
        $lineas = array_map('trim', explode("\n", $celda));

        $ultima = end($lineas);
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:puntos?|pts?)?$/iu', $ultima)) {
            array_pop($lineas);
        }

        while (!empty($lineas) && end($lineas) === '') {
            array_pop($lineas);
        }

        return implode("\n", $lineas);
    }
}
