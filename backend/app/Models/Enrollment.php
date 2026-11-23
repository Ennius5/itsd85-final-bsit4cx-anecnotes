<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $section_id
 * @property int $student_id
 * @property int|null $seat_row
 * @property int|null $seat_col
 * @property string $status
 * @property Section $section
 */
class Enrollment extends Model
{
    protected $fillable = ['section_id', 'student_id', 'seat_row', 'seat_col', 'status', 'enrolled_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enrolled_on' => 'date'];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
