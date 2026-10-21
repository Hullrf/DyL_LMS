<?php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Rol;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class CertificadoRegenerarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->roles()->attach(Rol::firstOrCreate(['nombre' => 'Administrador']));

        return $admin;
    }

    private function crearCertificado(User $instructor, string $archivoPdf = 'certificados/2025/certificado-CERT-REG.pdf', array $cursoAttrs = []): Certificado
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id] + $cursoAttrs);

        return Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-REG',
            'archivo_pdf'        => $archivoPdf,
        ]);
    }

    private function mockServicio(string $ruta): void
    {
        $this->mock(CertificadoService::class, fn (MockInterface $m) => $m->shouldReceive('generarPdf')->once()->andReturn($ruta));
    }

    public function test_estudiante_dueno_recibe_403(): void
    {
        $certificado = $this->crearCertificado(User::factory()->create());

        $this->actingAs($certificado->usuario)
            ->post(route('certificados.regenerar', $certificado))
            ->assertForbidden();
    }

    public function test_instructor_ajeno_recibe_403(): void
    {
        $certificado = $this->crearCertificado(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('certificados.regenerar', $certificado))
            ->assertForbidden();
    }

    public function test_instructor_dueno_regenera(): void
    {
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor);
        $this->mockServicio('certificados/2026/certificado-CERT-REG.pdf');

        $this->actingAs($instructor)
            ->post(route('certificados.regenerar', $certificado))
            ->assertRedirect()
            ->assertSessionHas('success', 'Certificado regenerado con la plantilla actual.');

        $this->assertSame('certificados/2026/certificado-CERT-REG.pdf', $certificado->fresh()->archivo_pdf);
    }

    public function test_admin_regenera(): void
    {
        $certificado = $this->crearCertificado(User::factory()->create());
        $this->mockServicio('certificados/2026/certificado-CERT-REG.pdf');

        $this->actingAs($this->admin())
            ->post(route('certificados.regenerar', $certificado))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_diplomado_sin_documento_conserva_pdf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2025/certificado-CERT-REG.pdf', 'viejo');
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor);
        $this->mock(CertificadoService::class, fn (MockInterface $m) => $m->shouldReceive('generarPdf')->once()->andThrow(new \RuntimeException('falta documento')));

        $this->actingAs($instructor)
            ->post(route('certificados.regenerar', $certificado))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('certificados/2025/certificado-CERT-REG.pdf', $certificado->fresh()->archivo_pdf);
        Storage::disk('public')->assertExists('certificados/2025/certificado-CERT-REG.pdf');
    }

    public function test_cambio_de_ruta_borra_el_pdf_viejo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2025/certificado-CERT-REG.pdf', 'viejo');
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor);
        $this->mockServicio('certificados/2026/certificado-CERT-REG.pdf');

        $this->actingAs($instructor)->post(route('certificados.regenerar', $certificado));

        Storage::disk('public')->assertMissing('certificados/2025/certificado-CERT-REG.pdf');
    }

    public function test_viejo_inexistente_no_falla(): void
    {
        Storage::fake('public');
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor);
        $this->mockServicio('certificados/2026/certificado-CERT-REG.pdf');

        $this->actingAs($instructor)
            ->post(route('certificados.regenerar', $certificado))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_regenera_pdf_real_con_plantilla_actual(): void
    {
        $certificado = $this->crearCertificado($this->admin(), 'certificados/1999/inexistente.pdf');
        $ruta        = null;

        try {
            $this->actingAs($this->admin())->post(route('certificados.regenerar', $certificado));

            $ruta = storage_path('app/public/' . $certificado->fresh()->archivo_pdf);
            $this->assertFileExists($ruta);
        } finally {
            if ($ruta && file_exists($ruta)) {
                unlink($ruta);
            }
        }
    }

    public function test_boton_visible_para_admin_y_dueno_del_curso(): void
    {
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor);

        foreach ([$this->admin(), $instructor] as $usuario) {
            $this->actingAs($usuario)
                ->get(route('certificados.show', $certificado))
                ->assertOk()
                ->assertSee('Regenerar PDF')
                ->assertSee(route('certificados.regenerar', $certificado), false);
        }
    }

    public function test_boton_oculto_para_el_estudiante(): void
    {
        $certificado = $this->crearCertificado(User::factory()->create());

        $this->actingAs($certificado->usuario)
            ->get(route('certificados.show', $certificado))
            ->assertOk()
            ->assertDontSee('Regenerar PDF');
    }

    public function test_iframe_de_previsualizacion_cambia_de_url_al_regenerar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2026/certificado-CERT-REG.pdf', 'pdf');
        $instructor  = User::factory()->create();
        $certificado = $this->crearCertificado($instructor, 'certificados/2026/certificado-CERT-REG.pdf');
        $certificado->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $url = route('certificados.previsualizar', $certificado) . '?v=' . $certificado->fresh()->updated_at->timestamp;

        $this->actingAs($instructor)
            ->get(route('certificados.show', $certificado))
            ->assertSee($url, false);
    }
}
