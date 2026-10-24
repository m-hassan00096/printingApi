# Deliveries Module — Tabaa v1 (Static) / مواصفات التسليمات

**Date:** 2026-10-24
**Repository:** m-hassan00096/printingApi
**Branch:** feature/deliveries, created from develop after merged Production PR #7
**Status:** Specification / design. **No Deliveries PHP implementation or database migration yet.**
**Related:** 06-orders.md, 07-finance.md, 08-production.md

## 1. Agreed business scope

1. The deliveries page has two tabs: **جاهزة للتسليم** (ready & partially ready orders, with disabled delivery button unless every item is ready) and **سجل التسليمات** (history including cancelled deliveries).
2. Search by **order number, customer name, or phone**. Delivery-history tab supports **delivery date from/to** (inclusive, Africa/Cairo local dates). Dates may be supplied independently; history also supports status filtering.
3. A CONFIRMED and not-yet-delivered order can be physically delivered **only if ALL active order items are READY_FOR_DELIVERY** (stage 4). Absolutely no partial physical deliveries in v1.
4. Delivery is allowed with **no collection** (zero amount); customer debt can remain outstanding.
5. A positive amount collected during delivery is booked in **Finance**, NOT in a separate deliveries ledger. Exactly one payment method per delivery: CASH=0, BANK_TRANSFER=1, E_WALLET=2; bank/wallet destination selection mandatory for non-cash. Zero amount creates no collection/receipt.
6. The displayed customer balance is the **overall signed Finance ledger balance**, not a calculated per-order outstanding amount. Credit/debit must be described with Finance's BalanceType and amount. Additional collected money may create account credit; require explicit UI acknowledgment when the amount would make the customer credit-positive. Backend rechecks under the customer/ledger lock.
7. A user's optional **receiverName** identifies the human who actually received the order. The staff user recording the handover is inferred from the authenticated session as **deliveredBy** (no client-chosen user).
8. Physical delivery timestamp is the **server time**, not a user-editable date; local delivery date is computed in **Africa/Cairo**. The record retains its own timestamp/local date forever for history.
9. **Cancelling a delivery is a single atomic command**, requiring `deliveries.cancel` permission and a **required reason**. If it created a posted Finance collection, automatically reverse that **exact linked collection**, in the same database transaction, and restore the order to READY_FOR_DELIVERY. No separate `finance.collections.reverse` HTTP permission required for this composite authorized operation. If that collection was already reversed, do **not** reverse it twice. No other customer collections/deposits may be affected.
10. Cancellation never physically initiates a bank/wallet transfer or cash refund. The UI must explicitly warn about physical funds settlement and obtain an acknowledgment before cancelling an actively paid delivery, because an accounting reversal is not a payment processor action. Refund API must NOT also be posted for the same reversal.
11. Cancellation preserves historical delivery and collection records. An order can be delivered again after cancellation, producing a **new** delivery record. At most ONE active delivery can exist for an order.
12. Keep everything **static and simple**; no dynamic workflows, no partial item deliveries, no shipping carrier/tracking, no independent cashier/till, and no standalone delivery finance tables.

## 2. Current system references checked on develop

- `Modules/Orders/Models/Order`: `status` DRAFT=0, CONFIRMED=1, CANCELLED=2; `production_status` 0..4, with READY_FOR_DELIVERY=3, DELIVERED=4; `delivered_at` and `delivered_local_date` already exist.
- `Modules/Orders/Models/OrderItem`: item `production_stage` 0..4, READY_FOR_DELIVERY=4. All active items must have stage 4.
- `Modules/Finance/Models/Collection` is backed by `finance_collections` and exposes `source`, `amount`, `status`, `payment_method`, `bank_account_id`, `e_wallet_id`, receipt number, and immutable ledger posting.
- `Modules/Finance/Enums/CollectionSource::DELIVERY` already exists as integer 2. `CollectionStatus` POSTED=1 and REVERSED=2. `BalanceType` ZERO=0, DEBIT=1, CREDIT=2.
- `Modules/Finance/Services/CollectionService` already has an internal `post(array $data, CollectionSource $source, ?int $actorId)` method and `reverse(Collection $collection, string $reason, int $actorId, string $keyHash)`. Reuse their domain rules via a registered Finance-owned adapter, rather than call Finance HTTP APIs or directly insert ledger rows.
- `Modules/Settings/Contracts/PaymentDestinationLookup` is the established way for Finance to resolve active bank accounts / e-wallets.
- Never query another module's Eloquent models directly inside Deliveries business services. Use contracts and DTOs; adapter implementation lives with the data-owning module.
- No modifications to the existing Finance debt-recognition rules on order confirmation or general customer collections.

