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
        Schema::table('vertical_trainings', function (Blueprint $table) {
            $table->string('script_title')->nullable()->after('status');
            $table->text('script_scenario')->nullable()->after('script_title');
            $table->text('script_body')->nullable()->after('script_scenario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vertical_trainings', function (Blueprint $table) {
            $table->dropColumn(['script_title', 'script_scenario', 'script_body']);
        });
    }
};
