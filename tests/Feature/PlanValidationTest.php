<?php

namespace Tests\Feature;

use App\Models\License;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Standard Basic Plan Flags (Retail)
     * Verifies that a basic retail license receives HTTP 200 with plan 'basic' / 'basico',
     * has base features (fast_pos, z_reports) set to true, and all premium features set to false.
     */
    public function test_standard_basic_plan_flags_retail(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Retail Básico',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-basic-retail-001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan' => 'basic',
                'plan_espanol' => 'basico',
                'business_type' => 'retail',
            ]);

        $features = $response->json('features');

        // Base features: MUST be true
        $this->assertTrue($features['fast_pos'], 'fast_pos must be true in basic plan');
        $this->assertTrue($features['z_reports'], 'z_reports must be true in basic plan');

        // Premium features: MUST be false
        $this->assertFalse($features['multi_caja'], 'multi_caja must be false in basic plan');
        $this->assertFalse($features['current_accounts'], 'current_accounts must be false in basic plan');
        $this->assertFalse($features['multiple_prices'], 'multiple_prices must be false in basic plan');
        $this->assertFalse($features['advanced_reports'], 'advanced_reports must be false in basic plan');
        $this->assertFalse($features['predictive_alerts'], 'predictive_alerts must be false in basic plan');
        $this->assertFalse($features['checks'], 'checks must be false in basic plan');
        $this->assertFalse($features['suppliers'], 'suppliers must be false in basic plan');
        $this->assertFalse($features['expenses'], 'expenses must be false in basic plan');
        $this->assertFalse($features['multi_rubro'], 'multi_rubro must be false in basic plan');
        $this->assertFalse($features['mercadopago_qr'], 'mercadopago_qr must be false in basic plan');
        $this->assertFalse($features['arca_afip'], 'arca_afip must be false in basic plan');

        // Vertical & Addon features: MUST be false
        $this->assertFalse($features['quotes'], 'quotes must be false for retail basic');
        $this->assertFalse($features['logistics'], 'logistics must be false for retail basic');
        $this->assertFalse($features['mobile_app'], 'mobile_app must be false when not in addons');
        $this->assertFalse($features['remote_access'], 'remote_access must be false when not in addons');
    }

    /**
     * Test 2: Standard Premium Plan Flags (Retail)
     * Verifies that a premium retail license receives HTTP 200 with plan 'pro' / 'premium',
     * all base and 11 premium features set to true (including Phase 0/1 flags),
     * while hardware exclusive features (quotes, logistics) remain false.
     */
    public function test_standard_premium_plan_flags_retail(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Retail Premium',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-premium-retail-001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan' => 'pro',
                'plan_espanol' => 'premium',
                'business_type' => 'retail',
            ]);

        $features = $response->json('features');

        // Base features: MUST be true
        $this->assertTrue($features['fast_pos'], 'fast_pos must be true in premium plan');
        $this->assertTrue($features['z_reports'], 'z_reports must be true in premium plan');

        // All 11 Premium features: MUST be true
        $this->assertTrue($features['multi_caja'], 'multi_caja must be true in premium plan');
        $this->assertTrue($features['current_accounts'], 'current_accounts must be true in premium plan');
        $this->assertTrue($features['advanced_reports'], 'advanced_reports must be true in premium plan');
        $this->assertTrue($features['predictive_alerts'], 'predictive_alerts must be true in premium plan');
        $this->assertTrue($features['checks'], 'checks must be true in premium plan');
        $this->assertTrue($features['suppliers'], 'suppliers must be true in premium plan');
        $this->assertTrue($features['expenses'], 'expenses must be true in premium plan');
        $this->assertTrue($features['multiple_prices'], 'multiple_prices must be true in premium plan');
        $this->assertTrue($features['multi_rubro'], 'multi_rubro must be true in premium plan (Phase 0/1)');
        $this->assertTrue($features['mercadopago_qr'], 'mercadopago_qr must be true in premium plan (Phase 0/1)');
        $this->assertTrue($features['arca_afip'], 'arca_afip must be true in premium plan (Phase 0/1)');

        // Hardware store exclusive features: MUST be false in Retail vertical
        $this->assertFalse($features['quotes'], 'quotes must be false in retail even if premium');
        $this->assertFalse($features['logistics'], 'logistics must be false in retail even if premium');

        // Addon-only features: MUST be false if not specifically granted
        $this->assertFalse($features['mobile_app'], 'mobile_app must be false without addon grant');
        $this->assertFalse($features['remote_access'], 'remote_access must be false without addon grant');
    }

    /**
     * Test 3: Basic Plan with Manual Override (extra module 'suppliers')
     * Verifies that adding 'suppliers' to allowed_addons on a Basic plan
     * overrides features.suppliers to true while keeping other premium features false.
     */
    public function test_basic_plan_with_manual_override_suppliers(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Básico Con Proveedores Extra',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['suppliers'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-override-suppliers-001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan' => 'basic',
                'plan_espanol' => 'basico',
            ]);

        $features = $response->json('features');

        // Base features: MUST remain true
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);

        // Overridden module: MUST evaluate to true
        $this->assertTrue($features['suppliers'], 'features.suppliers must be true via allowed_addons override');

        // Other premium modules: MUST remain false
        $this->assertFalse($features['multi_caja']);
        $this->assertFalse($features['current_accounts']);
        $this->assertFalse($features['advanced_reports']);
        $this->assertFalse($features['predictive_alerts']);
        $this->assertFalse($features['checks']);
        $this->assertFalse($features['expenses']);
        $this->assertFalse($features['multiple_prices']);
        $this->assertFalse($features['multi_rubro']);
        $this->assertFalse($features['mercadopago_qr']);
        $this->assertFalse($features['arca_afip']);
    }

    /**
     * Test 4: Discrepancy test with colloquial Spanish key 'proveedores'
     * Asserts that passing 'proveedores' in allowed_addons is currently ignored
     * because the system expects the canonical key 'suppliers' and lacks alias resolution.
     */
    public function test_discrepancy_check_spanish_key_proveedores(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Básico Clave Español',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['proveedores'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-spanish-key-001',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // The canonical key 'suppliers' is FALSE because 'proveedores' was not aliased
        $this->assertFalse($features['suppliers'], 'suppliers remains false when allowed_addons has "proveedores" without alias mapping');

        // 'proveedores' is not in the recognized feature set
        $this->assertArrayNotHasKey('proveedores', $features, 'Colloquial key "proveedores" should not exist as a recognized feature flag');
    }

    /**
     * Test 5: Vertical restriction enforcement
     * Asserts that for retail businesses, vertical-exclusive features ('quotes' and 'logistics')
     * are strictly forced to false even if manually placed in allowed_addons.
     * In contrast, a hardware_store business has them enabled.
     */
    public function test_vertical_restriction_enforcement_blocks_quotes_and_logistics_on_retail(): void
    {
        // 5a. Retail business attempting to inject hardware-exclusive addons
        $retailLicense = License::create([
            'client_name' => 'Comercio Retail Con Addons Ferretería',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['quotes', 'logistics'],
        ]);

        $retailResponse = $this->postJson('/api/validate', [
            'license_key' => $retailLicense->api_key,
            'installation_id' => 'device-retail-vertical-001',
        ]);

        $retailResponse->assertStatus(200);
        $retailFeatures = $retailResponse->json('features');

        // Vertical exclusivity must strictly block these on retail
        $this->assertFalse($retailFeatures['quotes'], 'quotes must be forced to false on retail even if present in allowed_addons');
        $this->assertFalse($retailFeatures['logistics'], 'logistics must be forced to false on retail even if present in allowed_addons');

        // 5b. Hardware store business: quotes and logistics are enabled natively
        $hardwareLicense = License::create([
            'client_name' => 'Ferretería Industrial',
            'business_type' => License::BUSINESS_HARDWARE,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $hardwareResponse = $this->postJson('/api/validate', [
            'license_key' => $hardwareLicense->api_key,
            'installation_id' => 'device-hardware-vertical-001',
        ]);

        $hardwareResponse->assertStatus(200);
        $hardwareFeatures = $hardwareResponse->json('features');

        $this->assertTrue($hardwareFeatures['quotes'], 'quotes must be true for hardware_store business');
        $this->assertTrue($hardwareFeatures['logistics'], 'logistics must be true for hardware_store business');
    }

    /**
     * Test 6: DRM & Hardware Lock
     * First activation binds installation_id to the license.
     * Subsequent activation with matching installation_id succeeds (200 OK).
     * Subsequent activation with different installation_id is rejected with HTTP 403.
     */
    public function test_drm_hardware_lock_enforcement(): void
    {
        $license = License::create([
            'client_name' => 'Comercio Candado Hardware',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'installation_id' => null, // Not yet activated
            'allowed_addons' => [],
        ]);

        $firstInstallationId = 'hw-device-terminal-alpha';
        $secondInstallationId = 'hw-device-terminal-beta-intruder';

        // 6a. First activation binds the installation_id
        $firstActivation = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => $firstInstallationId,
        ]);

        $firstActivation->assertStatus(200)
            ->assertJson(['status' => 'active']);

        $this->assertEquals(
            $firstInstallationId,
            $license->fresh()->installation_id,
            'First activation must persist installation_id to the database'
        );

        // 6b. Subsequent activation with same installation_id succeeds
        $secondActivationSameDevice = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => $firstInstallationId,
        ]);

        $secondActivationSameDevice->assertStatus(200)
            ->assertJson(['status' => 'active']);

        // 6c. Subsequent activation with different installation_id is rejected with HTTP 403
        $intruderActivation = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => $secondInstallationId,
        ]);

        $intruderActivation->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Esta licencia ya está vinculada a otra instalación.',
            ]);
    }

    /**
     * Test 7: SaaS expiration vs Lifetime
     * Expired SaaS license is rejected with HTTP 403 ('expired').
     * Lifetime license with an expired date is accepted with HTTP 200 ('active').
     */
    public function test_saas_expiration_vs_lifetime(): void
    {
        // 7a. SaaS license that has expired
        $expiredSaasLicense = License::create([
            'client_name' => 'Comercio SaaS Expirado',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'expiration_date' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'allowed_addons' => [],
        ]);

        $expiredSaasResponse = $this->postJson('/api/validate', [
            'license_key' => $expiredSaasLicense->api_key,
            'installation_id' => 'device-saas-expired-001',
        ]);

        $expiredSaasResponse->assertStatus(403)
            ->assertJson([
                'status' => 'expired',
                'message' => 'La licencia ha expirado.',
            ]);

        // 7b. Lifetime license: even if expiration_date is set in the past, it remains active
        $lifetimeLicense = License::create([
            'client_name' => 'Comercio Lifetime Perpetuo',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_LIFETIME,
            'is_active' => true,
            'expiration_date' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'allowed_addons' => [],
        ]);

        $lifetimeResponse = $this->postJson('/api/validate', [
            'license_key' => $lifetimeLicense->api_key,
            'installation_id' => 'device-lifetime-001',
        ]);

        $lifetimeResponse->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan_type' => License::TYPE_LIFETIME,
            ]);
    }

    /**
     * Test 8 (Boundary/Security): Suspended license returns HTTP 403 'suspended'
     */
    public function test_suspended_license_returns_403_suspended(): void
    {
        $suspendedLicense = License::create([
            'client_name' => 'Comercio Suspendido',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => false,
            'allowed_addons' => [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $suspendedLicense->api_key,
            'installation_id' => 'device-suspended-001',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'suspended',
                'message' => 'La licencia está suspendida. Contacte a soporte.',
            ]);
    }

    /**
     * Test 9 (Boundary/Security): Invalid license key returns HTTP 403 'error'
     */
    public function test_invalid_license_key_returns_403_not_found(): void
    {
        $response = $this->postJson('/api/validate', [
            'license_key' => 'pos_non_existent_key_1234567890',
            'installation_id' => 'device-nonexistent-001',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Licencia no encontrada.',
            ]);
    }

    /**
     * Test 10 (Validation): Missing required fields returns HTTP 422 Unprocessable Entity
     */
    public function test_missing_fields_validation_error(): void
    {
        $response = $this->postJson('/api/validate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_key', 'installation_id']);
    }
}
