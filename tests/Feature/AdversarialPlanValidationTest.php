<?php

namespace Tests\Feature;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdversarialPlanValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Standard list of exactly 17 features expected in the license response.
     */
    private const EXPECTED_FEATURES = [
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
    ];

    /**
     * Challenge 1: Multiple overrides provided simultaneously in allowed_addons on Basic.
     * e.g., ['suppliers', 'expenses', 'multiple_prices']
     */
    public function test_adversarial_multiple_concurrent_overrides_on_basic(): void
    {
        $license = License::create([
            'client_name' => 'Adversarial Test - Multiple Overrides',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['suppliers', 'expenses', 'multiple_prices'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'adv-device-multi-override-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan' => 'basic',
                'plan_espanol' => 'basico',
                'business_type' => 'retail',
            ]);

        $features = $response->json('features');

        // 1. Base features must be true
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);

        // 2. The 3 overridden features must be true
        $this->assertTrue($features['suppliers'], 'suppliers must be active via concurrent override');
        $this->assertTrue($features['expenses'], 'expenses must be active via concurrent override');
        $this->assertTrue($features['multiple_prices'], 'multiple_prices must be active via concurrent override');

        // 3. All other premium features must remain strictly false
        $this->assertFalse($features['multi_caja']);
        $this->assertFalse($features['current_accounts']);
        $this->assertFalse($features['advanced_reports']);
        $this->assertFalse($features['predictive_alerts']);
        $this->assertFalse($features['checks']);
        $this->assertFalse($features['multi_rubro']);
        $this->assertFalse($features['mercadopago_qr']);
        $this->assertFalse($features['arca_afip']);

        // 4. Vertical and ungranted addons must remain false
        $this->assertFalse($features['quotes']);
        $this->assertFalse($features['logistics']);
        $this->assertFalse($features['mobile_app']);
        $this->assertFalse($features['remote_access']);
    }

    /**
     * Challenge 1b: Extreme Case - All 11 premium features injected as overrides into a Basic plan.
     * The license should activate all 11 features but retain plan='basic' / 'basico',
     * while hardware-exclusive features remain false.
     */
    public function test_adversarial_all_eleven_premium_overrides_on_basic(): void
    {
        $allPremium = [
            'multi_caja',
            'current_accounts',
            'advanced_reports',
            'predictive_alerts',
            'checks',
            'suppliers',
            'expenses',
            'multiple_prices',
            'multi_rubro',
            'mercadopago_qr',
            'arca_afip',
        ];

        $license = License::create([
            'client_name' => 'Adversarial Test - All Premium Overrides on Basic',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => $allPremium,
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'adv-device-all-premium-basic-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'plan' => 'basic',
                'plan_espanol' => 'basico',
            ]);

        $features = $response->json('features');

        // All 11 premium features must be true
        foreach ($allPremium as $feat) {
            $this->assertTrue($features[$feat], "Feature {$feat} must be true via override");
        }

        // Hardware-exclusive features must STILL be false for retail
        $this->assertFalse($features['quotes'], 'quotes must not leak to retail even with all premium addons');
        $this->assertFalse($features['logistics'], 'logistics must not leak to retail even with all premium addons');
    }

    /**
     * Challenge 2: Invalid or non-existent addon strings (e.g., ['fake_addon', 'invalid']).
     * Must not crash, must not inject invalid keys into features dict, must not alter known features.
     */
    public function test_adversarial_invalid_and_nonexistent_addon_strings(): void
    {
        $license = License::create([
            'client_name' => 'Adversarial Test - Hostile/Invalid Addons',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [
                'fake_addon',
                'invalid',
                'SUPER_ADMIN',
                'DROP TABLE licenses;',
                '',
                '__proto__',
                'constructor',
            ],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'adv-device-invalid-addons-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // 1. None of the invalid/hostile keys should exist in features
        $this->assertArrayNotHasKey('fake_addon', $features);
        $this->assertArrayNotHasKey('invalid', $features);
        $this->assertArrayNotHasKey('SUPER_ADMIN', $features);
        $this->assertArrayNotHasKey('DROP TABLE licenses;', $features);
        $this->assertArrayNotHasKey('', $features);
        $this->assertArrayNotHasKey('__proto__', $features);
        $this->assertArrayNotHasKey('constructor', $features);

        // 2. Exact key count must remain strictly 17
        $this->assertCount(17, $features, 'Feature dictionary must contain strictly 17 keys');

        // 3. Base features must remain intact
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);

        // 4. Premium features must remain false
        $this->assertFalse($features['suppliers']);
        $this->assertFalse($features['multi_caja']);
    }

    /**
     * Challenge 2b: Mixed valid and invalid addons.
     * Valid addons must activate; invalid addons must be safely ignored without dictionary pollution.
     */
    public function test_adversarial_mixed_valid_and_invalid_addons(): void
    {
        $license = License::create([
            'client_name' => 'Adversarial Test - Mixed Addons',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['suppliers', 'bogus_module', 'expenses', 'invalid_hack'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'adv-device-mixed-addons-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // Valid ones activated
        $this->assertTrue($features['suppliers']);
        $this->assertTrue($features['expenses']);

        // Invalid ones omitted
        $this->assertArrayNotHasKey('bogus_module', $features);
        $this->assertArrayNotHasKey('invalid_hack', $features);
        $this->assertCount(17, $features);
    }

    /**
     * Challenge 2c: Duplicates in allowed_addons array.
     */
    public function test_adversarial_duplicate_addons_array(): void
    {
        $license = License::create([
            'client_name' => 'Adversarial Test - Duplicate Addons',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => ['suppliers', 'suppliers', 'suppliers'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'adv-device-dup-addons-01',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        $this->assertTrue($features['suppliers']);
        $this->assertCount(17, $features);
    }

    /**
     * Challenge 3: Hardware store exclusive features (quotes, logistics) CANNOT be acquired by retail
     * under ANY circumstances.
     *
     * We stress-test:
     * - Retail Basic with quotes/logistics in allowed_addons
     * - Retail Premium with quotes/logistics in allowed_addons
     * - Retail Basic with ALL 17 features explicitly injected in allowed_addons
     * - Retail Premium with ALL 17 features explicitly injected in allowed_addons
     * - Arbitrary/unrecognized business_type (e.g., restaurant) with Basic + quotes/logistics
     * - Arbitrary/unrecognized business_type (e.g., services) with Premium + quotes/logistics
     */
    public function test_adversarial_retail_under_no_circumstances_can_acquire_hardware_features(): void
    {
        $matrix = [
            'Retail Basic + Hardware Addons' => [
                'business_type' => License::BUSINESS_RETAIL,
                'plan' => License::PLAN_BASICO,
                'allowed_addons' => ['quotes', 'logistics'],
            ],
            'Retail Premium + Hardware Addons' => [
                'business_type' => License::BUSINESS_RETAIL,
                'plan' => License::PLAN_PREMIUM,
                'allowed_addons' => ['quotes', 'logistics'],
            ],
            'Retail Basic + Full 17 Feature Injection' => [
                'business_type' => License::BUSINESS_RETAIL,
                'plan' => License::PLAN_BASICO,
                'allowed_addons' => self::EXPECTED_FEATURES,
            ],
            'Retail Premium + Full 17 Feature Injection' => [
                'business_type' => License::BUSINESS_RETAIL,
                'plan' => License::PLAN_PREMIUM,
                'allowed_addons' => self::EXPECTED_FEATURES,
            ],
            'Arbitrary Vertical Gastronomy Basic + Hardware Addons' => [
                'business_type' => 'restaurant_gastronomy',
                'plan' => License::PLAN_BASICO,
                'allowed_addons' => ['quotes', 'logistics'],
            ],
            'Arbitrary Vertical Services Premium + Hardware Addons' => [
                'business_type' => 'services_consulting',
                'plan' => License::PLAN_PREMIUM,
                'allowed_addons' => ['quotes', 'logistics'],
            ],
        ];

        foreach ($matrix as $scenarioName => $config) {
            $license = License::create([
                'client_name' => "Adversarial Test - {$scenarioName}",
                'business_type' => $config['business_type'],
                'plan' => $config['plan'],
                'plan_type' => License::TYPE_SAAS,
                'is_active' => true,
                'allowed_addons' => $config['allowed_addons'],
            ]);

            $response = $this->postJson('/api/validate', [
                'license_key' => $license->api_key,
                'installation_id' => 'adv-device-matrix-'.md5($scenarioName),
            ]);

            $response->assertStatus(200, "Scenario [{$scenarioName}] must return 200 OK");
            $features = $response->json('features');

            $this->assertFalse(
                $features['quotes'],
                "Scenario [{$scenarioName}]: 'quotes' MUST BE FALSE for non-hardware businesses under any circumstance!"
            );
            $this->assertFalse(
                $features['logistics'],
                "Scenario [{$scenarioName}]: 'logistics' MUST BE FALSE for non-hardware businesses under any circumstance!"
            );
        }
    }

    /**
     * Challenge 3b: In contrast, hardware_store MUST have quotes and logistics enabled natively,
     * both in Basic and Premium.
     */
    public function test_adversarial_hardware_store_consistently_receives_quotes_and_logistics(): void
    {
        // Hardware Basic
        $hwBasic = License::create([
            'client_name' => 'Ferretería Básico Nativo',
            'business_type' => License::BUSINESS_HARDWARE,
            'plan' => License::PLAN_BASICO,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $resBasic = $this->postJson('/api/validate', [
            'license_key' => $hwBasic->api_key,
            'installation_id' => 'adv-device-hw-basic-01',
        ]);

        $resBasic->assertStatus(200);
        $featuresBasic = $resBasic->json('features');
        $this->assertTrue($featuresBasic['quotes'], 'quotes must be true for hardware_store basic');
        $this->assertTrue($featuresBasic['logistics'], 'logistics must be true for hardware_store basic');

        // Hardware Premium
        $hwPremium = License::create([
            'client_name' => 'Ferretería Premium Nativo',
            'business_type' => License::BUSINESS_HARDWARE,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'allowed_addons' => [],
        ]);

        $resPremium = $this->postJson('/api/validate', [
            'license_key' => $hwPremium->api_key,
            'installation_id' => 'adv-device-hw-premium-01',
        ]);

        $resPremium->assertStatus(200);
        $featuresPremium = $resPremium->json('features');
        $this->assertTrue($featuresPremium['quotes'], 'quotes must be true for hardware_store premium');
        $this->assertTrue($featuresPremium['logistics'], 'logistics must be true for hardware_store premium');
    }

    /**
     * Challenge 4: Schema Invariance & Strict Typing
     * Every feature in the features dictionary must be strictly a boolean (not int, not string, not null).
     * Every response must have exactly the 17 predefined keys.
     */
    public function test_adversarial_feature_dictionary_schema_and_types(): void
    {
        $testCases = [
            'basic_empty' => ['plan' => 'basico', 'type' => 'retail', 'addons' => []],
            'premium_empty' => ['plan' => 'premium', 'type' => 'retail', 'addons' => []],
            'hardware_basic' => ['plan' => 'basico', 'type' => 'hardware_store', 'addons' => []],
            'hardware_premium' => ['plan' => 'premium', 'type' => 'hardware_store', 'addons' => []],
            'basic_with_addons' => ['plan' => 'basico', 'type' => 'retail', 'addons' => ['suppliers', 'expenses']],
        ];

        foreach ($testCases as $name => $data) {
            $license = License::create([
                'client_name' => "Schema Test {$name}",
                'business_type' => $data['type'],
                'plan' => $data['plan'],
                'plan_type' => License::TYPE_SAAS,
                'is_active' => true,
                'allowed_addons' => $data['addons'],
            ]);

            $response = $this->postJson('/api/validate', [
                'license_key' => $license->api_key,
                'installation_id' => 'adv-device-schema-'.$name,
            ]);

            $response->assertStatus(200);
            $features = $response->json('features');

            $this->assertIsArray($features);
            $this->assertCount(17, $features, "Test case {$name} did not contain exactly 17 feature keys");

            foreach (self::EXPECTED_FEATURES as $expectedKey) {
                $this->assertArrayHasKey($expectedKey, $features, "Missing key {$expectedKey} in {$name}");
                $this->assertIsBool($features[$expectedKey], "Feature {$expectedKey} in {$name} is not strictly boolean");
            }
        }
    }
}
