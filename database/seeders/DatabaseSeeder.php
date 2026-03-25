<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea el usuario administrador inicial si no existe.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@posserver.com');

        if (!User::where('email', $email)->exists()) {
            User::create([
                'name'     => env('ADMIN_NAME', 'Administrador'),
                'email'    => $email,
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin1234!')),
            ]);
        }
    }
}
