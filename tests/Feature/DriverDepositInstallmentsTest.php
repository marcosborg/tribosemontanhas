<?php

namespace Tests\Feature;

use App\Models\{CurrentAccount, DriverDeposit, DriverDepositMovement, DriverDepositPlanItem};
use App\Services\{DriverDepositInstallmentService, DriverDepositPlanningService, DriverDepositService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DriverDepositInstallmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['drivers', 'companies', 'users', 'roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->string('name')->nullable(); $table->string('title')->nullable();
                $table->timestamps(); $table->softDeletes();
            });
        }
        Schema::create('permission_role', function (Blueprint $table) { $table->integer('role_id'); $table->integer('permission_id'); });
        Schema::create('tvde_weeks', function (Blueprint $table) {
            $table->id(); $table->date('start_date'); $table->date('end_date'); $table->softDeletes();
        });
        Schema::create('current_accounts', function (Blueprint $table) {
            $table->id(); $table->integer('driver_id'); $table->integer('tvde_week_id');
            $table->text('data')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        foreach (['2026_04_29_130000_create_driver_deposits_tables', '2026_05_21_100000_create_driver_deposit_planning_tables',
            '2026_09_17_080000_add_payment_date_to_driver_deposit_movements', '2026_09_25_120000_link_deposit_installments'] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
        DB::table('drivers')->insert([['id' => 1, 'name' => 'Motorista A'], ['id' => 2, 'name' => 'Motorista B']]);
        DB::table('companies')->insert([['id' => 1, 'name' => 'Empresa A'], ['id' => 2, 'name' => 'Empresa B']]);
        for ($i = 1; $i <= 4; $i++) DB::table('tvde_weeks')->insert([
            'id' => $i, 'start_date' => '2026-10-' . str_pad($i * 7 - 6, 2, '0', STR_PAD_LEFT),
            'end_date' => '2026-10-' . str_pad($i * 7, 2, '0', STR_PAD_LEFT),
        ]);
    }

    private function data(): array
    {
        return [
            'driver_id' => 1, 'company_id' => 1, 'total_amount' => '600.00', 'status' => 'active',
            'initial_payments' => [
                ['amount' => '100.00', 'payment_date' => '2026-09-20', 'payment_method' => 'Transferência'],
                ['amount' => '200.00', 'payment_date' => '2026-09-25', 'payment_method' => 'Numerário'],
            ],
            'weekly_payments' => [
                ['amount' => '100.00', 'tvde_week_id' => 1], ['amount' => '150.00', 'tvde_week_id' => 2],
                ['amount' => '50.00', 'tvde_week_id' => 3],
            ],
        ];
    }

    private function save(array $data, ?DriverDeposit $deposit = null): DriverDeposit
    {
        return app(DriverDepositInstallmentService::class)->save(Request::create('/', 'POST', $data), $deposit);
    }

    private function editableData(DriverDeposit $deposit): array
    {
        $data = $this->data();
        foreach (['initial' => 'initial_payments', 'weekly' => 'weekly_payments'] as $kind => $field) {
            foreach ($deposit->plan->items()->where('kind', $kind)->orderBy('id')->get() as $index => $item) {
                $data[$field][$index]['id'] = $item->id;
            }
        }
        return $data;
    }

    public function test_creation_validation_revalidation_and_reversal_never_double_count(): void
    {
        $deposit = $this->save($this->data());
        $service = app(DriverDepositInstallmentService::class);
        $ledger = app(DriverDepositService::class);
        $this->assertEquals(600, $deposit->plan->items()->sum('amount'));
        $this->assertEquals(300, $deposit->plan->items()->sum('paid_amount'));
        $this->assertEquals(300, $ledger->availableBalance($deposit));
        $this->assertSame(2, $deposit->movements()->where('type', 'payment')->count());
        foreach ([1 => -100, 2 => -150, 3 => -50] as $week => $expected) {
            $this->assertEquals($expected, $ledger->statementImpact($ledger->statementMovementsForWeek(1, 1, $week)));
        }
        $ids = $ledger->statementMovementsForWeek(1, 1, 1)->pluck('id')->all();
        $account = CurrentAccount::create(['driver_id' => 1, 'tvde_week_id' => 1]);
        $service->confirmWeek(1, 1, 1, $ids);
        $service->confirmWeek(1, 1, 1, $ids);
        $this->assertEquals(400, $ledger->availableBalance($deposit));
        $this->assertSame(3, $deposit->movements()->where('type', 'payment')->count());
        $row = app(DriverDepositPlanningService::class)->reconciliationRows()->first();
        $this->assertEquals(400, $row['received']); $this->assertEquals(200, $row['debt']);
        $this->assertEquals(-100, $ledger->statementImpact($ledger->statementMovementsForWeek(1, 1, 1)));
        $account->delete(); $service->reverseWeek(1, 1); $service->reverseWeek(1, 1);
        $this->assertEquals(300, $ledger->availableBalance($deposit));
        $this->assertSame(2, $deposit->movements()->where('type', 'payment')->count());
        $account->restore(); $service->confirmWeek(1, 1, 1, $ids);
        $this->assertEquals(400, $ledger->availableBalance($deposit));
        $this->assertSame(3, $deposit->movements()->withTrashed()->where('type', 'payment')->count());
    }

    public function test_future_edit_keeps_ids_and_preserves_initial_receipts(): void
    {
        $deposit = $this->save($this->data()); $data = $this->editableData($deposit);
        $ids = $deposit->movements()->pluck('id')->all();
        $data['weekly_payments'][1]['amount'] = '125.00'; $data['weekly_payments'][2]['amount'] = '75.00';
        $this->save($data, $deposit);
        $this->assertSame($ids, $deposit->movements()->pluck('id')->all());
        $this->assertEquals(-125, app(DriverDepositService::class)->statementImpact(app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 2)));
        $data['initial_payments'][0]['payment_method'] = 'Outro';
        $this->expectException(ValidationException::class); $this->save($data, $deposit);
    }

    public function test_validated_week_cannot_be_edited(): void
    {
        $deposit = $this->save($this->data()); $data = $this->editableData($deposit);
        CurrentAccount::create(['driver_id' => 1, 'tvde_week_id' => 1]);
        $data['weekly_payments'][0]['amount'] = 90; $data['weekly_payments'][1]['amount'] = 160;
        $this->expectException(ValidationException::class); $this->save($data, $deposit);
    }

    /** @dataProvider invalidRows */
    public function test_invalid_data_does_not_leave_partial_records(string $case): void
    {
        $data = $this->data();
        if ($case === 'sum') $data['total_amount'] = 601;
        if ($case === 'duplicate') $data['weekly_payments'][1]['tvde_week_id'] = 1;
        if ($case === 'date') $data['initial_payments'][0]['payment_date'] = '';
        if ($case === 'negative') $data['weekly_payments'][0]['amount'] = -100;
        if ($case === 'precision') $data['initial_payments'][0]['amount'] = '100.001';
        if ($case === 'foreign-id') $data['weekly_payments'][0]['id'] = 999;
        try { $this->save($data); $this->fail('Expected validation error'); }
        catch (ValidationException $e) { $this->assertSame(0, DriverDeposit::count()); }
    }

    public static function invalidRows(): array
    {
        return array_map(fn ($case) => [$case], ['sum', 'duplicate', 'date', 'negative', 'precision', 'foreign-id']);
    }

    public function test_all_initial_all_weekly_and_cents(): void
    {
        $data = $this->data(); $data['weekly_payments'] = []; $data['total_amount'] = 300;
        $deposit = $this->save($data); $this->assertSame('completed', $deposit->status);
        $data = $this->data(); $data['initial_payments'] = []; $data['total_amount'] = '300.01';
        $data['weekly_payments'][2]['amount'] = '50.01';
        $deposit = $this->save($data); $this->assertSame('active', $deposit->status);
        $this->assertEquals(0, app(DriverDepositService::class)->availableBalance($deposit));
    }

    public function test_pause_close_and_refund_only_use_received_money(): void
    {
        $deposit = $this->save($this->data()); $service = app(DriverDepositInstallmentService::class);
        $deposit->plan->update(['status' => 'paused']); $service->syncSchedule($deposit);
        $this->assertCount(0, app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1));
        $deposit->plan->update(['status' => 'active']); $service->syncSchedule($deposit);
        $this->assertCount(1, app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1));
        app(DriverDepositService::class)->createRefund($deposit, 1, 100, 'Devolução');
        $this->assertEquals(200, app(DriverDepositService::class)->availableBalance($deposit));
        $data = $this->editableData($deposit); $data['status'] = 'closed'; $this->save($data, $deposit);
        $this->assertSame(3, $deposit->plan->items()->where('status', 'cancelled')->count());
        $this->expectException(ValidationException::class);
        app(DriverDepositService::class)->createRefund($deposit, 1, 201, null);
    }

    public function test_foreign_driver_cannot_confirm_and_manual_receipt_survives_reversal(): void
    {
        $deposit = $this->save($this->data()); $service = app(DriverDepositInstallmentService::class);
        $ids = app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1)->pluck('id')->all();
        $service->confirmWeek(2, 1, 1, $ids); $service->confirmWeek(1, 2, 1, $ids);
        $this->assertEquals(300, app(DriverDepositService::class)->availableBalance($deposit));
        app(DriverDepositPlanningService::class)->recordMovement([
            'driver_deposit_id' => $deposit->id, 'driver_id' => 1, 'company_id' => 1,
            'type' => 'payment', 'amount' => 25, 'payment_date' => '2026-09-26',
        ]);
        $this->assertEquals(-75, app(DriverDepositService::class)->statementImpact(app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1)));
        $account = CurrentAccount::create(['driver_id' => 1, 'tvde_week_id' => 1]);
        $service->confirmWeek(1, 1, 1, $ids); $account->delete(); $service->reverseWeek(1, 1);
        $this->assertEquals(325, app(DriverDepositService::class)->availableBalance($deposit));
    }

    public function test_legacy_schedule_keeps_existing_behavior(): void
    {
        $deposit = DriverDeposit::create(['driver_id' => 1, 'company_id' => 1, 'total_amount' => 600, 'initial_payment' => 300, 'weekly_amount' => 100, 'status' => 'active']);
        app(DriverDepositService::class)->syncPlannedMovements($deposit, [1, 2, 3]);
        $this->assertNull($deposit->plan);
        $this->assertEquals(600, app(DriverDepositService::class)->availableBalance($deposit));
        $this->assertSame(4, $deposit->movements()->count());
    }

    public function test_company_report_controller_confirms_and_reverses_in_same_workflow(): void
    {
        Schema::table('drivers', fn (Blueprint $table) => $table->integer('company_id')->default(1));
        Schema::create('drivers_balances', function (Blueprint $table) {
            $table->id(); $table->integer('driver_id'); $table->integer('tvde_week_id');
            $table->decimal('value', 15, 2); $table->decimal('balance', 15, 2); $table->decimal('drivers_balance', 15, 2);
            $table->timestamps(); $table->softDeletes();
        });
        $deposit = $this->save($this->data());
        $controller = new class extends \App\Http\Controllers\Admin\CompanyReportController {
            public function filter($state_id = 1) { return ['company_id' => 1, 'tvde_week_id' => 1]; }
            protected function buildFuelDetailsPayload($driver): array { return []; }
            public function getWeekReport($company_id, $tvde_week_id) {
                $movements = app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1);
                return ['drivers' => collect([(object) ['id' => 1, 'total' => 900, 'earnings' => collect(['total' => 900]), 'deposit_movements' => $movements]])];
            }
        };
        $request = Request::create('/', 'POST', ['data' => [['tvde_week_id' => 1, 'driver' => ['id' => 1, 'total' => 900]]]]);
        $controller->validateData($request);
        $controller->revalidateData(Request::create('/', 'POST', ['driver_id' => 1, 'tvde_week_id' => 1, 'data' => []]));
        $this->assertEquals(400, app(DriverDepositService::class)->availableBalance($deposit));
        $this->assertSame(1, CurrentAccount::count());
        $controller->deleteData(1, 1);
        $this->assertEquals(300, app(DriverDepositService::class)->availableBalance($deposit));
        $this->assertSame(0, CurrentAccount::count());
    }

    public function test_integrated_partial_views_render_saved_rows_and_escape_payment_method(): void
    {
        $data = $this->data(); $data['initial_payments'][0]['payment_method'] = '<script>alert(1)</script>';
        $deposit = $this->save($data);
        $html = view('admin.driverDeposits.partials.installmentForm', [
            'driverDeposit' => $deposit, 'drivers' => \App\Models\Driver::all(), 'companies' => \App\Models\Company::all(),
            'tvdeWeeks' => \App\Models\TvdeWeek::all(), 'statuses' => DriverDeposit::STATUS_SELECT,
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ])->render();
        $this->assertStringContainsString('initial_payments[0][id]', $html);
        $this->assertStringContainsString('weekly_payments[2][amount]', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $summary = view('admin.driverDeposits.partials.installmentSummary', ['plan' => $deposit->plan])->render();
        $this->assertStringContainsString('150,00', $summary);
        $this->assertStringContainsString('20/09/2026', $summary);
    }

    public function test_legacy_and_integrated_reconciliation_are_added_without_double_counting(): void
    {
        $this->save($this->data());
        $plan = \App\Models\DriverDepositPlan::create(['driver_id' => 1, 'company_id' => 1, 'initial_amount' => 50, 'weekly_amount' => 0, 'total_weeks' => 0]);
        $plan->items()->create(['amount' => 50, 'paid_amount' => 50, 'status' => 'paid']);
        $row = app(DriverDepositPlanningService::class)->reconciliationRows()->first();
        $this->assertEquals(650, $row['planned']);
        $this->assertEquals(350, $row['received']);
        $this->assertEquals(300, $row['debt']);
    }

    public function test_delete_guards_preserve_receipts_but_allow_uncollected_deposit_removal(): void
    {
        \Illuminate\Support\Facades\Gate::before(fn (?\App\Models\User $user) => true);
        $controller = app(\App\Http\Controllers\Admin\DriverDepositController::class);
        $received = $this->save($this->data());
        $controller->destroy($received);
        $this->assertNotNull($received->fresh());
        $data = $this->data(); $data['initial_payments'] = []; $data['total_amount'] = 300;
        $uncollected = $this->save($data);
        $controller->destroy($uncollected);
        $this->assertTrue($uncollected->fresh()->trashed());
        $this->assertCount(1, app(DriverDepositService::class)->statementMovementsForWeek(1, 1, 1));
    }

    public function test_linked_plan_cannot_be_regenerated_or_backfilled(): void
    {
        $deposit = $this->save($this->data());
        $this->artisan('driver-deposits:backfill-planning', ['--force' => true])->assertExitCode(0);
        $this->assertSame(1, \App\Models\DriverDepositPlan::count());
        $this->expectException(ValidationException::class);
        app(DriverDepositPlanningService::class)->generateItems($deposit->plan);
    }
}
