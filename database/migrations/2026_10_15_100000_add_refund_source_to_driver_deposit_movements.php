<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('driver_deposit_movements', function (Blueprint $table) {
            // Historical refunds keep their existing statement impact until explicitly classified.
            $table->string('refund_source')->nullable();
        });

        // Imported initial receipts must precede the weekly receipts and refunds they funded.
        $depositIds = DB::table('driver_deposit_plans')->whereNull('deleted_at')
            ->whereNotNull('driver_deposit_id')->pluck('driver_deposit_id');
        foreach ($depositIds as $depositId) {
            $movements = DB::table('driver_deposit_movements as m')
                ->leftJoin('tvde_weeks as w', 'm.tvde_week_id', '=', 'w.id')
                ->where('m.driver_deposit_id', $depositId)->whereNull('m.deleted_at')
                ->orderByRaw('COALESCE(w.start_date, m.payment_date, m.created_at)')->orderBy('m.id')
                ->select('m.*')->get();
            $balance = 0;
            foreach ($movements as $movement) {
                $cents = (int) round($movement->amount * 100);
                if (in_array($movement->type, ['payment', 'adjustment'])) $balance += $cents;
                if (in_array($movement->type, ['refund', 'internal_debit', 'writeoff'])) $balance -= $cents;
                DB::table('driver_deposit_movements')->where('id', $movement->id)->update(['balance_after' => $balance / 100]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('driver_deposit_movements', fn (Blueprint $table) => $table->dropColumn('refund_source'));
    }
};
