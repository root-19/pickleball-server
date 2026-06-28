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
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['location', 'amenities', 'parking_slots', 'latitude', 'longitude', 'time_slots']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->string('location')->nullable()->after('name');
            $table->text('amenities')->nullable()->after('court_type');
            $table->integer('parking_slots')->nullable()->after('amenities');
            $table->decimal('latitude', 10, 7)->nullable()->after('parking_slots');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('time_slots')->nullable()->after('longitude');
        });
    }
};
