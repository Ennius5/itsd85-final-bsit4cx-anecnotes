<?php

use App\Jobs\AnalyzeAnecdote;
use App\Models\Anecdote;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
    [$this->adviser, $this->section, $this->student] = classroom();
    $this->teacher = User::factory()->create();
    $this->guidance = User::factory()->guidance()->create();
    $this->admin = User::factory()->admin()->create();
});

function anecdotePayload(int $studentId, array $overrides = []): array
{
    return array_merge([
        'id' => (string) Str::uuid(),
        'student_id' => $studentId,
        'occurred_at' => now()->subMinutes(10)->toIso8601String(),
        'location' => 'Canteen',
        'description' => 'Pushed a classmate during recess and called him names.',
        'action_taken' => 'Talked to both students.',
        'device_time' => now()->toIso8601String(),
    ], $overrides);
}

it('creates an anecdote, records the section, and queues AI analysis', function () {
    Sanctum::actingAs($this->teacher);
    $payload = anecdotePayload($this->student->id);

    $this->postJson('/api/v1/anecdotes', $payload)
        ->assertCreated()
        ->assertJsonPath('data.id', $payload['id'])
        ->assertJsonPath('data.section', '8-Rizal')
        ->assertJsonPath('data.author_id', $this->teacher->id)
        ->assertJsonPath('data.analysis.status', 'pending');

    Queue::assertPushed(AnalyzeAnecdote::class, fn ($job) => $job->anecdoteId === $payload['id']);
    expect(AuditLog::where('action', 'created')->where('auditable_id', $payload['id'])->exists())->toBeTrue();
});

it('is idempotent: sending the same id twice does not duplicate', function () {
    Sanctum::actingAs($this->teacher);
    $payload = anecdotePayload($this->student->id);

    $this->postJson('/api/v1/anecdotes', $payload)->assertCreated();
    $this->postJson('/api/v1/anecdotes', $payload)->assertOk();

    expect(Anecdote::count())->toBe(1);
    Queue::assertPushed(AnalyzeAnecdote::class, 1);
});

it('refuses to reuse another user\'s anecdote id', function () {
    $existing = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->adviser->id]);
    Sanctum::actingAs($this->teacher);

    $this->postJson('/api/v1/anecdotes', anecdotePayload($this->student->id, ['id' => $existing->id]))->assertForbidden();
});

it('validates input', function () {
    Sanctum::actingAs($this->teacher);

    $this->postJson('/api/v1/anecdotes', anecdotePayload($this->student->id, ['description' => 'short', 'id' => 'not-a-uuid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description', 'id']);
    $this->postJson('/api/v1/anecdotes', anecdotePayload(999999))
        ->assertUnprocessable()->assertJsonValidationErrors(['student_id']);
});

it('scopes the list by role', function () {
    $mine = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id, 'section_id' => $this->section->id]);
    $other = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->guidance->id, 'section_id' => null]);

    Sanctum::actingAs($this->teacher);
    $this->getJson('/api/v1/anecdotes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);

    // The adviser sees anecdotes about students in their section, not unrelated ones.
    Sanctum::actingAs($this->adviser);
    $this->getJson('/api/v1/anecdotes')->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->guidance);
    $this->getJson('/api/v1/anecdotes')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/anecdotes?q=pushed')->assertOk();
    expect($other)->not->toBeNull();
});

it('paginates, filters and sorts', function () {
    Anecdote::factory()->count(3)->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);
    Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id, 'description' => 'Helped clean the classroom after the program.']);
    Sanctum::actingAs($this->guidance);

    $this->getJson('/api/v1/anecdotes?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 4);
    $this->getJson('/api/v1/anecdotes?q=helped')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/anecdotes?sort=occurred_at')->assertOk();
    $this->getJson('/api/v1/anecdotes?status=resolved')->assertOk()->assertJsonCount(0, 'data');
});

it('lets only permitted users view an anecdote', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->guidance->id]);

    Sanctum::actingAs($this->teacher);
    $this->getJson("/api/v1/anecdotes/{$a->id}")->assertForbidden();

    Sanctum::actingAs($this->guidance);
    $this->getJson("/api/v1/anecdotes/{$a->id}")->assertOk()->assertJsonPath('data.student.name', 'Dela Cruz, Juan');

    $this->getJson('/api/v1/anecdotes/'.Str::uuid())->assertNotFound()->assertJsonPath('message', 'Record not found.');
    $this->getJson('/api/v1/anecdotes/not-a-uuid')->assertNotFound();
});

it('lets the author edit text and re-queues analysis', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);
    Sanctum::actingAs($this->teacher);

    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['description' => 'Pushed a classmate twice during recess.'])
        ->assertOk()->assertJsonPath('data.description', 'Pushed a classmate twice during recess.');

    Queue::assertPushed(AnalyzeAnecdote::class);
    expect(AuditLog::where('action', 'updated')->first()->old_values)->toHaveKey('description');
});

it('lets guidance override the category but not edit the text', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);
    Sanctum::actingAs($this->guidance);

    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['category_override' => 'defiance_disrespect'])
        ->assertOk()->assertJsonPath('data.category_override', 'defiance_disrespect');
    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['description' => 'Changed by someone else.'])
        ->assertForbidden();
    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['category_override' => 'not_a_category'])
        ->assertUnprocessable();
});

it('blocks other teachers from editing or deleting', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->adviser->id]);
    Sanctum::actingAs($this->teacher);

    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['location' => 'Hallway'])->assertForbidden();
    $this->deleteJson("/api/v1/anecdotes/{$a->id}")->assertForbidden();
});

it('lets the author or admin delete (soft delete)', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);
    $b = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);

    Sanctum::actingAs($this->teacher);
    $this->deleteJson("/api/v1/anecdotes/{$a->id}")->assertNoContent();

    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/anecdotes/{$b->id}")->assertNoContent();

    expect(Anecdote::count())->toBe(0)->and(Anecdote::withTrashed()->count())->toBe(2);
});

it('lets only guidance resolve, with a note, and then locks the text', function () {
    $a = Anecdote::factory()->create(['student_id' => $this->student->id, 'author_id' => $this->teacher->id]);

    Sanctum::actingAs($this->teacher);
    $this->postJson("/api/v1/anecdotes/{$a->id}/resolve", ['resolution_note' => 'Handled.'])->assertForbidden();

    Sanctum::actingAs($this->guidance);
    $this->postJson("/api/v1/anecdotes/{$a->id}/resolve", [])->assertUnprocessable();
    $this->postJson("/api/v1/anecdotes/{$a->id}/resolve", ['resolution_note' => 'Met the student and parent.'])
        ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolved_by', $this->guidance->id);

    Sanctum::actingAs($this->teacher);
    $this->patchJson("/api/v1/anecdotes/{$a->id}", ['location' => 'Hallway'])->assertForbidden();
});

it('searches students by name or LRN', function () {
    Sanctum::actingAs($this->teacher);

    $this->getJson('/api/v1/students?q=dela')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.section', '8-Rizal');
    $this->getJson('/api/v1/students?q='.substr($this->student->lrn, 0, 6))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/students/{$this->student->id}")->assertOk()->assertJsonPath('data.lrn', $this->student->lrn);
});
