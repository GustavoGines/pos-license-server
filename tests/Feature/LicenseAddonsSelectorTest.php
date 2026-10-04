<?php

namespace Tests\Feature;

use App\Filament\Resources\Licenses\Pages\CreateLicense;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Valida que un plan Básico pueda recibir módulos premium individuales
 * (multi_rubro, mercadopago_qr, arca_afip, suppliers) desde el panel de Filament
 * y que la API /api/validate emita los flags correspondientes.
 */
class LicenseAddonsSelectorTest extends TestCase
{
    use RefreshDatabase;

    private const PHASE_FLAGS = ['multi_rubro', 'mercadopago_qr', 'arca_afip'];

    /** Flags emitidos por LicenseValidationController::mapFeatures. */
    private const ALL_PREMIUM_ADDONS = [
        'multi_caja', 'current_accounts', 'advanced_reports', 'predictive_alerts', 'checks',
        'suppliers', 'expenses', 'multi_rubro', 'mercadopago_qr', 'arca_afip', 'multiple_prices',
    ];

    private function validate(License $license, string $device = 'device-addons-001')
    {
        return $this->postJson('/api/validate', [
            'license_key'     => $license->api_key,
            'installation_id' => $device,
        ]);
    }

    private function makeBasic(array $addons): License
    {
        return License::create([
            'client_name'    => 'Comercio Básico con Extras',
            'business_type'  => License::BUSINESS_RETAIL,
            'plan'           => License::PLAN_BASICO,
            'plan_type'      => License::TYPE_LIFETIME,
            'is_active'      => true,
            'allowed_addons' => $addons,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function phaseFlagProvider(): array
    {
        return [
            'multi_rubro'    => ['multi_rubro'],
            'mercadopago_qr' => ['mercadopago_qr'],
            'arca_afip'      => ['arca_afip'],
            'suppliers'      => ['suppliers'],
        ];
    }

    /**
     * @dataProvider phaseFlagProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phaseFlagProvider')]
    public function test_basic_plan_with_single_extra_addon_only_enables_that_flag(string $flag): void
    {
        $license = $this->makeBasic([$flag]);

        $response = $this->validate($license)->assertStatus(200);
        $features = $response->json('features');

        $this->assertTrue($features[$flag], "{$flag} debe estar habilitado como override");
        $this->assertTrue($features['fast_pos']);
        $this->assertTrue($features['z_reports']);

        foreach (self::ALL_PREMIUM_ADDONS as $other) {
            if ($other === $flag) {
                continue;
            }
            $this->assertFalse($features[$other], "{$other} debe seguir apagado en plan Básico");
        }
    }

    public function test_basic_plan_without_addons_keeps_phase_flags_off(): void
    {
        $features = $this->validate($this->makeBasic([]))->assertStatus(200)->json('features');

        foreach (self::PHASE_FLAGS as $flag) {
            $this->assertFalse($features[$flag], "{$flag} debe estar apagado en Básico sin extras");
        }
    }

    public function test_premium_plan_enables_all_phase_flags_without_addons(): void
    {
        $license = License::create([
            'client_name'    => 'Comercio Premium',
            'business_type'  => License::BUSINESS_RETAIL,
            'plan'           => License::PLAN_PREMIUM,
            'plan_type'      => License::TYPE_LIFETIME,
            'is_active'      => true,
            'allowed_addons' => [],
        ]);

        $features = $this->validate($license)->assertStatus(200)->json('features');

        foreach (self::PHASE_FLAGS as $flag) {
            $this->assertTrue($features[$flag], "{$flag} debe estar activo en Premium");
        }
    }

    public function test_extra_addons_survive_plan_upgrade_and_downgrade(): void
    {
        $license = $this->makeBasic(['multi_rubro']);

        $license->update(['plan' => License::PLAN_PREMIUM]);
        $this->assertTrue($this->validate($license->fresh())->json('features.multi_rubro'));

        $license->update(['plan' => License::PLAN_BASICO]);
        $features = $this->validate($license->fresh())->json('features');

        $this->assertTrue($features['multi_rubro'], 'El extra pagado debe mantenerse al volver a Básico');
        $this->assertFalse($features['mercadopago_qr']);
        $this->assertFalse($features['arca_afip']);
    }

    public function test_filament_form_allows_saving_basic_license_with_phase_flags(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateLicense::class)
            ->fillForm([
                'client_name'     => 'Cliente Filament Básico',
                'business_type'   => 'retail',
                'plan'            => 'basico',
                'plan_type'       => 'saas',
                'expiration_date' => now()->addMonth()->toDateString(),
                'allowed_addons'  => ['multi_rubro', 'mercadopago_qr', 'arca_afip', 'suppliers'],
                'is_active'       => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $license = License::where('client_name', 'Cliente Filament Básico')->firstOrFail();

        $this->assertSame('basico', $license->plan);
        $this->assertEqualsCanonicalizing(
            ['multi_rubro', 'mercadopago_qr', 'arca_afip', 'suppliers'],
            $license->allowed_addons
        );

        $features = $this->validate($license, 'device-filament-001')->assertStatus(200)->json('features');
        $this->assertTrue($features['multi_rubro']);
        $this->assertTrue($features['mercadopago_qr']);
        $this->assertTrue($features['arca_afip']);
        $this->assertTrue($features['suppliers']);
        $this->assertFalse($features['multi_caja'], 'multi_caja no fue seleccionado y debe seguir apagado');
    }

    public function test_filament_form_rejects_unknown_addon_keys(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateLicense::class)
            ->fillForm([
                'client_name'     => 'Cliente Clave Inválida',
                'business_type'   => 'retail',
                'plan'            => 'basico',
                'plan_type'       => 'saas',
                'expiration_date' => now()->addMonth()->toDateString(),
                'allowed_addons'  => ['proveedores'], // clave inexistente: la correcta es "suppliers"
            ])
            ->call('create')
            ->assertHasFormErrors(['allowed_addons.0']);
    }

    /**
     * Guardia de consistencia: todo flag que la API pueda emitir como override
     * (excepto los exclusivos de vertical) debe ser seleccionable en el panel.
     */
    public function test_every_api_feature_flag_is_selectable_in_filament_selector(): void
    {
        $this->actingAs(User::factory()->create());

        $selectable = [
            'fast_pos', 'z_reports', 'quotes', 'current_accounts', 'multiple_prices', 'multi_caja',
            'mobile_app', 'remote_access', 'advanced_reports', 'predictive_alerts', 'logistics',
            'checks', 'suppliers', 'expenses', 'multi_rubro', 'mercadopago_qr', 'arca_afip',
        ];

        Livewire::test(CreateLicense::class)
            ->fillForm([
                'client_name'     => 'Cliente Todos los Flags',
                'business_type'   => 'hardware_store',
                'plan'            => 'basico',
                'plan_type'       => 'saas',
                'expiration_date' => now()->addMonth()->toDateString(),
                'allowed_addons'  => $selectable,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
