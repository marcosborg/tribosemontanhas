<script>
$(function () {
    function showFieldTab(field) {
        const pane = $(field).closest('.tab-pane');
        if (pane.length) $('.driver-tabs a[href="#' + pane.attr('id') + '"]').tab('show');
    }
    const error = $('.driver-form-tabs .has-error').first();
    if (error.length) showFieldTab(error);
    // Reveal required inputs in another tab before the browser tries to focus them.
    const form = $('.driver-form-tabs').closest('form')[0];
    if (form) form.addEventListener('invalid', function (event) {
        if (!form.querySelector(':invalid') || form.querySelector(':invalid') === event.target) showFieldTab(event.target);
    }, true);
    $('.driver-tabs a').on('shown.bs.tab', function () {
        $('.driver-tabs a').attr('aria-selected', 'false');
        $(this).attr('aria-selected', 'true');
    });
});
</script>
