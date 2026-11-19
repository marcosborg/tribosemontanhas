<style>
#week-calendar { position: absolute; z-index: 100; width: 310px; max-width: 90vw; padding: 12px; background: white; border: 1px solid #ccc; border-radius: 6px; box-shadow: 0 4px 12px #0002; }
.week-calendar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.week-calendar-header button { border: 0; background: #eee; padding: 4px 12px; font-size: 22px; }
.week-calendar-days, .week-calendar-row { display: grid; grid-template-columns: repeat(7, 1fr); width: 100%; text-align: center; }
.week-calendar-row { border: 0; background: white; padding: 8px 0; border-radius: 4px; }
.week-calendar-row:hover, .week-calendar-row:focus { background: #dceeff; outline: 2px solid #337ab7; }
.week-calendar-row[aria-pressed="true"] { background: #337ab7; color: white; font-weight: bold; }
.week-calendar-row .outside-month { opacity: .55; }
#choose-week { height: auto; white-space: normal; text-align: left; }
#week-period-summary { padding: 10px; background: #eef4f9; border-radius: 4px; }
</style>
<script>
$(function () {
    const input = document.getElementById('week_start');
    const count = document.getElementById('week_count');
    const changed = document.getElementById('weekly_schedule');
    const calendar = document.getElementById('week-calendar');
    const toggle = document.getElementById('choose-week');
    const summary = document.getElementById('week-period-summary');
    let selected = moment(input.value, 'YYYY-MM-DD', true);
    if (!selected.isValid()) selected = moment().startOf('isoWeek');
    let month = selected.clone().startOf('month');
    const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    function update() {
        toggle.textContent = selected.format('DD/MM/YYYY') + ' a ' + selected.clone().add(6, 'days').format('DD/MM/YYYY') + ' ▾';
        if (changed.value !== '1' && summary.dataset.existing) {
            summary.textContent = 'Período atual: ' + summary.dataset.existing + '. Se escolher outra semana ou duração, o período será atualizado.';
        } else if (Number.isInteger(Number(count.value)) && Number(count.value) > 0 && Number(count.value) <= 520) {
            summary.textContent = selected.format('DD/MM/YYYY') + ' a ' + selected.clone().add(Number(count.value), 'weeks').subtract(1, 'day').format('DD/MM/YYYY') + ' · ' + count.value + (Number(count.value) === 1 ? ' semana' : ' semanas');
        } else summary.textContent = 'Indique um número de semanas entre 1 e 520.';
    }
    function render() {
        document.getElementById('week-calendar-month').textContent = months[month.month()] + ' ' + month.year();
        const rows = document.getElementById('week-calendar-rows');
        rows.replaceChildren();
        let day = month.clone().startOf('isoWeek');
        for (let row = 0; row < 6; row++) {
            const monday = day.clone();
            const button = document.createElement('button'); button.type = 'button'; button.className = 'week-calendar-row';
            button.setAttribute('aria-label', 'Semana de ' + monday.format('DD/MM/YYYY') + ' a ' + monday.clone().add(6,'days').format('DD/MM/YYYY'));
            button.setAttribute('aria-pressed', monday.isSame(selected, 'day') ? 'true' : 'false');
            for (let col = 0; col < 7; col++) {
                const span = document.createElement('span'); span.textContent = day.date();
                if (day.month() !== month.month()) span.className = 'outside-month';
                button.appendChild(span); day.add(1, 'day');
            }
            button.addEventListener('click', function () {
                selected = monday; input.value = monday.format('YYYY-MM-DD'); changed.value = '1'; update();
                calendar.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus();
            }); rows.appendChild(button);
        }
    }
    toggle.addEventListener('click', function () { calendar.hidden = !calendar.hidden; toggle.setAttribute('aria-expanded', String(!calendar.hidden)); if (!calendar.hidden) render(); });
    document.getElementById('previous-week-month').addEventListener('click', function () { month.subtract(1,'month'); render(); });
    document.getElementById('next-week-month').addEventListener('click', function () { month.add(1,'month'); render(); });
    count.addEventListener('input', function () { changed.value = '1'; update(); });
    document.addEventListener('click', function (event) { if (!calendar.contains(event.target) && event.target !== toggle) { calendar.hidden = true; toggle.setAttribute('aria-expanded','false'); } });
    calendar.addEventListener('keydown', function (event) { if (event.key === 'Escape') { calendar.hidden = true; toggle.setAttribute('aria-expanded','false'); toggle.focus(); } });
    update();
});
</script>
