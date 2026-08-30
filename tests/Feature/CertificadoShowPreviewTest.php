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
        $response->assertSee(Storage::disk('public')->url($certificado->archivo_pdf), false);
    }
}
