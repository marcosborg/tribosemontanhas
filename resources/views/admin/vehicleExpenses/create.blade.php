@extends('layouts.admin')
@section('content')
<div class="content">
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('global.create') }} {{ trans('cruds.vehicleExpense.title_singular') }}
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.vehicle-expenses.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group {{ $errors->has('is_group_expense') ? 'has-error' : '' }}">
                            <div>
                                <input type="hidden" name="is_group_expense" value="0">
                                <input type="checkbox" name="is_group_expense" id="is_group_expense" value="1" {{ old('is_group_expense', 0) == 1 ? 'checked' : '' }}>
                                <label for="is_group_expense" style="font-weight: 400">Despesa em grupo</label>
                            </div>
                            @if($errors->has('is_group_expense'))
                                <span class="help-block" role="alert">{{ $errors->first('is_group_expense') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('group_label') ? 'has-error' : '' }}" id="group_label_wrapper">
                            <label for="group_label">Referencia do grupo</label>
                            <input class="form-control" type="text" name="group_label" id="group_label" value="{{ old('group_label', '') }}">
                            @if($errors->has('group_label'))
                                <span class="help-block" role="alert">{{ $errors->first('group_label') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('vehicle_item') ? 'has-error' : '' }}" id="single_vehicle_wrapper">
                            <label for="vehicle_item_id">{{ trans('cruds.vehicleExpense.fields.vehicle_item') }}</label>
                            <select class="form-control select2" name="vehicle_item_id" id="vehicle_item_id">
                                @foreach($vehicle_items as $id => $entry)
                                    <option value="{{ $id }}" {{ old('vehicle_item_id') == $id ? 'selected' : '' }}>{{ $entry }}</option>
                                @endforeach
                            </select>
                            @if($errors->has('vehicle_item'))
                                <span class="help-block" role="alert">{{ $errors->first('vehicle_item') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.vehicle_item_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('vehicle_item_ids') ? 'has-error' : '' }}" id="group_vehicles_wrapper">
                            <label for="vehicle_item_ids">Viaturas</label>
                            <select class="form-control select2" name="vehicle_item_ids[]" id="vehicle_item_ids" multiple>
                                @foreach($vehicle_items as $id => $entry)
                                    @if($id !== '')
                                        <option value="{{ $id }}" {{ in_array($id, old('vehicle_item_ids', [])) ? 'selected' : '' }}>{{ $entry }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @if($errors->has('vehicle_item_ids'))
                                <span class="help-block" role="alert">{{ $errors->first('vehicle_item_ids') }}</span>
                            @endif
                            <span class="help-block">Selecione pelo menos duas viaturas.</span>
                        </div>
                        <div class="form-group {{ $errors->has('expense_type') ? 'has-error' : '' }}">
                            <label class="required">{{ trans('cruds.vehicleExpense.fields.expense_type') }}</label>
                            @foreach(App\Models\VehicleExpense::EXPENSE_TYPE_RADIO as $key => $label)
                                <div>
                                    <input type="radio" id="expense_type_{{ $key }}" name="expense_type" value="{{ $key }}" {{ old('expense_type', array_key_first(App\Models\VehicleExpense::EXPENSE_TYPE_RADIO)) === (string) $key ? 'checked' : '' }} required>
                                    <label for="expense_type_{{ $key }}" style="font-weight: 400">{{ $label }}</label>
                                </div>
                            @endforeach
                            @if($errors->has('expense_type'))
                                <span class="help-block" role="alert">{{ $errors->first('expense_type') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.expense_type_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('date') ? 'has-error' : '' }}">
                            <label class="required" for="date">{{ trans('cruds.vehicleExpense.fields.date') }}</label>
                            <input class="form-control date" type="text" name="date" id="date" value="{{ old('date') }}" required>
                            @if($errors->has('date'))
                                <span class="help-block" role="alert">{{ $errors->first('date') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.date_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
                            <label for="description">{{ trans('cruds.vehicleExpense.fields.description') }}</label>
                            <textarea class="form-control ckeditor" name="description" id="description">{!! old('description') !!}</textarea>
                            @if($errors->has('description'))
                                <span class="help-block" role="alert">{{ $errors->first('description') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.description_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('files') ? 'has-error' : '' }}">
                            <label for="files">Fatura / documentos</label>
                            <div class="needsclick dropzone" id="files-dropzone"></div>
                            @if($errors->has('files'))
                                <span class="help-block" role="alert">{{ $errors->first('files') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.files_helper') }}</span>
                        </div>
                        <div class="form-group {{ $errors->has('payment_reference') ? 'has-error' : '' }}">
                            <label for="payment_reference">Referencia pagamento</label>
                            <input class="form-control" type="text" name="payment_reference" id="payment_reference" value="{{ old('payment_reference', '') }}">
                            @if($errors->has('payment_reference'))
                                <span class="help-block" role="alert">{{ $errors->first('payment_reference') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('pay_to') ? 'has-error' : '' }}">
                            <label for="pay_to">Pagar a</label>
                            <input class="form-control" type="text" name="pay_to" id="pay_to" value="{{ old('pay_to', '') }}">
                            @if($errors->has('pay_to'))
                                <span class="help-block" role="alert">{{ $errors->first('pay_to') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('is_paid') ? 'has-error' : '' }}">
                            <div>
                                <input type="hidden" name="is_paid" value="0">
                                <input type="checkbox" name="is_paid" id="is_paid" value="1" {{ old('is_paid', 0) == 1 ? 'checked' : '' }}>
                                <label for="is_paid" style="font-weight: 400">Ja pago</label>
                            </div>
                            @if($errors->has('is_paid'))
                                <span class="help-block" role="alert">{{ $errors->first('is_paid') }}</span>
                            @endif
                        </div>
                        <div class="form-group {{ $errors->has('value') ? 'has-error' : '' }}" id="single_value_wrapper">
                            <label class="required" for="value">{{ trans('cruds.vehicleExpense.fields.value') }}</label>
                            <input class="form-control" type="number" name="value" id="value" value="{{ old('value', '0') }}" step="0.01" required>
                            @if($errors->has('value'))
                                <span class="help-block" role="alert">{{ $errors->first('value') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.value_helper') }}</span>
                        </div>
                        <div class="form-group" id="group_values_wrapper">
                            <label>Valores por viatura</label>
                            <table class="table table-bordered table-striped" id="group_values_table">
                                <thead>
                                    <tr>
                                        <th>Viatura</th>
                                        <th style="width: 220px;">Valor</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            @foreach($errors->get('vehicle_values.*') as $messages)
                                @foreach($messages as $message)
                                    <span class="help-block text-danger" role="alert">{{ $message }}</span>
                                @endforeach
                            @endforeach
                            <span class="help-block">Indique manualmente o valor de cada viatura selecionada.</span>
                        </div>
                        <div class="form-group {{ $errors->has('vat') ? 'has-error' : '' }}">
                            <label class="required" for="vat">{{ trans('cruds.vehicleExpense.fields.vat') }}</label>
                            <input class="form-control" type="number" name="vat" id="vat" value="{{ old('vat', '23.00') }}" step="0.01" required>
                            @if($errors->has('vat'))
                                <span class="help-block" role="alert">{{ $errors->first('vat') }}</span>
                            @endif
                            <span class="help-block">{{ trans('cruds.vehicleExpense.fields.vat_helper') }}</span>
                        </div>
                        <div class="form-group">
                            <button class="btn btn-danger" type="submit">
                                {{ trans('global.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(function () {
        var oldVehicleValues = @json(old('vehicle_values', []));
        var $groupToggle = $('#is_group_expense');
        var $singleVehicleWrapper = $('#single_vehicle_wrapper');
        var $groupVehiclesWrapper = $('#group_vehicles_wrapper');
        var $groupLabelWrapper = $('#group_label_wrapper');
        var $singleValueWrapper = $('#single_value_wrapper');
        var $groupValuesWrapper = $('#group_values_wrapper');
        var $singleValue = $('#value');
        var $groupVehicles = $('#vehicle_item_ids');
        var $groupTableBody = $('#group_values_table tbody');

        function syncMode() {
            var isGroup = $groupToggle.is(':checked');

            $singleVehicleWrapper.toggle(!isGroup);
            $singleValueWrapper.toggle(!isGroup);
            $groupVehiclesWrapper.toggle(isGroup);
            $groupLabelWrapper.toggle(isGroup);
            $groupValuesWrapper.toggle(isGroup);

            $singleValue.prop('required', !isGroup);
            renderGroupValueRows();
        }

        function renderGroupValueRows() {
            $groupTableBody.empty();

            if (!$groupToggle.is(':checked')) {
                return;
            }

            $groupVehicles.find('option:selected').each(function () {
                var id = $(this).val();
                var label = $(this).text();
                var value = oldVehicleValues[id] || '';

                $groupTableBody.append(
                    '<tr data-vehicle-id="' + id + '">' +
                        '<td>' + $('<div>').text(label).html() + '</td>' +
                        '<td>' +
                            '<input class="form-control" type="number" name="vehicle_values[' + id + ']" value="' + value + '" step="0.01" min="0" required>' +
                        '</td>' +
                    '</tr>'
                );
            });

            if ($groupTableBody.children().length === 0) {
                $groupTableBody.append('<tr><td colspan="2">Selecione viaturas para definir os valores.</td></tr>');
            }
        }

        $groupToggle.on('change', syncMode);
        $groupVehicles.on('change', function () {
            $(this).find('option:selected').each(function () {
                var id = $(this).val();
                var currentInput = $groupTableBody.find('tr[data-vehicle-id="' + id + '"] input');
                if (currentInput.length) {
                    oldVehicleValues[id] = currentInput.val();
                }
            });
            renderGroupValueRows();
        });

        syncMode();
    });
</script>
<script>
    $(document).ready(function () {
        function SimpleUploadAdapter(editor) {
            editor.plugins.get('FileRepository').createUploadAdapter = function(loader) {
                return {
                    upload: function() {
                        return loader.file.then(function (file) {
                            return new Promise(function(resolve, reject) {
                                var xhr = new XMLHttpRequest();
                                xhr.open('POST', '{{ route('admin.vehicle-expenses.storeCKEditorImages') }}', true);
                                xhr.setRequestHeader('x-csrf-token', window._token);
                                xhr.setRequestHeader('Accept', 'application/json');
                                xhr.responseType = 'json';

                                var genericErrorText = `Couldn't upload file: ${ file.name }.`;
                                xhr.addEventListener('error', function() { reject(genericErrorText) });
                                xhr.addEventListener('abort', function() { reject() });
                                xhr.addEventListener('load', function() {
                                    var response = xhr.response;

                                    if (!response || xhr.status !== 201) {
                                        return reject(response && response.message ? `${genericErrorText}\n${xhr.status} ${response.message}` : `${genericErrorText}\n ${xhr.status} ${xhr.statusText}`);
                                    }

                                    $('form').append('<input type="hidden" name="ck-media[]" value="' + response.id + '">');
                                    resolve({ default: response.url });
                                });

                                if (xhr.upload) {
                                    xhr.upload.addEventListener('progress', function(e) {
                                        if (e.lengthComputable) {
                                            loader.uploadTotal = e.total;
                                            loader.uploaded = e.loaded;
                                        }
                                    });
                                }

                                var data = new FormData();
                                data.append('upload', file);
                                data.append('crud_id', '{{ $vehicleExpense->id ?? 0 }}');
                                xhr.send(data);
                            });
                        })
                    }
                };
            }
        }

        var allEditors = document.querySelectorAll('.ckeditor');
        for (var i = 0; i < allEditors.length; ++i) {
            ClassicEditor.create(allEditors[i], {
                extraPlugins: [SimpleUploadAdapter]
            });
        }
    });
</script>

<script>
    var uploadedFilesMap = {}
    Dropzone.options.filesDropzone = {
        url: '{{ route('admin.vehicle-expenses.storeMedia') }}',
        maxFilesize: 5,
        addRemoveLinks: true,
        headers: {
            'X-CSRF-TOKEN': "{{ csrf_token() }}"
        },
        params: {
            size: 5
        },
        success: function (file, response) {
            $('form').append('<input type="hidden" name="files[]" value="' + response.name + '">')
            uploadedFilesMap[file.name] = response.name
        },
        removedfile: function (file) {
            file.previewElement.remove()
            var name = ''
            if (typeof file.file_name !== 'undefined') {
                name = file.file_name
            } else {
                name = uploadedFilesMap[file.name]
            }
            $('form').find('input[name="files[]"][value="' + name + '"]').remove()
        },
        init: function () {
@if(isset($vehicleExpense) && $vehicleExpense->files)
            var files = {!! json_encode($vehicleExpense->files) !!}
            for (var i in files) {
                var file = files[i]
                this.options.addedfile.call(this, file)
                file.previewElement.classList.add('dz-complete')
                $('form').append('<input type="hidden" name="files[]" value="' + file.file_name + '">')
            }
@endif
        },
        error: function (file, response) {
            if ($.type(response) === 'string') {
                var message = response
            } else {
                var message = response.errors.file
            }
            file.previewElement.classList.add('dz-error')
            _ref = file.previewElement.querySelectorAll('[data-dz-errormessage]')
            _results = []
            for (_i = 0, _len = _ref.length; _i < _len; _i++) {
                node = _ref[_i]
                _results.push(node.textContent = message)
            }

            return _results
        }
    }
</script>
@endsection
