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
        Schema::table('users', function (Blueprint $table) {
            $table->string('parking_slots', 10)->nullable()->after('company_location');
            $table->string('opening_time', 20)->nullable()->after('parking_slots');
            $table->string('closing_time', 20)->nullable()->after('opening_time');
            $table->text('amenities')->nullable()->after('closing_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['parking_slots', 'opening_time', 'closing_time', 'amenities']);
        });
    }
};
