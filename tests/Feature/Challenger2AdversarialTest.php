<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Challenger2AdversarialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * --------------------------------------------------------------------------
     * PROBE 1: ReleaseController::store Token Bypass Verification (VULN-01)
     * --------------------------------------------------------------------------
     */

    /**
     * Probe 1a (regresión de VULN-01, ya corregida): cuando CI_DEPLOY_TOKEN es null / no está
     * configurado, una petición con "token": null NO debe registrar un release (HTTP 401).
     * Antes del arreglo, (null !== null) era false y la petición pasaba con HTTP 201.
     */
    public function test_probe_1a_release_token_bypass_when_token_config_is_null(): void
    {
        // Simulate unset CI_DEPLOY_TOKEN and null config
        Config::set('app.ci_deploy_token', null);
        putenv('CI_DEPLOY_TOKEN'); // unset env
        unset($_ENV['CI_DEPLOY_TOKEN']);
        unset($_SERVER['CI_DEPLOY_TOKEN']);

        $payload = [
            'version' => 'v2.0.0-pwn',
            'component' => 'frontend',
            'download_url' => 'https://example.com/malicious_update.zip',
            'changelog' => 'Injected unauthorized release',
            'is_critical' => true,
            'channel' => 'stable',
            'token' => null,
        ];

        $response = $this->postJson('/api/releases/new', $payload);

        $this->assertEquals(
            401,
            $response->status(),
            'VULN-01 REGRESIÓN: con CI_DEPLOY_TOKEN null el endpoint debe rechazar (fail-closed).'
        );

        $this->assertDatabaseMissing('releases', [
            'version' => 'v2.0.0-pwn',
        ]);
    }

    /**
     * Probe 1b (regresión de VULN-01): lo mismo cuando la clave 'token' se omite por completo.
     */
    public function test_probe_1b_release_token_bypass_when_token_key_is_omitted(): void
    {
        Config::set('app.ci_deploy_token', null);
        putenv('CI_DEPLOY_TOKEN');
        unset($_ENV['CI_DEPLOY_TOKEN']);
        unset($_SERVER['CI_DEPLOY_TOKEN']);

        $payload = [
            'version' => 'v2.0.1-omitted',
            'component' => 'backend',
            'download_url' => 'https://example.com/trojan_backend.tar.gz',
        ];

        $response = $this->postJson('/api/releases/new', $payload);

        $this->assertEquals(
            401,
            $response->status(),
            'VULN-01 REGRESIÓN: omitir la clave token no debe saltear la autenticación.'
        );

        $this->assertDatabaseMissing('releases', [
            'version' => 'v2.0.1-omitted',
        ]);
    }

    /**
     * Probe 1c: Contrast behavior when a legitimate CI_DEPLOY_TOKEN IS configured.
     * When expectedToken is non-null, unauthenticated requests MUST be rejected with 401.
     */
    public function test_probe_1c_release_rejects_unauthorized_when_token_is_configured(): void
    {
        Config::set('app.ci_deploy_token', 'super_secret_ci_deploy_token_2026');

        // Request with token = null
        $responseNull = $this->postJson('/api/releases/new', [
            'version' => 'v2.0.2',
            'download_url' => 'https://example.com/legit.zip',
            'token' => null,
        ]);
        $responseNull->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);

        // Request with wrong token
        $responseWrong = $this->postJson('/api/releases/new', [
            'version' => 'v2.0.2',
            'download_url' => 'https://example.com/legit.zip',
            'token' => 'wrong_token',
        ]);
        $responseWrong->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);

        // Request with correct token succeeds
        $responseCorrect = $this->postJson('/api/releases/new', [
            'version' => 'v2.0.2',
            'download_url' => 'https://example.com/legit.zip',
            'token' => 'super_secret_ci_deploy_token_2026',
        ]);
        $responseCorrect->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * --------------------------------------------------------------------------
     * PROBE 2: DRM Hardware Locking Under Unusual and Concurrent Inputs
     * --------------------------------------------------------------------------
     */

    /**
     * Probe 2a: Falsy string '0' as installation_id vulnerability.
     * In PHP, empty("0") evaluates to TRUE.
     * If an installation has ID '0', does a subsequent request with a different ID
     * overwrite the binding instead of returning 403 Forbidden?
     */
    public function test_probe_2a_drm_hardware_lock_falsy_zero_bypass(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Terminal Zero',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'installation_id' => null,
            'allowed_addons' => [],
        ]);

        // Device 1 registers with installation_id = "0"
        $res1 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => '0',
        ]);
        $res1->assertStatus(200);

        $license->refresh();
        $this->assertEquals('0', $license->installation_id, 'License should initially bind to "0"');

        // Device 2 (Intruder) attempts to validate with "intruder-device-999"
        // Expected DRM behavior: MUST return 403 Forbidden ("Esta licencia ya está vinculada a otra instalación.")
        // Actual behavior due to empty("0") === true in LicenseValidationController line 48:
        $res2 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'intruder-device-999',
        ]);

        $license->refresh();

        // L-07 REMEDIADO: Ahora installation_id = "0" NO es considerado vacío.
        // El segundo dispositivo ("intruder-device-999") debe ser rechazado con 403 Forbidden
        // y el installation_id original ("0") debe preservarse intacto.
        $this->assertEquals(
            403,
            $res2->status(),
            'L-07 FIXED: installation_id "0" must lock the license and reject intruder with 403 Forbidden!'
        );
        $this->assertEquals(
            '0',
            $license->installation_id,
            'L-07 FIXED: License installation_id "0" must not be overwritten by intruder!'
        );
    }

    /**
     * Probe 2b: Unusual input validation - empty string and whitespace installation_id.
     * Verifies framework middleware sanitization and validation behavior.
     */
    public function test_probe_2b_drm_empty_string_and_whitespace_inputs(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Edge Cases',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        // 1. Empty string ""
        $resEmpty = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => '',
        ]);
        $resEmpty->assertStatus(422)
            ->assertJsonValidationErrors(['installation_id']);

        // 2. Whitespace-only string "   "
        $resWhitespace = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => '   ',
        ]);
        $resWhitespace->assertStatus(422)
            ->assertJsonValidationErrors(['installation_id']);

        // 3. License key whitespace-only
        $resKeyWhitespace = $this->postJson('/api/validate', [
            'license_key' => '   ',
            'installation_id' => 'valid-device-123',
        ]);
        $resKeyWhitespace->assertStatus(422)
            ->assertJsonValidationErrors(['license_key']);
    }

    /**
     * Probe 2c: Case-sensitivity and whitespace trimming in installation_id.
     */
    public function test_probe_2c_drm_casing_and_padding_behavior(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Casing Test',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'installation_id' => 'hw-pos-terminal-alpha',
            'allowed_addons' => [],
        ]);

        // Case mismatch: uppercase vs lowercase
        $resUpper = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'HW-POS-TERMINAL-ALPHA',
        ]);

        // PHP strict comparison ($license->installation_id !== $installationId) is case-sensitive
        $resUpper->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Esta licencia ya está vinculada a otra instalación.',
            ]);

        // Same ID with trailing whitespace: Laravel's TrimStrings middleware trims request input
        $resPadded = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'hw-pos-terminal-alpha   ',
        ]);
        $resPadded->assertStatus(200);
    }

    /**
     * Probe 2d: Concurrency simulation / Race condition on initial hardware locking.
     * Verifies that conditional atomic update (whereNull / where '') prevents overwriting.
     */
    public function test_probe_2d_drm_concurrency_race_condition(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Concurrent Binding',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'installation_id' => null, // Unbound
            'allowed_addons' => [],
        ]);

        // Terminal B binds first via the API
        $resB = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'terminal-bravo-first-save',
        ]);
        $resB->assertStatus(200);

        // Terminal A attempts to bind afterwards and is rejected atomically with 403
        $resA = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'terminal-alpha-last-save-overwrites',
        ]);
        $resA->assertStatus(403);

        $license->refresh();
        $this->assertEquals(
            'terminal-bravo-first-save',
            $license->installation_id,
            'Atomic update and pessimistic locking preserve the first bound terminal'
        );
    }

    /**
     * Probe 3: L-01, L-08, L-14, L-17 Verification (expires_at, nonce, cryptographic signature & release sha256).
     */
    public function test_probe_3_validation_includes_expires_at_nonce_and_signature_and_release_sha256(): void
    {
        Config::set('app.license_signing_secret', 'test-signing-secret-key-2026');
        $expDate = now()->addDays(30)->format('Y-m-d');

        $license = License::create([
            'client_name' => 'Comercio Firma Criptográfica',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'expiration_date' => $expDate,
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-signed-001',
            'nonce' => 'nonce-challenge-xyz-987',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'plan',
                'plan_type',
                'server_time',
                'expires_at',
                'client_name',
                'business_type',
                'plan_espanol',
                'features',
                'nonce',
                'signature',
            ]);

        $this->assertNotNull($response->json('expires_at'), 'expires_at must be included in validation response (L-08)');
        $this->assertStringStartsWith($expDate, $response->json('expires_at'));
        $this->assertSame('nonce-challenge-xyz-987', $response->json('nonce'));
        $this->assertSame(64, strlen((string) $response->json('signature')));

        // L-14: Release SHA-256 support (optional for backwards compatibility, validated when present)
        Config::set('app.ci_deploy_token', 'ci-secret-token');
        $validSha = str_repeat('a', 64);

        $this->postJson('/api/releases/new', [
            'version' => 'v3.1.0',
            'component' => 'frontend',
            'channel' => 'stable',
            'download_url' => 'https://example.com/update_v3.1.0.zip',
            'sha256' => $validSha,
            'changelog' => 'Signed release',
            'token' => 'ci-secret-token',
        ])->assertStatus(201);

        $checkRes = $this->getJson('/api/check-update?component=frontend&current_version=3.0.0&channel=stable');
        $checkRes->assertStatus(200)
            ->assertJsonPath('data.sha256', $validSha);
    }
}
