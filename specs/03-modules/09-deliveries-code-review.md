# Deliveries Module — Code Review

Review branch: `feature/deliveries`
Target branch: `develop`
Date: 2026-10-24 / 2026-10-25 (Africa/Cairo)
Status: code corrections committed; CI validation required; **not merged**.

## Findings and fixes

| Severity | Problem | Resolution | Regression |
|---|---|---|---|
| HIGH | Laravel app defaults to UTC but `delivered_at` was saved from a Cairo-local Carbon object. Serializing it back as UTC can shift the instant by +3h. | Persist `CarbonImmutable::now('UTC')`; independently derive `delivered_local_date` by conversion to `Africa/Cairo`; use the same UTC instant in Orders and Deliveries. | Cairo midnight boundary test checks persisted UTC instant, API ISO-8601 offset, and stored local date in both modules. |
| HIGH | Under MySQL's REPEATABLE READ, nonlocking delivery existence queries can start a consistent-read snapshot before the customer lock. Later plain ledger `SUM` would ignore another user's just-committed collection, giving an incorrect pre-collection balance and potentially bypassing credit confirmation. | Add Finance-owned `LedgerService::centsForPosting`, fetching the customer's ledger movements via `SELECT ... FOR UPDATE` after customer lock; use that only for delivery posting. | SQL locking assertion; **two independent MySQL connections** demonstrate an old snapshot versus current locked balance after another session's committed ledger movement. |
| MEDIUM | Delivery history used `whereDate` on `delivered_local_date`, a DATE column, wrapping it with a DB function and impairing its index. | Use ordinary indexed column predicates `where('delivered_local_date', >=/<=, ...)`. | Integration test asserting both endpoints' filter bounds and SQL avoids `DATE(...)`. |
| LOW | Delivery POST for an unknown order ID produced business conflict (409) rather than expected not found (404). | In Orders owner adapter, return 404 for an absent Order. State conflicts on existing orders remain 409. | Unknown-ID feature test. |

## Also inspected

- **Atomic writes:** Delivery and optional Finance collection share a database transaction. Cancellation and linked exact Collection reversal share a transaction, with rollback on failure and no duplicated ledger movement.
- **Order invariants:** Confirmed + nondeleted + every active item ready, current-read item locks while holding the order lock. Production can no longer undo after physical delivery.
- **Re-delivery:** Cancelled Delivery history persists; a new delivery row can exist later. Current active delivery is checked while holding the Orders row lock. No `UNIQUE(order_id)` blocks legitimate re-delivery.
- **Authorization:** `deliveries.create` needed for handover; a positive payment additionally needs `finance.collections.create`. `deliveries.cancel` authorizes reversal of **only the linked** delivery receipt, never an arbitrary Finance collection.
- **Idempotency:** Actor + operation + hashed key + canonical payload prevent duplicate delivery/cancel. Positive-payment success remains replayable after bank/wallet deactivation.
- **Read paths:** Ready page loads grouped orders and products with batched overall customer balances. History uses batch Orders snapshots and Finance collection projections. Monetary details are redacted when the actor lacks Finance permission.
- **Contract consistency:** CamelCase, localized messages, decimal strings, 422 validation / 409 conflicts / 403 authorization / 404 missing resources, dates localized to Cairo.

## Concurrency verification scope and residual risks

The two-independent-connection MySQL regression proves real row-lock contention and stale-vs-current snapshot balance behavior under REPEATABLE READ. **It does not orchestrate two complete Laravel HTTP workers simultaneously delivering/cancelling the same order**; a multiprocess end-to-end race test and load-based EXPLAIN query planning are worthwhile before high-concurrency production traffic.

`LedgerService::centsForPosting` performs an indexed, locking read of the customer's ledger rows and sums decimal cents in PHP to guarantee a current view. This trades extra row reads/locks for correctness; review cost if a customer eventually has a very large ledger.

The refund/physical settlement confirmation flag is an acknowledgment only. Accounting reversal never itself transfers bank/wallet funds.

Existing SQLite suite contains skipped tests; review those separately rather than describing them as failures.

## Verification / local checkout

```powershell
git fetch origin
git switch feature/deliveries
git pull --ff-only origin feature/deliveries
composer dump-autoload
php artisan migrate
php artisan module:seed Deliveries
php artisan test
```

This review does not open a PR or merge to develop automatically.
