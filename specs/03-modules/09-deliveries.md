# Deliveries Module — Tabaa v1 (Static) / مواصفات التسليمات

**Date:** 2026-10-24
**Repository:** m-hassan00096/printingApi
**Branch:** feature/deliveries, created from develop after merged Production PR #7
**Status:** v1 backend implementation committed on `feature/deliveries` (six endpoints, migration, contracts, tests). **Not merged into `develop`.**
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

## 5. Detailed API Contracts (v1 — for review before implementation)

This is the **canonical public HTTP contract** for the 6 Deliveries endpoints. Examples use illustrative IDs/numbers; PHP code is **not yet implemented**. The global ApiResponse convention, Sanctum SPA authentication, Orders/Finance/Settings enums and permission system remain unchanged.

### 5.0 Shared conventions

- Base: `/api/v1/deliveries`. Read requests return `{"success":true,"message":"","data":{...}}`. Successful writes return HTTP 200 with localized `message` and `data:null`. The UI refetches after a write.
- All input/output keys are camelCase; IDs positive integers; money is an **EGP decimal string**, never a float. Dates `YYYY-MM-DD`, timestamps ISO-8601. Delivery date filters use the recorded `Africa/Cairo` **local delivery day** (not order creation date).
- Errors: validation **422** `{"success":false,"message":"...","errors":{"field":["..."]}}`; business rule conflict **409** `{"success":false,"message":"...","errors":[]}`; forbidden **403**; nonexistent resource **404**.
- Pagination: `page` defaults 1, `perPage` defaults 20 (range 1–100); `{"currentPage":1,"lastPage":1,"perPage":20,"total":1}`. Unknown filters/sorts/input keys are rejected with 422.
- Search: string 1–200 characters after trim, escaped SQL LIKE wildcards; matches immutable Orders snapshots for `orderNumber`, `customerName`, `customerPhone`. Actor/user ID and balances never accepted from the client.
- Both POST endpoints require header `Idempotency-Key` (nonblank string, max 160 bytes). The fingerprint includes operation, target order/delivery ID and canonical request body; same successful request/key/actor replays success without duplication, reuse for another request gives 409.
- Enum values from existing modules: `OrderProductionStatus` NOT_STARTED=0, IN_PRODUCTION=1, PARTIALLY_READY=2, READY_FOR_DELIVERY=3, DELIVERED=4; `OrderItemStage` READY_FOR_DELIVERY=4; `PaymentMethod` CASH=0, BANK_TRANSFER=1, E_WALLET=2; `BalanceType` ZERO=0, DEBIT=1, CREDIT=2; `CollectionSource.DELIVERY=2` and `CollectionStatus` POSTED=1 / REVERSED=2. New `DeliveryStatus` ACTIVE=1, CANCELLED=2. Display translated `*Label` fields.
- Customer balance means **the overall Finance ledger account** (positive/debit = customer owes money, negative/credit = customer has credit), *not* an amount allocated to this particular order. Staff need `finance.balances.view` to see it; otherwise ready-list/preview return `customerBalance:null`.
- The frontend may request Settings bank/wallet choices from the existing `GET /api/v1/selects?selects=bankAccounts,eWallets`, respecting existing Select permissions.

### 5.1 Routes, methods and permission matrix

| Method | Path (after /api/v1) | Permission | Response data |
|---|---|---|---|
| GET | `/deliveries/ready` | `deliveries.ready.view` | `orders[]` + `pagination` |
| GET | `/deliveries/ready/{order}` | `deliveries.ready.view` | `order`, `customerBalance`, `canDeliver`, `canCollect` |
| POST | `/deliveries/orders/{order}/deliver` | `deliveries.create`; additionally `finance.collections.create` for positive payment | `null` |
| GET | `/deliveries` | `deliveries.list` | `deliveries[]` + `pagination` |
| GET | `/deliveries/{delivery}` | `deliveries.view` | `delivery` |
| POST | `/deliveries/{delivery}/cancel` | `deliveries.cancel` only (includes linked financial reversal) | `null` |

