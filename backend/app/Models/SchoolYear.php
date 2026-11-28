<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_current
 */
class SchoolYear extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'is_current'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }
}
