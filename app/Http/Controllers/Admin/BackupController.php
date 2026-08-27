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
        return view('admin.backups.index');
    }

    public function crear(): StreamedResponse
    {
        $nombreArchivo = 'backup_dyl_lms_' . now()->format('Y-m-d_His') . '.sql';

        Log::info('Backup de base de datos creado', [
            'user_id'   => Auth::id(),
            'user_name' => Auth::user()->name,
        ]);

        return response()->streamDownload(function () {
            $this->backupService->crearDump('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restaurar(Request $request)
    {
        $request->validate([
            'confirmacion' => 'required|in:RESTAURAR',
            'archivo'      => 'required|file|extensions:sql|max:51200',
        ]);

        try {
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
            'sentencias' => $ejecutadas,
        ]);

        return redirect()->route('admin.backups.index')
            ->with('success', "Base de datos restaurada correctamente ({$ejecutadas} sentencias ejecutadas).");
    }
}
