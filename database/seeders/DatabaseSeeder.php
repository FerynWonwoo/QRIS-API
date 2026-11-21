<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin QRIS',
            'email'    => 'admin@qris.test',
            'password' => 'rahasia123',
            'role'     => 'admin',
        ]);

        User::create([
            'name'     => 'Feryn User',
            'email'    => 'user@qris.test',
            'password' => 'rahasia123',
            'role'     => 'user',
        ]);
    }
}