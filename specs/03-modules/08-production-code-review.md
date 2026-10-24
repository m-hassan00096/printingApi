# Production Module — Code Review

Reviewed: 2026-10-24
Source branch: `feature/production`
Target branch: `develop`
Scope: Production module, Orders/Settings integration adapters, API validation, permissions, database persistence and tests.
Status: Fixes applied on feature branch; do not merge automatically.

## Findings fixed

| Priority | Finding | Fix |
|---|---|---|
| HIGH | Under MySQL REPEATABLE READ, an early unguarded item-to-order lookup may establish a read snapshot before acquiring the order lock. The later `order->items()->pluck('production_stage')` could therefore read stale sibling states despite holding the order lock, producing an incorrect `orders.production_status` when concurrent users modify different items on the same order. | In Orders-owned `OrdersProductionAdapter`, calculate the rollup with `order->items()->orderBy('id')->lockForUpdate()->pluck('production_stage')`, a current/locking read, inside the same transaction while the order is locked. A MySQL-specific SQL assertion guards the query shape. |
| MEDIUM | `myDesignWork` retrieved every historic matching order-item ID into PHP and built a potentially huge `WHERE IN` list. | Pass a scoped Eloquent SQL subquery (`SELECT DISTINCT order_item_id FROM production_item_events WHERE actor_id = ? AND event_type = ?`) to the Orders-owned gateway, without making Orders import Production models. |
| MEDIUM | Retrying an already-successful Send/Assign request after the selected printing location is deactivated would be rejected by Form Request (HTTP 422) before the idempotency service could return the original success. | Form Request validates numeric ID and printing-locations group membership (422); workflow transaction locks the location and enforces *current active status* as business rule (409) on **new** attempts, while successful exact idempotency replays remain accepted. Regression test added. |
| MEDIUM | Query listing production items performed a Settings name lookup per item (N+1). History workflow could also repeat lookups for the same location. | Settings lookup has a batch `names` API with per-request memoization; Product query resolves printing location labels in one pass. |
| LOW | Board filtering by `order_items.production_stage` lacked a dedicated index. | Added Orders-owned migration for composite index `(production_stage, order_id)`. |
| LOW | Rename of work-filter argument temporarily left an undefined variable in Orders adapter. | Corrected signature to `?Builder $myItemQuery`; both CI suites returned green after correction. |

## Invariants checked

- Source stages cannot be skipped; DESIGN_COMPLETED stays in the Design column until printing is chosen.
- Active printing location must be selected before entering printing, both individual and batch. Location may change while in printing.
- Batch sends are all-or-nothing, sort IDs and roll up affected orders inside a DB transaction.
- The successful idempotency key is fingerprinted against action, actor and canonical payload; retries cannot double-post events.
- Undo is stepwise; audit history is append-only; production changes are prohibited after physical delivery.
- User-facing `myDesignWork` is derived from the session actor, not an untrusted frontend user ID.
- Combined complete-and-send requires **both** design complete and printing send permissions.
- Form Requests cover basic input structure and reference membership; domain state checks throw localized HTTP 409 BusinessException.
- Orders remains source of truth for product and order current stages; no duplicate state table or dynamic engine has been added.

## Residual / nonblocking recommendations

1. CI runs a dedicated MySQL 8 Production suite. It asserts the locking SQL for rollup, **but does not yet simulate two simultaneously executing independent MySQL client connections**. A real two-worker contention test is a recommended improvement before high-traffic use.
2. Future Deliveries operations must lock `orders` first, re-check all active items READY_FOR_DELIVERY and commit delivery atomically with final status; production already blocks updates after `delivered_at`.
3. If printer deactivation should be prohibited while still assigned to in-flight items, settle that business policy separately and implement a Settings deactivation guard in Orders; current policy permits historic printer links, and new assignments are blocked once inactive.
4. The existing 9 PHPUnit skipped tests should be audited independently; no assertion about their purpose or safety is made here.
5. Database indexes should be checked with EXPLAIN and real-volume data after deployment if the Kanban board grows large.

## Verification commands

```bash
git switch feature/production
git pull --ff-only origin feature/production
composer dump-autoload
php artisan migrate
php artisan module:seed Production
php artisan test
```

No merge to `develop` is part of this review.
