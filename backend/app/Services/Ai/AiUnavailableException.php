<?php

namespace App\Services\Ai;

use RuntimeException;

/** Every configured provider failed (timeout, quota, server error or invalid output). */
class AiUnavailableException extends RuntimeException
{
    /** @param  list<array{provider: string, model: string, error: string, latency_ms: int}>  $failures */
    public function __construct(public readonly array $failures)
    {
        parent::__construct('All AI providers failed: '.implode('; ', array_map(
            fn ($f) => "{$f['provider']}: {$f['error']}",
            $failures,
        )));
    }
}