## 3. Data model — Deliveries owns only delivery records and idempotency

### 3.1 deliveries

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | Surrogate history identifier |
| `order_id` | BIGINT FK orders, indexed | **Not globally unique**; allows cancelled record followed by replacement delivery |
| `collection_id` | BIGINT FK finance_collections, nullable, unique | Optional linked **delivery-only** collection. NULL when delivered without payment |
| `receiver_name` | VARCHAR(150), nullable | Actual receiving person's name |
| `delivered_by` | BIGINT FK users | Employee who confirmed this handover |
| `delivered_by_name_snapshot` | VARCHAR(150) | Historic display name; do not overwrite if user is renamed |
| `delivered_at` | TIMESTAMP | Server-recorded historical instant |
| `delivered_local_date` | DATE | Cairo local date for history filters, always retained when cancelled |
| `status` | TINYINT | ACTIVE=1, CANCELLED=2; integer-backed enum + label |
| `cancelled_by` | BIGINT FK users, nullable | Authenticated employee who cancelled |
| `cancelled_by_name_snapshot` | VARCHAR(150), nullable | Historic canceller display |
| `cancelled_at` | TIMESTAMP, nullable | Time of cancellation |
| `cancellation_reason` | TEXT, nullable | Required when cancelled |
| `created_at` / `updated_at` | TIMESTAMPS | Eloquent bookkeeping |

- **No `received_amount` column:** historical received amount derives from the linked Finance collection; for cancelled/reversed cases the response exposes both original amount and effective/posted amount.
- **No duplicated customer ID, phone, order total, or ledger balance:** use Orders and Finance contracts. Customer/order name and phone on the list come from immutable post-confirmation order snapshots.
- Index `(delivered_local_date, id)`, `(order_id, status)`, `(status, delivered_local_date, id)`; `collection_id` unique (nullable).
- **One active delivery invariant:** under a database transaction, lock the order first, then check `orders.delivered_at` / order production status and the existing ACTIVE delivery, and reject duplicates (409). Do not add naive `UNIQUE(order_id)`: it would block re-delivery. If database-enforced conditional uniqueness is later needed, design a MySQL/SQLite-compatible generated/nullable key carefully; do NOT make two independently authoritative active-state flags.
- Avoid soft-delete on deliveries: records are historical, not deletable.

### 3.2 delivery_idempotency_keys

- `id` BIGINT; `actor_id` BIGINT FK users; `operation` VARCHAR(40); `key_hash` CHAR(64); `request_hash` CHAR(64); `result_delivery_id` BIGINT nullable; timestamps.
- UNIQUE `(actor_id, operation, key_hash)`. Store successful result ID for safe replay after delivery changed state, cancelled, or re-delivered; reject same key reused for a different payload with localized 409.
- Require HTTP `Idempotency-Key` for *both* delivery creation and cancellation, hash header server-side, canonicalize request payload before hashing.
- Never persist a "completed" claim separately from the successful transaction.

## 4. Read-side UI / behavior

### 4.1 جاهزة للتسليم (ready board)

- Display currently CONFIRMED, undelivered, non-soft-deleted Orders with **at least one active item** and **at least one READY_FOR_DELIVERY item**; includes PARTIALLY_READY and READY_FOR_DELIVERY. Fully nonready orders remain on Production; no disabled zero-ready cards in this tab.
- Return grouped order cards including `orderId`, `orderNumber`, `customerId`, `customerName`, `customerPhone`, `orderTotal` (decimal string), `readyItems`, `totalItems`, `isReadyForDelivery`, `productionStatus` / `productionStatusLabel`, `items[]` with item `id`, `serviceName`, `quantity`, `productionStage` and label, and current customer balance (amount, type, typeLabel).
- `canDeliver` depends on eligibility, including *all* items ready; authorization is enforced server-side at POST regardless of frontend button.
- Search `filter[search]`: order number/customer name/customer phone; optionally `filter[customerId]`. Paginate orders, not products; do not fetch an unbounded set of items or issue N+1 Finance balance queries.
- Ordering: newest or closest due date first (document exactly when API implemented).

### 4.2 Delivery confirmation modal

