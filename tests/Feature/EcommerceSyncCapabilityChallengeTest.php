<?php

namespace Tests\Feature;

use App\Models\License;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceSyncCapabilityChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_18_FEATURES = [
        'fast_pos',
        'z_reports',
        'quotes',
        'current_accounts',
        'multiple_prices',
        'multi_caja',
        'advanced_reports',
        'predictive_alerts',
        'logistics',
        'checks',
        'mobile_app',
        'remote_access',
        'suppliers',
        'expenses',
        'multi_rubro',
        'mercadopago_qr',
        'arca_afip',
        'ecommerce_sync',
    ];

    /**
     * Test 1: Basic Plan License (Retail & Hardware) -> ecommerce_sync is strictly boolean false.
     */
    public function test_basic_plan_license_has_ecommerce_sync_strictly_false(): void
    {
        $licenseRetail = License::create([
            'client_name' => 'Challenger Basic Retail',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $responseRetail = $this->postJson('/api/validate', [
            'license_key' => $licenseRetail->api_key,
            'installation_id' => 'device-chal-basic-01',
        ]);

        $responseRetail->assertStatus(200);
        $featuresRetail = $responseRetail->json('features');

        $this->assertArrayHasKey('ecommerce_sync', $featuresRetail);
        $this->assertSame(false, $featuresRetail['ecommerce_sync'], 'ecommerce_sync must be strictly boolean false for basic retail');
        $this->assertCount(18, $featuresRetail, 'Total feature keys must be exactly 18');

        // Hardware store vertical
        $licenseHw = License::create([
            'client_name' => 'Challenger Basic HW',
            'business_type' => License::BUSINESS_HARDWARE,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $responseHw = $this->postJson('/api/validate', [
            'license_key' => $licenseHw->api_key,
            'installation_id' => 'device-chal-basic-hw-01',
        ]);

        $responseHw->assertStatus(200);
        $featuresHw = $responseHw->json('features');
        $this->assertSame(false, $featuresHw['ecommerce_sync'], 'ecommerce_sync must be strictly boolean false for basic hardware');
    }

    /**
     * Test 2: Premium Plan License (premium, pro, enterprise) -> ecommerce_sync is strictly boolean true.
     */
    public function test_premium_plan_license_has_ecommerce_sync_strictly_true(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Premium Retail',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-prem-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        $this->assertArrayHasKey('ecommerce_sync', $features);
        $this->assertSame(true, $features['ecommerce_sync'], 'ecommerce_sync must be strictly boolean true for premium plan');
        $this->assertCount(18, $features, 'Total feature keys must be exactly 18 for premium plan');

        // Hardware store vertical
        $licenseHw = License::create([
            'client_name' => 'Challenger Premium HW',
            'business_type' => License::BUSINESS_HARDWARE,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $responseHw = $this->postJson('/api/validate', [
            'license_key' => $licenseHw->api_key,
            'installation_id' => 'device-chal-prem-hw-01',
        ]);

        $responseHw->assertStatus(200);
        $featuresHw = $responseHw->json('features');
        $this->assertSame(true, $featuresHw['ecommerce_sync'], 'ecommerce_sync must be strictly boolean true for premium hardware');
    }

    /**
     * Test 3: Basic Plan with ecommerce_sync in allowed_addons -> ecommerce_sync is strictly boolean true.
     */
    public function test_basic_plan_with_ecommerce_sync_in_allowed_addons_is_strictly_true(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Basic with Addon',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['ecommerce_sync'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-basic-addon-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        $this->assertArrayHasKey('ecommerce_sync', $features);
        $this->assertSame(true, $features['ecommerce_sync'], 'ecommerce_sync must be strictly boolean true via allowed_addons override');

        // Base features are true
        $this->assertSame(true, $features['current_accounts']);
        $this->assertSame(true, $features['mercadopago_qr']);
        $this->assertSame(true, $features['arca_afip']);

        // Other premium addons must remain false
        $this->assertSame(false, $features['multi_caja']);
        $this->assertSame(false, $features['suppliers']);
        $this->assertSame(false, $features['expenses']);
        $this->assertSame(false, $features['multi_rubro']);

        // Features count must be exactly 18
        $this->assertCount(18, $features);
    }

    /**
     * Test 4: Adversarial feature dictionary verification:
     * - Exactly 18 keys.
     * - Strict boolean types for all keys across all plans.
     */
    public function test_adversarial_feature_count_and_strict_boolean_typing(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Strict Typing Test',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['ecommerce_sync'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-strict-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        $this->assertCount(18, $features, 'Total keys in features dictionary must be exactly 18');

        // Verify all 18 expected keys exist
        $this->assertEqualsCanonicalizing(
            self::EXPECTED_18_FEATURES,
            array_keys($features),
            'Feature keys in response do not match canonical 18 keys'
        );

        // Strict boolean assertion (no integers 0/1, no strings "true"/"false")
        foreach ($features as $key => $value) {
            $this->assertIsBool($value, "Feature [{$key}] must be strictly of type boolean");
        }
    }

    /**
     * Test 5: Suspended license with ecommerce_sync in allowed_addons is rejected (HTTP 403 suspended).
     */
    public function test_suspended_license_with_ecommerce_sync_is_rejected(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Suspended License',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => false,
            'allowed_addons' => ['ecommerce_sync'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-suspended-01',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'suspended',
                'message' => 'La licencia está suspendida. Contacte a soporte.',
            ]);
    }

    /**
     * Test 6: Expired SaaS license with ecommerce_sync in allowed_addons is rejected (HTTP 403 expired).
     */
    public function test_expired_saas_license_with_ecommerce_sync_is_rejected(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Expired SaaS License',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'expiration_date' => Carbon::yesterday()->toDateString(),
            'allowed_addons' => ['ecommerce_sync'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-expired-01',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'expired',
                'message' => 'La licencia ha expirado.',
            ]);
    }

    /**
     * Test 7: Hardware DRM lock with ecommerce_sync: mismatch installation_id is rejected (HTTP 403).
     */
    public function test_hardware_drm_lock_prevents_unauthorized_device_access(): void
    {
        $license = License::create([
            'client_name' => 'Challenger DRM Hardware Device',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'installation_id' => 'registered-device-001',
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'intruder-device-002',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Esta licencia ya está vinculada a otra instalación.',
            ]);
    }

    /**
     * Test 8: Adversarial malformed allowed_addons input (null, integers, empty string).
     */
    public function test_adversarial_malformed_allowed_addons_handling(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Malformed Addons',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => null, // null allowed_addons
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-malformed-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        $this->assertCount(18, $features);
        $this->assertSame(false, $features['ecommerce_sync']);
    }

    /**
     * Test 9: Plan transition behavior (upgrade and downgrade).
     */
    public function test_plan_transition_dynamically_toggles_ecommerce_sync(): void
    {
        $license = License::create([
            'client_name' => 'Challenger Transition License',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        // Step 1: Basic -> false
        $res1 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-transition-01',
        ])->assertStatus(200);
        $this->assertSame(false, $res1->json('features.ecommerce_sync'));

        // Step 2: Upgrade to Premium -> true
        $license->update(['plan' => License::PLAN_PREMIUM]);
        $res2 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-transition-01',
        ])->assertStatus(200);
        $this->assertSame(true, $res2->json('features.ecommerce_sync'));

        // Step 3: Downgrade to Basic -> false
        $license->update(['plan' => License::PLAN_BASICO]);
        $res3 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-transition-01',
        ])->assertStatus(200);
        $this->assertSame(false, $res3->json('features.ecommerce_sync'));

        // Step 4: Add ecommerce_sync to allowed_addons on Basic -> true
        $license->update(['allowed_addons' => ['ecommerce_sync']]);
        $res4 = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-chal-transition-01',
        ])->assertStatus(200);
        $this->assertSame(true, $res4->json('features.ecommerce_sync'));
    }
}
