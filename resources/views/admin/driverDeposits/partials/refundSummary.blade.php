<h4>Recebimentos e devoluções</h4>
<table class="table table-bordered">
    <thead><tr><th>Origem</th><th>Recebido</th><th>Devolvido</th><th>Por devolver / retido</th></tr></thead>
    <tbody>
        @foreach(['initial', 'weekly'] as $source)
            <tr>
                <td>{{ $refundSummary[$source]['label'] }}</td>
                @foreach(['received', 'refunded', 'remaining'] as $field)
                    <td>{{ number_format($refundSummary[$source][$field], 2, ',', '.') }} €</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
@if($refundSummary['unclassified_refunds'] > 0)
    <p class="alert alert-warning">Existem {{ number_format($refundSummary['unclassified_refunds'], 2, ',', '.') }} € de devoluções antigas por classificar. Edite essas devoluções para identificar a origem; esse valor já está descontado do saldo disponível, mas ainda não dos totais por origem.</p>
@endif
@if($refundSummary['other_debits'] > 0)
    <p class="help-block">Abatimentos e regularizações: {{ number_format($refundSummary['other_debits'], 2, ',', '.') }} €. Estes valores reduzem o saldo disponível e estão incluídos no valor retido, sem atribuição a uma origem.</p>
@endif
<p class="help-block">O valor por devolver / retido corresponde ao recebido menos as devoluções classificadas. O limite de cada nova devolução também considera o saldo total disponível.</p>
