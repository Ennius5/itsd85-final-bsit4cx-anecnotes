<?php

namespace App\Jobs;

use App\Models\Anecdote;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\AnecdoteClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

/**
 * Runs AI classification off the request path, so saving an anecdote never waits
 * for (or fails because of) the AI provider. Retries with backoff when every provider fails.
 */
class AnalyzeAnecdote implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Seconds between retries: about two hours in total.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function __construct(public string $anecdoteId, public ?int $requestedBy = null) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('ai')];
    }

    public function handle(AnecdoteClassifier $classifier): void
    {
        if (! config('ai.enabled')) {
            return;
        }

        $anecdote = Anecdote::with('student')->find($this->anecdoteId);
        if (! $anecdote) {
            return; // deleted before analysis ran
        }

        try {
            $analysis = $classifier->classify($anecdote, $this->requestedBy);
            // Log IDs and outcomes only, never anecdote text.
            Log::info('ai.classified', ['anecdote' => $anecdote->id, 'analysis' => $analysis->id, 'outcome' => $analysis->outcome]);
        } catch (AiUnavailableException $e) {
            Log::warning('ai.unavailable', ['anecdote' => $anecdote->id, 'attempt' => $this->attempts()]);
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 3600);
            }
        }
    }
}
