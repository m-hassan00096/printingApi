# Settings Module — Pre-PR Code Review

**Branch:** `feature/settings-module`  
**Target:** `develop`  
**Scope:** Laravel Settings CRUD, select providers, database schema, validation, authorization, default selection, seeders and feature tests.

## Review verdict

**Conditionally ready for a Pull Request review, but not certified for production-integrated Customers / Production / Finance workflows.** This is a static GitHub repository review. The latest local result reported by the developer **before** these review changes was **169 passing Settings tests (682 assertions)**. The current branch contains **178 discovered Settings test methods**; the newly added/modified tests must be rerun locally. No Pull Request or merge was made as part of this review.

## Verified structure

- Parameter Values, Bank Accounts and E-Wallets use `auth:sanctum` plus operation-level Spatie permissions on CRUD routes.
- FormRequests validate API fields; mutation Actions / `PaymentDestinationDefaultService` handle writes; QueryBuilder services allowlist index filters/sorts.
- `printing_locations` and `customer_types` use backend-controlled groups; **both now accept optional nullable `description`** on create/update, in accordance with the latest owner decision.
- Payment destinations use the shared int-backed `DefaultStatus` enum; management resources and shared Selects expose numeric `isDefault` (`0` or `1`).
- Bank/wallet Selects only include active, non-soft-deleted options and mask account/mobile identifiers.
- A transactional lock row per payment type coordinates default changes through the shared service on MySQL. Bank and wallet defaults are independent.
- `parameter_values` has a group-scoped unique name constraint including soft-deleted records. Bank account numbers and wallet phones are unique across active and soft-deleted rows.
- `SettingsDatabaseSeeder` runs technical-parameter and permission seeders, then Identity's `RoleSeeder` to synchronize all registered web permissions into the existing `admin` role. It never seeds real or dummy payment destinations.
- Management resources expose full account/phone identifiers only behind the corresponding management view permission.

## Findings addressed during review

| Priority | Finding | Resolution on `feature/settings-module` |
|---|---|---|
| Medium — contract | Printing-location `description` was prohibited in Store/Update despite the owner's latest instruction to allow it. | Removed group-specific `prohibitedIf` validation. Replaced contradictory feature tests with create/update/clear-description cases. Updated `SET-004` and relevant spec sections. |
| Medium — stale mutation | A previously route-bound ParameterValue could be soft-deleted by another request while remaining loaded in an in-flight Delete Action. | Delete Action now re-queries a non-deleted row inside a transaction with `lockForUpdate`, rejecting stale records rather than silently deleting again. Added a targeted stale-model regression test. |
| Low — test isolation | ParameterValue tests could switch users after a stateful admin request while retaining Sanctum's previous password hash in the test session. | Cleared the test session before `actingAs` in the shared test helper. Added a regression expecting a permission-based `403` instead of an authentication `401`. |
| Low — setup | A dedicated aggregate Settings seeder was needed for the standard module-seed flow. | `SettingsDatabaseSeeder` is present, delegates to `ParameterSeeder` and `SettingsPermissionSeeder`, and has coverage for idempotency and avoiding payment seed data. |
| Medium — admin access | Seeding Settings after Identity created new permissions but could leave an existing `admin` role unable to access Settings (403). | `SettingsDatabaseSeeder` now calls the existing Identity `RoleSeeder` after registering Settings permissions; added a regression test for an existing admin role. No separate role-permission seeder was introduced. |\n| Documentation | Existing spec sections disagreed about printing-location descriptions. | Updated field definitions, request behavior and acceptance criteria to reflect nullable description support for both lookup groups. |

## Outstanding integration risks and decisions

### 1. Historical-use restrictions — important before other modules integrate

Settings cannot yet enforce the rule forbidding deletion of values already referenced by Customers, Production, receipts or advance payments. Customer-type deactivation also cannot be blocked for currently associated customers until those relationships/tables exist.

**Before the owning modules start storing references**, implement historical-use restrictions, active/group eligibility checks, and required name/financial snapshots in the relevant integration paths. Do not assume passing Settings CRUD tests proves these future cross-module invariants.

### 2. Default concurrency — verify against the target MySQL deployment

`PaymentDestinationDefaultService` uses `settings_payment_default_locks`, transactions and `lockForUpdate()` to serialize mutations per payment type. The current feature suite uses SQLite in memory and **does not reproduce concurrent MySQL requests**. Perform a concurrent create/update/default-switch smoke test on the target MySQL version. There is not yet a separate database constraint preventing multiple default rows if writes bypass the service.

### 3. Bank/wallet uniqueness after Soft Delete — decision still open (`SET-OPEN-01`)

`bank_accounts.account_number` and `e_wallets.phone` remain reserved after Soft Delete by their current unique indexes. This behavior is provisional pending business approval; do not change schema or silently approve number reuse during code review.

### 4. Replacement-default priority — decision still open (`SET-OPEN-02`)

When multiple active candidates exist, the implementation chooses the oldest ID. The owner accepted that explicitly submitting `isDefault: 0` on the oldest active default may leave it selected again. The general fallback-priority policy remains provisional.

### 5. Illustrative lookup seed values

`SettingsDatabaseSeeder` creates the technical lookup groups and permissions, not example `parameter_values`. The spec still includes illustrative example names; confirm whether example records should be seeded on installations before adding user-visible data. Avoid guessing banking credentials or customer/payment data.

## Final checks before PR

```powershell
git pull origin feature/settings-module

php artisan test --testsuite="Settings Module"
php artisan test

php artisan route:list --path=api/v1/settings
php artisan route:list --path=api/v1/selects
```

**Expected Settings test discovery: 178 tests, not yet freshly executed after these review changes.** Run new migrations and `SettingsDatabaseSeeder` against a disposable development database. Do not call these new tests passing until the developer reports their result.

No merge to `develop` and no PR creation were performed during this review.
