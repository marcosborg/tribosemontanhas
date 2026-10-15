document.addEventListener('DOMContentLoaded', function () {
    const base = document.getElementById('value');
    const vat = document.getElementById('vat');
    const total = document.getElementById('final_value');
    if (!base || !vat || !total) return;
    const rounded = n => Math.round((n + Number.EPSILON) * 100) / 100;
    const number = input => Number(input.value || 0);
    function update(recalculate) {
        const tax = rounded(number(base) * number(vat) / 100);
        const calculated = rounded(number(base) + tax);
        if (recalculate || total.value === '') total.value = calculated.toFixed(2);
        document.getElementById('expense_vat_amount').textContent = tax.toFixed(2).replace('.', ',');
        document.getElementById('expense_adjustment').textContent = rounded(number(total) - calculated).toFixed(2).replace('.', ',');
    }
    base.addEventListener('input', () => update(true));
    vat.addEventListener('input', () => {
        update(true);
        document.querySelectorAll('#group_values_table tbody tr[data-vehicle-id]').forEach(updateGroup);
    });
    total.addEventListener('input', () => update(false));
    function updateGroup(row) {
        const inputs = row.querySelectorAll('input');
        if (inputs.length === 2) inputs[1].value = rounded(number(inputs[0]) * (1 + number(vat) / 100)).toFixed(2);
    }
    const groupTable = document.getElementById('group_values_table');
    if (groupTable) groupTable.addEventListener('input', event => {
        if (event.target.name.startsWith('vehicle_values[')) updateGroup(event.target.closest('tr'));
    });
    update(false);
});
