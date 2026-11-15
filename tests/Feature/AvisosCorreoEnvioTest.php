<?php

namespace Tests\Feature;

use App\Mail\AvisoLms;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class AvisosCorreoEnvioTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public static function tiposIncluidos(): array
    {
        return [
            'vencimiento'   => ['vencimiento'],
            'calificacion'  => ['calificacion'],
            'intento_extra' => ['intento_extra'],
            'anuncio'       => ['anuncio'],
            'certificado'   => ['certificado'],
        ];
    }

    #[DataProvider('tiposIncluidos')]
    public function test_encola_un_correo_por_cada_tipo_incluido(string $tipo): void
    {
        Mail::fake();
        $u = User::factory()->create();

        Notificacion::crear($u->id, $tipo, 'Titulo', 'Mensaje', 'https://x.test');

        Mail::assertQueued(AvisoLms::class, fn (AvisoLms $m) => $m->hasTo($u->email));
    }

    public function test_no_encola_foro_mensaje_ni_entrega(): void
    {
        Mail::fake();
        $u = User::factory()->create();

        foreach (['foro', 'mensaje', 'entrega'] as $tipo) {
            Notificacion::crear($u->id, $tipo, 'Titulo', 'Mensaje');
        }

        Mail::assertNothingQueued();
    }

    public function test_respeta_el_opt_out_de_la_categoria(): void
    {
        Mail::fake();
        $u = User::factory()->create(['correo_desactivados' => ['anuncios']]);

        Notificacion::crear($u->id, 'anuncio', 'Anuncio', 'Texto');
        Mail::assertNothingQueued();

        Notificacion::crear($u->id, 'calificacion', 'Nota', 'Texto');
        Mail::assertQueued(AvisoLms::class, 1);
    }

    public function test_no_envia_a_usuario_inactivo(): void
    {
        Mail::fake();
        $u = User::factory()->create(['estado' => 'inactivo']);

        Notificacion::crear($u->id, 'calificacion', 'Nota', 'Texto');

        Mail::assertNothingQueued();
    }

    public function test_no_envia_a_usuario_eliminado(): void
    {
        Mail::fake();
        $u = User::factory()->create();
        $u->delete();

        Notificacion::crear($u->id, 'calificacion', 'Nota', 'Texto');

        Mail::assertNothingQueued();
    }

    public function test_un_fallo_del_correo_no_rompe_la_creacion_del_aviso(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Zoho caído'));
        $u = User::factory()->create();

        $aviso = Notificacion::crear($u->id, 'anuncio', 'Anuncio', 'Texto');

        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'tipo' => 'anuncio']);
    }

    public function test_vencimientos_manda_un_solo_correo_aunque_el_comando_corra_dos_veces(): void
    {
        Mail::fake();
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'Vence manana', 'fecha_cierre' => now()->addHours(12)]);

        $this->artisan('notificaciones:vencimientos')->assertSuccessful();
        $this->artisan('notificaciones:vencimientos')->assertSuccessful();

        Mail::assertQueued(AvisoLms::class, 1);
    }
}
