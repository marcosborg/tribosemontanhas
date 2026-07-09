<?php

namespace App\Services;

use App\Models\VehicleItem;
use App\Models\VehicleUsage;
use Carbon\Carbon;

class CarTrackClassificationService
{
    public const STATUS_DRIVER = 'driver';
    public const STATUS_COMPANY = 'company';
    public const STATUS_MANUAL = 'manual';

    public function classify(?string $licensePlate, $date): array
    {
        $vehicle = $this->findVehicle($licensePlate);

        if (! $vehicle) {
            return $this->manual('missing_vehicle');
        }

        $date = Carbon::parse($date);

        if ($vehicle->vehicle_type === VehicleItem::VEHICLE_TYPE_MANAGEMENT) {
            return $this->company($vehicle, 'management_vehicle');
        }

        $usage = VehicleUsage::query()
            ->where('vehicle_item_id', $vehicle->id)
            ->whereNull('deleted_at')
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (! $usage) {
            return $this->manual('missing_usage', $vehicle->id);
        }

        if ($this->usageHasException($usage, 'personal')) {
            return $this->company($vehicle, 'personal_usage');
        }

        if (! $this->usageIsDriverBillable($usage)) {
            return $this->manual('missing_driver', $vehicle->id);
        }

        if (! $usage->driver_id) {
            return $this->manual('missing_driver', $vehicle->id);
        }

        return [
            'classification_status' => self::STATUS_DRIVER,
            'classification_reason' => 'normal_driver',
            'vehicle_item_id' => $vehicle->id,
            'driver_id' => $usage->driver_id,
            'company_id' => null,
        ];
    }

    private function findVehicle(?string $licensePlate): ?VehicleItem
    {
        $normalized = $this->normalizePlate($licensePlate);

        if ($normalized === '') {
            return null;
        }

        return VehicleItem::query()
            ->whereRaw("REPLACE(REPLACE(UPPER(license_plate), ' ', ''), '-', '') = ?", [$normalized])
            ->first();
    }

    private function company(VehicleItem $vehicle, string $reason): array
    {
        if (! $vehicle->company_id) {
            return $this->manual('missing_company', $vehicle->id);
        }

        return [
            'classification_status' => self::STATUS_COMPANY,
            'classification_reason' => $reason,
            'vehicle_item_id' => $vehicle->id,
            'driver_id' => null,
            'company_id' => $vehicle->company_id,
        ];
    }

    private function manual(string $reason, ?int $vehicleItemId = null): array
    {
        return [
            'classification_status' => self::STATUS_MANUAL,
            'classification_reason' => $reason,
            'vehicle_item_id' => $vehicleItemId,
            'driver_id' => null,
            'company_id' => null,
        ];
    }

    private function usageIsDriverBillable(VehicleUsage $usage): bool
    {
        $value = $usage->usage_exceptions;

        return $value === null
            || $value === ''
            || $value === 'usage'
            || $this->usageHasException($usage, 'usage');
    }

    private function usageHasException(VehicleUsage $usage, string $needle): bool
    {
        $value = $usage->usage_exceptions;

        if ($value === $needle) {
            return true;
        }

        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && in_array($needle, $decoded, true);
    }

    private function normalizePlate(?string $licensePlate): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $licensePlate)) ?? '';
    }
}
