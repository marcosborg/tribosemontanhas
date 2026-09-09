<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Support\Facades\Gate;

class InactiveDriverController extends Controller
{
    public function index()
    {
        abort_unless(Gate::allows('company_expenses_menu_access') && Gate::allows('driver_access'), 403);

        $companyId = session('company_id');
        $drivers = Driver::where('state_id', 2)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->with(['latestVehicleAllocation.vehicle_item' => fn ($query) => $query->withTrashed()])
            ->orderBy('name')
            ->get();

        return view('admin.inactiveDrivers.index', compact('drivers'));
    }
}
