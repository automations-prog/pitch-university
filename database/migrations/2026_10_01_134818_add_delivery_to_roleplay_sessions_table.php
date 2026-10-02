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
        Schema::table('roleplay_sessions', function (Blueprint $table) {
            $table->json('events')->nullable()->after('transcript');
            $table->json('delivery')->nullable()->after('score');
            $table->string('delivery_status')->nullable()->after('delivery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roleplay_sessions', function (Blueprint $table) {
            $table->dropColumn(['events', 'delivery', 'delivery_status']);
        });
    }
};
