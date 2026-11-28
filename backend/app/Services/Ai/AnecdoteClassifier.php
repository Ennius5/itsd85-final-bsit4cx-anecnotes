<?php

namespace App\Services\Ai;

use App\Enums\Category;
use App\Enums\Severity;
use App\Models\AiAnalysis;
use App\Models\Anecdote;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Classifies one anecdote by category and severity and records the call (T1-T3, T6).
 */
class AnecdoteClassifier
{
    public function __construct(private AiClient $client, private Redactor $redactor) {}

    /**
     * @throws AiUnavailableException after logging the failed attempt
     */
    public function classify(Anecdote $anecdote, ?int $requestedBy = null): AiAnalysis
    {
        $text = $this->redactor->redact($this->composeText($anecdote), $this->peopleFor($anecdote));
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $text],
        ];
        $hash = hash('sha256', json_encode($messages, JSON_UNESCAPED_UNICODE) ?: '');

        try {
            $result = $this->client->json($messages, [$this, 'validate'], 200);
        } catch (AiUnavailableException $e) {
            $failures = $e->failures;
            $last = end($failures) ?: ['provider' => 'none', 'model' => '-', 'latency_ms' => 0];
            $analysis = AiAnalysis::create([
                'anecdote_id' => $anecdote->id,
                'kind' => 'classification',
                'provider' => $last['provider'],
                'model' => $last['model'],
                'outcome' => 'failed',
                'output' => null,
                'raw_response' => ['failures' => $e->failures],
                'input_sha256' => $hash,
                'latency_ms' => array_sum(array_column($e->failures, 'latency_ms')),
                'attempt' => count($e->failures),
                'requested_by' => $requestedBy,
            ]);
            $this->audit($analysis, $requestedBy);

            throw $e;
        }

        $analysis = AiAnalysis::create([
            'anecdote_id' => $anecdote->id,
            'kind' => 'classification',
            'provider' => $result->provider,
            'model' => $result->model,
            'outcome' => $result->usedFallback ? 'fallback_used' : 'ok',
            'category' => $result->data['category'],
            'severity' => $result->data['severity'],
            'confidence' => round(max(0, min(1, (float) $result->data['confidence'])), 2),
            'output' => mb_substr((string) ($result->data['rationale'] ?? ''), 0, 500),
            'raw_response' => $result->raw,
            'input_sha256' => $hash,
            'latency_ms' => $result->latencyMs,
            'attempt' => $result->attempt,
            'requested_by' => $requestedBy,
        ]);
        $this->audit($analysis, $requestedBy);

        return $analysis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data): ?string
    {
        if (! in_array($data['category'] ?? null, Category::values(), true)) {
            return 'unknown category';
        }
        if (! in_array($data['severity'] ?? null, Severity::values(), true)) {
            return 'unknown severity';
        }
        if (! is_numeric($data['confidence'] ?? null)) {
            return 'confidence is not a number';
        }

        return null;
    }

    private function composeText(Anecdote $anecdote): string
    {
        $parts = ['Anecdote: '.$anecdote->description];
        if ($anecdote->location) {
            $parts[] = 'Location: '.$anecdote->location;
        }
        if ($anecdote->action_taken) {
            $parts[] = 'Action taken by teacher: '.$anecdote->action_taken;
        }

        return implode("\n", $parts);
    }

    /**
     * Names to hide: the student, classmates in the same section, and staff.
     *
     * @return array<int, array{0: string, 1: list<string>}>
     */
    private function peopleFor(Anecdote $anecdote): array
    {
        $student = $anecdote->student;
        $people = [['[STUDENT]', array_filter([$student->first_name, $student->last_name, $student->middle_name])]];

        if ($anecdote->section_id) {
            $n = 2;
            Enrollment::with('student')
                ->where('section_id', $anecdote->section_id)
                ->where('student_id', '!=', $student->id)
                ->get()
                ->each(function (Enrollment $e) use (&$people, &$n) {
                    $s = $e->student;
                    if ($s) {
                        $people[] = ['[STUDENT_'.$n++.']', array_filter([$s->first_name, $s->last_name])];
                    }
                });
        }

        User::query()->pluck('name')->each(function (string $name) use (&$people) {
            $people[] = ['[TEACHER]', array_values(array_filter(explode(' ', $name), fn ($w) => mb_strlen($w) >= 3 && ! str_ends_with($w, '.')))];
        });

        return $people;
    }

    private function systemPrompt(): string
    {
        return (string) file_get_contents(resource_path('prompts/'.config('ai.prompt_version').'.txt'));
    }

    private function audit(AiAnalysis $analysis, ?int $userId): void
    {
        AuditLog::record('ai_call', $analysis, null, [
            'anecdote_id' => $analysis->anecdote_id,
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'outcome' => $analysis->outcome,
            'category' => $analysis->category,
            'severity' => $analysis->severity,
            'confidence' => $analysis->confidence,
            'latency_ms' => $analysis->latency_ms,
        ], $userId);
    }
}
