<?php

namespace App\Services;

use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\DB;

class BackupService
{
    /**
     * Genera el dump completo de la base de datos actual y lo escribe en
     * $destino. $destino admite cualquier stream wrapper de PHP (fopen()),
     * por ejemplo 'php://output' para transmitirlo directo como descarga
     * sin tocar disco. Usa las mismas credenciales que ya tiene configurada
     * la aplicación (config/database.php) — no requiere configuración
     * adicional.
     */
    public function crearDump(string $destino): void
    {
        $conexion = config('database.default');
        $config   = config("database.connections.{$conexion}");

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";

        $dump = new Mysqldump($dsn, $config['username'], $config['password'], [
            'add-drop-table'     => true,
            'no-data'            => false,
            'single-transaction' => true,
            'lock-tables'        => false,
        ]);

        $dump->start($destino);
    }

    /**
     * Ejecuta un archivo .sql (generado por crearDump()) contra la base de
     * datos actual, sentencia por sentencia. Reemplaza por completo las
     * tablas incluidas en el archivo (los dumps de crearDump() llevan
     * DROP TABLE IF EXISTS antes de cada CREATE TABLE). No hay rollback
     * automático si una sentencia falla a mitad de camino: MySQL hace
     * commit implícito en cada DDL (CREATE/DROP TABLE), así que una
     * transacción envolvente no serviría para deshacerlo.
     *
     * @return int cantidad de sentencias ejecutadas con éxito
     * @throws \RuntimeException si el archivo no se puede leer, o si
     *         alguna sentencia falla (el mensaje incluye cuál).
     */
    public function restaurarDesdeArchivo(string $rutaArchivo): int
    {
        $contenido = file_get_contents($rutaArchivo);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo de backup.');
        }

        $sentencias = $this->dividirEnSentencias($contenido);
        $total      = count($sentencias);
        $ejecutadas = 0;

        foreach ($sentencias as $i => $sentencia) {
            try {
                DB::unprepared($sentencia);
                $ejecutadas++;
            } catch (\Throwable $e) {
                $numero = $i + 1;
                throw new \RuntimeException(
                    "Falló la sentencia {$numero} de {$total}: {$e->getMessage()}"
                );
            }
        }

        return $ejecutadas;
    }

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
