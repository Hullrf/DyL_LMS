<?php

namespace Tests\Feature;

use Tests\TestCase;

class ColaProgramadaTest extends TestCase
{
    public function test_el_scheduler_procesa_la_cola_cada_cinco_minutos(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:work --stop-when-empty --max-time=240')
            ->assertSuccessful();
    }
}
