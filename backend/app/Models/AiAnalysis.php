<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One call to the AI provider (T3 logging).
 *
 * @property int $id
 * @property string|null $anecdote_id
 * @property string $kind
 * @property string $provider
 * @property string $model
 * @property string $outcome
 * @property string|null $category
 * @property string|null $severity
 * @property float|null $confidence
 * @property string|null $output
 * @property int $latency_ms
 * @property int $attempt
 * @property Carbon $created_at
 */
class AiAnalysis extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'anecdote_id', 'behavior_flag_id', 'kind', 'provider', 'model', 'outcome', 'category', 'severity',
        'confidence', 'output', 'raw_response', 'input_sha256', 'latency_ms', 'attempt', 'requested_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['raw_response' => 'array', 'confidence' => 'float'];
    }

    /** @return BelongsTo<Anecdote, $this> */
    public function anecdote(): BelongsTo
    {
        return $this->belongsTo(Anecdote::class);
    }
}