- Fetch a fresh delivery preview scoped to an eligible/partly-ready confirmed order. Display order number, customer name/phone, order total, **current overall customer balance before payment** (finance ledger), default collected amount `"0.00"`, computed estimated after-payment balance, payment method / destination when >0, and optionally receiverName.
- Estimated balance is UI-only; service calculates authoritative final balance after lock. Use decimal strings and integer-cent math (not JS floating-point for accounting invariants).
- If the collection would create/extend customer credit, require explicit confirmation (`confirmCreditBalance: true`); backend rechecks at submission, because the customer might have other payments between preview and confirmation.
- Do not require collection when delivery amount is zero. If payment amount >0, require payment method and appropriate destination, using Settings selects through Finance adapter.
- Cash, bank, and e-wallet as shown in supplied screenshots.

### 4.3 سجل التسليمات (history)

- Search order number, customer name, customer phone; filter inclusive `filter[dateFrom]` and `filter[dateTo]` using historical **deliveries.delivered_local_date**, not order creation date, and independent bounds are supported; optional `filter[status]` ACTIVE/CANCELLED. Dates in Africa/Cairo. Paginated and sorted by delivery time descending then ID.
- Columns: order number, customer, phone, **delivered by**, **amount received at the handover** (original amount), **effective collection amount** (0 if reversed), delivery timestamp, status, plus details/cancel action where permitted.
- Cancelled history remains visible with original delivered timestamp, cancellation reason, operator, cancellation time, and any linked reversed collection.
- The "received at handover" amount is NOT every collection for the order and is NOT the customer's balance.
- Do not auto-count reversed collections as money still held by the business.

## 5. Suggested API contracts — same global API envelope

All endpoints use `auth:sanctum` and explicit Spatie permissions. GET: `{"success":true,"message":"","data":{...}}`. Writes: localized `message`; write `data` should be `null` where the frontend can refetch, unless a newly created delivery ID is needed immediately (here v1 uses `data:null` and refreshes). Validation 422, business conflict 409, unauthorized 403, absent resource 404. CamelCase keys, IDs integers, money strings.

| Verb | Route | Query/Request | Response `data` | Permission |
|---|---|---|---|---|
| GET | /api/v1/deliveries/ready | `filter[search]`, `filter[customerId]`, `page`, `perPage`, `sort` | `orders[]`, `pagination` | `deliveries.ready.view` |
| GET | /api/v1/deliveries/ready/{order} | order ID | `order`, `customerBalance`, `canDeliver` | `deliveries.ready.view` |
| POST | /api/v1/deliveries/orders/{order}/deliver | request below + Idempotency-Key | null, message | `deliveries.create`; **also** `finance.collections.create` when amount >0 |
| GET | /api/v1/deliveries | `filter[search]`, `filter[dateFrom]`, `filter[dateTo]`, `filter[status]`, `page`, `perPage`, `sort` | `deliveries[]`, `pagination` | `deliveries.list` |
| GET | /api/v1/deliveries/{delivery} | delivery ID | `delivery` (including receipt/cancellation metadata) | `deliveries.view` |
| POST | /api/v1/deliveries/{delivery}/cancel | `reason`, conditional `fundsReturnConfirmed` + Idempotency-Key | null, message | `deliveries.cancel` **only** |

### 5.1 Delivery POST request

```json
{
  "receiverName": "أحمد محمد",
  "collectedAmount": "2000.00",
  "paymentMethod": 1,
  "paymentDestinationId": 3,
  "confirmCreditBalance": false
}
```

Rules:
- `receiverName` optional string, max 150; blank becomes null.
- `collectedAmount` decimal string, 0 or positive, up to two decimal places, compatible with Finance DECIMAL(12,2). If omitted, default `"0.00"`; no numeric JSON float.
- `paymentMethod` MUST be absent/null when amount=0; required enum 0/1/2 when amount>0. `paymentDestinationId` only for bank/wallet: required then; prohibited/null for cash/zero.
- `confirmCreditBalance` optional boolean; when >0 collection would produce a credit balance, require it true or return business 409. No automatic overpayment cap because Finance supports credit.
- Do NOT accept `customerId`, `orderTotal`, `deliveredBy`, `deliveredAt`, `collectionSource` or `balanceAfter` from frontend.

### 5.2 Delivery cancellation POST request

```json
{
  "reason": "تم التأكيد بالخطأ",
  "fundsReturnConfirmed": true
}
```

- `reason` required, trim, length 3..3000, not empty.
- `fundsReturnConfirmed` must be true when the linked collection remains POSTED; not required when no linked collection or it is already REVERSED.
- The confirmation flag acknowledges **external physical funds reconciliation**; software neither triggers a payout nor relies on the flag as proof of bank transfer. Employees must settle the real cash/bank/wallet balance appropriately.
- Reversal must affect **only** `deliveries.collection_id` and use the same cancellation reason and authenticated actor in Finance audit.

