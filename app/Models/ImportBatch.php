<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['headers' => 'array', 'column_map' => 'array', 'collected_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class)->orderBy('row_number');
    }

    public function percent(): int
    {
        return $this->total_rows ? (int) floor($this->processed_rows * 100 / $this->total_rows) : 0;
    }
}
