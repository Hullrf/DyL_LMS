<?php

namespace App\Services;

class BackupService
{
    /**
     * Separa un dump SQL en sentencias individuales ejecutables. Cada
     * sentencia termina con ";" seguido de salto de línea (formato que
     * produce druidfi/mysqldump-php, igual que mysqldump). Las líneas de
     * comentario puro (que empiezan con "--") se descartan; las líneas
     * condicionales de MySQL ("/*!...*\/;") SÍ son sentencias ejecutables
     * válidas y se conservan.
     *
     * @return array<int, string>
     */
    public function dividirEnSentencias(string $sql): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $sql);
        $sentenciaActual = '';
        $sentencias = [];

        foreach ($lineas as $linea) {
            $lineaSinEspacios = trim($linea);

            if ($lineaSinEspacios === '' || str_starts_with($lineaSinEspacios, '--')) {
                continue;
            }

            $sentenciaActual .= ($sentenciaActual === '' ? '' : "\n") . $linea;

            if (str_ends_with($lineaSinEspacios, ';')) {
                $sentencias[] = trim($sentenciaActual);
                $sentenciaActual = '';
            }
        }

        if (trim($sentenciaActual) !== '') {
            $sentencias[] = trim($sentenciaActual);
        }

        return $sentencias;
    }
}
