<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Court;
use App\Models\OpenPlayQueue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OpenPlaySystemSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update owner account
        $owner = User::updateOrCreate(
            ['email' => 'owner@gmail.com'],
            [
                'name' => 'Court Owner',
                'email' => 'owner@gmail.com',
                'phone' => '09171234567',
                'password' => Hash::make('Test12345'),
                'role' => 'owner',
            ]
        );

        $this->command->info('Owner account created/updated: owner@gmail.com / Test12345');

        // Create an open play court for the owner
        $court = Court::updateOrCreate(
            ['user_id' => $owner->id, 'name' => 'Open Play Court 1'],
            [
                'user_id' => $owner->id,
                'name' => 'Open Play Court 1',
                'location' => 'Manila, Philippines',
                'price_per_hour' => 500.00,
                'time_slots' => json_encode([
                    ['start' => '09:00', 'end' => '10:00'],
                    ['start' => '10:00', 'end' => '11:00'],
                    ['start' => '14:00', 'end' => '15:00'],
                    ['start' => '15:00', 'end' => '16:00'],
                ]),
                'is_active' => true,
            ]
        );

        $this->command->info('Open play court created: Open Play Court 1');

        // Create test users for open play
        $testUsers = [];
        for ($i = 1; $i <= 10; $i++) {
            $user = User::updateOrCreate(
                ['email' => "player{$i}@test.com"],
                [
                    'name' => "Player {$i}",
                    'email' => "player{$i}@test.com",
                    'phone' => '0917' . str_pad((string)($i + 123456), 7, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password123'),
                    'role' => 'user',
                ]
            );
            $testUsers[] = $user;
        }

        $this->command->info('10 test users created/updated (player1@test.com to player10@test.com)');

        // Create open play queue entries
        $today = Carbon::today()->toDateString();

        // Matched pair (9:00-10:00)
        $queue1 = OpenPlayQueue::create([
            'user_id' => $testUsers[0]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '09:00',
            'time_slot_end' => '10:00',
            'status' => 'matched',
            'payment_status' => 'paid',
            'payment_deadline' => Carbon::now()->subMinutes(5),
            'paid_at' => Carbon::now()->subMinutes(5),
            'matched_with' => $testUsers[1]->id,
            'slot_opener' => true,
            'matched_at' => Carbon::now()->subMinutes(4),
        ]);

        $queue2 = OpenPlayQueue::create([
            'user_id' => $testUsers[1]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '09:00',
            'time_slot_end' => '10:00',
            'status' => 'matched',
            'payment_status' => 'paid',
            'payment_deadline' => Carbon::now()->subMinutes(4),
            'paid_at' => Carbon::now()->subMinutes(4),
            'matched_with' => $testUsers[0]->id,
            'slot_opener' => false,
            'matched_at' => Carbon::now()->subMinutes(4),
        ]);

        // Waiting players (10:00-11:00) - pending payment
        $queue3 = OpenPlayQueue::create([
            'user_id' => $testUsers[2]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '10:00',
            'time_slot_end' => '11:00',
            'status' => 'waiting',
            'payment_status' => 'pending',
            'payment_deadline' => Carbon::now()->addSeconds(30),
        ]);

        $queue4 = OpenPlayQueue::create([
            'user_id' => $testUsers[3]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '10:00',
            'time_slot_end' => '11:00',
            'status' => 'waiting',
            'payment_status' => 'pending',
            'payment_deadline' => Carbon::now()->addSeconds(30),
        ]);

        // Another waiting pair (14:00-15:00)
        $queue5 = OpenPlayQueue::create([
            'user_id' => $testUsers[4]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '14:00',
            'time_slot_end' => '15:00',
            'status' => 'waiting',
            'payment_status' => 'pending',
            'payment_deadline' => Carbon::now()->addSeconds(30),
        ]);

        $queue6 = OpenPlayQueue::create([
            'user_id' => $testUsers[5]->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => '14:00',
            'time_slot_end' => '15:00',
            'status' => 'waiting',
            'payment_status' => 'pending',
            'payment_deadline' => Carbon::now()->addSeconds(30),
        ]);

        $this->command->info('Open play queue seeded:');
        $this->command->info('- 2 matched players (9:00-10:00)');
        $this->command->info('- 4 waiting players in 2 time slots (10:00-11:00, 14:00-15:00)');
        $this->command->info('');
        $this->command->info('Open play system seeded successfully!');
    }
}
