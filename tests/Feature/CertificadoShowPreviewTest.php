<?php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoShowPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_muestra_un_iframe_con_el_pdf_real(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2026/certificado-CERT-TEST.pdf', 'contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST',
            'archivo_pdf'        => 'certificados/2026/certificado-CERT-TEST.pdf',
        ]);

        $response = $this->actingAs($estudiante)->get(route('certificados.show', $certificado));

        $response->assertOk();
        $response->assertSee('<iframe', false);
        $response->assertSee(route('certificados.previsualizar', $certificado), false);
    }

    public function test_previsualizar_sirve_el_pdf_en_linea_al_dueno(): void
    {
        // response()->file() lee del disco real (igual que descargar()), así
        // que aquí usamos un archivo real en storage/app/public en vez de
        // Storage::fake('public').
        $rutaRelativa  = 'certificados/2026/certificado-CERT-TEST-PREVIEW.pdf';
        $rutaAbsoluta  = storage_path('app/public/' . $rutaRelativa);
        if (!is_dir(dirname($rutaAbsoluta))) {
            mkdir(dirname($rutaAbsoluta), 0755, true);
        }
        file_put_contents($rutaAbsoluta, '%PDF-1.4 contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST-PREVIEW',
            'archivo_pdf'        => $rutaRelativa,
        ]);

        try {
            $response = $this->actingAs($estudiante)->get(route('certificados.previsualizar', $certificado));

            $response->assertOk();
            $disposition = (string) $response->headers->get('content-disposition');
            $this->assertStringNotContainsString('attachment', $disposition);
        } finally {
            @unlink($rutaAbsoluta);
        }
    }

    /**
     * Fix 2 (Important): el instructor del curso (no admin, no el dueño del
     * certificado) puede ver la insignia "Certificado emitido" que enlaza aquí
     * desde su propia matriz de calificaciones — antes recibía 403.
     */
    public function test_show_permite_al_instructor_del_curso_ver_el_certificado_de_su_estudiante(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-INSTRUCTOR-SHOW',
        ]);

        $response = $this->actingAs($instructor)->get(route('certificados.show', $certificado));

        $response->assertOk();
    }

    public function test_descargar_permite_al_instructor_del_curso_descargar_el_certificado_de_su_estudiante(): void
    {
        // descargar() lee del disco real (igual que previsualizar()), así que aquí
        // usamos un archivo real en storage/app/public en vez de Storage::fake('public').
        $rutaRelativa = 'certificados/2026/certificado-CERT-INSTRUCTOR-DESCARGA.pdf';
        $rutaAbsoluta = storage_path('app/public/' . $rutaRelativa);
        if (!is_dir(dirname($rutaAbsoluta))) {
            mkdir(dirname($rutaAbsoluta), 0755, true);
        }
        file_put_contents($rutaAbsoluta, '%PDF-1.4 contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-INSTRUCTOR-DESCARGA',
            'archivo_pdf'        => $rutaRelativa,
        ]);

        try {
            $response = $this->actingAs($instructor)->get(route('certificados.descargar', $certificado));

            $response->assertOk();
        } finally {
            @unlink($rutaAbsoluta);
        }
    }

    public function test_previsualizar_permite_al_instructor_del_curso_ver_el_pdf_de_su_estudiante(): void
    {
        $rutaRelativa = 'certificados/2026/certificado-CERT-INSTRUCTOR-PREVIEW.pdf';
        $rutaAbsoluta = storage_path('app/public/' . $rutaRelativa);
        if (!is_dir(dirname($rutaAbsoluta))) {
            mkdir(dirname($rutaAbsoluta), 0755, true);
        }
        file_put_contents($rutaAbsoluta, '%PDF-1.4 contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-INSTRUCTOR-PREVIEW',
            'archivo_pdf'        => $rutaRelativa,
        ]);

        try {
            $response = $this->actingAs($instructor)->get(route('certificados.previsualizar', $certificado));

            $response->assertOk();
        } finally {
            @unlink($rutaAbsoluta);
        }
    }

    public function test_previsualizar_devuelve_403_para_un_usuario_ajeno(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificados/2026/certificado-CERT-TEST.pdf', 'contenido-fake');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $otro       = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST',
            'archivo_pdf'        => 'certificados/2026/certificado-CERT-TEST.pdf',
        ]);

        $response = $this->actingAs($otro)->get(route('certificados.previsualizar', $certificado));

        $response->assertForbidden();
    }

    public function test_previsualizar_redirige_con_error_si_falta_el_documento_para_regenerar_la_carta(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST-SIN-DOC',
            'archivo_pdf'        => 'certificados/2026/no-existe.pdf', // no existe en el disco fake
        ]);

        $response = $this->actingAs($estudiante)->get(route('certificados.previsualizar', $certificado));

        $response->assertRedirect(route('certificados.show', $certificado));
        $response->assertSessionHas('error');
    }

    public function test_descargar_redirige_con_error_si_falta_el_documento_para_regenerar_la_carta(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-TEST-SIN-DOC-2',
            'archivo_pdf'        => 'certificados/2026/no-existe.pdf', // no existe en el disco fake
        ]);

        $response = $this->actingAs($estudiante)->get(route('certificados.descargar', $certificado));

        $response->assertRedirect(route('certificados.show', $certificado));
        $response->assertSessionHas('error');
    }
}
