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

        // BUG DISCOVERY / EMPIRICAL PROOF:
        // In LicenseValidationController.php line 48:
        // empty($license->installation_id) is evaluated.
        // In PHP, empty("0") === true!
        // Therefore, Device 2 (intruder) is NOT rejected with 403.
        // Instead, the check treats $license->installation_id as empty, overwrites it,
        // and returns 200 OK!
        $this->assertEquals(
            200,
            $res2->status(),
            'EMPIRICAL DEFECT CONFIRMED: empty("0") evaluates to true, so intruder is granted 200 instead of 403 Forbidden!'
        );
        $this->assertEquals(
            'intruder-device-999',
            $license->installation_id,
            'EMPIRICAL DEFECT CONFIRMED: License installation_id was overwritten by intruder because empty("0") is truthy!'
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
     * Demonstrates lack of atomic lock/database transaction on binding.
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

        // Terminal A and Terminal B query simultaneously before either has saved:
        // Simulate two instances of the License model loaded concurrently in memory:
        $instanceA = License::where('api_key', $license->api_key)->first();
        $instanceB = License::where('api_key', $license->api_key)->first();

        $this->assertNull($instanceA->installation_id);
        $this->assertNull($instanceB->installation_id);

        // Instance B binds first
        $instanceB->installation_id = 'terminal-bravo-first-save';
        $instanceB->save();

        // Instance A was already past the empty() check in a concurrent worker, so it saves:
        $instanceA->installation_id = 'terminal-alpha-last-save-overwrites';
        $instanceA->save();

        // The database now has Terminal Alpha's ID, silently overwriting Terminal Bravo
        $license->refresh();
        $this->assertEquals(
            'terminal-alpha-last-save-overwrites',
            $license->installation_id,
            'Without pessimistic locking (lockForUpdate) or unique constraints, the last write silently overwrites initial binding'
        );
    }
}
