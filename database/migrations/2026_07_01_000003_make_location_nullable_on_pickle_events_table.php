<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Raw SQL avoids requiring doctrine/dbal for a column change on Laravel 10.
        DB::statement('ALTER TABLE `pickle_events` MODIFY `location` VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `pickle_events` MODIFY `location` VARCHAR(255) NOT NULL');
    }
};
