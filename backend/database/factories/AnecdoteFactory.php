<?php

namespace Database\Factories;

use App\Models\Anecdote;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Anecdote>
 */
class AnecdoteFactory extends Factory
{
    protected $model = Anecdote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'student_id' => Student::factory(),
            'author_id' => User::factory(),
            'section_id' => null,
            'occurred_at' => now()->subHour(),
            'location' => 'Classroom',
            'description' => 'Talked loudly during the lesson and would not stop when asked.',
            'action_taken' => null,
            'device_created_at' => now()->subHour(),
        ];
    }
}
