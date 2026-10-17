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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('type');
            $table->decimal('max_grade', 8, 2);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
            $table->index('class_id');
            $table->index('type');
            $table->index('published_at');
            $table->index('due_date');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
