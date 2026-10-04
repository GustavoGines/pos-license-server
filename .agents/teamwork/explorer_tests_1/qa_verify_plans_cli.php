<?php
/**
 * Standalone Empirical QA Verification Script for pos-license-server
 * Can be run via: php .agents/teamwork/explorer_tests_1/qa_verify_plans_cli.php
 */

require __DIR__ . '/../../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\License;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\LicenseValidationController;

echo "=====================================================\n";
echo " QA EMPIRICAL VERIFICATION: pos-license-server\n";
echo "=====================================================\n\n";

// Configure SQLite in-memory for zero side-effects
Config::set('database.default', 'sqlite');
Config::set('database.connections.sqlite.database', ':memory:');
DB::purge('sqlite');
DB::reconnect('sqlite');

echo "[1/4] Running database migrations on SQLite in-memory...\n";
Artisan::call('migrate', ['--force' => true]);
echo "      Migrations executed successfully.\n\n";

$controller = new LicenseValidationController();

// CASE 1: Standard Basic Plan
echo "[2/4] Testing Case 1: Standard Basic Plan (Retail)...\n";
$basic = License::create([
    'client_name'   => 'Empresa Basica Test',
    'business_type' => 'retail',
    'plan'          => 'basico',
    'plan_type'     => 'saas',
    'is_active'     => true,
    'allowed_addons'=> [],
]);

$req1 = Request::create('/api/validate', 'POST', [
    'license_key'     => $basic->api_key,
    'installation_id' => 'cli-inst-basic',
]);

$res1 = $controller->validateKey($req1);
$data1 = $res1->getData(true);
$features1 = $data1['features'];

assert($features1['fast_pos'] === true, 'fast_pos must be true');
assert($features1['z_reports'] === true, 'z_reports must be true');
assert($features1['multi_rubro'] === false, 'multi_rubro must be false');
assert($features1['mercadopago_qr'] === false, 'mercadopago_qr must be false');
assert($features1['arca_afip'] === false, 'arca_afip must be false');
assert($features1['suppliers'] === false, 'suppliers must be false');
echo "      PASS: Standard Basic plan verified correctly.\n\n";

// CASE 2: Standard Premium Plan
echo "[3/4] Testing Case 2: Standard Premium Plan (Retail)...\n";
$premium = License::create([
    'client_name'   => 'Empresa Premium Test',
    'business_type' => 'retail',
    'plan'          => 'premium',
    'plan_type'     => 'saas',
    'is_active'     => true,
    'allowed_addons'=> [],
]);

$req2 = Request::create('/api/validate', 'POST', [
    'license_key'     => $premium->api_key,
    'installation_id' => 'cli-inst-premium',
]);

$res2 = $controller->validateKey($req2);
$data2 = $res2->getData(true);
$features2 = $data2['features'];

assert($features2['fast_pos'] === true, 'fast_pos must be true');
assert($features2['multi_caja'] === true, 'multi_caja must be true');
assert($features2['multi_rubro'] === true, 'multi_rubro must be true');
assert($features2['mercadopago_qr'] === true, 'mercadopago_qr must be true');
assert($features2['arca_afip'] === true, 'arca_afip must be true');
assert($features2['suppliers'] === true, 'suppliers must be true');
assert($features2['quotes'] === false, 'quotes must be false for retail');
echo "      PASS: Standard Premium plan verified correctly.\n\n";

// CASE 3: Basic Plan with Override (Suppliers / Proveedores)
echo "[4/4] Testing Case 3: Basic Plan with Manual Override (suppliers)...\n";
$override = License::create([
    'client_name'   => 'Empresa Override Test',
    'business_type' => 'retail',
    'plan'          => 'basico',
    'plan_type'     => 'saas',
    'is_active'     => true,
    'allowed_addons'=> ['suppliers'],
]);

$req3 = Request::create('/api/validate', 'POST', [
    'license_key'     => $override->api_key,
    'installation_id' => 'cli-inst-override',
]);

$res3 = $controller->validateKey($req3);
$data3 = $res3->getData(true);
$features3 = $data3['features'];

assert($features3['fast_pos'] === true, 'fast_pos must be true');
assert($features3['suppliers'] === true, 'suppliers override must be true');
assert($features3['multi_rubro'] === false, 'multi_rubro must remain false');
assert($features3['arca_afip'] === false, 'arca_afip must remain false');
echo "      PASS: Basic plan with manual override (suppliers) verified correctly.\n\n";

echo "=====================================================\n";
echo " ALL EMPIRICAL VERIFICATIONS PASSED SUCCESSFULLY (0 FAILURES)\n";
echo "=====================================================\n";
