<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('notificaciones:vencimientos')->hourly()->withoutOverlapping();

Schedule::command('backup:crear')->dailyAt('03:00')->timezone('America/Bogota')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
