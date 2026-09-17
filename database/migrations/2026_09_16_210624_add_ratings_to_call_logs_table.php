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
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('clarity')->nullable();
            $table->string('energy_tone')->nullable();
            $table->string('composure_on_pushback')->nullable();
            $table->string('overall_gut_check')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['clarity', 'energy_tone', 'composure_on_pushback', 'overall_gut_check']);
        });
    }
};
