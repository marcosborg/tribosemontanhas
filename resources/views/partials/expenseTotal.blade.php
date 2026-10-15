<div class="form-group" id="expense_total_wrapper">
    <label for="final_value">Valor final com IVA (€)</label>
    <input class="form-control" type="number" name="final_value" id="final_value" min="0" step="0.01" value="{{ old('final_value', $expense->display_total ?? '') }}">
    @error('final_value')<span class="help-block text-danger">{{ $message }}</span>@enderror
    <span class="help-block">Calculado a partir do valor sem IVA e da taxa. Pode acertar o total para coincidir com a fatura. Alterar o valor sem IVA ou a taxa volta a calcular o total.</span>
    <div>IVA calculado: <span id="expense_vat_amount">0,00</span> € · Acerto: <span id="expense_adjustment">0,00</span> €</div>
</div>
