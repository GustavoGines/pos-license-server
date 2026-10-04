<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\License;
use App\Http\Controllers\Api\LicenseValidationController;
use App\Filament\Resources\Licenses\Schemas\LicenseForm;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;

echo "=== EMPIRICAL VERIFICATION SCRIPT ===\n\n";

// 1. Inspect LicenseForm options
echo "1. Checking LicenseForm allowed_addons options:\n";
// Reflection or instantiation
$reflection = new ReflectionClass(LicenseForm::class);
$source = file_get_contents($reflection->getFileName());
$hasMultiRubro = strpos($source, "'multi_rubro'") !== false;
$hasMercadopagoQr = strpos($source, "'mercadopago_qr'") !== false;
$hasArcaAfip = strpos($source, "'arca_afip'") !== false;
$hasSuppliers = strpos($source, "'suppliers'") !== false;

echo " - 'suppliers' in LicenseForm: " . ($hasSuppliers ? 'YES' : 'NO') . "\n";
echo " - 'multi_rubro' in LicenseForm: " . ($hasMultiRubro ? 'YES' : 'NO') . "\n";
echo " - 'mercadopago_qr' in LicenseForm: " . ($hasMercadopagoQr ? 'YES' : 'NO') . "\n";
echo " - 'arca_afip' in LicenseForm: " . ($hasArcaAfip ? 'YES' : 'NO') . "\n\n";

// 2. Test controller mapFeatures logic via reflection or controller instance
echo "2. Testing LicenseValidationController feature calculations:\n";
$controller = new LicenseValidationController();
$reflectionController = new ReflectionClass($controller);
$mapFeaturesMethod = $reflectionController->getMethod('mapFeatures');
$mapFeaturesMethod->setAccessible(true);

// Case A: Basic Retail, no addons
$licBasic = new License([
    'plan' => 'basico',
    'business_type' => 'retail',
    'allowed_addons' => [],
]);
$businessAddonsBasic = ['fast_pos', 'z_reports'];
$featuresBasic = $mapFeaturesMethod->invoke($controller, $businessAddonsBasic, $licBasic);

echo "Case A: Basic Plan (Retail, no addons):\n";
echo " - fast_pos: " . ($featuresBasic['fast_pos'] ? 'true' : 'false') . "\n";
echo " - z_reports: " . ($featuresBasic['z_reports'] ? 'true' : 'false') . "\n";
echo " - suppliers: " . ($featuresBasic['suppliers'] ? 'true' : 'false') . "\n";
echo " - multi_rubro: " . ($featuresBasic['multi_rubro'] ? 'true' : 'false') . "\n";
echo " - mercadopago_qr: " . ($featuresBasic['mercadopago_qr'] ? 'true' : 'false') . "\n";
echo " - arca_afip: " . ($featuresBasic['arca_afip'] ? 'true' : 'false') . "\n\n";

// Case B: Premium Retail, no addons
$licPremium = new License([
    'plan' => 'premium',
    'business_type' => 'retail',
    'allowed_addons' => [],
]);
$businessAddonsPremium = [
    'fast_pos', 'z_reports', 'multi_caja', 'current_accounts',
    'advanced_reports', 'predictive_alerts', 'checks', 'suppliers',
    'expenses', 'multi_rubro', 'mercadopago_qr', 'arca_afip', 'multiple_prices'
];
$featuresPremium = $mapFeaturesMethod->invoke($controller, $businessAddonsPremium, $licPremium);

echo "Case B: Premium Plan (Retail, no addons):\n";
echo " - fast_pos: " . ($featuresPremium['fast_pos'] ? 'true' : 'false') . "\n";
echo " - suppliers: " . ($featuresPremium['suppliers'] ? 'true' : 'false') . "\n";
echo " - multi_rubro: " . ($featuresPremium['multi_rubro'] ? 'true' : 'false') . "\n";
echo " - mercadopago_qr: " . ($featuresPremium['mercadopago_qr'] ? 'true' : 'false') . "\n";
echo " - arca_afip: " . ($featuresPremium['arca_afip'] ? 'true' : 'false') . "\n";
echo " - quotes (hardware only): " . ($featuresPremium['quotes'] ? 'true' : 'false') . "\n";
echo " - logistics (hardware only): " . ($featuresPremium['logistics'] ? 'true' : 'false') . "\n\n";

// Case C: Basic Plan with 'suppliers' override
$licOverride = new License([
    'plan' => 'basico',
    'business_type' => 'retail',
    'allowed_addons' => ['suppliers'],
]);
$addonsOverride = array_values(array_unique(array_merge($businessAddonsBasic, ['suppliers'])));
$featuresOverride = $mapFeaturesMethod->invoke($controller, $addonsOverride, $licOverride);

echo "Case C: Basic Plan with 'suppliers' override in allowed_addons:\n";
echo " - fast_pos: " . ($featuresOverride['fast_pos'] ? 'true' : 'false') . "\n";
echo " - suppliers: " . ($featuresOverride['suppliers'] ? 'true' : 'false') . "\n";
echo " - expenses: " . ($featuresOverride['expenses'] ? 'true' : 'false') . "\n";
echo " - multi_rubro: " . ($featuresOverride['multi_rubro'] ? 'true' : 'false') . "\n\n";

// Case D: Retail trying to override 'quotes'
$licRetailQuotes = new License([
    'plan' => 'basico',
    'business_type' => 'retail',
    'allowed_addons' => ['quotes'],
]);
$addonsRetailQuotes = array_values(array_unique(array_merge($businessAddonsBasic, ['quotes'])));
$featuresRetailQuotes = $mapFeaturesMethod->invoke($controller, $addonsRetailQuotes, $licRetailQuotes);

echo "Case D: Retail trying to override 'quotes' in allowed_addons:\n";
echo " - quotes: " . ($featuresRetailQuotes['quotes'] ? 'true' : 'false') . " (Expected false due to vertical restriction)\n\n";

// 3. Security Check: ReleaseController store token
echo "3. Security Analysis: ReleaseController store token check:\n";
$expectedToken = config('app.ci_deploy_token', env('CI_DEPLOY_TOKEN'));
echo " - config('app.ci_deploy_token'): " . var_export(config('app.ci_deploy_token'), true) . "\n";
echo " - env('CI_DEPLOY_TOKEN'): " . var_export(env('CI_DEPLOY_TOKEN'), true) . "\n";
echo " - Evaluated expectedToken: " . var_export($expectedToken, true) . "\n";
$simulatedInputToken = null; // attacker sends no token
$isAuthorized = ($simulatedInputToken === $expectedToken);
echo " - If expectedToken is null and request sends no token, is authorized? " . ($isAuthorized ? 'YES (VULNERABILITY!)' : 'NO') . "\n";

echo "\n=== END OF SCRIPT ===\n";
