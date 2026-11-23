<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anecdotes', function (Blueprint $table) {
            $table->uuid('id')->primary(); // generated on the phone; makes sync idempotent
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->string('location', 120)->nullable();
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->string('category_override', 24)->nullable();
            $table->string('severity_override', 8)->nullable();
            $table->string('status', 8)->default('open'); // open | resolved
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('device_created_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['student_id', 'occurred_at']);
        });

        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('anecdote_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('behavior_flag_id')->nullable(); // FK added with behavior_flags (M4)
            $table->string('kind', 16); // classification | summary
            $table->string('provider', 20);
            $table->string('model', 100);
            $table->string('outcome', 16); // ok | fallback_used | failed
            $table->string('category', 24)->nullable();
            $table->string('severity', 8)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->text('output')->nullable();
            $table->json('raw_response')->nullable();
            $table->char('input_sha256', 64);
            $table->unsignedInteger('latency_ms');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['anecdote_id', 'created_at']);
        });

        // Similar-wording detection (M4) uses trigram similarity; PostgreSQL only.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX anecdotes_description_trgm ON anecdotes USING gin (description gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
        Schema::dropIfExists('anecdotes');
    }
};
