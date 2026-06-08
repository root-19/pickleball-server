<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['date', 'start_time', 'end_time', 'notes']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('booking_date')->after('court_id');
            $table->string('time_slot_start')->after('booking_date');
            $table->string('time_slot_end')->after('time_slot_start');
            $table->unsignedTinyInteger('duration_hours')->default(1)->after('time_slot_end');
            $table->decimal('total_price', 10, 2)->default(0)->after('duration_hours');
            $table->string('booking_code')->unique()->after('total_price');
            $table->string('status')->default('confirmed')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
