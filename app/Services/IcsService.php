<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class IcsService
{
    /** @param Collection<int, \stdClass> $eventos objetos del CalendarioService */
    public function generar(Collection $eventos, string $nombre): string
    {
        $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'lms.local';
        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        $lineas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DyL Quality Campus//Calendario//ES',
            'CALSCALE:GREGORIAN', 'X-WR-CALNAME:' . $this->escapar($nombre)];

        foreach ($eventos as $e) {
            $lineas[] = 'BEGIN:VEVENT';
            $lineas[] = "UID:{$e->uid}@{$host}";
            $lineas[] = "DTSTAMP:{$stamp}";
            $lineas[] = 'DTSTART:' . $e->inicia->format('Ymd\THis');
            $lineas[] = 'DTEND:' . $e->fin->format('Ymd\THis');
            $lineas[] = 'SUMMARY:' . $this->escapar($e->titulo);
            if (!empty($e->detalle)) $lineas[] = 'DESCRIPTION:' . $this->escapar($e->detalle);
            if (!empty($e->url))     $lineas[] = 'URL:' . $e->url;
            $lineas[] = 'END:VEVENT';
        }
        $lineas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn($l) => $this->plegar($l), $lineas)) . "\r\n";
    }

    private function escapar(string $t): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $t);
    }

    /** RFC 5545: líneas de máx. 75 bytes; la continuación empieza con un espacio. No parte caracteres UTF-8. */
    private function plegar(string $linea): string
    {
        $salida = $actual = '';
        $bytes  = 0;
        foreach (mb_str_split($linea) as $c) {
            $b = strlen($c);
            if ($bytes + $b > 75) {
                $salida .= $actual . "\r\n ";
                $actual = '';
                $bytes  = 1;
            }
            $actual .= $c;
            $bytes  += $b;
        }

        return $salida . $actual;
    }
}
