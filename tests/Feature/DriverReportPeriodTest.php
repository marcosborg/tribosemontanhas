<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class DriverReportPeriodTest extends TestCase
{
    public function test_report_uses_inclusive_employment_dates_not_current_status(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('drivers', function (Blueprint $table) {
            $table->id(); $table->integer('company_id'); $table->integer('state_id');
            $table->date('start_date')->nullable(); $table->date('end_date')->nullable(); $table->softDeletes();
        });
        $cases = [
            [2, '2026-01-01', '2026-11-10', true], // Now inactive, employed during the week.
            [1, '2026-11-16', null, false], // Future recruit.
            [2, '2026-01-01', '2026-11-08', false],
            [2, '2026-01-01', '2026-11-09', true], // Last day is Monday.
            [1, '2026-11-15', null, true], // First day is Sunday.
            [1, '2026-11-11', '2026-11-12', true],
            [1, null, null, true], // Legacy fallback.
            [2, null, null, false],
            [2, null, '2026-11-09', true],
        ];
        $expected = [];
        foreach ($cases as [$state, $start, $end, $included]) {
            $id = DB::table('drivers')->insertGetId(['company_id'=>27, 'state_id'=>$state, 'start_date'=>$start, 'end_date'=>$end]);
            if ($included) $expected[] = $id;
        }
        DB::table('drivers')->insert(['company_id'=>99, 'state_id'=>1, 'start_date'=>'2026-01-01']);
        $this->assertSame($expected, Driver::where('company_id', 27)->activeDuring('2026-11-09', '2026-11-15')->orderBy('id')->pluck('id')->all());
        // A status change today must not remove a dated driver from an earlier report.
        DB::table('drivers')->where('id', 5)->update(['state_id'=>2]);
        $this->assertSame($expected, Driver::where('company_id', 27)->activeDuring('2026-11-09', '2026-11-15')->orderBy('id')->pluck('id')->all());
    }
}
