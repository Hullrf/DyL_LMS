<?php

namespace Tests\Feature;

use App\Mail\AvisoLms;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AvisoLmsMailTest extends TestCase
{
    use RefreshDatabase;

    private function aviso(string $titulo, string $mensaje, ?string $url): Notificacion
    {
        $usuario = User::factory()->create();

        return Notificacion::crear($usuario->id, 'calificacion', $titulo, $mensaje, $url);
    }

    public function test_asunto_es_el_titulo_del_aviso(): void
    {
        Mail::fake();
        $mail = new AvisoLms($this->aviso('Actividad calificada', 'Ya tienes tu nota.', null));

        $this->assertSame('Actividad calificada', $mail->envelope()->subject);
    }

    public function test_el_cuerpo_incluye_titulo_mensaje_boton_y_enlace_al_perfil(): void
    {
        Mail::fake();
        $url  = 'https://campus.test/actividades/7';
        $mail = new AvisoLms($this->aviso('Actividad calificada', 'Ya tienes tu nota.', $url));

        $html = $mail->render();

        $this->assertStringContainsString('Actividad calificada', $html);
        $this->assertStringContainsString('Ya tienes tu nota.', $html);
        $this->assertStringContainsString('Abrir en el campus', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
    }

    public function test_sin_enlace_no_muestra_el_boton(): void
    {
        Mail::fake();
        $mail = new AvisoLms($this->aviso('Aviso sin enlace', 'Solo texto.', null));

        $html = $mail->render();

        $this->assertStringContainsString('Solo texto.', $html);
        $this->assertStringNotContainsString('Abrir en el campus', $html);
    }

    public function test_escapa_el_html_del_titulo_y_del_mensaje(): void
    {
        Mail::fake();
        $mail = new AvisoLms($this->aviso('<script>alert(1)</script>', '<b>negrita</b>', null));

        $html = $mail->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>negrita</b>', $html);
    }
}
