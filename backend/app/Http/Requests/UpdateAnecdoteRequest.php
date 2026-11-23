<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnecdoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // AnecdotePolicy is checked in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:+1 day'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'action_taken' => ['sometimes', 'nullable', 'string', 'max:2000'],
            /** Human correction of the AI category. Null clears it. */
            'category_override' => ['sometimes', 'nullable', Rule::enum(Category::class)],
            /** Human correction of the AI severity. Null clears it. */
            'severity_override' => ['sometimes', 'nullable', Rule::enum(Severity::class)],
        ];
    }

    /** @return list<string> */
    public static function textFields(): array
    {
        return ['occurred_at', 'location', 'description', 'action_taken'];
    }
}
