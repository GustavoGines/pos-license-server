<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\License;

class VerifySqliteDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_in_memory_migrations_and_license_creation(): void
    {
        $license = License::create([
            'client_name'   => 'Empresa de Prueba',
            'business_type' => 'retail',
            'plan'          => 'basico',
            'plan_type'     => 'saas',
            'is_active'     => true,
        ]);

        $this->assertDatabaseHas('licenses', [
            'id'          => $license->id,
            'client_name' => 'Empresa de Prueba',
            'plan'        => 'basico',
        ]);
    }
}
