# Copilot instructions for this repository

This file contains concise, actionable guidance for AI coding assistants working in this Laravel codebase. Focus on discoverable patterns, workflows, and examples that make contributions safe and effective.

1. Project overview
- **Framework**: Laravel 9 (PHP ^8.0). See `composer.json` and `README.md`.
- **Admin-first app**: Most functionality lives under the `admin` route prefix and `App\Http\Controllers\Admin` controllers. See `routes/web.php` and `app/Http/Controllers/Admin`.
- **Locale / i18n**: Default locale is `pt_PT` (see `config/app.php`) — string keys and language files live in `resources/lang`.

2. Key conventions and patterns (use these exactly)
- **Routes**: Admin routes are grouped with `prefix => 'admin', namespace => 'Admin', middleware => ['auth']`. Follow resource naming conventions (e.g., `Route::resource('cars', 'CarsController')`). Example: `routes/web.php`.
- **Controller patterns**: Many controllers expose the following standardized actions:
  - `massDestroy` (bulk delete route: `DELETE .../destroy`)
  - `parseCsvImport` and `processCsvImport` (two-step CSV import flow)
  - `storeMedia` and `storeCKEditorImages` (media upload endpoints)
  - `pdf` endpoints for downloadable reports (e.g., `FinancialStatementController@pdf`).
  Reference: `app/Http/Controllers/Admin/*`.
- **Resource naming**: Plural resource names (e.g., `cars`, `companies`, `receipts`) and controller class names follow `XxxController`.

3. Common integrations and packages
- **Spatie Media Library**: media endpoints and `storeMedia` methods indicate use of Spatie packages — be careful to respect storage config in `config/filesystems.php` and `storage/` permissions.
- **Yajra DataTables, DomPDF**: listed in `composer.json` — controllers generate server-side datatables and PDFs.
- **Laravel Sanctum**: present in `composer.json` — token or SPA auth may be used elsewhere.

4. Developer workflows & commands (concrete)
- Install PHP deps: `composer install`.
- Prepare environment: copy `.env.example` → `.env`; run `php artisan key:generate`.
- Run migrations (requires DB configured in `.env`): `php artisan migrate`.
- Run tests: `vendor/bin/phpunit` (or `./vendor/bin/phpunit --configuration phpunit.xml`). Tests live under `tests/Unit` and `tests/Feature`.
- Frontend build: `npm install` then `npm run dev` or `npm run production` (see `package.json` and `webpack.mix.js`).
- Run app locally: `php artisan serve` (or your preferred dev server).

5. Safety rules when editing code
- Preserve route names, resource paths and controller method signatures to avoid breaking references in views and JS.
- When adding media handling, update storage disk config and run `php artisan storage:link` if public files are required.
- Respect the CSV import pattern: keep `parseCsvImport` (validation & mapping) and `processCsvImport` (insertion) separate.

6. Testing & CI notes
- `phpunit.xml` sets environment overrides for testing (email, cache, queue, session). Tests expect `QUEUE_CONNECTION=sync` and `SESSION_DRIVER=array`.
- Unit tests focus on `app/` classes and controllers — modify configuration in `phpunit.xml` only if you understand test environment implications.

7. Where to look for concrete examples
- Bulk-delete pattern: search for `massDestroy` in `app/Http/Controllers/Admin`.
- CSV import: search for `parseCsvImport` / `processCsvImport`.
- Media handling: look for `storeMedia` / `storeCKEditorImages` methods and controllers with `media` routes (e.g., `CarsController`, `CompanyController`).

8. When to ask the maintainer
- Database credentials and seed data (if you need a reproducible dev DB).
- Expected production storage setup (S3 vs local) before changing media/storage code.

If anything above is unclear or you'd like more examples (controller snippets, a runbook for local setup, or CI details), tell me what to expand and I'll iterate.
