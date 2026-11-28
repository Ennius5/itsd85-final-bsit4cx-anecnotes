<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of who changed what (accountability, RA 10173).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, ?int $userId = null): self
    {
        $request = request();

        return static::create([
            'user_id' => $userId ?? $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject ? strtolower(class_basename($subject)) : null,
            'auditable_id' => $subject ? (string) $subject->getKey() : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
