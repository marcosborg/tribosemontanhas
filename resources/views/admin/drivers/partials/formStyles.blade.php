<style>
.driver-tabs { display: flex; flex-wrap: wrap; gap: 4px; }
.driver-tabs > li { float: none; }
.driver-form-tabs { padding: 8px 0 20px; }
.driver-form-tabs h3 { font-size: 17px; margin: 18px 0; }
.driver-field-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 24px; }
.driver-field-grid .form-group { min-width: 0; margin-bottom: 14px; }
.driver-field-grid .select2-container { width: 100% !important; }
.driver-field-grid textarea { min-height: 90px; }
.driver-field-grid .help-block { font-size: 12px; }
.driver-form-tabs th { width: 32%; }
@media (max-width: 1199px) { .driver-field-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) { .driver-field-grid { grid-template-columns: 1fr; } }
</style>
