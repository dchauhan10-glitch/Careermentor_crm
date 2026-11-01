<?php

namespace App\Models;

use App\Support\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'mobile', 'parent_mobile', 'email', 'state_id', 'city_id', 'city_text',
        'course_id', 'college_id', 'exam_id', 'exam_rank', 'admission_year',
        'first_source_id', 'lead_status_id', 'priority', 'remarks',
    ];

    protected $attributes = ['priority' => 'warm', 'enquiry_count' => 1];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Student $s) {
            if (! $s->student_code) {
                $s->student_code = 'CM-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT);
                $s->saveQuietly();
            }
        });
    }

    /** Only the students this user is allowed to see. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $q;
        }
        if ($user->isTeamLead()) {
            $teamId = $user->ledTeam?->id;

            return $q->where(fn ($w) => $w->where('assigned_user_id', $user->id)
                ->when($teamId, fn ($w) => $w->orWhere('team_id', $teamId)));
        }

        return $q->where('assigned_user_id', $user->id);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function firstSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'first_source_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class)->latest();
    }

    public function callEntries(): HasMany
    {
        return $this->hasMany(CallEntry::class)->orderByDesc('called_at')->orderByDesc('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->latest('created_at');
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(AssignmentHistory::class)->latest('created_at');
    }
}