Laravel must register the static `/ready` and `/ready/{order}` routes before the dynamic `/{delivery}`. Delivery cancellation does NOT grant global access to the separate Finance reversal endpoint.

### 5.2 GET /api/v1/deliveries/ready — جاهزة للتسليم

Example request:

```http
GET /api/v1/deliveries/ready?filter[search]=الريادة&filter[customerId]=12&page=1&perPage=20&sort=dueDate
```

**Query:** `filter[search]` (optional string max 200), `filter[customerId]` (optional positive int), `page`, `perPage`, `sort` enum `dueDate|-dueDate|id|-id` (default `dueDate` ascending, NULL last, then ID ascending). Invalid values: 422.

Scope: CONFIRMED, not deleted, undelivered, **at least one** active order item ready in stage 4. Include PARTIALLY_READY but disable its delivery button. Do not show zero-ready, Draft, Cancelled or Delivered orders.

**200 example:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "orders": [
      {
        "orderId": 125,
        "orderNumber": "ORD-261024001",
        "customerId": 12,
        "customerName": "مؤسسة الريادة",
        "customerPhone": "01234567890",
        "dueDate": "2026-10-26",
        "orderTotal": "9600.00",
        "productionStatus": 3,
        "productionStatusLabel": "جاهز للتسليم",
        "readyItemsCount": 2,
        "totalItemsCount": 2,
        "isReadyForDelivery": true,
        "canDeliver": true,
        "customerBalance": {"amount": "6920.00", "type": 1, "typeLabel": "مدين"},
        "items": [
          {"id": 301, "serviceName": "كروت شخصية", "quantity": 1000, "productionStage": 4, "productionStageLabel": "جاهز للتسليم"},
          {"id": 302, "serviceName": "استيكرات", "quantity": 500, "productionStage": 4, "productionStageLabel": "جاهز للتسليم"}
        ]
      }
    ],
    "pagination": {"currentPage": 1, "lastPage": 1, "perPage": 20, "total": 1}
  }
}
```

For partially-ready orders: `productionStatus:2`, `readyItemsCount < totalItemsCount` and `canDeliver:false`; cards include current stages for non-ready items. `canDeliver` also requires the logged-in user to hold `deliveries.create`. The POST action rechecks eligibility under lock. `customerBalance:null` if missing balance-view permission; batch-query balances to avoid N+1.

### 5.3 GET /api/v1/deliveries/ready/{order} — preview/modal

`GET /api/v1/deliveries/ready/125`

**200 example:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "order": {
      "orderId": 125,
      "orderNumber": "ORD-261024001",
      "customerId": 12,
      "customerName": "مؤسسة الريادة",
      "customerPhone": "01234567890",
      "orderTotal": "9600.00",
      "dueDate": "2026-10-26",
      "productionStatus": 3,
      "productionStatusLabel": "جاهز للتسليم",
      "readyItemsCount": 2,
      "totalItemsCount": 2,
      "isReadyForDelivery": true,
      "items": [
        {"id": 301, "serviceName": "كروت شخصية", "quantity": 1000, "productionStage": 4, "productionStageLabel": "جاهز للتسليم"},
        {"id": 302, "serviceName": "استيكرات", "quantity": 500, "productionStage": 4, "productionStageLabel": "جاهز للتسليم"}
      ]
    },
    "customerBalance": {"amount": "6920.00", "type": 1, "typeLabel": "مدين"},
    "canDeliver": true,
    "canCollect": true
  }
}
```

`canCollect` requires **both** deliveries.create and finance.collections.create; `canDeliver` requires ready state + deliveries.create. Partially-ready orders may be previewed with both ready counters and `canDeliver:false`. Draft/Cancelled/Delivered or absent order returns 404 in this ready-screen scope.

