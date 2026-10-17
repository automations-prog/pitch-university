<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The bundled fronting tracks open their vertical's
     * roleplay; other tracks open none.
     */
    public function up(): void
    {
        Schema::table('course_tracks', function (Blueprint $table) {
            $table->string('vertical')->nullable()->after('slug');
        });

        foreach (['medicare', 'aca', 'meals'] as $vertical) {
            DB::table('course_tracks')->where('slug', "{$vertical}-fronting")->update(['vertical' => $vertical]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_tracks', function (Blueprint $table) {
            $table->dropColumn('vertical');
        });
    }
};
