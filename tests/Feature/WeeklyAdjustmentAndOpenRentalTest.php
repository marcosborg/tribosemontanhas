<?php

namespace Tests\Feature;

use App\Http\Requests\{StoreAdjustmentRequest, UpdateAdjustmentRequest, StoreCarHireRequest, UpdateCarHireRequest};
use App\Models\CarHire;
use App\Services\AdjustmentPeriod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Tests\TestCase;

class WeeklyAdjustmentAndOpenRentalTest extends TestCase
{
    public function test_weekly_period_includes_complete_weeks_and_crosses_years(): void
    {
        $this->assertSame(['start_date'=>'2026-11-16', 'end_date'=>'2026-11-22'], AdjustmentPeriod::dates('2026-11-19', 1));
        $this->assertSame(['start_date'=>'2026-12-28', 'end_date'=>'2027-01-17'], AdjustmentPeriod::dates('2027-01-01', 3));
        foreach ([new StoreAdjustmentRequest(), new UpdateAdjustmentRequest()] as $request) {
            $data = ['name'=>'Dano', 'type'=>'deduct', 'company_id'=>27, 'weekly_schedule'=>1, 'week_start'=>'2026-11-19', 'week_count'=>5];
            $this->assertTrue(Validator::make($data, $request->rules())->passes());
            foreach ([0, -1, 1.5, null] as $count) {
                $this->assertTrue(Validator::make(array_replace($data, ['week_count'=>$count]), $request->rules())->fails());
            }
        }
    }

    public function test_rentals_allow_no_end_date_and_detect_open_period_overlaps(): void
    {
        foreach ([new StoreCarHireRequest(), new UpdateCarHireRequest()] as $request) {
            $data = ['name'=>'Aluguer', 'amount'=>350, 'start_date'=>'2026-11-16', 'end_date'=>null, 'driver_id'=>1];
            $this->assertTrue(Validator::make($data, $request->rules())->passes());
            $this->assertTrue(Validator::make(array_replace($data, ['end_date'=>'2026-11-15']), $request->rules())->fails());
        }
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:']); DB::purge('sqlite');
        Schema::create('car_hires', function (Blueprint $t) { $t->id(); $t->date('start_date'); $t->date('end_date')->nullable(); $t->softDeletes(); });
        DB::table('car_hires')->insert([
            ['start_date'=>'2026-01-01','end_date'=>'2026-06-30'],
            ['start_date'=>'2026-11-16','end_date'=>null],
            ['start_date'=>'2027-01-01','end_date'=>'2027-01-31'],
        ]);
        $this->assertSame([2,3], CarHire::overlapping('2026-11-19', null)->orderBy('id')->pluck('id')->all());
        $this->assertSame([2], CarHire::overlapping('2026-12-01', '2026-12-31')->pluck('id')->all());
        $this->assertSame([], CarHire::overlapping('2026-07-01', '2026-07-31')->pluck('id')->all());
    }
}
