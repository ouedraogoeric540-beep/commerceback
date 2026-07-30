$user = \App\Models\User::firstOrCreate(
    ['email' => 'acheteur@test.com'],
    [
        'name' => 'Acheteur Test',
        'password' => \Illuminate\Support\Facades\Hash::make('password123')
    ]
);

$role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Acheteur']);
$user->assignRole($role);

echo "Compte créé avec succes.\n";
