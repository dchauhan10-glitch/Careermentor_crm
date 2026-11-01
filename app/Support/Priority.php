<?php

namespace App\Support;

enum Priority: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
