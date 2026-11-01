<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatus extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_closed', 'is_active'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function newStatus(): self
    {
        return static::where('name', 'New')->firstOrFail();
    }
}
