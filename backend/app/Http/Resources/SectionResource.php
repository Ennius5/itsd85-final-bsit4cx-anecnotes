<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_year_id' => $this->school_year_id,
            'grade_level' => $this->grade_level,
            'name' => $this->name,
            /** e.g. "8-Rizal" */
            'label' => $this->label(),
            'adviser_id' => $this->adviser_id,
            'seat_rows' => $this->seat_rows,
            'seat_cols' => $this->seat_cols,
        ];
    }
}
