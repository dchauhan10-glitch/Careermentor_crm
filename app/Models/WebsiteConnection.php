<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WebsiteConnection extends Model
{
    use SoftDeletes;

    protected $fillable = ['source_id', 'name', 'is_active', 'per_minute_limit'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Creates a new key and returns it in full. The full key is shown to the
     * Super Admin only once; afterwards only a scrambled copy is kept.
     */
    public function issueKey(): string
    {
        $prefix = 'crm_'.Str::lower(Str::random(8));
        $key = $prefix.'_'.Str::random(40);
        $this->key_prefix = $prefix;
        $this->key_hash = hash('sha256', $key);
        $this->save();

        return $key;
    }

    public static function findActiveByKey(?string $key): ?self
    {
        if (! $key || ! preg_match('/^(crm_[a-z0-9]{8})_[A-Za-z0-9]{40}$/', $key, $m)) {
            return null;
        }

        $conn = static::where('key_prefix', $m[1])->where('is_active', true)->first();

        return $conn && hash_equals($conn->key_hash, hash('sha256', $key)) ? $conn : null;
    }
}
