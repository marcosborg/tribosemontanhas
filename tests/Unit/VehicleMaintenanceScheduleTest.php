<?php

namespace Tests\Unit;

use App\Models\VehicleMaintenanceSchedule;
use PHPUnit\Framework\TestCase;

class VehicleMaintenanceScheduleTest extends TestCase
{
    public function test_it_calculates_the_next_service_from_the_last_service_and_interval(): void
    {
        $schedule = new VehicleMaintenanceSchedule([
            'interval_km' => 20000,
            'last_service_km' => 100000,
        ]);

        $this->assertSame(120000, $schedule->nextServiceKm());
    }

    public function test_it_marks_maintenance_as_due_soon_or_ok_from_the_current_mileage(): void
    {
        $schedule = new VehicleMaintenanceSchedule([
            'interval_km' => 20000,
            'last_service_km' => 100000,
        ]);

        $this->assertSame('ok', $schedule->statusFor(117999));
        $this->assertSame('soon', $schedule->statusFor(118000));
        $this->assertSame('due', $schedule->statusFor(120000));
    }

    public function test_it_keeps_tire_rotation_at_fifteen_thousand_kilometres(): void
    {
        $this->assertSame(15000, VehicleMaintenanceSchedule::TIRE_ROTATION_INTERVAL_KM);
    }
}
