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
            $table->foreignId('course_module_id')->nullable()->after('vertical')->constrained()->nullOnDelete();

            $table->index(['course_module_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roleplay_sessions', function (Blueprint $table) {
            $table->dropForeign(['course_module_id']);
            $table->dropIndex(['course_module_id', 'user_id']);
            $table->dropColumn('course_module_id');
        });
    }
};
