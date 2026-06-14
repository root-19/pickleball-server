<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_play_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('court_id')->constrained('courts')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('time_slot_start');
            $table->time('time_slot_end');
            $table->enum('status', ['waiting', 'matched', 'completed', 'cancelled', 'timeout'])->default('waiting');
            $table->foreignId('matched_with')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('slot_opener')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index(['court_id', 'booking_date', 'time_slot_start']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_play_queues');
    }
};
