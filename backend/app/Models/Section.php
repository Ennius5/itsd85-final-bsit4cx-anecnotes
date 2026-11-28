<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $school_year_id
 * @property int|null $adviser_id
 * @property int $grade_level
 * @property string $name
 * @property int $seat_rows
 * @property int $seat_cols
 */
class Section extends Model
{
    use SoftDeletes;

    protected $fillable = ['school_year_id', 'adviser_id', 'grade_level', 'name', 'seat_rows', 'seat_cols'];

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function label(): string
    {
        return "{$this->grade_level}-{$this->name}";
    }
}
