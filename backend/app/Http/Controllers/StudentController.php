<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * Search students.
     *
     * Roles: admin, teacher, guidance (any teacher may write an anecdote about any current student).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** Name or LRN. */
            'q' => ['sometimes', 'string', 'max:100'],
            'section_id' => ['sometimes', 'integer'],
            'sort' => ['sometimes', Rule::in(['last_name', '-last_name', 'lrn'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Student::query()->with('currentEnrollment.section')
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn (Builder $q) => $q
                    ->whereRaw('lower(last_name) like ?', [$like])
                    ->orWhereRaw('lower(first_name) like ?', [$like])
                    ->orWhere('lrn', 'like', $term.'%'));
            })
            ->when($filters['section_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('currentEnrollment', fn (Builder $e) => $e->where('section_id', $v)));

        match ($filters['sort'] ?? 'last_name') {
            '-last_name' => $query->orderByDesc('last_name')->orderByDesc('first_name'),
            'lrn' => $query->orderBy('lrn'),
            default => $query->orderBy('last_name')->orderBy('first_name'),
        };

        return StudentResource::collection($query->paginate($filters['per_page'] ?? 20));
    }

    /**
     * Get a student.
     *
     * Roles: admin, teacher, guidance.
     */
    public function show(Student $student): StudentResource
    {
        return new StudentResource($student->load('currentEnrollment.section'));
    }
}
