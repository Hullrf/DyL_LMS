<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AvisosPorCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AvisosCorreoCategoriasTest extends TestCase
{
    use RefreshDatabase;

    public function test_categoria_de_mapea_los_tipos_incluidos(): void
    {
        $this->assertSame('vencimientos', AvisosPorCorreoService::categoriaDe('vencimiento'));
        $this->assertSame('calificaciones', AvisosPorCorreoService::categoriaDe('calificacion'));
        $this->assertSame('calificaciones', AvisosPorCorreoService::categoriaDe('intento_extra'));
        $this->assertSame('anuncios', AvisosPorCorreoService::categoriaDe('anuncio'));
        $this->assertSame('certificados', AvisosPorCorreoService::categoriaDe('certificado'));

        foreach (['foro', 'mensaje', 'entrega', 'otro'] as $tipo) {
            $this->assertNull(AvisosPorCorreoService::categoriaDe($tipo), "{$tipo} no debe salir por correo");
        }
    }

    public function test_recibe_correo_por_defecto_en_todas_las_categorias(): void
    {
        $usuario = new User();

        foreach (array_keys(AvisosPorCorreoService::CATEGORIAS) as $categoria) {
            $this->assertTrue($usuario->recibeCorreo($categoria), "por defecto debe recibir {$categoria}");
        }
    }

    public function test_recibe_correo_respeta_categorias_desactivadas(): void
    {
        $usuario = new User();
        $usuario->correo_desactivados = ['anuncios'];

        $this->assertFalse($usuario->recibeCorreo('anuncios'));
        foreach (['vencimientos', 'calificaciones', 'certificados'] as $categoria) {
            $this->assertTrue($usuario->recibeCorreo($categoria));
        }
    }

    public function test_la_columna_existe_y_acepta_null(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'correo_desactivados'));

        $usuario = User::factory()->create();

        $this->assertNull($usuario->fresh()->correo_desactivados);
    }
}
