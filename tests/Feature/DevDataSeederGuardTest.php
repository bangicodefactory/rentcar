<?php

namespace Tests\Feature;

use Database\Seeders\DevDataSeeder;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * Pins DevDataSeeder's production guard.
 *
 * The seeder attaches fake vehicles/bookings/payments to the real owner
 * account and generates TVA factures for every un-invoiced payment. Run
 * against a live DB that pollutes real invoice numbering — which happened
 * on directonderweg prod on 2026-07-06 (490 rows cleaned up by hand).
 * The guard must throw before the seeder touches any table.
 *
 */
class DevDataSeederGuardTest extends TestCase
{
    use WithClient;

    public function test_seeder_refuses_to_run_in_production(): void
    {
        $this->asClient('directonderweg');
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must never run in production');

        // No DB setup on purpose: the guard has to fire before the seeder's
        // first query, so an unseeded test DB must not be reachable.
        (new DevDataSeeder())->run();
    }
}
