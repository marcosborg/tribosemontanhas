<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\{VehicleExpensesController, CompanyExpenseController};
use App\Http\Requests\{StoreVehicleExpenseRequest, UpdateVehicleExpenseRequest, StoreCompanyExpenseRequest};
use App\Models\{VehicleExpense, CompanyExpense};
use App\Services\VehicleProfitabilityCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Tests\TestCase;

class ExpenseTotalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['vehicle_expenses', 'company_expenses'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id(); $t->timestamps(); $t->softDeletes();
                foreach (['value','vat','invoice_value','weekly_value'] as $field) $t->decimal($field,15,2)->nullable();
                foreach (['expense_type','normalized_type','date','description','expense_mode','name','start_date','end_date','group_uuid','group_label'] as $field) $t->string($field)->nullable();
                $t->integer('qty')->nullable(); $t->integer('company_id')->nullable(); $t->integer('vehicle_item_id')->nullable();
                $t->boolean('is_paid')->default(false); $t->dateTime('paid_at')->nullable();
            });
        }
        (require database_path('migrations/2026_10_15_120000_add_final_value_to_expenses.php'))->up();
    }

    public function test_vehicle_total_is_calculated_and_manual_cents_survive_update(): void
    {
        $data = ['value'=>100, 'vat'=>23, 'expense_type'=>'Outros'];
        $controller = app(VehicleExpensesController::class);
        $controller->store(StoreVehicleExpenseRequest::create('/', 'POST', $data));
        $expense = VehicleExpense::first();
        $this->assertEquals(123, $expense->display_total);
        $expense->setRelation('media', collect());
        $controller->update(UpdateVehicleExpenseRequest::create('/', 'PUT', $data + ['final_value'=>122.99]), $expense);
        $this->assertEquals(122.99, $expense->fresh()->display_total);
        $this->assertNull($expense->fresh()->invoice_value);
        $method = new \ReflectionMethod(VehicleProfitabilityCalculator::class, 'calculateExpenseTreasuryAndVat');
        $method->setAccessible(true);
        [$total, $vat] = $method->invoke(app(VehicleProfitabilityCalculator::class), $expense->fresh());
        $this->assertEquals(122.99, $total);
        $this->assertEqualsWithDelta(22.99, $vat, 0.00001);
    }

    public function test_company_payload_calculates_total_and_preserves_explicit_zero(): void
    {
        $method = new \ReflectionMethod(CompanyExpenseController::class, 'payload');
        $method->setAccessible(true);
        $data = ['expense_mode'=>'accounting','expense_type'=>'Outros','value'=>111.72,'vat'=>23];
        $controller = app(CompanyExpenseController::class);
        $payload = $method->invoke($controller, StoreCompanyExpenseRequest::create('/', 'POST', $data));
        $expense = CompanyExpense::create($payload);
        $this->assertEquals(137.42, $expense->fresh()->display_total);
        foreach ([137.41, 0] as $override) {
            $payload = $method->invoke($controller, StoreCompanyExpenseRequest::create('/', 'POST', $data + ['final_value'=>$override]));
            $expense->update($payload);
            $this->assertEquals($override, $expense->fresh()->display_total);
        }
    }

    public function test_legacy_values_and_invoice_semantics_are_not_rewritten(): void
    {
        $vehicle = VehicleExpense::create(['value'=>100, 'vat'=>23,'invoice_value'=>200]);
        $company = CompanyExpense::create(['value'=>100,'vat'=>23,'invoice_value'=>122.99]);
        $this->assertEquals(123, $vehicle->display_total);
        $this->assertEquals(122.99, $company->display_total);
        $this->assertNull($vehicle->fresh()->final_value);
        $this->assertNull($company->fresh()->final_value);
        $method = new \ReflectionMethod(VehicleProfitabilityCalculator::class, 'calculateExpenseTreasuryAndVat');
        $method->setAccessible(true);
        [$total] = $method->invoke(app(VehicleProfitabilityCalculator::class), $vehicle);
        $this->assertEquals(100, $total);
    }

    public function test_group_expenses_have_independent_totals(): void
    {
        Schema::create('vehicle_items', fn (Blueprint $t) => $t->id());
        DB::table('vehicle_items')->insert([['id'=>1], ['id'=>2]]);
        app(VehicleExpensesController::class)->store(StoreVehicleExpenseRequest::create('/', 'POST', [
            'is_group_expense'=>1, 'vehicle_item_ids'=>[1,2], 'vehicle_values'=>[1=>100,2=>200],
            'vehicle_final_values'=>[1=>122.99], 'vat'=>23, 'expense_type'=>'Outros',
        ]));
        $this->assertEquals([122.99,246], VehicleExpense::orderBy('id')->get()->pluck('display_total')->all());
        $this->assertSame(1, VehicleExpense::distinct()->count('group_uuid'));
    }

    public function test_unrelated_legacy_edit_keeps_existing_treasury_semantics(): void
    {
        $expense = VehicleExpense::create(['value'=>100,'vat'=>23,'invoice_value'=>200]);
        $expense->setRelation('media', collect());
        app(VehicleExpensesController::class)->update(UpdateVehicleExpenseRequest::create('/', 'PUT', [
            'value'=>100,'vat'=>23,'final_value'=>123,'description'=>'Descrição corrigida',
        ]), $expense);
        $this->assertNull($expense->fresh()->final_value);
        $this->assertEquals(200, $expense->fresh()->invoice_value);
    }

    public function test_invalid_money_is_rejected(): void
    {
        foreach ([new StoreVehicleExpenseRequest(), new UpdateVehicleExpenseRequest(), new StoreCompanyExpenseRequest()] as $request) {
            foreach ([-0.01, 'invalid'] as $value) {
                $rules = $request->rules();
                $this->assertTrue(Validator::make(['final_value'=>$value], ['final_value'=>$rules['final_value']])->fails());
            }
        }
    }
}
