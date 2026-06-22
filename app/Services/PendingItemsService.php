<?php

namespace App\Services;

use App\Models\PendingTask;
use App\Models\VehicleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PendingItemsService
{
    public const DOCUMENT_FIELDS = [
        'green_card_expires_at' => 'Carta Verde',
        'private_conditions_expires_at' => 'Condições Particulares',
        'inspection_expires_at' => 'Inspeção',
        'dua_expires_at' => 'DUA',
        'fire_extinguisher_expires_at' => 'Extintor',
        'emel_expires_at' => 'EMEL',
        'cartrack_expires_at' => 'Cartrack',
    ];

    public function documents(): Collection
    {
        $today = Carbon::today();
        $threshold = $today->copy()->addDays(30);

        $vehicles = VehicleItem::query()
            ->where(function ($query) use ($threshold) {
                foreach (array_keys(self::DOCUMENT_FIELDS) as $field) {
                    $query->orWhereDate($field, '<=', $threshold);
                }
            })
            ->orderBy('license_plate')
            ->get();

        return $vehicles
            ->flatMap(function (VehicleItem $vehicle) use ($today, $threshold) {
                return collect(self::DOCUMENT_FIELDS)
                    ->map(function (string $label, string $field) use ($vehicle, $today, $threshold) {
                        $expiresAt = $vehicle->{$field};

                        if (! $expiresAt || $expiresAt->gt($threshold)) {
                            return null;
                        }

                        $days = $today->diffInDays($expiresAt, false);

                        return [
                            'type' => 'document',
                            'vehicle' => $vehicle,
                            'vehicle_id' => $vehicle->id,
                            'license_plate' => $vehicle->license_plate,
                            'label' => $label,
                            'field' => $field,
                            'date' => $expiresAt,
                            'days' => $days,
                            'status' => $this->statusForDays($days),
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->sortBy([
                ['date', 'asc'],
                ['license_plate', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    public function openTasks(): Collection
    {
        return PendingTask::query()
            ->whereNull('completed_at')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PendingTask $task) => $this->formatTask($task));
    }

    public function dueTasks(): Collection
    {
        $threshold = Carbon::today()->addDays(30);

        return PendingTask::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $threshold)
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PendingTask $task) => $this->formatTask($task));
    }

    public function summary(int $limit = 10): array
    {
        $documents = $this->documents();
        $tasks = $this->dueTasks();
        $items = $documents
            ->concat($tasks)
            ->sortBy([
                ['date', 'asc'],
                ['label', 'asc'],
            ])
            ->values();

        return [
            'count' => $items->count(),
            'items' => $items->take($limit),
        ];
    }

    private function formatTask(PendingTask $task): array
    {
        $today = Carbon::today();
        $days = $task->due_date ? $today->diffInDays($task->due_date, false) : null;

        return [
            'type' => 'task',
            'task' => $task,
            'label' => $task->title,
            'description' => $task->description,
            'date' => $task->due_date,
            'days' => $days,
            'status' => $days === null ? 'normal' : $this->statusForDays($days),
        ];
    }

    private function statusForDays(int $days): string
    {
        if ($days < 0) {
            return 'expired';
        }

        if ($days <= 7) {
            return 'urgent';
        }

        return 'normal';
    }
}
