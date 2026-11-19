<?php

namespace App\Services;

use Carbon\Carbon;

class AdjustmentPeriod
{
    public static function dates(string $day, int $weeks): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $day)->startOfWeek(Carbon::MONDAY);
        return [
            'start_date' => $start->format(config('panel.date_format')),
            'end_date' => $start->copy()->addWeeks($weeks)->subDay()->format(config('panel.date_format')),
        ];
    }
}