### 5.3 Example GET delivery history record

```json
{
  "id": 31,
  "orderId": 1026,
  "orderNumber": "ORD-1026",
  "customerName": "مؤسسة الريادة",
  "customerPhone": "01234567890",
  "deliveredById": 7,
  "deliveredByName": "مدير المطبعة",
  "receiverName": null,
  "receivedAmount": "2000.00",
  "effectiveReceivedAmount": "0.00",
  "collectionId": 47,
  "receiptNumber": "COL-2026-00047",
  "collectionStatus": 2,
  "status": 2,
  "statusLabel": "ملغي",
  "deliveredAt": "2026-10-24T15:30:00+03:00",
  "cancelledAt": "2026-10-24T16:00:00+03:00",
  "cancelledByName": "المدير",
  "cancellationReason": "تم التأكيد بالخطأ"
}
```

*Illustrative values only; real order number and timestamps come from persisted data and Orders' actual number format.*

## 6. Transaction boundaries and module contracts

### Delivery creation (one DB transaction, retryable deadlock-safe)

1. Claim deliveries Idempotency-Key; successful replay returns original successful response *without reposting*.
2. Lock Orders-owned order row via **Orders-owned DeliveryOrderGateway** (not direct Deliveries model imports). Verify CONFIRMED, undelivered, no ACTIVE delivery, non-deleted, and every active item stage=READY_FOR_DELIVERY with at least one active item. Validate with current/locking reads under MySQL REPEATABLE READ.
3. For `collectedAmount > 0`: require finance collection permission, call a **Finance-owned DeliveryCollectionGateway** that internally uses Finance `CollectionService::post(..., CollectionSource::DELIVERY, actor)` and locks customer, checks live balance and the optional credit-confirm flag, validates method/destination and posts ledger+receipt. Return `collectionId` and immutable receipt details.
4. Insert Deliveries-owned delivery record with server time/Cairo date, actor/receiver, optional `collection_id`. Enforce only one ACTIVE delivery under the locked Order and rechecked history.
5. Via Orders-owned gateway set `orders.delivered_at`, `delivered_local_date` and `production_status=DELIVERED` (commercial status stays CONFIRMED). An Orders activity may be recorded via Orders-owned writer for consistency.
6. Commit. Any failure rolls back both delivery and Finance posting; concurrent duplicate submissions cannot both succeed.

### Delivery cancellation (one DB transaction)

1. Claim idempotency; lock/order validate exactly matching ACTIVE Delivery and current Orders DELIVERED state. Coordinate lock order to match Delivery and Production actions (order before affected item rows).
2. If `collection_id` exists: via Finance-owned gateway lock customer then linked collection; validate it belongs to this delivery order/customer and has source DELIVERY. If POSTED, require funds-return acknowledgment and reverse with existing immutable Finance collection-reversal domain logic. If already REVERSED, skip extra reversal.
3. Set delivery `status=CANCELLED`, `cancelled_by`, `cancelled_at`, and required reason, retaining original `delivered_at` and `collection_id`. No physical money transfer is performed.
4. Via Orders-owned gateway clear current `orders.delivered_at` and `delivered_local_date` and restore `production_status=READY_FOR_DELIVERY`; the historical date is preserved on Deliveries. The order is now eligible for a new delivery.
5. Commit the entire operation; no "delivery cancelled but collection still posted" partial-success state is allowed for a linked currently-posted collection.

### Suggested internal interfaces and ownership

- **Orders owns:** `DeliveryOrderGateway` interface, DTO projections/lock/eligibility checks, and the sole writer for Orders delivery timestamps and aggregate state. Orders implements/exports adapter through its provider. There must never be a second authoritative delivery status on Orders beyond current delivered_at/production_status.
- **Finance owns:** `DeliveryCollectionGateway` interface and adapter: `balance(customerId)`, `postDuringDelivery(orderId, customerId, money, method, destinationId, actorId, confirmCreditBalance)` and `reverseForDelivery(collectionId, orderId, customerId, actorId, reason, ... )`. If Contracts are consumer-owned in actual implementation, bind them in the owner provider with no cross-module Eloquent imports; avoid direct Finance model/SQL from Deliveries.
- **Deliveries owns:** `Delivery` history model, Idempotency service, `DeliverOrderAction`, `CancelDeliveryAction`, Delivery query/resource/controllers, and its permission seeder.
- **Concurrency:** The Order lock is acquired before OrderItem locking, so concurrent Production mark-ready/undo and delivery cannot both succeed unexpectedly. Finance's customer lock and collection lock ordering must match its existing posting/reversal flows. Review the complete lock graph and test independently connected MySQL 8 workers (not just SQLite) before merging.
- **No HTTP-to-HTTP orchestration**; all participating writes use one connection and outer Laravel transaction.

