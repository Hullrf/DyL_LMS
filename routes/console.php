<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('notificaciones:vencimientos')->hourly()->withoutOverlapping();

// Procesa la cola (correos de avisos). Con QUEUE_CONNECTION=sync no hay nada que procesar y sale al instante.
Schedule::command('queue:work --stop-when-empty --max-time=240')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('backup:crear')->dailyAt('03:00')->timezone('America/Bogota')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
