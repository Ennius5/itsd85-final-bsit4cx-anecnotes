<?php

namespace App\Http\Resources;

use App\Models\AiAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiAnalysis */
class AiAnalysisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** classification | summary */
            'kind' => $this->kind,
            'provider' => $this->provider,
            'model' => $this->model,
            /** ok | fallback_used | failed */
            'outcome' => $this->outcome,
            'category' => $this->category,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            /** One-sentence rationale, with placeholders instead of names. */
            'output' => $this->output,
            'latency_ms' => $this->latency_ms,
            'created_at' => $this->created_at->toIso8601String(),
            'label' => $this->kind === 'summary' ? 'AI-generated - verify against the records' : 'AI suggestion',
        ];
    }
}