The frontend defaults `collectedAmount="0.00"`. It calculates estimated after-payment balance from cents (do not use JS floats for accounting). Preview is advisory; POST recalculates balance under a customer lock. No extra preview API is necessary.

### 5.4 POST /api/v1/deliveries/orders/{order}/deliver — التسليم والتحصيل

**Headers:** `Idempotency-Key: <unique-request-key>`, `Content-Type: application/json`

**Paid delivery example:**

```http
POST /api/v1/deliveries/orders/125/deliver
Idempotency-Key: deliver-order-125-attempt-1
```

```json
{
  "receiverName": "أحمد محمد",
  "collectedAmount": "2000.00",
  "paymentMethod": 1,
  "paymentDestinationId": 3,
  "confirmCreditBalance": false
}
```

**Without payment (also valid):**

```json
{"receiverName": null, "collectedAmount": "0.00"}
```

**Validation rules:**

| Field | Rules |
|---|---|
| `receiverName` | Optional, nullable, trim, string max 150; blank normalized to null |
| `collectedAmount` | Optional decimal **string**, defaults `"0.00"`, nonnegative, max 2 fractional digits, fits DECIMAL(12,2); JSON numeric value forbidden |
| `paymentMethod` | Required integer enum 0/1/2 when collectedAmount > 0; absent/null for zero |
| `paymentDestinationId` | Required positive ID when paymentMethod is BANK_TRANSFER or E_WALLET; absent/null for CASH or zero. Wrong type/nonexistent -> 422; deactivated during posting -> 409 |
| `confirmCreditBalance` | Optional boolean, defaults false. Must be true if positive payment would create/extend **credit** balance (negative signed ledger) after posting, checked while Finance customer account locked |
| All other fields | Rejected with 422; no client-supplied actor, customer, totals, source, timestamps, orderId or collectionId |

**Eligibility/business:** order is CONFIRMED, active and not delivered, contains >=1 active item, ALL active items in stage READY_FOR_DELIVERY=4, no active Delivery record. Else 409. Must have deliveries.create; if positive amount also finance.collections.create or 403.

**Atomic DB effects:** optional Finance Collection source=DELIVERY (2) with one COLLECTION ledger movement and unique receipt number; create Deliveries record; set Orders `delivered_at`, `delivered_local_date` (Cairo) and `production_status=DELIVERED`. No separate order-debt posting because it was already recorded at confirmation. For `"0.00"` create no Collection and no LedgerEntry; customer balance may remain debit/credit.

**200 success:**

```json
{"success":true,"message":"تم تسليم الأوردر بنجاح.","data":null}
```

Repeat same successful Idempotency-Key returns same success, with no new collection/delivery; client refetches ready/history/balance.

### 5.5 GET /api/v1/deliveries — سجل التسليمات + فلتر التاريخ

Example:

```http
GET /api/v1/deliveries?filter[search]=الريادة&filter[dateFrom]=2026-10-01&filter[dateTo]=2026-10-24&filter[status]=1&page=1&perPage=20&sort=-deliveredAt
```

**Query:** `filter[search]` optional max 200; `filter[dateFrom]` / `filter[dateTo]` optional dates `YYYY-MM-DD`, each usable independently; inclusive against **deliveries.delivered_local_date**; if both supplied and from > to, 422 on `filter.dateTo`; `filter[status]` optional 1=ACTIVE, 2=CANCELLED. Sort whitelist `id|-id|deliveredAt|-deliveredAt`, default `-deliveredAt` then `-id`. Page/perPage shared rules.

