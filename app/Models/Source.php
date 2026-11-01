<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Source extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'website' => 'Website',
        'physical_upload' => 'College / school / coaching upload',
        'sheet_import' => 'Old Google Sheet import',
        'manual' => 'Manual entry',
        'other' => 'Other authorized source',
    ];

    protected $fillable = ['name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
