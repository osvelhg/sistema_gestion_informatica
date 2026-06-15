<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Administrador',
                'email'    => 'admin@sgi.cu',
                'password' => 'Micasa5246..',
                'role'     => 'Administrador',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $userData['password'] = Hash::make($userData['password']);
            $userData['active'] = true;
            $userData['email_verified_at'] = now();

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            $user->assignRole($role);
        }
    }
}
