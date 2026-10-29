<?php

namespace Tests\Feature;

use App\Models\{VehicleExpense, CompanyExpense};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class ExpenseCategoriesTest extends TestCase
{
    public function test_categories_are_shared_and_old_inputs_are_normalized(): void
    {
        $this->assertArrayNotHasKey('Acessórios', VehicleExpense::EXPENSE_TYPE_RADIO);
        $this->assertArrayNotHasKey('Outros', VehicleExpense::EXPENSE_TYPE_RADIO);
        $this->assertArrayHasKey('Vários/outros', CompanyExpense::EXPENSE_TYPE_RADIO);
        foreach ([VehicleExpense::class, CompanyExpense::class] as $class) {
            foreach (['Acessórios', 'Outros', 'Vários / Outros', 'Vários/outros'] as $type) {
                $this->assertSame('Vários/outros', (new $class(['expense_type'=>$type]))->expense_type);
            }
            $this->assertSame('Seguro', (new $class(['expense_type'=>'Seguro']))->expense_type);
        }
    }

    public function test_migration_preserves_values_and_includes_deleted_history(): void
    {
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        foreach (['vehicle_expenses','company_expenses'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->string('expense_type'); $t->string('name');
                $t->decimal('value',15,2); $t->softDeletes();
            });
            foreach (['Acessórios','Outros','Seguro'] as $i=>$type) {
                DB::table($table)->insert(['expense_type'=>$type,'name'=>$type,'value'=>123.45,'deleted_at'=>$i===1 ? '2026-10-01 00:00:00' : null]);
            }
        }
        $migration = require database_path('migrations/2026_10_29_100000_unify_miscellaneous_expense_categories.php');
        $migration->up();
        $migration = require database_path('migrations/2026_10_29_130000_normalize_miscellaneous_category_label.php');
        $migration->up(); $migration->up();
        foreach (['vehicle_expenses','company_expenses'] as $table) {
            $this->assertSame(['Vários/outros','Vários/outros','Seguro'], DB::table($table)->orderBy('id')->pluck('expense_type')->all());
            $this->assertEquals(370.35, DB::table($table)->sum('value'));
            $this->assertSame(1, DB::table($table)->whereNotNull('deleted_at')->count());
        }
        $this->assertSame('Vários/outros', DB::table('company_expenses')->value('name'));
    }
}
