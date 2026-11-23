<?php

use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** A current school year with one section advised by $adviser and one enrolled student. */
function classroom(?User $adviser = null): array
{
    $adviser ??= User::factory()->create();
    $year = SchoolYear::create(['name' => '2026-2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-02', 'is_current' => true]);
    $section = Section::create(['school_year_id' => $year->id, 'adviser_id' => $adviser->id, 'grade_level' => 8, 'name' => 'Rizal']);
    $student = Student::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
    Enrollment::create(['section_id' => $section->id, 'student_id' => $student->id, 'seat_row' => 1, 'seat_col' => 1, 'enrolled_on' => '2026-06-15']);

    return [$adviser, $section, $student];
}

/** Two fake OpenAI-compatible providers, main and backup. */
function fakeAiProviders(): void
{
    config([
        'ai.enabled' => true,
        'ai.providers' => [
            ['name' => 'groq', 'base_url' => 'https://main.test/v1', 'model' => 'main-model', 'api_key' => 'k1'],
            ['name' => 'openrouter', 'base_url' => 'https://backup.test/v1', 'model' => 'backup-model', 'api_key' => 'k2'],
        ],
    ]);
}

/** @param array<string, mixed>|string $content */
function aiReply(array|string $content): array
{
    return ['id' => 'chatcmpl-1', 'choices' => [['message' => ['role' => 'assistant', 'content' => is_array($content) ? json_encode($content) : $content]]], 'usage' => ['total_tokens' => 120]];
}

function validClassification(): array
{
    return ['category' => 'aggression_bullying', 'severity' => 'medium', 'confidence' => 0.82, 'rationale' => '[STUDENT] pushed a classmate.'];
}
