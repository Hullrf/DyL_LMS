<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public static function paginas(): array
    {
        return [
            'layout guest (login)' => ['guest'],
            'layout app (perfil)'  => ['app'],
            'layout de errores'    => ['error'],
        ];
    }

    private function html(string $pagina): string
    {
        return match ($pagina) {
            'guest' => $this->get(route('login'))->assertOk()->getContent(),
            'app'   => $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->getContent(),
            'error' => $this->get('/esta-ruta-no-existe')->assertNotFound()->getContent(),
        };
    }

    #[DataProvider('paginas')]
    public function test_la_pagina_declara_los_iconos_de_la_pestana(string $pagina): void
    {
        $html = $this->html($pagina);

        // El ?v= esquiva el CDN/navegador, que pudieron cachear el favicon.ico vacío de antes.
        $this->assertStringContainsString('<link rel="icon" href="' . asset('favicon.ico') . '?v=2"', $html);
        $this->assertStringContainsString('rel="icon" type="image/png"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
    }

    public function test_el_favicon_ico_no_esta_vacio_y_es_un_icono_valido(): void
    {
        $ruta = public_path('favicon.ico');

        $this->assertFileExists($ruta);
        $this->assertGreaterThan(1000, filesize($ruta));
        // Cabecera ICO: reservado 0, tipo 1 (icono).
        $this->assertSame("\x00\x00\x01\x00", substr(file_get_contents($ruta), 0, 4));
    }

    public function test_los_png_del_icono_existen(): void
    {
        $this->assertFileExists(public_path('images/logo/logo-icon-light-256.png'));
    }
}
