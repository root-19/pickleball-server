<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('pickle_events')->onDelete('cascade');
            $table->enum('status', ['waiting', 'confirmed', 'cancelled', 'timeout'])->default('waiting');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->boolean('first_payer')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_queues');
    }
};
