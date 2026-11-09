<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private BackupService $backupService)
    {
    }

    public function index()
    {
        return view('admin.backups.index', [
            'backups'   => $this->backupService->listar(),
            'conservar' => BackupService::CONSERVAR,
        ]);
    }

    /** Descarga directa del dump (sin guardar copia en el servidor). */
    public function crear(): StreamedResponse
    {
        $nombreArchivo = 'backup_dyl_lms_' . now()->format('Y-m-d_His') . '.sql';

        return response()->streamDownload(function () {
            $this->backupService->crearDump('php://output');

            Log::info('Backup de base de datos descargado', [
                'user_id'   => Auth::id(),
                'user_name' => Auth::user()->name,
            ]);
        }, $nombreArchivo, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /** Crea un backup en el disco de backups del servidor. */
    public function guardar()
    {
        try {
            $nombre = $this->backupService->guardarBackup();
        } catch (\Throwable $e) {
            Log::error('Backup manual en servidor falló', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return back()->withErrors(['backup' => 'No se pudo crear el backup: ' . $e->getMessage()]);
        }

        Log::info('Backup guardado en el servidor', ['user_id' => Auth::id(), 'archivo' => $nombre]);

        return back()->with('success', "Backup creado: {$nombre}");
    }

    public function descargar(string $nombre)
    {
        abort_unless($this->backupService->existe($nombre), 404);

        return $this->backupService->descargar($nombre);
    }

    public function eliminar(string $nombre)
    {
        abort_unless($this->backupService->existe($nombre), 404);

        $this->backupService->eliminar($nombre);
        Log::warning('Backup eliminado', ['user_id' => Auth::id(), 'archivo' => $nombre]);

        return back()->with('success', 'Backup eliminado.');
    }

    public function restaurarGuardado(Request $request, string $nombre)
    {
        abort_unless($this->backupService->existe($nombre), 404);

        $request->validate(['confirmacion' => 'required|in:RESTAURAR']);

        try {
            [$ejecutadas, $seguridad] = $this->backupService->restaurarDesdeGuardado($nombre);
        } catch (\Throwable $e) {
            Log::error('Restauración desde backup guardado falló', [
                'user_id' => Auth::id(),
                'archivo' => $nombre,
                'error'   => $e->getMessage(),
            ]);

            return back()->withErrors(['backup' => 'La restauración falló: ' . $e->getMessage()]);
        }

        Log::warning('Restauración desde backup guardado ejecutada', [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'archivo'    => $nombre,
            'seguridad'  => $seguridad,
            'sentencias' => $ejecutadas,
        ]);

        return redirect()->route('admin.backups.index')
            ->with('success', "Base de datos restaurada desde {$nombre} ({$ejecutadas} sentencias). Backup de seguridad previo: {$seguridad}.");
    }

    public function restaurar(Request $request)
    {
        $request->validate([
            'confirmacion' => 'required|in:RESTAURAR',
            'archivo'      => 'required|file|extensions:sql,gz|max:51200',
        ]);

        try {
            $seguridad  = $this->backupService->guardarBackup(); // si falla, no se restaura nada
            $ejecutadas = $this->backupService->restaurarDesdeArchivo(
                $request->file('archivo')->getRealPath()
            );
        } catch (\Throwable $e) {
            Log::error('Restauración de base de datos falló', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return back()->withErrors(['archivo' => 'La restauración falló: ' . $e->getMessage()]);
        }

        Log::warning('Restauración de base de datos ejecutada', [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'archivo'    => $request->file('archivo')->getClientOriginalName(),
            'seguridad'  => $seguridad,
            'sentencias' => $ejecutadas,
        ]);

        return redirect()->route('admin.backups.index')
            ->with('success', "Base de datos restaurada correctamente ({$ejecutadas} sentencias). Backup de seguridad previo: {$seguridad}.");
    }
}
