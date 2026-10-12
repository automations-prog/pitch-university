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
            $table->string('vertical')->default('medicare')->after('user_id');

            $table->index(['user_id', 'vertical', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roleplay_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'vertical', 'created_at']);
            $table->dropColumn('vertical');
        });
    }
};
