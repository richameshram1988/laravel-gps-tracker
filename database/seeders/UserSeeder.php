<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        foreach (['User A', 'User B', 'User C', 'User D'] as $i => $name) {
            User::create([
                'name' => $name,
                'email' => 'user' . ($i + 1) . '@example.com',
                'password' => 'password',
            ]);
        }
    }
}
