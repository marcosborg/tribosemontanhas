<?php
namespace Tests\Feature;

use App\Http\Controllers\Admin\ReimbursementController;
use App\Models\Reimbursement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReimbursementPaymentDateTest extends TestCase
{
    public function test_payment_date_is_independent_of_verification_value_and_week(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id(); $table->decimal('value', 15, 2); $table->boolean('verified');
            $table->integer('tvde_week_id'); $table->timestamps(); $table->softDeletes();
        });
        (require database_path('migrations/2026_11_19_110000_add_payment_date_to_reimbursements.php'))->up();
        Gate::define('reimbursement_edit', fn ($user = null) => true);
        $entry = Reimbursement::create(['value' => 263.46, 'verified' => 1, 'tvde_week_id' => 1]);
        $controller = app(ReimbursementController::class);
        foreach (['2026-11-19', '2026-10-01', null] as $date) {
            $controller->updatePaymentDate(Request::create('/', 'PATCH', ['payment_date' => $date]), $entry);
            $entry->refresh();
            $this->assertSame($date, $entry->payment_date?->format('Y-m-d'));
            $this->assertEquals(263.46, $entry->value);
            $this->assertEquals(1, $entry->verified);
            $this->assertEquals(1, $entry->tvde_week_id);
        }
        $this->expectException(ValidationException::class);
        $controller->updatePaymentDate(Request::create('/', 'PATCH', ['payment_date' => '2026-02-30']), $entry);
    }
}
