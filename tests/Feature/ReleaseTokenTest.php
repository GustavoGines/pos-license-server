<?php

namespace Tests\Feature;

use App\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valida la protección del endpoint POST /api/releases/new (CI/CD).
 *
 * Contexto: en producción (Render) se ejecuta `config:cache`, por lo que `env()`
 * fuera de los archivos de config devuelve null. El token debe leerse SIEMPRE
 * desde `config('app.ci_deploy_token')`.
 */
class ReleaseTokenTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_TOKEN = 'token-de-prueba-ci-123';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'version'      => 'v9.9.9',
            'component'    => 'frontend',
            'download_url' => 'https://example.com/release.zip',
            'changelog'    => 'Release de prueba',
        ], $overrides);
    }

    public function test_rejects_request_without_token_when_token_is_configured(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        $this->postJson('/api/releases/new', $this->payload())
            ->assertStatus(401);

        $this->assertSame(0, Release::count());
    }

    public function test_rejects_request_with_wrong_token(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        $this->postJson('/api/releases/new', $this->payload(['token' => 'otro-token']))
            ->assertStatus(401);

        $this->assertSame(0, Release::count());
    }

    /**
     * Regresión del bug original: token esperado null + token enviado ausente
     * (null !== null === false) dejaba pasar la petición sin autenticación.
     */
    public function test_rejects_request_without_token_when_expected_token_is_null(): void
    {
        config(['app.ci_deploy_token' => null]);

        $this->postJson('/api/releases/new', $this->payload())
            ->assertStatus(401);

        $this->assertSame(0, Release::count());
    }

    public function test_rejects_non_string_token_with_401_instead_of_server_error(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        $this->postJson('/api/releases/new', $this->payload(['token' => ['x']]))
            ->assertStatus(401);

        $this->assertSame(0, Release::count());
    }

    public function test_rejects_request_without_token_when_expected_token_is_empty_string(): void
    {
        config(['app.ci_deploy_token' => '']);

        $this->postJson('/api/releases/new', $this->payload())
            ->assertStatus(401);

        $this->postJson('/api/releases/new', $this->payload(['token' => '']))
            ->assertStatus(401);

        $this->assertSame(0, Release::count());
    }

    public function test_unauthenticated_request_is_rejected_before_field_validation(): void
    {
        config(['app.ci_deploy_token' => null]);

        // Sin campos: debe responder 401 (no 422), probando que la auth corre primero.
        $this->postJson('/api/releases/new', [])
            ->assertStatus(401);
    }

    public function test_accepts_request_with_correct_token_and_creates_release(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        $this->postJson('/api/releases/new', $this->payload(['token' => self::VALID_TOKEN]))
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('releases', [
            'version'   => 'v9.9.9',
            'component' => 'frontend',
        ]);
    }

    public function test_correct_token_still_validates_required_fields(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        $this->postJson('/api/releases/new', ['token' => self::VALID_TOKEN])
            ->assertStatus(422);
    }

    public function test_check_update_remains_public_for_pos_clients(): void
    {
        config(['app.ci_deploy_token' => self::VALID_TOKEN]);

        Release::create([
            'version'      => 'v2.0.0',
            'component'    => 'frontend',
            'download_url' => 'https://example.com/v2.zip',
            'changelog'    => 'v2',
            'is_critical'  => false,
            'channel'      => 'stable',
        ]);

        $this->getJson('/api/check-update?current_version=1.0.0&component=frontend&channel=stable')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'update_available' => true]);
    }

    public function test_ci_deploy_token_is_exposed_via_config_for_config_cache(): void
    {
        // Garantiza que la clave exista en config/app.php (leída con env() dentro del archivo
        // de config), requisito para que funcione con `php artisan config:cache`.
        $this->assertArrayHasKey('ci_deploy_token', config('app'));
    }
}
