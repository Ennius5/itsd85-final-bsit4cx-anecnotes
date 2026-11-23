<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnecdoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Client-generated UUID; sending the same id twice does not create a duplicate. */
            'id' => ['required', 'uuid'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            /** When it happened. Defaults to now on the phone. */
            'occurred_at' => ['required', 'date', 'before_or_equal:+1 day'],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            /** Phone clock when the record was saved. */
            'device_time' => ['required', 'date'],
        ];
    }
}
