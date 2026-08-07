<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Réinitialiser le mot de passe de tous les utilisateurs de test à "password123"
$newPassword = \Illuminate\Support\Facades\Hash::make('password123');

$users = \App\Models\User::all();
foreach ($users as $u) {
    $u->password = $newPassword;
    $u->save();
    echo "✅ Mot de passe réinitialisé pour: {$u->name} ({$u->email})\n";
}
echo "\nTous les mots de passe sont maintenant: password123\n";
