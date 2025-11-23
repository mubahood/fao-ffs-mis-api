<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
echo "Testing authentication...\n";
echo "Phone: " . $user->phone_number . "\n";

// Try JWT auth with phone_number
config(['auth.providers.users.field' => 'phone_number']);
$token = auth('api')->attempt([
    'phone_number' => $user->phone_number,
    'password' => 'admin123',
]);

if ($token) {
    echo "TOKEN: " . $token . "\n";
} else {
    echo "Auth failed\n";
}
