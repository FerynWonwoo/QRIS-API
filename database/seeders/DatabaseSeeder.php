<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@qris.test'],
            [
                'name' => 'Admin QRIS',
                'password' => 'rahasia123',
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@qris.test'],
            [
                'name' => 'Feryn User',
                'password' => 'rahasia123',
                'role' => 'user',
            ]
        );
    }
}