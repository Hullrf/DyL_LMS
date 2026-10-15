<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class AyudaFlotanteTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    public function test_el_boton_aparece_en_el_dashboard_con_los_datos_configurados(): void
    {
        config(['lms.soporte' => [
            'correo' => 'soporte@dyl.test', 'url' => null,
            'horario' => 'Lunes a viernes, 8:00 am a 6:00 pm', 'faq_url' => null,
        ]]);

        $this->actingAs($this->estudiante())->get('/dashboard')
            ->assertOk()
            ->assertSee('Ayuda')
            ->assertSee('soporte@dyl.test')
            ->assertSee('Lunes a viernes, 8:00 am a 6:00 pm');
    }

    public function test_sin_datos_no_se_muestran_lineas_vacias_ni_variables_literales(): void
    {
        config(['lms.soporte' => ['correo' => null, 'url' => null, 'horario' => null, 'faq_url' => null]]);

        $html = $this->actingAs($this->estudiante())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('mailto:"', $html);
        $this->assertStringNotContainsString('Horario de atención:</', $html);
    }
}
