<?php

use App\Models\License;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test License creation and API response logic
$license = License::create([
    'client_name' => 'Test Client',
    'plan'        => 'pro',
    'plan_type'   => 'saas',
    'expiration_date' => now()->addYear(),
]);

echo "Created License ID: {$license->id}\n";
echo "Plan: {$license->plan}\n";
echo "Plan Type: {$license->plan_type}\n";

$controller = new \App\Http\Controllers\Api\LicenseValidationController();
$request = new \Illuminate\Http\Request([
    'license_key' => $license->api_key,
    'installation_id' => 'test-id-123',
]);

$response = $controller->validateKey($request);
echo "Response JSON:\n";
echo json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";

// Cleanup
$license->delete();
