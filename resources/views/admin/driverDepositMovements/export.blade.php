<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Movimentos reais de caucao</title>
</head>
<body>
    <h3>Movimentos reais de caucao</h3>
    <table border="1" cellspacing="0" cellpadding="4">
        <thead>
            <tr>
                <th>Data</th>
                <th>Motorista</th>
                <th>Empresa</th>
                <th>Semana</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Metodo</th>
                <th>Descricao</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movements as $movement)
                <tr>
                    <td>{{ optional($movement->created_at)->format('Y-m-d') }}</td>
                    <td>{{ $movement->driver->name ?? '' }}</td>
                    <td>{{ $movement->company->name ?? '' }}</td>
                    <td>{{ $movement->tvde_week->start_date ?? '' }}</td>
                    <td>{{ \App\Models\DriverDepositMovement::REAL_TYPE_SELECT[$movement->type] ?? $movement->type }}</td>
                    <td>{{ number_format($movement->amount, 2) }} &euro;</td>
                    <td>{{ $movement->payment_method }}</td>
                    <td>{{ $movement->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
