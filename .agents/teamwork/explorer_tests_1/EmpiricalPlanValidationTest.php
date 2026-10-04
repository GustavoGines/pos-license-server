<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\License;

class EmpiricalPlanValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_basic_plan_flags(): void
    {
        $license = License::create([
            'client_name'   => 'Cliente Basico',
            'business_type' => 'retail',
            'plan'          => 'basico',
            'plan_type'     => 'saas',
            'is_active'     => true,
            'allowed_addons'=> [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key'     => $license->api_key,
            'installation_id' => 'inst-uuid-basic-1',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // Assert base features are true
        $this->assertTrue($features['fast_pos'], 'fast_pos should be true in basic');
        $this->assertTrue($features['z_reports'], 'z_reports should be true in basic');

        // Assert premium features are false
        $this->assertFalse($features['multi_caja'], 'multi_caja should be false in basic');
        $this->assertFalse($features['current_accounts'], 'current_accounts should be false in basic');
        $this->assertFalse($features['advanced_reports'], 'advanced_reports should be false in basic');
        $this->assertFalse($features['suppliers'], 'suppliers should be false in basic');
        $this->assertFalse($features['expenses'], 'expenses should be false in basic');
        $this->assertFalse($features['multi_rubro'], 'multi_rubro should be false in basic');
        $this->assertFalse($features['mercadopago_qr'], 'mercadopago_qr should be false in basic');
        $this->assertFalse($features['arca_afip'], 'arca_afip should be false in basic');
    }

    public function test_standard_premium_plan_flags(): void
    {
        $license = License::create([
            'client_name'   => 'Cliente Premium',
            'business_type' => 'retail',
            'plan'          => 'premium',
            'plan_type'     => 'saas',
            'is_active'     => true,
            'allowed_addons'=> [],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key'     => $license->api_key,
            'installation_id' => 'inst-uuid-premium-1',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // Assert base and premium features are true
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);
        $this->assertTrue($features['multi_caja']);
        $this->assertTrue($features['current_accounts']);
        $this->assertTrue($features['advanced_reports']);
        $this->assertTrue($features['suppliers']);
        $this->assertTrue($features['expenses']);
        $this->assertTrue($features['multiple_prices']);
        $this->assertTrue($features['multi_rubro']);
        $this->assertTrue($features['mercadopago_qr']);
        $this->assertTrue($features['arca_afip']);

        // Retail vertical exclusion: quotes and logistics are false for retail
        $this->assertFalse($features['quotes']);
        $this->assertFalse($features['logistics']);
    }

    public function test_basic_plan_with_manual_override_suppliers(): void
    {
        $license = License::create([
            'client_name'   => 'Cliente Basico Con Proveedores',
            'business_type' => 'retail',
            'plan'          => 'basico',
            'plan_type'     => 'saas',
            'is_active'     => true,
            'allowed_addons'=> ['suppliers'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key'     => $license->api_key,
            'installation_id' => 'inst-uuid-basic-override-1',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // Base features
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);

        // Overridden feature MUST be true
        $this->assertTrue($features['suppliers'], 'suppliers MUST be true when manually assigned to basic plan');

        // Other premium features remain false
        $this->assertFalse($features['multi_caja']);
        $this->assertFalse($features['advanced_reports']);
        $this->assertFalse($features['multi_rubro']);
        $this->assertFalse($features['mercadopago_qr']);
        $this->assertFalse($features['arca_afip']);
    }

    public function test_discrepancy_check_spanish_key_proveedores(): void
    {
        $license = License::create([
            'client_name'   => 'Cliente Con Key Espanol',
            'business_type' => 'retail',
            'plan'          => 'basico',
            'plan_type'     => 'saas',
            'is_active'     => true,
            'allowed_addons'=> ['proveedores'],
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key'     => $license->api_key,
            'installation_id' => 'inst-uuid-basic-spanish-1',
        ]);

        $response->assertStatus(200);
        $features = $response->json('features');

        // Notice: In the current codebase, the key is 'suppliers', NOT 'proveedores'!
        // When 'proveedores' is stored in allowed_addons, features['suppliers'] is FALSE,
        // and 'proveedores' is not even present in features array!
        $this->assertFalse($features['suppliers'] ?? false);
        $this->assertArrayNotHasKey('proveedores', $features);
    }
}
