<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyReceiptRequest;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Requests\UpdateReceiptRequest;
use App\Models\Driver;
use App\Models\Receipt;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use App\Models\DriversBalance;
use App\Models\Company;
use App\Models\TvdeWeek;

class ReceiptController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('receipt_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $onlyPaid = url()->current() == url('/admin/receipts/paid') ? 1 : 0;

            $query = Receipt::query()
                ->where('paid', $onlyPaid)
                ->with(['driver.company', 'tvde_week'])
                ->leftJoin('tvde_weeks', 'receipts.tvde_week_id', '=', 'tvde_weeks.id')
                ->select(['receipts.*', 'tvde_weeks.start_date as tvde_week_start_date']);

            $table = DataTables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'receipt_show';
                $editGate = 'receipt_edit';
                $deleteGate = 'receipt_delete';
                $crudRoutePart = 'receipts';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', fn($row) => $row->id ?: '');
            $table->addColumn('driver_name', fn($row) => optional($row->driver)->name ?: '');
            $table->editColumn('value', fn($row) => $row->value ?: '');
            $table->editColumn('balance', fn($row) => $row->balance ?: '');

            // cálculos/HTML (mantém como tinhas)
            $table->editColumn('iva', function ($row) {
                $driver = Driver::find($row->driver->id)->load('contract_vat');
                $factor = $driver->contract_vat->iva / 100;
                return $driver ? number_format(($row->balance * $factor), 2, '.') : '';
            });
            $table->editColumn('rf', function ($row) {
                $driver = Driver::find($row->driver->id)->load('contract_vat');
                $factor = $driver->contract_vat->rf / 100;
                return $driver ? number_format(- ($row->balance * $factor), 2, '.') : '';
            });
            $table->editColumn('final', fn($row) => number_format($row->balance, 2, '.', ''));

            $table->editColumn(
                'file',
                fn($row) =>
                $row->file ? '<a href="' . $row->file->getUrl() . '" target="_blank">' . trans('global.downloadFile') . '</a>' : ''
            );
            $table->editColumn(
                'receipt_value',
                fn($row) =>
                '<input id="receipt_value-' . $row->id . '" type="number" value="' . $row->verified_value . '" ' . ($row->verified ? 'disabled' : '') . '>'
            );
            $table->editColumn(
                'verified',
                fn($row) =>
                '<input id="verified-' . $row->id . '" onclick="checkVerified(' . $row->id . ')" type="checkbox" ' . ($row->verified ? 'disabled checked' : '') . '>'
            );
            $table->editColumn(
                'paid',
                fn($row) =>
                '<input id="check-' . $row->id . '" onclick="checkPay(' . $row->id . ')" type="checkbox" ' . ($row->paid ? 'disabled checked' : '') . '>'
            );
            $table->editColumn(
                'amount_transferred',
                fn($row) =>
                '<input id="amount_transferred-' . $row->id . '" type="number" value="' . $row->amount_transferred . '" ' . ($row->verified ? 'disabled' : '') . '>'
            );
            $table->addColumn('tvde_week_start_date', fn($row) => $row->tvde_week_start_date ?: '');

            // -------- Filtros por coluna (como já tinhas) --------
            $strip = fn($v) => trim($v, '^$');

            $table->filterColumn('driver_name', function ($query, $keyword) use ($strip) {
                $kw = $strip($keyword);
                $query->whereHas('driver', fn($q) => $q->where('name', 'like', "%{$kw}%"));
            });

            $table->filterColumn('tvde_week_start_date', function ($query, $keyword) use ($strip) {
                $kw = $strip($keyword);
                $query->where('tvde_weeks.start_date', 'like', "%{$kw}%");
            });

            $table->filterColumn('verified', function ($query, $keyword) {
                if ($keyword === '1' || $keyword === '0') {
                    $query->where('receipts.verified', $keyword);
                }
            });

            $table->filterColumn('paid', function ($query, $keyword) {
                if ($keyword === '1' || $keyword === '0') {
                    $query->where('receipts.paid', $keyword);
                }
            });

            // -------- Filtro GLOBAL (caixa "Procurar") --------
            $table->filter(function ($query) use ($request) {
                $search = data_get($request->input('search'), 'value');
                if ($search !== null && $search !== '') {
                    $search = trim($search, '^$');
                    $query->where(function ($q) use ($search) {
                        $q->where('receipts.id', 'like', "%{$search}%")
                            ->orWhere('receipts.value', 'like', "%{$search}%")
                            ->orWhere('receipts.amount_transferred', 'like', "%{$search}%")
                            ->orWhere('tvde_weeks.start_date', 'like', "%{$search}%")
                            ->orWhereDate('receipts.created_at', 'like', "%{$search}%")
                            ->orWhereHas(
                                'driver',
                                fn($qq) =>
                                $qq->where('name', 'like', "%{$search}%")
                            );
                    });
                }
            });

            $table->rawColumns(['actions', 'placeholder', 'file', 'receipt_value', 'amount_transferred', 'paid', 'verified']);

            return $table->make(true);
        }

        $drivers    = Driver::orderBy('name')->get();
        $companies  = Company::all();
        $tvde_weeks = TvdeWeek::get();

        return view('admin.receipts.index', compact('drivers', 'companies', 'tvde_weeks'));
    }

    public function create()
    {
        abort_if(Gate::denies('receipt_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.receipts.create', compact('drivers', 'tvde_weeks'));
    }

    public function store(StoreReceiptRequest $request)
    {

        $tvde_week_id = session()->get('tvde_week_id');

        $receipt = Receipt::create($request->all());

        if ($request->input('file', false)) {
            $receipt->addMedia(storage_path('tmp/uploads/' . basename($request->input('file'))))->toMediaCollection('file');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $receipt->id]);
        }

        //AtualDriversBalance
        $driver_id = $request->driver_id;
        $value = $request->value;
        $drivers_balance = DriversBalance::where([
            'driver_id' => $driver_id,
            'tvde_week_id' => $tvde_week_id
        ])->first();
        $drivers_balance->drivers_balance = $value;
        $drivers_balance->save();

        return redirect()->back()->with('message', 'Recibo enviado com sucesso. Obrigado.');
    }

    public function edit(Receipt $receipt)
    {
        abort_if(Gate::denies('receipt_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $drivers = Driver::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $receipt->load('driver', 'tvde_week');
        $tvde_weeks = TvdeWeek::pluck('start_date', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.receipts.edit', compact('drivers', 'receipt', 'tvde_weeks'));
    }

    public function update(UpdateReceiptRequest $request, Receipt $receipt)
    {
        $receipt->update($request->all());

        if ($request->input('file', false)) {
            if (!$receipt->file || $request->input('file') !== $receipt->file->file_name) {
                if ($receipt->file) {
                    $receipt->file->delete();
                }
                $receipt->addMedia(storage_path('tmp/uploads/' . basename($request->input('file'))))->toMediaCollection('file');
            }
        } elseif ($receipt->file) {
            $receipt->file->delete();
        }

        return redirect()->route('admin.receipts.index');
    }

    public function show(Receipt $receipt)
    {
        abort_if(Gate::denies('receipt_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $receipt->load('driver');

        return view('admin.receipts.show', compact('receipt'));
    }

    public function destroy(Receipt $receipt)
    {
        abort_if(Gate::denies('receipt_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $receipt->delete();

        return back();
    }

    public function massDestroy(MassDestroyReceiptRequest $request)
    {
        $receipts = Receipt::find(request('ids'));

        foreach ($receipts as $receipt) {
            $receipt->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('receipt_create') && Gate::denies('receipt_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model = new Receipt();
        $model->id = $request->input('crud_id', 0);
        $model->exists = true;
        $media = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }

    public function checkPay($receipt_id)
    {
        $receipt = Receipt::find($receipt_id);
        $receipt->paid = true;
        $receipt->save();
    }

    public function checkVerified($receipt_id, $receipt_value, $amount_transferred)
    {
        $receipt = Receipt::find($receipt_id);
        $receipt->verified = true;
        $receipt->verified_value = $receipt_value;
        $receipt->amount_transferred = $amount_transferred;
        $receipt->save();

        //AtualDriversBalance
        $driver_id = $receipt->driver_id;
        $drivers_balance = DriversBalance::where([
            'driver_id' => $driver_id
        ])->orderBy('id', 'desc')->first();
        $balance = $drivers_balance->balance - $receipt_value;
        $drivers_balance->balance = $balance;
        $drivers_balance->drivers_balance = $balance;
        $drivers_balance->save();
    }
}
