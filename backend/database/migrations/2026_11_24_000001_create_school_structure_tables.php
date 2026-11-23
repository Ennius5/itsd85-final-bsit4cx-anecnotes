<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 9)->unique(); // 2026-2027
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('grade_level');
            $table->string('name', 60);
            $table->unsignedSmallInteger('seat_rows')->default(6);
            $table->unsignedSmallInteger('seat_cols')->default(7);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_year_id', 'grade_level', 'name']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->char('lrn', 12)->unique();
            $table->string('last_name', 80);
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->char('sex', 1);
            $table->date('birthdate');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_row')->nullable();
            $table->unsignedSmallInteger('seat_col')->nullable();
            $table->string('status', 12)->default('active'); // active | transferred | dropped
            $table->date('enrolled_on');
            $table->timestamps();
            $table->unique(['section_id', 'student_id']);
            $table->unique(['section_id', 'seat_row', 'seat_col']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_years');
    }
};
