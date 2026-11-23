<?php

namespace App\Http\Resources;

use App\Models\Anecdote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Anecdote */
class AnecdoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->fullName(),
                'lrn' => $this->student->lrn,
            ]),
            'section' => $this->whenLoaded('section', fn () => $this->section?->label()),
            'author_id' => $this->author_id,
            'author' => $this->whenLoaded('author', fn () => $this->author->name),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'location' => $this->location,
            'description' => $this->description,
            'action_taken' => $this->action_taken,
            'category_override' => $this->category_override?->value,
            'severity_override' => $this->severity_override?->value,
            /** open | resolved */
            'status' => $this->status,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_note' => $this->resolution_note,
            'analysis' => $this->analysisSummary(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Short form of the current AI analysis, always labeled as machine-generated.
     *
     * @return array<string, mixed>
     */
    private function analysisSummary(): array
    {
        if (! config('ai.enabled')) {
            return ['status' => 'disabled', 'label' => 'AI analysis is turned off'];
        }

        $latest = $this->relationLoaded('latestAnalysis') ? $this->latestAnalysis : $this->latestAnalysis()->first();

        if ($latest === null) {
            return ['status' => 'pending', 'label' => 'Analysis pending'];
        }
        if ($latest->outcome === 'failed') {
            return ['status' => 'failed', 'label' => 'AI analysis unavailable. The record is saved; analysis will retry.'];
        }

        return [
            'status' => 'done',
            'category' => $latest->category,
            'severity' => $latest->severity,
            'confidence' => $latest->confidence,
            'label' => 'AI suggestion',
        ];
    }
}
