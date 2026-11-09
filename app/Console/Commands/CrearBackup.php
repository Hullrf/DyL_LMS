<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrearBackup extends Command
{
    protected $signature = 'backup:crear';

    protected $description = 'Crea un backup de la base de datos en el disco de backups y poda los más viejos';

    public function handle(BackupService $backups): int
    {
        try {
            $nombre = $backups->guardarBackup();
        } catch (\Throwable $e) {
            $backups->notificarFallo($e);
            $this->error('Falló el backup: ' . $e->getMessage());

            return self::FAILURE;
        }

        Log::info('Backup automático de la base de datos creado', ['archivo' => $nombre]);
        $this->info("Backup creado: {$nombre}");

        return self::SUCCESS;
    }
}
