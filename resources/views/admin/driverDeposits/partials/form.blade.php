@if($driverDeposit && !$driverDeposit->plan)
    @include('admin.driverDeposits.partials.legacyForm')
@else
    @include('admin.driverDeposits.partials.installmentForm')
@endif