**200 example:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "deliveries": [
      {
        "id": 88,
        "orderId": 125,
        "orderNumber": "ORD-261024001",
        "customerId": 12,
        "customerName": "مؤسسة الريادة",
        "customerPhone": "01234567890",
        "deliveredById": 7,
        "deliveredByName": "مدير المطبعة",
        "receiverName": "أحمد محمد",
        "receivedAmount": "2000.00",
        "effectiveReceivedAmount": "2000.00",
        "collectionId": 502,
        "receiptNumber": "COL-2026-00502",
        "collectionStatus": 1,
        "status": 1,
        "statusLabel": "تم التسليم",
        "deliveredAt": "2026-10-24T15:30:00+03:00",
        "deliveredLocalDate": "2026-10-24",
        "cancelledAt": null,
        "cancelledByName": null,
        "cancellationReason": null
      }
    ],
    "pagination": {"currentPage": 1, "lastPage": 1, "perPage": 20, "total": 1}
  }
}
```

`receivedAmount` is historical collection amount **only at that handover**, `"0.00"` if none; `effectiveReceivedAmount` is `"0.00"` if linked collection was REVERSED, otherwise the original value. Optional receipt IDs/details require `finance.collections.view`; when permission missing, receipt fields are null, but handover amount/status still visible. A cancelled handover remains in history using its **original delivery date**, with status 2 and cancellation metadata. Re-deliveries of same order appear as separate entries, not merged.

### 5.6 GET /api/v1/deliveries/{delivery} — details

Example `GET /api/v1/deliveries/88`.

**200 example:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "delivery": {
      "id": 88,
      "orderId": 125,
      "orderNumber": "ORD-261024001",
      "customerId": 12,
      "customerName": "مؤسسة الريادة",
      "customerPhone": "01234567890",
      "orderTotal": "9600.00",
      "deliveredById": 7,
      "deliveredByName": "مدير المطبعة",
      "receiverName": "أحمد محمد",
      "status": 1,
      "statusLabel": "تم التسليم",
      "deliveredAt": "2026-10-24T15:30:00+03:00",
      "deliveredLocalDate": "2026-10-24",
      "receivedAmount": "2000.00",
      "effectiveReceivedAmount": "2000.00",
      "collection": {
        "id": 502,
        "receiptNumber": "COL-2026-00502",
        "source": 2,
        "sourceLabel": "تحصيل وقت التسليم",
        "status": 1,
        "statusLabel": "مرحّل",
        "amount": "2000.00",
        "paymentMethod": 1,
        "paymentMethodLabel": "تحويل بنكي",
        "paymentDestinationId": 3,
        "destinationLabel": "الحساب الرئيسي"
      },
      "cancelledById": null,
      "cancelledByName": null,
      "cancelledAt": null,
      "cancellationReason": null,
      "canCancel": true
    }
  }
}
```

`canCancel` requires active delivery and `deliveries.cancel`; POST always rechecks. For zero payment: `collection:null` and amounts `"0.00"`. For a cancelled delivery: original collection stays as historical metadata, status REVERSED when reversed by cancellation, original `receivedAmount` stays and `effectiveReceivedAmount` becomes `"0.00"`. Detailed `collection` is only visible to users with `finance.collections.view`, otherwise null. No exposure of wallet secrets or internal ledger rows.

### 5.7 POST /api/v1/deliveries/{delivery}/cancel — إلغاء التسليم وعكس التحصيل

**Headers:** `Idempotency-Key: <unique-request-key>`, `Content-Type: application/json`.

```http
POST /api/v1/deliveries/88/cancel
Idempotency-Key: cancel-delivery-88-attempt-1
```

```json
{
  "reason": "تم تسجيل التسليم بالخطأ",
  "fundsReturnConfirmed": true
}
```

**Validation:** `reason` required trimmed string length 3..3000; `fundsReturnConfirmed` optional boolean. For a linked **POSTED** collection, must be true (otherwise 409); for zero-payment delivery or already-REVERSED receipt, not required. **The flag acknowledges manual cash/bank/wallet settlement; software does not transfer funds.**

**Authorization:** `deliveries.cancel` **alone** permits this **specific linked** Finance reversal in the same action; it does not grant generic Finance reverse permission. Never accept arbitrary collectionId in request body.

