# Settings Module — Pre-PR Code Review

**Branch:** `feature/settings-module`  
**Target:** `develop`  
**Scope:** Laravel Settings CRUD, shared Selects, migrations, enums, validation, authorization, default selection, seeders and feature tests.

## Review outcome

**Conditionally ready for PR review, not yet certified for production-integrated Finance or Customers workflows.** This is a static repository review. The suite last reported by the developer was **169 passing tests (682 assertions)** *before* the review changes; all new review tests must be run locally before approval. No merge or PR creation is included in this review.

### Verified structure

- ParameterValues, Bank Accounts and E-Wallets expose the intended CRUD routes, and all are protected with `auth:sanctum` plus operation-level Spatie permissions.
- Requests own API validation; Actions and `PaymentDestinationDefaultService` own mutations; QueryBuilder services handle allowlisted pagination/search/status filters.
- Payment defaults use a shared integer-backed `DefaultStatus` enum with bank and wallet types managed separately. The Selects API returns numeric `isDefault`, with active-only options and masked account/phone identifiers.
- Technical lookup groups are seeded idempotently. Bank/wallet creation is user-driven rather than pre-populated with banking details.
- Soft-deleted values are excluded from ordinary index and select queries. Parameter-value names are permanently unique within a group. Bank/wallet identifiers are presently unique even after soft deletion.

## Findings resolved on this branch

| Priority | Finding | Resolution |
|---|---|---|
| Medium | Printing locations could accept and persist a nonempty `description`, contradicting the approved name-and-status-only data contract. | Store and Update FormRequests now reject nonempty `description` for the `printing_locations` group; added focused Feature Tests. Customer type descriptions remain supported. |
| Low | No single Settings seeder entrypoint combined technical-group and permission initialization. | Added `SettingsDatabaseSeeder` and tests for idempotency and no bank/wallet seed records. |
| Contract | `isDefault: 0` for the current oldest active default may reselect that same record through the fallback algorithm. | Explicitly **accepted by the owner**; preserved current algorithm and added bank and wallet Regression Tests rather than forcing a different candidate. |
| Documentation | Old spec statements still described implemented bank/wallet APIs as proposed and printing-location description restrictions as untested. | Updated the module specification accordingly. |

## Remaining risks / integration gates

### 1. Historical-use restrictions — important before cross-module use

Current Settings Delete Actions do not yet check Customer, Production or Finance usage, and the customer-type update route does not check whether active customers currently reference the type before deactivation. **These restrictions must be implemented when the owning tables/relationships become available, before allowing deletion or deactivation of in-use records.**

Once other modules reference Settings data, the owning modules must also validate the exact lookup group, active/not-deleted eligibility on new selections, and persist required historic snapshots. Until then, do not treat Settings DELETE as safe against historic business records.

### 2. Default concurrency — validate on target MySQL

`PaymentDestinationDefaultService` serializes per-type changes using `settings_payment_default_locks` and transactional `lockForUpdate()`. This is appropriate for MySQL and independent bank/wallet defaults. **The feature suite uses SQLite in-memory**, where `FOR UPDATE` behavior does not exercise true concurrent MySQL locking.

Before production rollout, run concurrent create/change-default requests against the deployed MySQL version. There is no additional database uniqueness constraint preventing multiple default rows if writes bypass the managed Action/Service. All writes must go through this service.

### 3. Unapproved bank/wallet identifier reuse

`bank_accounts.account_number` and `e_wallets.phone` use simple unique indexes, reserving identifiers after Soft Delete. This is **provisional** until the owner resolves `SET-OPEN-01`. A change to reuse-on-delete rules requires intentional schema and validation changes.

### 4. Replacement-default priority

The current fallback selects the oldest eligible active row (`id ASC`). Exact fallback priority with multiple candidates remains open (`SET-OPEN-02`). The owner specifically accepts the possibility that `isDefault: 0` on the current default leaves it as the selected default when the fallback picks the same row.

### 5. Optional example lookup values

`SettingsDatabaseSeeder` initializes parameter groups and permissions only; it does not add the illustrative editable `parameter_values` mentioned in the spec. If the business requires initial customer-type/printing-location examples on first install, approve their exact labels first and add a safe, idempotent seeder. No bank or wallet sample credentials should be inserted.

## Required local verification before PR

```powershell
git pull origin feature/settings-module

php artisan test --testsuite="Settings Module"
php artisan test
php artisan route:list --path=api/v1/settings
php artisan route:list --path=api/v1/selects
```

**Expected suite discovery after review:** 175 Settings tests (169 previously passing + 6 new review tests). Do not claim a passing result for the new tests until executed.

Verify migration and seeding on a disposable, non-production database. Test `SettingsDatabaseSeeder` idempotency, and confirm the desired policy for `SET-OPEN-01` and `SET-OPEN-02` before irreversible production commitments.

**Review boundary:** No changes made to `develop`; no automatic merge or PR created.
