<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lrn' => $this->lrn,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'sex' => $this->sex,
            'birthdate' => $this->birthdate->toDateString(),
            /** Current section, e.g. "8-Rizal", or null when not enrolled this school year. */
            'section' => $this->whenLoaded('currentEnrollment', fn () => $this->currentEnrollment?->section?->label()),
            'section_id' => $this->whenLoaded('currentEnrollment', fn () => $this->currentEnrollment?->section_id),
        ];
    }
}
