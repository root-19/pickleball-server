<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Player One', 'email' => 'player1@test.com', 'phone' => '09171234567'],
            ['name' => 'Player Two', 'email' => 'player2@test.com', 'phone' => '09171234568'],
            ['name' => 'Player Three', 'email' => 'player3@test.com', 'phone' => '09171234569'],
            ['name' => 'Player Four', 'email' => 'player4@test.com', 'phone' => '09171234570'],
            ['name' => 'Player Five', 'email' => 'player5@test.com', 'phone' => '09171234571'],
            ['name' => 'Player Six', 'email' => 'player6@test.com', 'phone' => '09171234572'],
            ['name' => 'Player Seven', 'email' => 'player7@test.com', 'phone' => '09171234573'],
            ['name' => 'Player Eight', 'email' => 'player8@test.com', 'phone' => '09171234574'],
            ['name' => 'Player Nine', 'email' => 'player9@test.com', 'phone' => '09171234575'],
            ['name' => 'Player Ten', 'email' => 'player10@test.com', 'phone' => '09171234576'],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]);
        }
    }
}
