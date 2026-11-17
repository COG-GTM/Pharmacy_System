<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FreshInstallMigrationTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $base = config('database.connections.mysql');
        $this->database = $base['database'] . '_fresh_install_test';

        try {
            DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$this->database}`");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Needs a MySQL server the app can create databases on: ' . $e->getMessage());
        }

        config(['database.connections.fresh_install' => array_merge($base, ['database' => $this->database])]);
    }

    protected function tearDown(): void
    {
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$this->database}`");
        DB::purge('fresh_install');

        parent::tearDown();
    }

    public function test_all_migrations_run_on_an_empty_mysql_database(): void
    {
        $exitCode = Artisan::call('migrate:fresh', ['--database' => 'fresh_install', '--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $foreignKeys = collect(DB::connection('fresh_install')->select(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, REFERENCED_TABLE_NAME AS r
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$this->database]
        ))->map(fn ($fk) => "{$fk->t}.{$fk->c} -> {$fk->r}")->all();

        foreach ([
            'clients.user_id -> users',
            'pharmacies.area_id -> areas',
            'payments.order_id -> orders',
            'orders_medicines.order_id -> orders',
            'areas.country_id -> countries',
        ] as $expected) {
            $this->assertContains($expected, $foreignKeys);
        }
    }
}
