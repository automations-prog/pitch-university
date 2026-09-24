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
        Schema::create('course_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_exam_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->json('question_ids');
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('score_pct')->nullable();
            $table->json('tag_breakdown')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_exam_id', 'section']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_exam_attempts');
    }
};
