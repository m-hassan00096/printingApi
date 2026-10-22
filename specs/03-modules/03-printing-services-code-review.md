# Printing Services Module — Implementation Review & Handoff

**Branch:** `feature/printing-services`  
**Target:** `develop`  
**Reference:** [03-printing-services.md](./03-printing-services.md)  
**State:** Initial implementation, localization, and modular Settings customer-type deletion guard committed on feature/printing-services. **Latest owner-reported Printing Services module suite passed after the integration: 37 tests, 172 assertions, 2.29 s (2026-10-22)**. The newly added **Settings-only ParameterValueUsageRegistryTest** is not included in that module suite, and a complete project regression run after integration is not yet reported. The targeted **MySQL 8 CI job passed** (fresh migrations, live-name unique index, second-connection InnoDB row-lock contention); full concurrent multi-request pricing/Orders scenarios remain unverified. Draft PR #2 is not merged.

## Delivered independently of Settings

- New `Modules/PrintingServices` nwidart module, enabled in `modules_statuses.json`.
- Independent module providers, routes, enums, models, migrations, seeders, Actions, FormRequests, resources, select and pricing services.
- `PrintingServiceStatus` (0/1) stored in the service model; `PriceTierActionStatus` (0/1/2/3) is **input-only** for atomic updates.
- Tables: `printing_services` and `printing_service_price_tiers`; reference the already-existing Settings `customer_types` via `parameter_values.id`, not a new customer-type table.
- One service update endpoint accepts unchanged/create/update/delete tier actions and requires `priceTierId` for updates/deletes. Absent stored tiers are retained; failed operations roll back the entire transaction.
- Price precedence: specific customer type + quantity, specific catch-all, generic + quantity, generic catch-all, optional base price. Pricing in EGP; base zero versus null distinct; strict positive tier prices.
- Inclusive, nullable quantity bounds; no overlap in the same tier bucket apart from one catch-all fallback.
- Live service names are normalized for whitespace, compared case-sensitively using a unique SHA-256 live-name key; soft deleting a never-used service frees its name and soft deletes tiers.
- Paginated management list uses only `filter[search]` on name and `filter[status]`; shared select key `printingServices` uses a dedicated read permission.
- PHPUnit feature tests under `Modules/PrintingServices/tests/Feature` and money arithmetic unit tests under `Modules/PrintingServices/tests/Unit`; a separate suite is registered in `phpunit.xml`.
- No modifications to existing Settings/Identity business implementation. Identity's **existing** `RoleSeeder` syncs permissions after module permission seeding.

## PHPUnit results (reported by project owner, 2026-10-22)

