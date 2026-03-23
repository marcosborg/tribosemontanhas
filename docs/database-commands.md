# Database Commands

This project includes Artisan commands to:

- switch the active local DB credentials between `sandbox` and `production`
- clone the production database into the sandbox database

## Environment variables

Configure these variables in `.env`:

```env
DB_SANDBOX_HOST=127.0.0.1
DB_SANDBOX_PORT=3306
DB_SANDBOX_DATABASE=tribos
DB_SANDBOX_USERNAME=root
DB_SANDBOX_PASSWORD=

DB_PRODUCTION_HOST=162.241.85.33
DB_PRODUCTION_PORT=3306
DB_PRODUCTION_DATABASE=gestvde_tribos
DB_PRODUCTION_USERNAME=gestvde_tribos
DB_PRODUCTION_PASSWORD=...
```

## `db:switch`

Switches the current `DB_*` values in `.env` to one of the configured profiles.

Usage:

```bash
php artisan db:switch sandbox
php artisan db:switch production
```

What it changes:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

By default it also runs `php artisan config:clear`. To skip that step:

```bash
php artisan db:switch sandbox --no-clear
```

## `db:sync-prod-to-sandbox`

Copies the production database defined by `DB_PRODUCTION_*` into the sandbox database defined by `DB_SANDBOX_*`.

Usage:

```bash
php artisan db:sync-prod-to-sandbox
```

Force mode without interactive confirmation:

```bash
php artisan db:sync-prod-to-sandbox --force
```

What the command does:

1. Validates that both `production` and `sandbox` profiles are complete.
2. Connects directly to the production MySQL server.
3. Drops the sandbox database if it exists.
4. Recreates the sandbox database with `utf8mb4` / `utf8mb4_unicode_ci`.
5. Copies all base tables and their data.
6. Recreates views from production in sandbox.

Important notes:

- This command is destructive for the sandbox database. Everything currently in `DB_SANDBOX_DATABASE` is deleted.
- The MySQL user configured in `DB_SANDBOX_USERNAME` must have permission to `DROP DATABASE` and `CREATE DATABASE`.
- If `DB_SANDBOX_DATABASE` points to the same database you use locally in `DB_DATABASE`, that local database will be replaced.
- The command clones structure, rows, and views. It is intended for MySQL.
