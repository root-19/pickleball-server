<?php

namespace Database\Seeders;

use App\Models\OpenPlayQueue;
use App\Models\User;
use App\Models\Court;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class OpenPlayQueueSeeder extends Seeder
{
    public function run(): void
    {
        $testUsers = User::where('email', 'like', 'player%@test.com')->get();
        $court = Court::first();

        if (!$court) {
            $this->command->warn('No court found. Skipping queue seeder.');
            return;
        }

        if ($testUsers->count() < 2) {
            $this->command->warn('Not enough test users. Skipping queue seeder.');
            return;
        }

        $today = Carbon::today()->toDateString();
        $timeSlotStart = '09:00';
        $timeSlotEnd = '10:00';

        // Create 2 paid players (should auto-match)
        $user1 = $testUsers[0];
        $user2 = $testUsers[1];

        $queue1 = OpenPlayQueue::create([
            'user_id' => $user1->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => $timeSlotStart,
            'time_slot_end' => $timeSlotEnd,
            'status' => 'matched',
            'payment_status' => 'paid',
            'payment_deadline' => Carbon::now()->subMinutes(5),
            'paid_at' => Carbon::now()->subMinutes(5),
            'matched_with' => $user2->id,
            'slot_opener' => true,
            'matched_at' => Carbon::now()->subMinutes(4),
        ]);

        $queue2 = OpenPlayQueue::create([
            'user_id' => $user2->id,
            'court_id' => $court->id,
            'booking_date' => $today,
            'time_slot_start' => $timeSlotStart,
            'time_slot_end' => $timeSlotEnd,
            'status' => 'matched',
            'payment_status' => 'paid',
            'payment_deadline' => Carbon::now()->subMinutes(4),
            'paid_at' => Carbon::now()->subMinutes(4),
            'matched_with' => $user1->id,
            'slot_opener' => false,
            'matched_at' => Carbon::now()->subMinutes(4),
        ]);

        // Create 2 waiting players (pending payment)
        if ($testUsers->count() >= 4) {
            $user3 = $testUsers[2];
            $user4 = $testUsers[3];

            OpenPlayQueue::create([
                'user_id' => $user3->id,
                'court_id' => $court->id,
                'booking_date' => $today,
                'time_slot_start' => '10:00',
                'time_slot_end' => '11:00',
                'status' => 'waiting',
                'payment_status' => 'pending',
                'payment_deadline' => Carbon::now()->addSeconds(30),
            ]);

            OpenPlayQueue::create([
                'user_id' => $user4->id,
                'court_id' => $court->id,
                'booking_date' => $today,
                'time_slot_start' => '10:00',
                'time_slot_end' => '11:00',
                'status' => 'waiting',
                'payment_status' => 'pending',
                'payment_deadline' => Carbon::now()->addSeconds(30),
            ]);
        }

        $this->command->info('Open play queue seeded successfully.');
    }
}
