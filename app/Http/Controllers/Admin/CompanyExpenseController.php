<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyCompanyExpenseRequest;
use App\Http\Requests\StoreCompanyExpenseRequest;
use App\Http\Requests\UpdateCompanyExpenseRequest;
use App\Models\Company;
use App\Models\CompanyExpense;
use App\Services\AccountingCompanyExpenseImporter;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class CompanyExpenseController extends Controller
{
    use CsvImportTrait, MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('company_expense_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $accountingReady = Schema::hasColumn('company_expenses', 'expense_mode');

        if ($request->ajax()) {
            if (session()->has('company_id') && session()->get('company_id') !== '0') {
                $query = CompanyExpense::where('company_id', session()->get('company_id'))->with(['company'])->select(sprintf('%s.*', (new CompanyExpense)->table));
            } else {
                $query = CompanyExpense::with(['company'])->select(sprintf('%s.*', (new CompanyExpense)->table));
            }

            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) use ($accountingReady) {
                $viewGate = 'company_expense_show';
                $editGate = 'company_expense_edit';
                $deleteGate = 'company_expense_delete';
                $crudRoutePart = 'company-expenses';

                $actions = view(
                    'partials.datatablesActions',
                    compact(
                        'viewGate',
                        'editGate',
                        'deleteGate',
                        'crudRoutePart',
                        'row'
                    )
                )->render();

                if ($accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING && !$row->is_paid && Gate::allows('company_expense_edit')) {
                    $actions .= sprintf(
                        '<form action="%s" method="POST" style="display:inline-block">%s<button class="btn btn-xs btn-success" type="submit">Marcar pago</button></form>',
                        route('admin.company-expenses.mark-paid', $row->id),
                        csrf_field()
                    );
                }

                return $actions;
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('name', function ($row) use ($accountingReady) {
                return $accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING
                    ? (CompanyExpense::EXPENSE_TYPE_RADIO[$row->expense_type] ?? $row->expense_type)
                    : ($row->name ?: '');
            });
            $table->addColumn('company_name', function ($row) {
                return $row->company ? $row->company->name : '';
            });

            $table->editColumn('weekly_value', function ($row) use ($accountingReady) {
                return $accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING ? $row->value : $row->weekly_value;
            });
            $table->editColumn('expense_mode', fn ($row) => $accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING ? 'Contabilidade' : 'Recorrente');
            $table->editColumn('date', fn ($row) => $accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING ? $row->date : $row->start_date . ' — ' . $row->end_date);
            $table->editColumn('is_paid', fn ($row) => $accountingReady && $row->expense_mode === CompanyExpense::MODE_ACCOUNTING ? ($row->is_paid ? 'Pago' : 'Por pagar') : '—');
            $table->addColumn('files', fn ($row) => $row->files->map(fn ($media) => sprintf('<a href="%s" target="_blank">%s</a>', $media->getUrl(), e($media->file_name)))->implode('<br>'));

            $table->rawColumns(['actions', 'placeholder', 'company', 'files']);

            return $table->make(true);
        }

        $unpaidCount = $accountingReady
            ? CompanyExpense::where('expense_mode', CompanyExpense::MODE_ACCOUNTING)->where('is_paid', false)->count()
            : 0;
        $companies = Company::orderBy('name')->pluck('name', 'id');

        return view('admin.companyExpenses.index', compact('unpaidCount', 'accountingReady', 'companies'));
    }

    public function create()
    {
        abort_if(Gate::denies('company_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.companyExpenses.create', compact('companies'));
    }

    public function store(StoreCompanyExpenseRequest $request)
    {
        $payload = $this->payload($request);
        $companyExpense = CompanyExpense::create($payload);

        foreach ($request->input('files', []) as $file) {
            $companyExpense->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('files');
        }
        foreach ($request->file('documents', []) as $file) {
            $companyExpense->addMedia($file)->toMediaCollection('files');
        }
        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $companyExpense->id]);
        }

        return redirect()->route('admin.company-expenses.index');
    }

    public function edit(CompanyExpense $companyExpense)
    {
        abort_if(Gate::denies('company_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $companyExpense->load('company');

        return view('admin.companyExpenses.edit', compact('companies', 'companyExpense'));
    }

    public function update(UpdateCompanyExpenseRequest $request, CompanyExpense $companyExpense)
    {
        $companyExpense->update($this->payload($request, $companyExpense));

        foreach ($request->file('documents', []) as $file) {
            $companyExpense->addMedia($file)->toMediaCollection('files');
        }

        return redirect()->route('admin.company-expenses.index');
    }

    public function show(CompanyExpense $companyExpense)
    {
        abort_if(Gate::denies('company_expense_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyExpense->load('company');

        return view('admin.companyExpenses.show', compact('companyExpense'));
    }

    public function destroy(CompanyExpense $companyExpense)
    {
        abort_if(Gate::denies('company_expense_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyExpense->delete();

        return back();
    }

    public function massDestroy(MassDestroyCompanyExpenseRequest $request)
    {
        $companyExpenses = CompanyExpense::find(request('ids'));

        foreach ($companyExpenses as $companyExpense) {
            $companyExpense->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function markPaid(CompanyExpense $companyExpense)
    {
        abort_if(Gate::denies('company_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $companyExpense->update(['is_paid' => true, 'paid_at' => now()]);
        return redirect()->route('admin.company-expenses.index');
    }

    public function importAccounting(Request $request, AccountingCompanyExpenseImporter $importer)
    {
        abort_if(Gate::denies('company_expense_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $request->validate([
            'accounting_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'accounting_file' => ['required', 'file', function ($attribute, $file, $fail) {
                if (!in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt', 'xls', 'xlsx'], true)) $fail('O ficheiro deve ser CSV, TXT, XLS ou XLSX.');
            }],
        ]);

        try {
            $file = $request->file('accounting_file');
            $selectedCompanyId = $request->filled('accounting_company_id')
                ? (int) $request->input('accounting_company_id')
                : (session('company_id') && session('company_id') !== '0' ? (int) session('company_id') : null);
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalName(), $selectedCompanyId);
            return redirect()->route('admin.company-expenses.index')
                ->with('message', 'Importacao concluida: ' . $result['imported'] . ' despesas importadas.')
                ->with('companyExpenseImportReport', $result);
        } catch (\Throwable $exception) {
            return redirect()->route('admin.company-expenses.index')->withErrors(['accounting_file' => $exception->getMessage()]);
        }
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('company_expense_create') && Gate::denies('company_expense_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $model = new CompanyExpense();
        $model->id = $request->input('crud_id', 0);
        $model->exists = true;
        $media = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');
        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }

    private function payload(Request $request, ?CompanyExpense $existing = null): array
    {
        $payload = $request->all();
        if ($request->input('expense_mode') === CompanyExpense::MODE_ACCOUNTING) {
            $payload['name'] = $request->input('expense_type');
            $payload['weekly_value'] = $request->input('value');
            $payload['start_date'] = $request->input('date');
            $payload['end_date'] = $request->input('date');
            $payload['qty'] = 1;
            $wasPaid = (bool) optional($existing)->is_paid;
            $payload['is_paid'] = $request->boolean('is_paid');
            $payload['paid_at'] = $payload['is_paid'] ? ($wasPaid ? $existing->paid_at : now()) : null;
        } else {
            $payload['expense_type'] = null;
            $payload['date'] = null;
            $payload['value'] = null;
            $payload['invoice_value'] = null;
            $payload['is_paid'] = false;
            $payload['paid_at'] = null;
        }
        return $payload;
    }
}
