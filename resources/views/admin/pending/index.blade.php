@extends('layouts.admin')

@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Pendentes de documentos
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover datatable datatable-PendingDocuments">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Matricula</th>
                                    <th>Documento</th>
                                    <th>Data de expiracao</th>
                                    <th>Estado</th>
                                    <th>&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $item)
                                    <tr class="{{ $item['status'] === 'expired' ? 'danger' : ($item['status'] === 'urgent' ? 'warning' : '') }}">
                                        <td></td>
                                        <td>{{ $item['license_plate'] }}</td>
                                        <td>{{ $item['label'] }}</td>
                                        <td>{{ $item['date']->format('Y-m-d') }}</td>
                                        <td>
                                            @if($item['status'] === 'expired')
                                                <span class="label label-danger">Expirado ha {{ abs($item['days']) }} dias</span>
                                            @elseif($item['status'] === 'urgent')
                                                <span class="label label-warning">Faltam {{ $item['days'] }} dias</span>
                                            @else
                                                <span class="label label-info">Faltam {{ $item['days'] }} dias</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('vehicle_item_edit')
                                                <a class="btn btn-xs btn-info" href="{{ route('admin.vehicle-items.edit', $item['vehicle_id']) }}">
                                                    Entrar na viatura
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($documents->isEmpty())
                        <p class="text-muted">Nao existem documentos pendentes.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Nova tarefa
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.pendentes.tasks.store') }}">
                        @csrf
                        <div class="form-group {{ $errors->has('title') ? 'has-error' : '' }}">
                            <label class="required" for="title">Titulo</label>
                            <input class="form-control" type="text" name="title" id="title" value="{{ old('title') }}" required>
                            @if($errors->has('title'))
                                <span class="help-block" role="alert">{{ $errors->first('title') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('due_date') ? 'has-error' : '' }}">
                            <label for="due_date">Data de conclusao</label>
                            <input class="form-control" type="date" name="due_date" id="due_date" value="{{ old('due_date') }}">
                            @if($errors->has('due_date'))
                                <span class="help-block" role="alert">{{ $errors->first('due_date') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
                            <label for="description">Descricao</label>
                            <textarea class="form-control" name="description" id="description" rows="4">{{ old('description') }}</textarea>
                            @if($errors->has('description'))
                                <span class="help-block" role="alert">{{ $errors->first('description') }}</span>
                            @endif
                        </div>
                        <button class="btn btn-danger" type="submit">Guardar tarefa</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Todo-list
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover datatable datatable-PendingTasks">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Tarefa</th>
                                    <th>Data de conclusao</th>
                                    <th>Estado</th>
                                    <th>&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $item)
                                    <tr class="{{ $item['status'] === 'expired' ? 'danger' : ($item['status'] === 'urgent' ? 'warning' : '') }}">
                                        <td></td>
                                        <td>
                                            <strong>{{ $item['label'] }}</strong>
                                            @if($item['description'])
                                                <div class="text-muted">{{ $item['description'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $item['date'] ? $item['date']->format('Y-m-d') : '' }}</td>
                                        <td>
                                            @if($item['date'])
                                                @if($item['status'] === 'expired')
                                                    <span class="label label-danger">Atrasada ha {{ abs($item['days']) }} dias</span>
                                                @elseif($item['status'] === 'urgent')
                                                    <span class="label label-warning">Faltam {{ $item['days'] }} dias</span>
                                                @else
                                                    <span class="label label-info">Faltam {{ $item['days'] }} dias</span>
                                                @endif
                                            @else
                                                <span class="label label-default">Sem data</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.pendentes.tasks.complete', $item['task']) }}" style="display:inline-block">
                                                @csrf
                                                <button class="btn btn-xs btn-success" type="submit">Concluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($tasks->isEmpty())
                        <p class="text-muted">Nao existem tarefas abertas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
    $(function () {
        $('.datatable-PendingDocuments').DataTable({ order: [[3, 'asc'], [1, 'asc']], pageLength: 50 });
        $('.datatable-PendingTasks').DataTable({ order: [[2, 'asc']], pageLength: 50 });
    });
</script>
@endsection
