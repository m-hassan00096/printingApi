# Printing Services Module — Implementation Review & Handoff

**Branch:** `feature/printing-services`  
**Target:** `develop`  
**Reference:** [03-printing-services.md](./03-printing-services.md)  
**State:** Initial implementation, review fixes, localization, and **modular Settings deletion guard** committed on feature/printing-services. Latest owner-reported full suite before this integration: **341 passed (1,294 assertions, 32.39 s)** on 2026-10-22. **Eight additional Settings/PrintingServices integration tests and locking changes now require a new run**. MySQL concurrency and Orders integration remain unverified; no merge approval yet.

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
| `php artisan test` — **latest, including translation tests** | **341 passed** | **1,294** | **32.39 s** |

These outputs were supplied from the owner's **local environment**, not executed by the GitHub connector. The latest full project run included the Printing Services module and the six new translation tests; **29 module tests are written** (14 CRUD, 7 pricing, 2 money, 6 translations). Module tests are included within the global suite, not added on top of 341. Full-suite green does not prove MySQL concurrency, migration/index collation behavior or future Orders integration.

## Static code review — 2026-10-22

| Finding | Severity | Review action / disposition |
|---|---|---|
| Name-only search treated user-entered `%` / `_` as SQL LIKE wildcards | Medium — fixed | Escaped literal search input using an explicit `ESCAPE '!'` clause and added regression coverage. Parameter binding remains in place. |
| Nested tier customer-type errors returned `errors.customerTypeId`, preventing field-row feedback | Medium — fixed | Action validation now returns `errors.priceTiers.N.customerTypeId` (and tier price errors likewise use a nested key). Price preview still uses the top-level field. |
| Historic persisted order item deletion behavior had no executable coverage | Medium — test added | New isolated `order_items` fixture asserts 409 even if an order item is soft-deleted. Actual Orders integration and deletion/create concurrency remain separate work. |
| Atomic tier range swaps were documented but not explicitly tested | Medium — test added | New case swaps ranges in one service update; validates only final consistent state. |
| Customer-type parameter Soft Delete could bypass price-tier relations | **Code fix committed; tests pending** | Settings-owned `ParameterValueUsageGuard` interface and `ParameterValueUsageRegistry` registered in Settings provider; PrintingServices registers `CustomerTypeUsageGuard` via its provider. Referenced customer types (including historical/Soft Deleted tiers) now trigger a localized 409 during Settings DeleteAction. Eight dedicated integration tests added. |
| `live_name_hash` is populated by the Actions, not intrinsically enforced by a database computed expression/model invariant | Low–Medium — design risk | Existing API mutation paths set/clear it correctly. Internal writers in future modules must use the Action; otherwise a direct model insert/rename could leave it NULL/stale and bypass intended live-name uniqueness. Decide on a model/database invariant before broader module integration. |
| MySQL transaction serialization and decimal/index behavior tested only indirectly through SQLite PHPUnit | **Pre-merge verification** | Run a MySQL migration/check and concurrent overlapping tier write/rename tests; a passing SQLite suite cannot prove MySQL locking/index behavior. |
| `order_items` and historical price snapshots do not yet belong to an implemented Orders module | Deferred cross-module dependency | Follow PS-OPEN-02, PS-OPEN-03 and PS-OPEN-08 when Orders is designed; enforce deletion race prevention and server-derived type at that time. |

### Review changes on the branch

- `2e488a0` — literal name search and tier-indexed validation errors.
- `3b2349b` — four feature regression tests, reported passing in the subsequent 23-test module run.
- `399b2b3` — localized Printing Services error messages and FormRequest validation in Arabic/English.
- `19c5f50` — six new translation tests, bringing the module to **29 written tests**.
- `be58a0b` — corrected assertions for Laravel's flat validation-error keys; the owner's latest full suite (**341 passed**) includes these changes.
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
# Latest full suite (341 tests) passed per owner on 2026-10-22; rerun after future changes:
php artisan test --testsuite="Printing Services Module"
php artisan test
php artisan route:list --path=api/v1/printing-services
php artisan route:list --path=api/v1/selects
php artisan db:seed --class="Modules\PrintingServices\Database\Seeders\PrintingServicesDatabaseSeeder"
```

**Validation focus:** create with nullable/free base price; positive tiers; lookup group membership; case-sensitive name duplicate protection and reuse after Soft Delete; open and inclusive ranges; catch-all fallback; atomic 0/1/2/3 tier commands; price preview order; names-only filter; no independent tier endpoints; 401/403 permissions.

## Boundaries and integration blockers

1. **Latest full PHPUnit suite passed in the owner's local environment** (341 tests, 1,294 assertions on 2026-10-22); these owner-provided results were not executed by the GitHub connector. The owner also provided route:list output showing all six Printing Services endpoints with auth:sanctum and matching permission middleware; migrations/seed output and MySQL-specific behavior remain unverified. Any subsequent code changes require rerunning tests.
2. **Orders historic-use rule is conditional.** Orders does not exist yet in this project branch. `DeletePrintingServiceAction` currently checks the prospective `order_items.printing_service_id` relation **when that table exists** and fails closed if the table exists without the FK column. Once Orders is built, the owning Orders module must explicitly lock/reference the service on order creation and test the delete/create race across draft/cancelled/soft-deleted order records. Passing Printing Services tests today does not prove this future contract.
3. **Settings customer-type historic protection now implemented for active PrintingServices module.** A Settings-owned registry calls PrintingServices' registered guard before Soft Delete; active/historical tier references block it. Re-run eight integration tests and verify registration/locking against MySQL. **Module disable caveat:** provider-based registration is absent when PrintingServices is disabled; do not disable the module with persisted tiers until durable fail-closed registration is introduced. Type **deactivation** policy remains open.
4. **MySQL concurrency.** SQLite tests do not prove real `lockForUpdate` serialization. Test two competing tier writes, live-name rename/create operations and the service-delete/order-create race against actual MySQL.
5. **Unicode normalization.** Whitespace trimming/collapse and letter-case-sensitive uniqueness are approved. NFC/NFD equivalence is still a technical detail in PS-OPEN-11, to resolve with tests if needed.
6. **Numeric storage.** `DECIMAL(30,2)` and PHP signed integer limits are technical storage boundaries; there is no independent business cap. Test overflow/precision to avoid truncation.
7. **Orders price snapshots.** Orders owns final line total and manual override behavior; these snapshots and update triggers are not new Printing Services columns.

**Review verdict:** The latest **owner-confirmed full suite (341 passing)** predates the new Settings/PrintingServices integration; the module now has **37 written tests (29 prior + 8 new)** pending rerun. Settings' customer-type Soft Delete guard and tier-write locking are committed but must be locally verified. Before merge sign-off: rerun full tests, verify MySQL locks/indexes, keep the referenced module enabled until a fail-closed deployment policy exists, and explicitly track Orders integration. No production-readiness claim.
