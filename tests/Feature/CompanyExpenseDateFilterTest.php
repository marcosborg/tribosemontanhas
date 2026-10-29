<?php

namespace Tests\Feature;

use App\Models\CompanyExpense;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class CompanyExpenseDateFilterTest extends TestCase
{
    public function test_date_filter_includes_boundaries_and_overlapping_periods_without_leaking_companies(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('company_expenses', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->string('expense_mode')->nullable();
            $t->date('date')->nullable(); $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes();
        });
        foreach (['2026-09-30', '2026-10-01', '2026-10-31', '2026-11-01'] as $date) {
            DB::table('company_expenses')->insert(['company_id' => 1, 'expense_mode' => 'accounting', 'date' => $date]);
        }
        foreach ([1, 2] as $company) {
            DB::table('company_expenses')->insert(['company_id' => $company, 'expense_mode' => 'recurring', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30']);
        }
        $query = fn ($from, $to) => CompanyExpense::where('company_id', 1)->inDateRange($from, $to)->orderBy('id')->pluck('id')->all();
        $this->assertSame([2, 3, 5], $query('2026-10-01', '2026-10-31'));
        $this->assertSame([3, 4, 5], $query('2026-10-31', null));
        $this->assertSame([1, 2, 5], $query(null, '2026-10-01'));
        $this->assertSame([1, 2, 3, 4, 5], $query(null, null));
        $this->assertSame([], $query('2027-01-01', '2027-01-31'));
        Schema::table('company_expenses', fn (Blueprint $t) => $t->dropColumn(['expense_mode', 'date']));
        $this->assertSame([5], CompanyExpense::where('company_id', 1)->inDateRange('2026-10-01', '2026-10-31', false)->pluck('id')->all());
    }
}
