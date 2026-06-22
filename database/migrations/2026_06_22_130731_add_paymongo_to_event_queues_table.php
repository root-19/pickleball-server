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
        Schema::table('event_queues', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('paymongo_source_id')->nullable()->after('payment_method');
            $table->string('paymongo_payment_id')->nullable()->after('paymongo_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_queues', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paymongo_source_id', 'paymongo_payment_id']);
        });
    }
};