## 7. Permissions and identity

- `deliveries.ready.view` — ready cards and modal preview
- `deliveries.list` — historical deliveries list
- `deliveries.view` — delivery details
- `deliveries.create` — physical handover without payment
- `finance.collections.create` **also needed** on POST with positive collectedAmount (approved default: collection financial permission)
- `deliveries.cancel` — cancels delivery **and authorizes the linked Finance reversal** as ONE composite operation; do not additionally require finance.collections.reverse.
- All actor IDs derived from authenticated Sanctum stateful session; super-admin receives permissions using existing Identity RoleSeeder; role itself is not hardcoded as an override.
- Return 403 for missing permissions, never rely on hiding buttons in React.

## 8. Error handling

- HTTP 422: malformed decimal string, bad ID/enum/payment field combination, bad date range, empty reason, missing Idempotency-Key, too-long receiverName/unknown input.
- HTTP 409: order is Draft/Cancelled/Delivered or not fully ready; missing/currently inactive payment destination under transactional lock; explicit credit confirmation needed; delivery already cancelled; linked collection violates expected status/type/customer; reused key with mismatched payload; refund acknowledgement missing if collection is POSTED (if treated as business confirmation).
- HTTP 404: unknown order/delivery resource.
- HTTP 403: missing deliveries or (positive-payment only) Finance create-collection permission.
- GET messages empty; write messages localized in Arabic; follow global `ApiResponse` conventions.

## 9. Tests / acceptance criteria

1. The ready tab shows fully ready and partially ready order cards with correct ready/total counts, services, balances, and disabled/allowed flags. Unready (zero ready), Draft, Cancelled, Delivered never appear; no N+1 queries.
2. Search matches order number, customer name, phone; history dateFrom/dateTo independently filter **Cairo delivery date**, inclusive on both ends; pagination on orders/deliveries.
3. Delivery with `"0.00"` creates no Finance collection/ledger entry, but sets Order DELIVERED and writes delivery history.
4. Positive cash/bank/wallet collection uses Finance `CollectionSource::DELIVERY`, only authorized payment destinations, unique receipt, and ledger movement. No duplicated liability booking.
5. Account balance type display handles debit, zero, credit; client cannot inject balance/order totals or bypass credit confirmation. Balance revalidated while customer row is locked.
6. Delivery with any unfinished item rejects entire operation with HTTP 409; transaction leaves Order, Finance, and Deliveries unchanged. No partial handover.
7. Cancellation with posted collection automatically posts exactly one COLLECTION_REVERSAL in same transaction and clears current delivery state; cancellation without collection changes no finance rows.
8. Cancellation with already-reversed linked collection does not post a second reversal; linked unrelated customer collections remain untouched.
9. Cancel + retry with same idempotency key is safe; with another key after cancelled returns 409; delivery can be repeated after cancellation and gets a new ID while cancelled record remains visible.
10. Cancelling a posted-money delivery requires reason and the confirmed settlement flag; the UI explains actual bank/wallet refund cannot be automated by the accounting operation.
11. Historical received/effective received amounts and employee/receiver/date/reason are correct, including cancellation followed by redelivery.
12. Permission matrix: delivery without money, delivery with money, cancellation-composite; no unauthorized cross-module finance mutation.
13. Race tests on real MySQL connections: two simultaneous deliveries of one Order; delivery vs Product undo; delivery vs cancellation; payment vs reversal; verify no double Collection/Delivery and all locks respect consistent ordering.
14. Test full PHPUnit SQLite regression and MySQL 8 delivery suite via GitHub Actions. Confirm existing Orders/Finance/Production tests still pass.

## 10. Implementation stages (do not start PHP work from this planning commit)

1. Confirm specification and lock/payment contract edge cases. Write implementation checklist against existing API standards.
2. Generate static Deliveries module, migration, enum, seeder and translation files, Contracts/adapters in Orders and Finance, Finance permission guards.
3. Implement ready-list/history/preview queries (pagination, date filters, non-N+1 balances), then Deliver/Cancel Actions with shared DB transactions and idempotency.
4. Add feature tests and real MySQL concurrency scenarios, then code review.
5. Open Pull Request `feature/deliveries -> develop` after green CI; merge only on explicit instruction.

**No dynamic stage management, no additional payment module, no direct Deliveries edits to Finance ledger tables.**
