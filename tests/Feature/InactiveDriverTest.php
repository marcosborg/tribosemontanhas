<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\InactiveDriverController;
use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InactiveDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('state_id');
            $table->integer('company_id');
            $table->softDeletes();
        });
        Schema::create('vehicle_usages', function (Blueprint $table) {
            $table->id();
            $table->integer('driver_id');
            $table->integer('vehicle_item_id')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('usage_exceptions')->nullable();
            $table->softDeletes();
        });
        DB::table('drivers')->insert([
            ['id' => 1, 'name' => 'Inactive', 'state_id' => 2, 'company_id' => 1],
            ['id' => 2, 'name' => 'Active', 'state_id' => 1, 'company_id' => 1],
            ['id' => 3, 'name' => 'Other company', 'state_id' => 2, 'company_id' => 2],
        ]);
    }

    public function test_latest_allocation_uses_dates_and_ignores_deleted_future_and_non_usage_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 9));
        $insert = fn ($values) => DB::table('vehicle_usages')->insertGetId(array_merge([
            'driver_id' => 1, 'vehicle_item_id' => 1, 'start_date' => '2026-01-01 10:00:00',
            'end_date' => null, 'usage_exceptions' => 'usage',
        ], $values));
        $expected = $insert(['start_date' => '2026-02-01 10:00:00', 'end_date' => '2026-02-20 18:45:00']);
        $insert([]); // A later ID is not necessarily the latest allocation.
        $insert(['start_date' => '2026-03-01 10:00:00', 'usage_exceptions' => 'maintenance']);
        $insert(['start_date' => '2026-03-01 10:00:00', 'deleted_at' => now()]);
        $insert(['start_date' => '2027-01-01 10:00:00']);
        $driver = Driver::with('latestVehicleAllocation')->findOrFail(1);
        $this->assertSame($expected, $driver->latestVehicleAllocation->id);
        $this->assertSame('2026-02-20 18:45:00', $driver->latestVehicleAllocation->end_date);
        $this->assertNull(Driver::findOrFail(2)->latestVehicleAllocation);
        $open = $insert(['start_date' => '2026-04-01 10:00:00', 'usage_exceptions' => null]);
        $this->assertSame($open, $driver->fresh()->latestVehicleAllocation->id);
        $this->assertNull($driver->fresh()->latestVehicleAllocation->end_date);
    }

    public function test_first_allocation_uses_earliest_valid_date_not_creation_order(): void
    {
        $insert = fn ($values) => DB::table('vehicle_usages')->insertGetId(array_merge([
            'driver_id' => 1, 'vehicle_item_id' => 1, 'start_date' => '2025-06-01 09:00:00',
            'end_date' => null, 'usage_exceptions' => 'usage',
        ], $values));
        $latest = $insert([]);
        $first = $insert(['start_date' => '2024-01-10 08:30:00']);
        $insert(['start_date' => '2023-01-01 09:00:00', 'usage_exceptions' => 'maintenance']);
        $insert(['start_date' => '2023-01-01 09:00:00', 'deleted_at' => now()]);
        $driver = Driver::with(['firstVehicleAllocation', 'latestVehicleAllocation'])->findOrFail(1);
        $this->assertSame($first, $driver->firstVehicleAllocation->id);
        $this->assertSame('2024-01-10 08:30:00', $driver->firstVehicleAllocation->start_date);
        $this->assertSame($latest, $driver->latestVehicleAllocation->id);
        $this->assertNull(Driver::findOrFail(2)->firstVehicleAllocation);
    }

    public function test_report_filters_by_company_and_inactive_state(): void
    {
        Gate::shouldReceive('allows')->with('company_expenses_menu_access')->andReturn(true);
        Gate::shouldReceive('allows')->with('driver_access')->andReturn(true);
        session(['company_id' => 1]);
        $view = app(InactiveDriverController::class)->index();
        $this->assertSame([1], $view->getData()['drivers']->pluck('id')->all());
    }

    public function test_report_requires_driver_permission(): void
    {
        Gate::shouldReceive('allows')->with('company_expenses_menu_access')->andReturn(true);
        Gate::shouldReceive('allows')->with('driver_access')->andReturn(false);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InactiveDriverController::class)->index();
    }
}
