# Printing Services Module — Implementation Review & Handoff

**Branch:** `feature/printing-services`  
**Target:** `develop`  
**Reference:** [03-printing-services.md](./03-printing-services.md)  
**State:** Initial implementation and code-review fixes committed. **Owner-reported tests passed before the latest review changes** (19 module tests, 331 full-suite tests on 2026-10-22). Four new tests have since been added, so **fresh test results are pending**. MySQL concurrency, route review and cross-module Orders integration remain unverified; no merge approval yet.

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
| `php artisan test` — all modules and application | **331 passed** | 1,253 | 20.73 s |

These outputs were supplied from the owner's **local environment**. They establish that the shown tests completed successfully in that environment; the repository connector has not independently executed PHPUnit. **19 module tests are already included in the 331 global tests**, not additional tests. A passing SQLite-based suite does not verify MySQL row-level locks, migration/index collation behavior or future Orders integration.

## Static code review — 2026-10-22

| Finding | Severity | Review action / disposition |
|---|---|---|
| Name-only search treated user-entered `%` / `_` as SQL LIKE wildcards | Medium — fixed | Escaped literal search input using an explicit `ESCAPE '!'` clause and added regression coverage. Parameter binding remains in place. |
| Nested tier customer-type errors returned `errors.customerTypeId`, preventing field-row feedback | Medium — fixed | Action validation now returns `errors.priceTiers.N.customerTypeId` (and tier price errors likewise use a nested key). Price preview still uses the top-level field. |
| Historic persisted order item deletion behavior had no executable coverage | Medium — test added | New isolated `order_items` fixture asserts 409 even if an order item is soft-deleted. Actual Orders integration and deletion/create concurrency remain separate work. |
| Atomic tier range swaps were documented but not explicitly tested | Medium — test added | New case swaps ranges in one service update; validates only final consistent state. |
| Customer-type parameter soft deletion can bypass the pricing reference because Laravel SoftDeletes does not invoke FK RESTRICT | **Integration blocker** | Existing Settings delete Action directly soft-deletes; neither the physical FK nor today's Pricing validation prevents this. Resolve at the Settings/PrintingServices boundary before exposing that workflow to users. |
| `live_name_hash` is populated by the Actions, not intrinsically enforced by a database computed expression/model invariant | Low–Medium — design risk | Existing API mutation paths set/clear it correctly. Internal writers in future modules must use the Action; otherwise a direct model insert/rename could leave it NULL/stale and bypass intended live-name uniqueness. Decide on a model/database invariant before broader module integration. |
| MySQL transaction serialization and decimal/index behavior tested only indirectly through SQLite PHPUnit | **Pre-merge verification** | Run a MySQL migration/check and concurrent overlapping tier write/rename tests; a passing SQLite suite cannot prove MySQL locking/index behavior. |
| `order_items` and historical price snapshots do not yet belong to an implemented Orders module | Deferred cross-module dependency | Follow PS-OPEN-02, PS-OPEN-03 and PS-OPEN-08 when Orders is designed; enforce deletion race prevention and server-derived type at that time. |

### Review changes on the branch

- `2e488a0` — literal name search and tier-indexed validation errors.
- `3b2349b` — four additional feature regression tests; module now has **23 written tests**, but the four additions have **not yet been reported as passing**.
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
# The original 19 module tests passed before the review changes; rerun all 23:
php artisan test --testsuite="Printing Services Module"
php artisan test
php artisan route:list --path=api/v1/printing-services
php artisan route:list --path=api/v1/selects
php artisan db:seed --class="Modules\PrintingServices\Database\Seeders\PrintingServicesDatabaseSeeder"
```

**Validation focus:** create with nullable/free base price; positive tiers; lookup group membership; case-sensitive name duplicate protection and reuse after Soft Delete; open and inclusive ranges; catch-all fallback; atomic 0/1/2/3 tier commands; price preview order; names-only filter; no independent tier endpoints; 401/403 permissions.

## Boundaries and integration blockers

1. **PHPUnit passed in the owner's local environment** (19 module tests and 331 full-suite tests); results were supplied by the owner and not executed by the GitHub connector. Artisan route/migration/seed outputs and MySQL-specific behavior have not been independently reviewed. Any further code changes require a rerun.
2. **Orders historic-use rule is conditional.** Orders does not exist yet in this project branch. `DeletePrintingServiceAction` currently checks the prospective `order_items.printing_service_id` relation **when that table exists** and fails closed if the table exists without the FK column. Once Orders is built, the owning Orders module must explicitly lock/reference the service on order creation and test the delete/create race across draft/cancelled/soft-deleted order records. Passing Printing Services tests today does not prove this future contract.
3. **Settings customer-type historic protection.** Before integrated usage, Settings must prevent unsafe deletion of customer types referenced by price tiers and agree on deactivation policy, without cascading pricing data.
4. **MySQL concurrency.** SQLite tests do not prove real `lockForUpdate` serialization. Test two competing tier writes, live-name rename/create operations and the service-delete/order-create race against actual MySQL.
5. **Unicode normalization.** Whitespace trimming/collapse and letter-case-sensitive uniqueness are approved. NFC/NFD equivalence is still a technical detail in PS-OPEN-11, to resolve with tests if needed.
6. **Numeric storage.** `DECIMAL(30,2)` and PHP signed integer limits are technical storage boundaries; there is no independent business cap. Test overflow/precision to avoid truncation.
7. **Orders price snapshots.** Orders owns final line total and manual override behavior; these snapshots and update triggers are not new Printing Services columns.

**Review verdict:** Earlier local PHPUnit execution was green (**19 module tests / 331 total before review changes**). The updated code and **23-test module suite** still need local rerun. Complete MySQL migration/concurrency verification, route/permission inspection, and explicitly handle Settings/Orders integration findings before final merge sign-off. No production or full test-pass claim is made for the latest commit.