| Test execution | Result | Assertions | Duration |
|---|---:|---:|---:|
| `php artisan test --testsuite="Printing Services Module"` | **19 passed** | 106 | 1.78 s |
| `php artisan test` — earlier full suite | **331 passed** | 1,253 | 20.73 s |
| `php artisan test --testsuite="Printing Services Module"` — after review fixes, before translation tests | **23 passed** | 121 | 1.88 s |
| `php artisan test` — previous full suite before Settings integration | **341 passed** | **1,294** | **32.39 s** |
| `php artisan test --testsuite="Printing Services Module"` — **after Settings/PrintingServices integration** | **37 passed** | **172** | **2.29 s** |
| GitHub Actions [run 39787527987](https://github.com/m-hassan00096/printingApi/actions/runs/39787527987) — full SQLite regression | **Passed** | CI | CI |
| Same run — MySQL 8 migrations and targeted DB invariant tests | **Passed** | CI | CI |

These outputs were supplied from the owner's **local environment**, not executed by the GitHub connector. **The Printing Services module now has 37 passing tests** (14 CRUD, 7 pricing, 2 money, 6 translations, 8 customer-type integration tests). The separate `Modules/Settings/tests/Feature/ParameterValues/ParameterValueUsageRegistryTest.php` was added after the previous full-suite result and is **not part of the Printing Services test suite**; a fresh entire-project run is required to verify it and detect broader regressions. The separate MySQL 8 CI job verified schema migrations, live-name uniqueness and name reuse, and contention on a locked customer-type row from an independent MySQL connection. Full end-to-end multi-request races and Orders integration are still unverified.

## Static code review — 2026-10-22

| Finding | Severity | Review action / disposition |
|---|---|---|
| Name-only search treated user-entered `%` / `_` as SQL LIKE wildcards | Medium — fixed | Escaped literal search input using an explicit `ESCAPE '!'` clause and added regression coverage. Parameter binding remains in place. |
| Nested tier customer-type errors returned `errors.customerTypeId`, preventing field-row feedback | Medium — fixed | Action validation now returns `errors.priceTiers.N.customerTypeId` (and tier price errors likewise use a nested key). Price preview still uses the top-level field. |
| Historic persisted order item deletion behavior had no executable coverage | Medium — test added | New isolated `order_items` fixture asserts 409 even if an order item is soft-deleted. Actual Orders integration and deletion/create concurrency remain separate work. |
| Atomic tier range swaps were documented but not explicitly tested | Medium — test added | New case swaps ranges in one service update; validates only final consistent state. |
| Customer-type parameter Soft Delete could bypass price-tier relations | **Code fix committed; Printing Services tests passed** | Settings-owned `ParameterValueUsageGuard` interface and `ParameterValueUsageRegistry` registered in Settings provider; PrintingServices registers `CustomerTypeUsageGuard` via its provider. Referenced customer types (including historical/Soft Deleted tiers) trigger a localized 409 during Settings DeleteAction. Eight dedicated integration tests **passed within the owner's 37-test module run**; the separate Settings registry test still awaits confirmation. |
| `live_name_hash` is populated by the Actions, not intrinsically enforced by a database computed expression/model invariant | Low–Medium — design risk | Existing API mutation paths set/clear it correctly. Internal writers in future modules must use the Action; otherwise a direct model insert/rename could leave it NULL/stale and bypass intended live-name uniqueness. Decide on a model/database invariant before broader module integration. |
| MySQL migrations and basic InnoDB/index behavior | **Targeted CI passed** | GitHub Actions run 39787527987 passed MySQL 8 fresh migrations, duplicate/case-different live-name checks, soft-delete reuse and independent row-lock timeout verification. Further multi-request price-tier/order races remain follow-ups. |
| `order_items` and historical price snapshots do not yet belong to an implemented Orders module | Deferred cross-module dependency | Follow PS-OPEN-02, PS-OPEN-03 and PS-OPEN-08 when Orders is designed; enforce deletion race prevention and server-derived type at that time. |

### Review changes on the branch

- `2e488a0` — literal name search and tier-indexed validation errors.
- `3b2349b` — four feature regression tests, reported passing in the subsequent 23-test module run.
- `399b2b3` — localized Printing Services error messages and FormRequest validation in Arabic/English.
- `19c5f50` — six new translation tests, bringing the module to **29 written tests**.
- `be58a0b` — corrected assertions for Laravel's flat validation-error keys; the previously reported full suite (**341 passed**) includes these changes.
- `c1b7b46` / `ebb4331` — Settings-owned deletion-guard registry and tier-write customer-type locking.
- `4edb38f` — eight additional Printing Services integration tests, now reported passing with the module suite (37 passed / 172 assertions).
- `bfddded` — localized Settings registry guard error via `lang/ar/settings.php` and `lang/en/settings.php`, with one additional Settings-only test; full regression still pending.
- No separate tier CRUD APIs were introduced. Service `status` enum, tier `actionStatus` enum, transaction boundary and existing authorizations remain unchanged.

## Remaining local verification

Run from the project root on a development/test database (do not run fresh migrations against an unreviewed production database):

```powershell
git fetch origin
git switch feature/printing-services
git pull --ff-only origin feature/printing-services
composer dump-autoload
php artisan optimize:clear
php artisan module:list
php artisan migrate:status
# Latest Printing Services module suite (37 tests) passed. Run Settings registry test and full suite after integration:
php artisan test --testsuite="Printing Services Module"
php artisan test
php artisan route:list --path=api/v1/printing-services
php artisan route:list --path=api/v1/selects
php artisan db:seed --class="Modules\PrintingServices\Database\Seeders\PrintingServicesDatabaseSeeder"
```

**Validation focus:** create with nullable/free base price; positive tiers; lookup group membership; case-sensitive name duplicate protection and reuse after Soft Delete; open and inclusive ranges; catch-all fallback; atomic 0/1/2/3 tier commands; price preview order; names-only filter; no independent tier endpoints; 401/403 permissions.

## Boundaries and integration blockers

1. **Owner-reported latest Printing Services module suite passed after integration** (37 tests / 172 assertions / 2.29 s). The last reported entire-project suite (341 passed) predates the latest Settings integration; run `php artisan test` again and include the Settings-only registry test. The owner also supplied `route:list` showing all six Printing Services endpoints with Sanctum plus matching permissions. MySQL behavior, migration status and seeding output remain unverified.
2. **Orders historic-use rule is conditional.** Orders does not exist yet in this project branch. `DeletePrintingServiceAction` currently checks the prospective `order_items.printing_service_id` relation **when that table exists** and fails closed if the table exists without the FK column. Once Orders is built, the owning Orders module must explicitly lock/reference the service on order creation and test the delete/create race across draft/cancelled/soft-deleted order records. Passing Printing Services tests today does not prove this future contract.
3. **Settings customer-type historic protection implemented for active PrintingServices module.** A Settings-owned registry calls PrintingServices' registered guard before Soft Delete; active/historical tier references block it. Eight integration tests passed in the owner-reported module run; full SQLite CI also passed. Targeted MySQL row locking was checked, though end-to-end simultaneous delete/tier-create tests are still outstanding. **Module disable caveat:** provider-based registration is absent when PrintingServices is disabled; do not disable the module with persisted tiers until durable fail-closed registration is introduced. Type **deactivation** policy remains open.
4. **MySQL concurrency.** A MySQL 8 CI test proved independent-connection row-lock contention and live-name unique-index behavior. Test full concurrent tier-batch writes, live-name rename/create races and the future service-delete/order-create race before relying on those specific production guarantees.
5. **Unicode normalization.** Whitespace trimming/collapse and letter-case-sensitive uniqueness are approved. NFC/NFD equivalence is still a technical detail in PS-OPEN-11, to resolve with tests if needed.
6. **Numeric storage.** `DECIMAL(30,2)` and PHP signed integer limits are technical storage boundaries; there is no independent business cap. Test overflow/precision to avoid truncation.
7. **Orders price snapshots.** Orders owns final line total and manual override behavior; these snapshots and update triggers are not new Printing Services columns.

**Review verdict:** The owner reports **37/37 Printing Services tests passed (172 assertions)** after Settings integration. GitHub Actions **full SQLite regression and targeted MySQL 8 database checks both passed** on draft PR #2 (run 39787527987). No merge has been performed. Open architectural boundaries: fail-closed Settings deletion when a referencing module is disabled; customer-type deactivation policy; and the future Orders implementation. Multi-request concurrency verification remains advisable. Not a production-readiness sign-off.
