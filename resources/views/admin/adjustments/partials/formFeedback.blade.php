<script>
$(function () {
    const amount = document.getElementById('amount');
    const impact = document.getElementById('adjustment-impact');
    const money = new Intl.NumberFormat('pt-PT', {style: 'currency', currency: 'EUR'});
    function updateImpact() {
        const selected = document.querySelector('input[name="type"]:checked');
        if (!selected) return;
        const deduct = selected.value === 'deduct';
        const raw = amount.value.trim();
        const number = Number(raw.replace(',', '.'));
        const signed = deduct ? -number : number;
        [amount, impact].forEach(function (element) {
            element.classList.toggle('is-deduct', deduct);
            element.classList.toggle('is-refund', !deduct);
        });
        impact.querySelector('.impact-title').textContent = deduct ? '− Débito (Deduct)' : '+ Reembolso (Refund)';
        impact.querySelector('.impact-value').textContent = raw === '' || !Number.isFinite(number)
            ? 'Indique o valor'
            : (signed < 0 || (number === 0 && deduct) ? '− ' : '+ ') + money.format(Math.abs(signed));
        const discounts = raw !== '' && number < 0 ? !deduct : deduct;
        impact.querySelector('.impact-description').textContent = discounts
            ? 'Este ajuste desconta ao motorista.' : 'Este ajuste acrescenta ao valor do motorista.';
    }
    document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', updateImpact));
    amount.addEventListener('input', updateImpact);
    updateImpact();
});
</script>
