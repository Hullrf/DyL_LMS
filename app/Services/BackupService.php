<?php

namespace App\Services;

use App\Models\User;
use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    /** Cuántos backups guardados se conservan; los más viejos se borran. */
    public const CONSERVAR = 14;

    private const CARPETA = 'backups';

    private const PATRON_NOMBRE = '/^backup_dyl_lms_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/';

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
            'add-drop-table'         => true,
            'add-locks'              => false, // un LOCK TABLES colgado rompería la sesión (SESSION_DRIVER=database)
            'no-data'                => [],
            'single-transaction'     => true,
            'lock-tables'            => false,
            'default-character-set' => $config['charset'],
        ], $config['options'] ?? []);

        $dump->start($destino);
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('filesystems.backup_disk'));
    }

    /**
     * Crea un backup comprimido (.sql.gz) en el disco de backups y poda los
     * más viejos dejando los últimos CONSERVAR.
     *
     * @return string nombre del archivo creado
     */
    public function guardarBackup(): string
    {
        $this->sinLimiteDeTiempo();

        $nombre = 'backup_dyl_lms_' . now()->format('Y-m-d_His') . '.sql.gz';
        $tmp    = tempnam(sys_get_temp_dir(), 'dylbk');

        try {
            $this->crearDump('compress.zlib://' . $tmp);

            $stream = fopen($tmp, 'rb');
            try {
                $this->disco()->put(self::CARPETA . '/' . $nombre, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($tmp);
        }

        $this->podar();

        return $nombre;
    }

    /**
     * @return array<int, array{nombre: string, tamano: int, fecha: \Illuminate\Support\Carbon}>
     *         más reciente primero
     */
    public function listar(): array
    {
        $lista = [];

        foreach ($this->disco()->files(self::CARPETA) as $ruta) {
            $nombre = basename($ruta);
            if (preg_match(self::PATRON_NOMBRE, $nombre) !== 1) {
                continue;
            }
            $lista[] = [
                'nombre' => $nombre,
                'tamano' => $this->disco()->size($ruta),
                'fecha'  => \Illuminate\Support\Carbon::createFromFormat('Y-m-d_His', substr($nombre, 15, 17)),
            ];
        }

        usort($lista, fn ($a, $b) => strcmp($b['nombre'], $a['nombre']));

        return $lista;
    }

    /** El nombre nunca viene del usuario tal cual: se valida contra el patrón (anti path traversal). */
    public function existe(string $nombre): bool
    {
        return preg_match(self::PATRON_NOMBRE, $nombre) === 1
            && $this->disco()->exists(self::CARPETA . '/' . $nombre);
    }

    public function descargar(string $nombre)
    {
        return $this->disco()->download(self::CARPETA . '/' . $nombre, $nombre);
    }

    public function eliminar(string $nombre): void
    {
        $this->disco()->delete(self::CARPETA . '/' . $nombre);
    }

    public function podar(int $conservar = self::CONSERVAR): int
    {
        $sobrantes = array_slice($this->listar(), $conservar);

        foreach ($sobrantes as $b) {
            $this->eliminar($b['nombre']);
        }

        return count($sobrantes);
    }

    /**
     * Restaura desde un backup guardado en el disco. Antes crea un backup de
     * seguridad del estado actual (así se puede volver atrás).
     *
     * @return array{0: int, 1: string} [sentencias ejecutadas, nombre del backup de seguridad]
     */
    public function restaurarDesdeGuardado(string $nombre): array
    {
        $this->sinLimiteDeTiempo();

        $seguridad = $this->guardarBackup();

        $tmp = tempnam(sys_get_temp_dir(), 'dylrs');
        try {
            $origen  = $this->disco()->readStream(self::CARPETA . '/' . $nombre);
            $destino = fopen($tmp, 'wb');
            stream_copy_to_stream($origen, $destino);
            fclose($destino);
            fclose($origen);

            return [$this->restaurarDesdeArchivo($tmp), $seguridad];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Avisa por correo a los administradores activos de que el backup
     * automático falló. Nunca lanza: un fallo del correo no debe tapar el
     * error original (ya queda en el log).
     */
    public function notificarFallo(\Throwable $e): void
    {
        Log::error('Backup automático de la base de datos falló', ['error' => $e->getMessage()]);

        try {
            $correos = User::where('estado', 'activo')
                ->whereHas('roles', fn ($q) => $q->where('nombre', 'Administrador'))
                ->pluck('email')
                ->all();

            if ($correos === []) {
                return;
            }

            Mail::raw(
                "El backup automático de la base de datos del LMS falló el " . now()->format('Y-m-d H:i') . " (UTC).\n\n"
                . "Error: {$e->getMessage()}\n\n"
                . "Revisa la sección Backups del panel de administración.",
                fn ($m) => $m->to($correos)->subject('[LMS DyL] Falló el backup automático')
            );
        } catch (\Throwable $mailError) {
            Log::error('No se pudo enviar el aviso de fallo de backup', ['error' => $mailError->getMessage()]);
        }
    }

    /**
     * Ejecuta un archivo .sql (o .sql.gz, el wrapper zlib lee ambos de forma
     * transparente) generado por crearDump() contra la base de datos actual,
     * sentencia por sentencia. Reemplaza por completo las
     * tablas incluidas en el archivo (los dumps de crearDump() llevan
     * DROP TABLE IF EXISTS antes de cada CREATE TABLE). No hay rollback
     * automático si una sentencia falla a mitad de camino: MySQL hace
     * commit implícito en cada DDL (CREATE/DROP TABLE), así que una
     * transacción envolvente no serviría para deshacerlo.
     *
     * @return int cantidad de sentencias ejecutadas con éxito
     * @throws \RuntimeException si el archivo no se puede leer, si el
     *         contenido no parece un backup completo y válido (falta el
     *         pie "-- Dump completed", no hay ningún CREATE TABLE o trae
     *         directivas DELIMITER — caso de archivo vacío, truncado,
     *         corrupto o con triggers; se detecta antes de ejecutar
     *         cualquier sentencia), o si alguna sentencia falla
     *         (el mensaje incluye cuál).
     */
    public function restaurarDesdeArchivo(string $rutaArchivo): int
    {
        $this->sinLimiteDeTiempo();

        $contenido = @file_get_contents('compress.zlib://' . $rutaArchivo);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo de backup.');
        }

        $pareceCompleto = str_contains($contenido, '-- Dump completed')
            && preg_match('/CREATE TABLE/i', $contenido) === 1;

        if (!$pareceCompleto) {
            throw new \RuntimeException(
                'El archivo no parece ser un backup completo y válido. No se ejecutó ninguna sentencia.'
            );
        }

        if (preg_match('/^\s*DELIMITER\s/mi', $contenido) === 1) {
            throw new \RuntimeException(
                'El archivo contiene directivas DELIMITER (triggers/rutinas), que no se pueden restaurar desde aquí. No se ejecutó ninguna sentencia.'
            );
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
                    "Falló la sentencia {$numero} de {$total}: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $ejecutadas;
    }

    /** Una restauración/backup cortado por timeout deja la BD a medias sin error visible. */
    private function sinLimiteDeTiempo(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);
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
