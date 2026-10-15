<?php

namespace Tests\Unit;

use App\Services\IcsService;
use Carbon\Carbon;
use Tests\TestCase;

class IcsServiceTest extends TestCase
{
    private function evento(string $titulo, ?string $detalle = null): \stdClass
    {
        return (object) [
            'uid' => 'act-1-cierre', 'tipo' => 'cierre', 'titulo' => $titulo, 'detalle' => $detalle,
            'inicia' => Carbon::parse('2026-10-16 18:00:00'), 'fin' => Carbon::parse('2026-10-16 18:30:00'),
            'url' => 'https://lms.test/actividades/1',
        ];
    }

    public function test_estructura_basica_con_crlf_y_horas_flotantes(): void
    {
        $ics = (new IcsService)->generar(collect([$this->evento('Entrega final')]), 'Mi calendario');

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Entrega final\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20261016T180000\r\n", $ics);
        $this->assertStringContainsString("DTEND:20261016T183000\r\n", $ics);
        $this->assertStringContainsString("URL:https://lms.test/actividades/1\r\n", $ics);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $ics)); // ningun LF suelto
    }

    public function test_escapa_coma_punto_y_coma_barra_y_saltos_de_linea(): void
    {
        $ics = (new IcsService)->generar(collect([$this->evento('A, B; C \\ D', "linea1\nlinea2")]), 'X');

        $this->assertStringContainsString('SUMMARY:A\, B\; C \\\\ D', $ics);
        $this->assertStringContainsString('DESCRIPTION:linea1\nlinea2', $ics);
    }

    public function test_lineas_largas_se_pliegan_a_75_bytes_sin_partir_multibyte(): void
    {
        $titulo = str_repeat('áéíóú ñ 😀 ', 30);
        $ics = (new IcsService)->generar(collect([$this->evento($titulo)]), 'X');

        foreach (explode("\r\n", $ics) as $linea) {
            $this->assertLessThanOrEqual(75, strlen($linea));
            $this->assertTrue(mb_check_encoding($linea, 'UTF-8'), 'linea con UTF-8 roto: ' . $linea);
        }
        // al desplegar (quitar CRLF + espacio) se recupera el titulo completo
        $this->assertStringContainsString(rtrim($titulo), str_replace("\r\n ", '', $ics));
    }

    public function test_sin_eventos_sigue_siendo_un_calendario_valido(): void
    {
        $ics = (new IcsService)->generar(collect(), 'Vacio');

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }
}
