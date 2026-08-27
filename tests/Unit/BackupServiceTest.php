<?php

namespace Tests\Unit;

use App\Services\BackupService;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupService();
    }

    public function test_separa_dos_sentencias_simples(): void
    {
        $sql = "SET NAMES utf8;\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
        $this->assertSame('SET NAMES utf8;', $sentencias[0]);
        $this->assertSame('DROP TABLE IF EXISTS `users`;', $sentencias[1]);
    }

    public function test_ignora_lineas_de_comentario(): void
    {
        $sql = "-- MySQL dump\n-- ------------\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(1, $sentencias);
        $this->assertSame('DROP TABLE IF EXISTS `users`;', $sentencias[0]);
    }

    public function test_soporta_create_table_multilinea_como_una_sola_sentencia(): void
    {
        $sql = "CREATE TABLE `users` (\n  `id` bigint NOT NULL,\n  `name` varchar(255) NOT NULL\n) ENGINE=InnoDB;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(1, $sentencias);
        $this->assertStringContainsString('CREATE TABLE `users`', $sentencias[0]);
        $this->assertStringContainsString('`id` bigint NOT NULL', $sentencias[0]);
        $this->assertStringEndsWith(') ENGINE=InnoDB;', $sentencias[0]);
    }

    public function test_incluye_condicionales_de_mysql_como_sentencia_propia(): void
    {
        $sql = "/*!40101 SET NAMES utf8 */;\nDROP TABLE IF EXISTS `users`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
        $this->assertSame('/*!40101 SET NAMES utf8 */;', $sentencias[0]);
    }

    public function test_ignora_lineas_vacias(): void
    {
        $sql = "DROP TABLE IF EXISTS `a`;\n\n\nDROP TABLE IF EXISTS `b`;\n";

        $sentencias = $this->service->dividirEnSentencias($sql);

        $this->assertCount(2, $sentencias);
    }

    public function test_sql_vacio_devuelve_array_vacio(): void
    {
        $this->assertSame([], $this->service->dividirEnSentencias(''));
    }
}
