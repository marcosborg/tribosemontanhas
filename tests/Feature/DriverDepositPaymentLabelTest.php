<?php
namespace Tests\Feature;

use App\Models\{DriverDepositMovement, DriverDepositPlanItem};
use Tests\TestCase;

class DriverDepositPaymentLabelTest extends TestCase
{
    public function test_status_uses_payment_records_and_plan_state_not_the_date(): void
    {
        $movement = new DriverDepositMovement(['type' => 'weekly_charge']);
        foreach (['pending' => 'Por pagar', 'paid' => 'Pago', 'cancelled' => 'Cancelado'] as $status => $label) {
            $movement->setRelation('plan_item', new DriverDepositPlanItem(['status' => $status, 'paid_amount' => 0, 'due_date' => '2020-01-01']));
            $this->assertSame($label, $movement->payment_status_label);
        }
        $movement->setRelation('plan_item', new DriverDepositPlanItem(['status' => 'pending', 'paid_amount' => 50]));
        $this->assertSame('Parcialmente pago', $movement->payment_status_label);
        $movement->type = 'payment';
        $this->assertSame('Pago', $movement->payment_status_label);
        $movement->type = 'refund';
        $this->assertSame('—', $movement->payment_status_label);
        $movement->type = 'weekly_charge';
        $movement->setRelation('plan_item', null);
        $this->assertSame('Pago', $movement->payment_status_label);
    }
}
