<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\TvdeMonth;
use App\Models\TvdeWeek;
use App\Models\TvdeYear;

class FilterResolver
{
    public function resolve(int $stateId = 1): array
    {
        $companyId = session()->get('company_id', 27);

        $validYears = TvdeYear::orderBy('name')
            ->whereHas('months', function ($month) use ($companyId) {
                $month->whereHas('weeks', function ($week) use ($companyId) {
                    $week->whereHas('tvdeActivities', function ($tvdeActivity) use ($companyId) {
                        $tvdeActivity->where('company_id', $companyId);
                    });
                });
            })
            ->get();

        $yearId = $this->resolveYearId($validYears);

        $validMonths = TvdeMonth::orderBy('number', 'asc')
            ->whereHas('weeks', function ($week) use ($companyId) {
                $week->whereHas('tvdeActivities', function ($tvdeActivity) use ($companyId) {
                    $tvdeActivity->where('company_id', $companyId);
                });
            })
            ->when($yearId, fn($q) => $q->where('year_id', $yearId))
            ->get();

        $monthId = $this->resolveMonthId($validMonths, $yearId);

        $validWeeks = TvdeWeek::orderBy('number', 'asc')
            ->whereHas('tvdeActivities', function ($tvdeActivity) use ($companyId) {
                $tvdeActivity->where('company_id', $companyId);
            })
            ->when($monthId, fn($q) => $q->where('tvde_month_id', $monthId))
            ->get();

        $weekId = $this->resolveWeekId($validWeeks, $monthId);
        $week = $weekId ? TvdeWeek::find($weekId) : null;

        session()->put('company_id', $companyId);
        if ($yearId) {
            session()->put('tvde_year_id', $yearId);
        }
        if ($monthId) {
            session()->put('tvde_month_id', $monthId);
        }
        if ($weekId) {
            session()->put('tvde_week_id', $weekId);
        }

        $drivers = Driver::where('company_id', $companyId)
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get()
            ->load('team');

        return [
            'company_id' => $companyId,
            'tvde_year_id' => $yearId,
            'tvde_years' => $validYears,
            'tvde_week_id' => $weekId,
            'tvde_week' => $week,
            'tvde_months' => $validMonths,
            'tvde_month_id' => $monthId,
            'tvde_weeks' => $validWeeks,
            'drivers' => $drivers,
        ];
    }

    protected function resolveYearId($validYears): ?int
    {
        $yearId = session()->get('tvde_year_id');
        if ($yearId && $validYears->contains('id', $yearId)) {
            return (int) $yearId;
        }

        $fallback = $validYears->sortByDesc('name')->first();
        if ($fallback) {
            return (int) $fallback->id;
        }

        $latest = TvdeYear::orderBy('name', 'desc')->first();
        return $latest ? (int) $latest->id : null;
    }

    protected function resolveMonthId($validMonths, ?int $yearId): ?int
    {
        $monthId = session()->get('tvde_month_id');
        if ($monthId && $validMonths->contains('id', $monthId)) {
            return (int) $monthId;
        }

        if ($yearId) {
            $fallback = $validMonths->sortByDesc('number')->first();
            if ($fallback) {
                return (int) $fallback->id;
            }
        }

        return null;
    }

    protected function resolveWeekId($validWeeks, ?int $monthId): ?int
    {
        $weekId = session()->get('tvde_week_id');
        if ($weekId && $validWeeks->contains('id', $weekId)) {
            return (int) $weekId;
        }

        if ($monthId) {
            $fallback = $validWeeks->sortByDesc('number')->first();
            if ($fallback) {
                return (int) $fallback->id;
            }
        }

        return null;
    }
}
