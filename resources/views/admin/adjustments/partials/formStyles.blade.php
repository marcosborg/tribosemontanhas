<style>
.adjustment-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 24px; align-items: start; }
.adjustment-grid .form-group { min-width: 0; margin-bottom: 0; }
.adjustment-grid .select2-container { width: 100% !important; }
.adjustment-grid input[type="radio"] + label { padding: 6px 8px; border: 2px solid transparent; border-radius: 4px; }
.adjustment-grid #type_deduct:checked + label { color: #a51d29; background: #fff0f1; border-color: #a51d29; font-weight: 700 !important; }
.adjustment-grid #type_refund:checked + label { color: #146c43; background: #eaf7ee; border-color: #146c43; font-weight: 700 !important; }
.adjustment-grid #amount { font-size: 22px; height: 46px; font-weight: 700; border-width: 2px; }
.adjustment-grid #amount.is-deduct, .adjustment-impact.is-deduct { color: #a51d29; border-color: #a51d29; background: #fff0f1; }
.adjustment-grid #amount.is-refund, .adjustment-impact.is-refund { color: #146c43; border-color: #146c43; background: #eaf7ee; }
.adjustment-impact { padding: 14px; border: 2px solid; border-radius: 6px; }
.adjustment-impact span { display: block; }
.impact-value { font-size: 25px; font-weight: 700; margin: 6px 0; }
.adjustment-notes { grid-column: 1 / -1; }
.adjustment-extra { margin: 20px 0; }
.adjustment-extra summary { cursor: pointer; margin-bottom: 12px; color: #555; }
.adjustment-extra .form-group { max-width: 320px; }
@media(max-width: 767px) { .adjustment-grid { grid-template-columns: 1fr; } }
</style>
