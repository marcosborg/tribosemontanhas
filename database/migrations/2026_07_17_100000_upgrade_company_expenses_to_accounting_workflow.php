<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_expenses', function (Blueprint $table) {
            $table->string('expense_mode')->default('recurring')->after('company_id');
            $table->string('expense_type')->nullable()->after('expense_mode');
            $table->date('date')->nullable()->after('expense_type');
            $table->text('description')->nullable()->after('date');
            $table->decimal('value', 15, 2)->nullable()->after('description');
            $table->decimal('invoice_value', 15, 2)->nullable()->after('value');
            $table->decimal('vat', 15, 2)->default(23)->after('invoice_value');
            $table->boolean('is_paid')->default(false)->after('vat');
            $table->timestamp('paid_at')->nullable()->after('is_paid');
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->string('pay_to')->nullable()->after('payment_reference');

            $table->index(['company_id', 'expense_mode', 'date'], 'company_expenses_accounting_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('company_expenses', function (Blueprint $table) {
            $table->dropIndex('company_expenses_accounting_lookup');
            $table->dropColumn([
                'expense_mode',
                'expense_type',
                'date',
                'description',
                'value',
                'invoice_value',
                'vat',
                'is_paid',
                'paid_at',
                'payment_reference',
                'pay_to',
            ]);
        });
    }
};
