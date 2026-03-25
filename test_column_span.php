<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Filament\Forms\Components\TextInput;

try {
    $input = TextInput::make('test')->columnSpan(2);
    echo "Success!";
} catch (\TypeError $e) {
    echo "TypeError: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage();
}
