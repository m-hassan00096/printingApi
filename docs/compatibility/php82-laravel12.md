# PHP 8.2 + Laravel 12 compatibility trial

This branch is **isolated from `develop`** for a local acceptance test on the PHP 8.2 hosting target.

## Dependency decisions

- PHP: `^8.2`, with Composer's `config.platform.php=8.2.30` to prevent resolving dependencies requiring a newer PHP runtime even when the developer's machine runs PHP 8.3/8.4.
- Framework: `laravel/framework:^12.0` (Composer selects the newest compatible Laravel 12 patch).
- Modules: `nwidart/laravel-modules:^12.0`.
- Permissions: `spatie/laravel-permission:^6.0`, the last supported series for PHP 8.2.
- Query builder: `spatie/laravel-query-builder:^6.0` to support PHP 8.2.
- PHPUnit: `^11.5.50`; `laravel/pao` removed because it requires PHP 8.3.
- Pint: `^1.24` lets Composer pick a version that supports PHP 8.2.
- Sanctum, Tinker, Pail, and Collision retain their existing constraints where compatible.

This is a **dependency downgrade for hosting compatibility**, not an excuse to disable migration safeguards, permissions, finance invariants, authentication, or automated tests.

## Local test (Windows PowerShell)

```powershell
cd C:\xampp\htdocs\printingApi
git status
git fetch origin
git switch -c feature/php82-laravel12-compat --track origin/feature/php82-laravel12-compat
php -v
composer -V
composer validate
composer install
composer check-platform-reqs
php artisan --version
php artisan optimize:clear
php artisan test
```

If the branch already exists locally, use `git switch feature/php82-laravel12-compat` and `git pull --ff-only origin feature/php82-laravel12-compat`.

**Important:** Do not use `composer install` on the currently committed Laravel 13 lock file. Wait until the compatibility workflow has generated and committed the PHP 8.2-compatible `composer.lock`. If it has not, use `composer update -W` **on this feature branch only** to resolve a new lock; do not copy the old `vendor/` folder or use `--ignore-platform-reqs`.

Before changing the database, back it up. Use a separate local `.env` / test database to avoid modifying production data. Run migrations only after checking the selected database connection. The frontend does not need a corresponding branch for this compatibility exercise.

## Promotion gates

1. PHP 8.2 Composer resolution with all project modules installed; lock committed.
2. Full SQLite regression and Finance/MySQL integration checks.
3. Local login, roles/permissions, order confirmation, design/production, delivery, collection correction, account ledger/refunds, and attachment archive smoke tests.
4. No merge to `develop` until validation and user approval.
