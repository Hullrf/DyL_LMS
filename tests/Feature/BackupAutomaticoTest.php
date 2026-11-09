<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private function nombre(int $n): string
    {
        return sprintf('backup_dyl_lms_2026-01-%02d_030000.sql.gz', $n);
    }

    public function test_podar_conserva_solo_los_mas_recientes(): void
    {
        Storage::fake('local');
        foreach (range(1, 5) as $n) {
            Storage::disk('local')->put('backups/' . $this->nombre($n), 'x');
        }

        $borrados = (new BackupService())->podar(2);

        $this->assertSame(3, $borrados);
        $quedan = array_column((new BackupService())->listar(), 'nombre');
        $this->assertSame([$this->nombre(5), $this->nombre(4)], $quedan);
    }

    public function test_existe_rechaza_nombres_fuera_del_patron(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/' . $this->nombre(1), 'x');
        $svc = new BackupService();

        $this->assertTrue($svc->existe($this->nombre(1)));
        $this->assertFalse($svc->existe($this->nombre(2)));
        $this->assertFalse($svc->existe('../.env'));
    }

    public function test_restaurar_rechaza_archivos_con_delimiter(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'dylt');
        file_put_contents($ruta, "CREATE TABLE a (id int);\nDELIMITER ;;\nCREATE TRIGGER t;;\nDELIMITER ;\n-- Dump completed\n");

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('DELIMITER');
            (new BackupService())->restaurarDesdeArchivo($ruta);
        } finally {
            unlink($ruta);
        }
    }

    public function test_restaurar_lee_archivos_gz(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'dylt');
        file_put_contents($ruta, gzencode("CREATE TABLE prueba_gz (id int);\nINSERT INTO prueba_gz VALUES (1);\n-- Dump completed\n"));

        try {
            $this->assertSame(2, (new BackupService())->restaurarDesdeArchivo($ruta));
            $this->assertSame(1, \DB::table('prueba_gz')->count());
        } finally {
            unlink($ruta);
        }
    }

    public function test_comando_exitoso_devuelve_success(): void
    {
        $this->mock(BackupService::class, fn ($m) => $m->shouldReceive('guardarBackup')->once()->andReturn($this->nombre(1)));

        $this->artisan('backup:crear')->assertSuccessful();
    }

    public function test_comando_fallido_avisa_por_correo_a_los_admins_activos(): void
    {
        $rol = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $admin = User::factory()->create(['estado' => 'activo', 'email' => 'admin@example.com']);
        $admin->roles()->attach($rol);
        $inactivo = User::factory()->create(['estado' => 'inactivo', 'email' => 'viejo@example.com']);
        $inactivo->roles()->attach($rol);

        // Solo falla guardarBackup; notificarFallo es el real.
        $this->partialMock(BackupService::class, fn ($m) => $m->shouldReceive('guardarBackup')->andThrow(new \RuntimeException('sin conexión')));

        $this->artisan('backup:crear')->assertFailed();

        // Mailer 'array' (phpunit.xml): un solo correo, solo al admin activo.
        $mensajes = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $mensajes);
        $destinos = collect($mensajes[0]->getEnvelope()->getRecipients())->map->getAddress()->all();
        $this->assertSame(['admin@example.com'], $destinos);
    }

    public function test_el_backup_esta_programado_diario_a_las_3_hora_bogota(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'backup:crear'));

        $this->assertNotNull($evento);
        $this->assertSame('0 3 * * *', $evento->expression);
        $this->assertSame('America/Bogota', (string) $evento->timezone);
    }
}
