@extends('layouts.admin')
@section('content')
<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('cruds.vehicleUsage.title') }}
                </div>
                <div class="panel-body">
                    <div>

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="active"><a href="#home" aria-controls="home" role="tab" data-toggle="tab">Fevereiro</a></li>
                            <li role="presentation"><a href="#profile" aria-controls="profile" role="tab" data-toggle="tab">Março</a></li>
                            <li role="presentation"><a href="#messages" aria-controls="messages" role="tab" data-toggle="tab">Abril</a></li>
                            <li role="presentation"><a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">Maio</a></li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content">
                            <div role="tabpanel" class="tab-pane active" id="home">
                                <img src="https://tribosemontanhas.gestvde.pt/assets/admin/img/fevereiro.png" class="img-responsive">
                            </div>
                            <div role="tabpanel" class="tab-pane" id="profile">
                                <img src="https://tribosemontanhas.gestvde.pt/assets/admin/img/marco.png" class="img-responsive">
                            </div>
                            <div role="tabpanel" class="tab-pane" id="messages">
                                <img src="https://tribosemontanhas.gestvde.pt/assets/admin/img/abril.png" class="img-responsive">
                            </div>
                            <div role="tabpanel" class="tab-pane" id="settings">
                                <img src="https://tribosemontanhas.gestvde.pt/assets/admin/img/maio.png" class="img-responsive">
                            </div>
                        </div>

                    </div>
                </div>
            </div>



        </div>
    </div>
</div>
@endsection
