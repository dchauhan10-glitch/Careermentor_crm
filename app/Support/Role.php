<?php

namespace App\Support;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case TeamLead = 'team_lead';
    case Counsellor = 'counsellor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::TeamLead => 'Team Lead',
            self::Counsellor => 'Counsellor',
        };
    }
}
