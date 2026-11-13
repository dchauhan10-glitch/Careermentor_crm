<?php

namespace App\Support;

/**
 * Display colours for lead statuses. Only affects how a status looks, never what it means.
 * green = Interested, red = Not Interested, grey = Not called yet, amber = follow-up needed.
 */
class StatusTone
{
    /** Statuses that count as "Not Called Yet" in the quick filters. */
    public const NOT_CALLED = ['New', 'Not Contacted'];

    private const TONES = [
        'Interested' => 'green',
        'Not Interested' => 'red',
        'New' => 'grey',
        'Not Contacted' => 'grey',
        'Call Back' => 'amber',
        'No Response' => 'amber',
        'Follow-up Required' => 'amber',
        'Converted' => 'done',
        'Closed' => 'grey',
    ];

    private const HEX = [
        'green' => '#10b981', 'red' => '#ef4444', 'grey' => '#94a3b8', 'amber' => '#f59e0b', 'done' => '#047857',
    ];

    public static function tone(?string $status): string
    {
        return self::TONES[$status] ?? 'grey';
    }

    /** Chart colour: same tone family, with small shade steps so neighbouring slices stay distinguishable. */
    public static function hex(?string $status): string
    {
        return match ($status) {
            'New' => '#cbd5e1',
            'Not Contacted' => '#94a3b8',
            'Closed' => '#64748b',
            'Call Back' => '#fbbf24',
            'No Response' => '#d97706',
            'Follow-up Required' => '#f59e0b',
            default => self::HEX[self::tone($status)],
        };
    }
}
