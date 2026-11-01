<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One call or review of a student. Insert-only: once saved it can never be
 * changed or removed. A mistake is fixed by saving a new correcting entry.
 */
class CallEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'student_id', 'user_id', 'called_at', 'lead_status_id', 'outcome',
        'advice', 'remarks', 'next_follow_up_at', 'corrects_call_entry_id',
    ];

    protected function casts(): array
    {
        return ['called_at' => 'datetime', 'next_follow_up_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Call entries cannot be edited. Add a correction instead.'));
        static::deleting(fn () => throw new LogicException('Call entries cannot be deleted.'));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_call_entry_id');
    }
}
