<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ColaProgramadaTest extends TestCase
{
    public function test_el_scheduler_procesa_la_cola_cada_cinco_minutos(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:work --stop-when-empty --max-time=240')
            ->assertSuccessful();
    }

    public function test_el_candado_de_la_cola_expira_en_10_minutos_si_el_worker_muere(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'queue:work'));

        $this->assertNotNull($evento);
        $this->assertSame(10, $evento->expiresAt);
    }

    public function test_el_smtp_corta_pronto_si_el_servidor_no_responde(): void
    {
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }
}
