<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->enum('court_quality', ['standard', 'pro'])->nullable()->after('court_type');
            $table->boolean('has_tent')->default(false)->after('court_quality');
            $table->enum('venue_type', ['outdoor', 'indoor'])->nullable()->after('has_tent');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['court_quality', 'has_tent', 'venue_type']);
        });
    }
};
