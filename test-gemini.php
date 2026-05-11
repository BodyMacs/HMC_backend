<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GeminiService;

try {
    echo "Testing Gemini...\n";
    $service = app(GeminiService::class);
    $res = $service->chat("Bonjour, qui es-tu ?");
    echo "Response: " . $res . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
