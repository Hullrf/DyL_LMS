<?php
// tests/Unit/NumeroEnPalabrasTest.php

namespace Tests\Unit;

use App\Support\NumeroEnPalabras;
use PHPUnit\Framework\TestCase;

class NumeroEnPalabrasTest extends TestCase
{
    public function test_dia_uno(): void
    {
        $this->assertSame('uno', NumeroEnPalabras::dia(1));
    }

    public function test_dia_veintinueve(): void
    {
        $this->assertSame('veintinueve', NumeroEnPalabras::dia(29));
    }

    public function test_dia_treinta_y_uno(): void
    {
        $this->assertSame('treinta y uno', NumeroEnPalabras::dia(31));
    }

    public function test_dia_dieciseis(): void
    {
        $this->assertSame('dieciséis', NumeroEnPalabras::dia(16));
    }

    public function test_dia_fuera_de_rango_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroEnPalabras::dia(32);
    }

    public function test_dia_cero_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroEnPalabras::dia(0);
    }
}