**Atomic DB effects:** lock and verify current ACTIVE delivery and its delivered Order; check exact linked collection belongs to same order/customer with source=DELIVERY. If POSTED, reverse through Finance-owned service exactly once; if already REVERSED, skip reversal. Preserve immutable original delivery/receipt history, set delivery status CANCELLED=2 and store actor/reason/cancelledAt; clear Orders current `delivered_at` / `delivered_local_date`, restore `production_status=READY_FOR_DELIVERY`. Item stages remain 4, allowing later physical delivery with a new Delivery row.

**200 success:**

```json
{"success":true,"message":"تم إلغاء التسليم وعكس التحصيل المرتبط به إن وجد.","data":null}
```

Same successful key replay: 200 with no second reversal. New key after cancelled: 409. Unknown delivery ID: 404. If reversal fails, the entire DB transaction fails, with no cancelled delivery and no changed order state.

### 5.8 Key error and concurrency cases

| Case | HTTP | Frontend / backend consequence |
|---|---|---|
| Missing/malformed Idempotency-Key; invalid date range, amount, enum, reason | 422 | Inline validation errors; no write |
| Bank/wallet destination ID missing or wrong type | 422 | Required select error |
| Order partly ready, no items, cancelled, or already delivered | 409 | No delivery; refresh card |
| Payment destination no longer active at posting | 409 | Refresh destination select |
| Positive collection creates customer credit without `confirmCreditBalance:true` | 409 | Ask for credit confirmation, then submit with a **new key** |
| Cancellation with posted receipt but `fundsReturnConfirmed` not true | 409 | Warn about funds settlement; retry with **new key** |
| Same Idempotency-Key used twice for same successful payload | 200 | No duplicate Delivery / receipt / reversal |
| Same key reused with different request/target | 409 | Reused key rejected |
| Insufficient deliveries permission / positive payment without finance.collections.create | 403 | No write |
| Unknown order/delivery | 404 | Not found |

Lock ordering: Orders adapter locks the order and checks all active item stages using **current locking reads**; Finance adapter locks customer/linked collection and writes ledger; Deliveries records status and idempotency in the shared outer DB transaction. Test with independent MySQL connections against concurrent deliver/deliver, deliver/Production undo, and deliver/cancel. Do **not** open separate HTTP requests to Finance. Idempotent replays must not fail solely because a previously used bank account became inactive after the original success.

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

## 10. Implementation status / المرحلة الحالية

Backend v1 has been implemented on `feature/deliveries`, without opening a PR or merging into `develop`:

- [x] Module registration and schema: `deliveries` and `delivery_idempotency_keys` with immutable cancellation history and support for re-delivery.
- [x] Orders-owned adapter for the ready cards, item counts, current-read order/item locking, delivered status and rollback to ready.
- [x] Finance-owned adapter for customer ledger balance (batched read), delivery collection source=2, payment destinations, and linked-collection reversal.
- [x] Six REST endpoints with input validation, permissions, Arabic/English messages, idempotent deliver/cancel and requested delivery-date filters.
- [x] Basic integration tests on SQLite and MySQL 8: paid and unpaid delivery, reversal, retries, credit confirmation, roles, date filters, bank/wallet and inactive/mismatched destination.
- [x] Last CI on commit `9bf4d881dee58ba5e63d33a18db25bdd7b5f1c30`: SQLite 478 passed / 9 skipped (2,376 assertions); MySQL 8 Deliveries 15 passed (157 assertions).
- [ ] **Before merge:** independent-connection MySQL race tests (two deliverers / Production undo / linked payment reversal), final code review and performance check with realistic order volumes. Current ordinary MySQL feature tests do not demonstrate true worker contention.
- [ ] Open a PR `feature/deliveries -> develop` after review and merge **only on explicit instruction**.

No dynamic stage management, partial physical delivery, additional payment module, or direct Deliveries writes to the Finance ledger tables.
