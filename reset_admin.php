<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
$user->password = password_hash('admin123', PASSWORD_DEFAULT);
$user->save();

echo "Admin password reset to: admin123\n";
echo "Username: " . $user->phone_number . "\n";
echo "Email: " . $user->email . "\n";
