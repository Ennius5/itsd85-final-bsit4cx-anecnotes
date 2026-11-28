<?php

namespace App\Services\Ai;

final class AiResult
{
    /**
     * @param  array<string, mixed>  $data  Parsed and validated JSON from the model
     * @param  array<string, mixed>  $raw  Provider response body
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly array $data,
        public readonly array $raw,
        public readonly int $latencyMs,
        public readonly int $attempt,
        public readonly bool $usedFallback,
    ) {}
}
