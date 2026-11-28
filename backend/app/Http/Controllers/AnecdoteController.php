<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Enums\Role;
use App\Http\Requests\StoreAnecdoteRequest;
use App\Http\Requests\UpdateAnecdoteRequest;
use App\Http\Resources\AiAnalysisResource;
use App\Http\Resources\AnecdoteResource;
use App\Jobs\AnalyzeAnecdote;
use App\Models\Anecdote;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnecdoteController extends Controller
{
    private const WITH = ['student', 'section', 'author', 'latestAnalysis'];

    /**
     * List anecdotes.
     *
     * Roles: admin and guidance see all; teachers see their own and those about students in sections they advise.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'student_id' => ['sometimes', 'integer'],
            'section_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(['open', 'resolved'])],
            /** Matches the human override or the newest AI category. */
            'category' => ['sometimes', Rule::enum(Category::class)],
            /** Search the description and the student's name. */
            'q' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['occurred_at', '-occurred_at'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = Anecdote::query()->with(self::WITH)
            ->when(! $user->hasRole(Role::Admin, Role::Guidance), fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('author_id', $user->id)
                ->orWhereHas('section', fn (Builder $s) => $s->where('adviser_id', $user->id))))
            ->when($filters['student_id'] ?? null, fn (Builder $q, $v) => $q->where('student_id', $v))
            ->when($filters['section_id'] ?? null, fn (Builder $q, $v) => $q->where('section_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $q) => $q
                ->where('category_override', $v)
                ->orWhere(fn (Builder $q) => $q->whereNull('category_override')
                    ->whereHas('latestAnalysis', fn (Builder $a) => $a->where('category', $v)))))
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn (Builder $q) => $q
                    ->whereRaw('lower(description) like ?', [$like])
                    ->orWhereHas('student', fn (Builder $s) => $s
                        ->whereRaw('lower(last_name) like ?', [$like])
                        ->orWhereRaw('lower(first_name) like ?', [$like])));
            });

        ($filters['sort'] ?? '-occurred_at') === 'occurred_at'
            ? $query->orderBy('occurred_at')
            : $query->orderByDesc('occurred_at');

        return AnecdoteResource::collection($query->paginate($filters['per_page'] ?? 20));
    }

    /**
     * Write an anecdote.
     *
     * Roles: teacher, guidance, admin. Idempotent on `id`: sending the same record again returns
     * it with 200 instead of creating a duplicate. AI analysis runs in the background.
     */
    public function store(StoreAnecdoteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $existing = Anecdote::withTrashed()->find($data['id']);
        if ($existing) {
            abort_unless($existing->author_id === $user->id && ! $existing->trashed(), 403, 'This record id is already used.');

            return (new AnecdoteResource($existing->load(self::WITH)))->response()->setStatusCode(200);
        }

        $student = Student::with('currentEnrollment')->findOrFail($data['student_id']);

        $anecdote = Anecdote::create([
            'id' => $data['id'],
            'student_id' => $student->id,
            'author_id' => $user->id,
            'section_id' => $student->currentEnrollment?->section_id,
            'occurred_at' => $data['occurred_at'],
            'location' => $data['location'] ?? null,
            'description' => $data['description'],
            'action_taken' => $data['action_taken'] ?? null,
            'device_created_at' => $data['device_time'],
        ]);

        AuditLog::record('created', $anecdote, null, $anecdote->only(['student_id', 'occurred_at', 'location']));
        AnalyzeAnecdote::dispatch($anecdote->id, $user->id)->afterCommit();

        return (new AnecdoteResource($anecdote->refresh()->load(self::WITH)))->response()->setStatusCode(201);
    }

    /**
     * Get an anecdote.
     *
     * Roles: admin, guidance, the author, or the adviser of the student's section.
     */
    public function show(Anecdote $anecdote): AnecdoteResource
    {
        Gate::authorize('view', $anecdote);

        return new AnecdoteResource($anecdote->load(self::WITH));
    }

    /**
     * Edit an anecdote or override the AI category and severity.
     *
     * Roles: the author (text and overrides, while open); guidance and admin (overrides only).
     * Changing the description queues a new AI analysis.
     */
    public function update(UpdateAnecdoteRequest $request, Anecdote $anecdote): AnecdoteResource
    {
        Gate::authorize('update', $anecdote);
        $data = $request->validated();

        $editsText = array_intersect(array_keys($data), UpdateAnecdoteRequest::textFields()) !== [];
        if ($editsText) {
            Gate::authorize('editText', $anecdote);
        }

        $old = $anecdote->only(array_keys($data));
        $anecdote->fill($data)->save();
        AuditLog::record('updated', $anecdote, $this->serialize($old), $this->serialize($anecdote->only(array_keys($data))));

        if ($anecdote->wasChanged('description')) {
            AnalyzeAnecdote::dispatch($anecdote->id, $request->user()?->id)->afterCommit();
        }

        return new AnecdoteResource($anecdote->load(self::WITH));
    }

    /**
     * Delete an anecdote (soft delete).
     *
     * Roles: the author while it is open, or admin.
     */
    public function destroy(Anecdote $anecdote): Response
    {
        Gate::authorize('delete', $anecdote);
        $anecdote->delete();
        AuditLog::record('deleted', $anecdote);

        return response()->noContent();
    }

    /**
     * Resolve an anecdote.
     *
     * Roles: guidance.
     */
    public function resolve(Request $request, Anecdote $anecdote): AnecdoteResource
    {
        Gate::authorize('resolve', $anecdote);
        $data = $request->validate([
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $anecdote->forceFill([
            'status' => 'resolved',
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
            'resolution_note' => $data['resolution_note'],
        ])->save();
        AuditLog::record('resolved', $anecdote, ['status' => 'open'], ['status' => 'resolved']);

        return new AnecdoteResource($anecdote->load(self::WITH));
    }

    /**
     * AI analysis of an anecdote: the current result and earlier runs.
     *
     * Roles: same as reading the anecdote. Output is labeled as machine-generated.
     */
    public function analysis(Anecdote $anecdote): JsonResponse
    {
        Gate::authorize('view', $anecdote);
        $runs = $anecdote->analyses()->orderByDesc('id')->get();

        return response()->json([
            'current' => $runs->isEmpty() ? null : new AiAnalysisResource($runs->first()),
            'history' => AiAnalysisResource::collection($runs->slice(1)->values()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function serialize(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v), $values);
    }
}
