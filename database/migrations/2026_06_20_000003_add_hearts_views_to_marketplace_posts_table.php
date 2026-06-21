<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('views')->default(0)->after('video');
            $table->unsignedBigInteger('hearts')->default(0)->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_posts', function (Blueprint $table) {
            $table->dropColumn(['views', 'hearts']);
        });
    }
};
