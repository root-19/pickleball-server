<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pickaball.com'],
            [
                'name'     => 'Picklepass',
                'email'    => 'admin@picklepass.com',
                'password' => Hash::make('Admin@123'),
                'role'     => 'admin',
            ]
        );
    }
}
