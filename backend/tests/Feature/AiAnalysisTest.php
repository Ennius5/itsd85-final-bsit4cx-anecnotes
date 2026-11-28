<?php

use App\Jobs\AnalyzeAnecdote;
use App\Models\AiAnalysis;
use App\Models\Anecdote;
use App\Models\AuditLog;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\AnecdoteClassifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    fakeAiProviders();
    [$this->adviser, $this->section, $this->student] = classroom();
    $this->anecdote = Anecdote::factory()->create([
        'student_id' => $this->student->id,
        'author_id' => $this->adviser->id,
        'section_id' => $this->section->id,
        'description' => 'Juan Dela Cruz pushed a classmate. His LRN is 123456789012 and his mother is at 0917 123 4567.',
    ]);
});

it('classifies with the main provider and logs the call', function () {
    Http::fake(['main.test/*' => Http::response(aiReply(validClassification()))]);

    $analysis = app(AnecdoteClassifier::class)->classify($this->anecdote, $this->adviser->id);

    expect($analysis->outcome)->toBe('ok')
        ->and($analysis->provider)->toBe('groq')
        ->and($analysis->category)->toBe('aggression_bullying')
        ->and($analysis->severity)->toBe('medium')
        ->and($analysis->confidence)->toBe(0.82)
        ->and($analysis->input_sha256)->toHaveLength(64)
        ->and(AuditLog::where('action', 'ai_call')->count())->toBe(1);
});

it('sends only redacted text to the provider', function () {
    Http::fake(['main.test/*' => Http::response(aiReply(validClassification()))]);

    app(AnecdoteClassifier::class)->classify($this->anecdote);

    Http::assertSent(function (Request $request) {
        $sent = json_encode($request['messages']);

        return ! str_contains($sent, 'Juan') && ! str_contains($sent, 'Dela Cruz')
            && ! str_contains($sent, '123456789012') && ! str_contains($sent, '0917')
            && str_contains($sent, '[STUDENT]') && str_contains($sent, '[LRN]') && str_contains($sent, '[PHONE]')
            && $request->hasHeader('Authorization', 'Bearer k1')
            && $request['temperature'] === 0;
    });
});

it('falls back to the backup provider when the main one fails', function () {
    Http::fake([
        'main.test/*' => Http::response(['error' => 'rate limited'], 429),
        'backup.test/*' => Http::response(aiReply(validClassification())),
    ]);

    $analysis = app(AnecdoteClassifier::class)->classify($this->anecdote);

    expect($analysis->outcome)->toBe('fallback_used')
        ->and($analysis->provider)->toBe('openrouter')
        ->and($analysis->attempt)->toBe(2);
});

it('treats invalid JSON or unknown categories as a failure and moves on', function () {
    Http::fake([
        'main.test/*' => Http::response(aiReply('Sure! The category is bullying.')),
        'backup.test/*' => Http::response(aiReply(['category' => 'crime', 'severity' => 'medium', 'confidence' => 0.9])),
    ]);

    expect(fn () => app(AnecdoteClassifier::class)->classify($this->anecdote))->toThrow(AiUnavailableException::class);
    expect(AiAnalysis::where('outcome', 'failed')->count())->toBe(1);
});

it('accepts JSON wrapped in code fences', function () {
    Http::fake(['main.test/*' => Http::response(aiReply("```json\n".json_encode(validClassification())."\n```"))]);

    expect(app(AnecdoteClassifier::class)->classify($this->anecdote)->outcome)->toBe('ok');
});

it('keeps the anecdote and reports failure when every provider is down', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    (new AnalyzeAnecdote($this->anecdote->id))->handle(app(AnecdoteClassifier::class));

    Sanctum::actingAs($this->adviser);
    $this->getJson("/api/v1/anecdotes/{$this->anecdote->id}")
        ->assertOk()
        ->assertJsonPath('data.description', $this->anecdote->description)
        ->assertJsonPath('data.analysis.status', 'failed');
});

it('does nothing when AI is turned off', function () {
    config(['ai.enabled' => false]);
    Http::fake();

    (new AnalyzeAnecdote($this->anecdote->id))->handle(app(AnecdoteClassifier::class));

    Http::assertNothingSent();
    Sanctum::actingAs($this->adviser);
    $this->getJson("/api/v1/anecdotes/{$this->anecdote->id}")->assertJsonPath('data.analysis.status', 'disabled');
});

it('serves the analysis through the API, labeled as AI output', function () {
    Http::fake(['main.test/*' => Http::response(aiReply(validClassification()))]);
    app(AnecdoteClassifier::class)->classify($this->anecdote);
    Sanctum::actingAs($this->adviser);

    $this->getJson("/api/v1/anecdotes/{$this->anecdote->id}/analysis")
        ->assertOk()
        ->assertJsonPath('current.category', 'aggression_bullying')
        ->assertJsonPath('current.label', 'AI suggestion')
        ->assertJsonPath('current.model', 'main-model');

    $this->getJson("/api/v1/anecdotes/{$this->anecdote->id}")
        ->assertJsonPath('data.analysis.status', 'done')
        ->assertJsonPath('data.analysis.label', 'AI suggestion');
});

it('runs end to end: create through the API, analyze in the queue, read the result', function () {
    Http::fake(['main.test/*' => Http::response(aiReply(validClassification()))]);
    Sanctum::actingAs($this->adviser);

    $id = (string) Str::uuid();
    $this->postJson('/api/v1/anecdotes', [
        'id' => $id, 'student_id' => $this->student->id, 'occurred_at' => now()->toIso8601String(),
        'description' => 'Hit a classmate with a ball on purpose during PE.', 'device_time' => now()->toIso8601String(),
    ])->assertCreated();

    // QUEUE_CONNECTION=sync in tests, so the job has already run.
    $this->getJson("/api/v1/anecdotes/{$id}")->assertJsonPath('data.analysis.status', 'done');
});
