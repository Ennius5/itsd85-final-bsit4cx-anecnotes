<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\Severity;
use Database\Factories\AnecdoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $student_id
 * @property int $author_id
 * @property int|null $section_id
 * @property Carbon $occurred_at
 * @property string|null $location
 * @property string $description
 * @property string|null $action_taken
 * @property Category|null $category_override
 * @property Severity|null $severity_override
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 * @property Carbon|null $device_created_at
 * @property Student $student
 * @property User $author
 * @property Section|null $section
 * @property AiAnalysis|null $latestAnalysis
 */
class Anecdote extends Model
{
    /** @use HasFactory<AnecdoteFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id', 'student_id', 'author_id', 'section_id', 'occurred_at', 'location', 'description',
        'action_taken', 'category_override', 'severity_override', 'device_created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'device_created_at' => 'datetime',
            'category_override' => Category::class,
            'severity_override' => Severity::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class)->withTrashed();
    }

    /** @return HasMany<AiAnalysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class)->where('kind', 'classification');
    }

    /**
     * Newest classification run (ok, fallback or failed).
     *
     * @return HasOne<AiAnalysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class)->where('kind', 'classification')->latestOfMany();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
