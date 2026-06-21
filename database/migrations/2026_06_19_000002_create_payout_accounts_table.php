<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['gcash', 'maya', 'card']);
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('card_holder')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_expiry', 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
