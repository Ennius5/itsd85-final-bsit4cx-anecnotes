<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lrn' => '9999'.fake()->unique()->numerify('########'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'sex' => fake()->randomElement(['M', 'F']),
            'birthdate' => fake()->dateTimeBetween('-15 years', '-12 years')->format('Y-m-d'),
        ];
    }
}
