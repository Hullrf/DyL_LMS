<?php
// app/Support/NumeroEnPalabras.php

namespace App\Support;

class NumeroEnPalabras
{
    private const DIAS = [
        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
        11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve', 20 => 'veinte',
        21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro', 25 => 'veinticinco',
        26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta',
        31 => 'treinta y uno',
    ];

    /**
     * Convierte un día del mes (1-31) a su nombre en español, tal como se
     * usa en el texto de las cartas de diplomado ("a los veintinueve (29) días...").
     */
    public static function dia(int $dia): string
    {
        if (!isset(self::DIAS[$dia])) {
            throw new \InvalidArgumentException("Día fuera de rango: {$dia}. Debe estar entre 1 y 31.");
        }

        return self::DIAS[$dia];
    }
}
