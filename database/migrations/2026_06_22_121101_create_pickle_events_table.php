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
        Schema::create('pickle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // owner
            $table->string('title');
            $table->string('location');
            $table->string('event_image')->nullable();
            $table->date('event_date');
            $table->string('open_time');   // open play start
            $table->string('close_time');  // open play end
            $table->integer('max_players');
            $table->decimal('price_per_head', 10, 2);
            $table->text('rules')->nullable();
            $table->text('about')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickle_events');
    }
};
