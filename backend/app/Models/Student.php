<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $lrn
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $sex
 * @property Carbon $birthdate
 * @property Enrollment|null $currentEnrollment
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['lrn', 'last_name', 'first_name', 'middle_name', 'sex', 'birthdate'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['birthdate' => 'date'];
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Active enrollment in the current school year.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->where('status', 'active')
            ->whereHas('section.schoolYear', fn ($q) => $q->where('is_current', true));
    }

    /** @return HasMany<Anecdote, $this> */
    public function anecdotes(): HasMany
    {
        return $this->hasMany(Anecdote::class);
    }

    public function fullName(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }
}
