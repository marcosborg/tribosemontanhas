<?php

namespace App\Http\Requests;

use App\Models\ExpenseReimbursement;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyExpenseReimbursementRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('expense_reimbursement_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:expense_reimbursements,id',
        ];
    }
}
