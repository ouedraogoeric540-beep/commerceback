<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Réinitialiser le cache des rôles et permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Création des Rôles
        $roles = [
            'Visiteur',
            'Acheteur',
            'Vendeur',
            'Administrateur',
            'Super-Administrateur'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // 2. Création d'un compte de test pour CHAQUE rôle (sauf Visiteur qui n'a pas de compte)
        $testUsers = [
            'Acheteur' => 'acheteur@commerce.com',
            'Vendeur' => 'vendeur@commerce.com',
            'Administrateur' => 'admin2@commerce.com', // admin standard
            'Super-Administrateur' => 'admin@commerce.com'
        ];

        foreach ($testUsers as $roleName => $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Test ' . $roleName,
                    'password' => Hash::make('password123'),
                ]
            );

            // Assigner le rôle s'il ne l'a pas déjà
            if (!$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
    }
}

