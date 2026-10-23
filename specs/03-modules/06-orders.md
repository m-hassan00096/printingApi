# Tabaa — Orders Core Specification (v1.1)

> **Implementation scope:** Orders Core on `feature/orders` only: persisted quotations (Draft), products, pricing snapshots, upload/download of order-item files, list/search/filter, activities, safe deletion, customer/service reference guards, and lifecycle/finance contracts. **NO actual Finance, Production, or Deliveries mutations in this module.** Confirm and Cancel may not post without a real Finance adapter. This specification records business decisions reviewed October 22, 2026.

## 1. Module boundaries

- Orders owns `orders`, `order_items`, `order_item_files`, `order_activities`, `order_daily_sequences` and all associated permissions.
- Customers owns customer records; Orders locks `customers` row on creation/reassignment and uses only ACTIVE live customers. Customers `POST` returns created CustomerResource including `id` to immediately choose the customer from the Add Customer modal. Order `customer_id` can change while DRAFT only.
- PrintingServices owns active services, pricing tiers and PriceResolver. Orders locks `printing_services` before referencing them; Orders implements/registers `PrintingServiceUsageGuard` which blocks deletion for **all historically referenced** services, including DRAFT/CANCELLED/soft-deleted items.
- Orders implements/registers Customers' `CustomerUsageGuard` (any persisted order, including draft/cancelled/soft-deleted, blocks Customer Soft Delete) and `CustomerStatusTransitionGuard` (**any historical persisted order**, including Draft, Cancelled, Delivered or Soft Deleted, blocks customer deactivation). This stronger rule supersedes the former undelivered-only rule in the Customers spec.
- Production later owns actual item stage transitions, designer ownership, undo, printer reassignment and automatic roll-up of order.production_status. Deliveries later owns actual delivery and `delivered_at`. Finance later owns immutable ledger, collections, applied credit and refunds.
- **No finance ledger inside Orders.** Do not allow an HTTP success indicating a confirmed/cancelled financially posted order until Finance records the matching ledger changes in the **same transaction**; expose explicit localized 409 until a real adapter is implemented and tested.

## 2. Statuses

### 2.1 OrderCommercialStatus (integer)
- DRAFT=0: quotation, no external-facing ORD number, not eligible for production, **no liability**.
- CONFIRMED=1: confirmed business order, daily number assigned; depends on Finance adapter transaction.
- CANCELLED=2: cancellation with reason; Finance reverses the order liability, but previously paid deposit **does not get refunded automatically**. Refund is a separate explicit Finance command executed only when money really leaves the selected destination.
- Delivery completion is **not** the same thing as commercial status; deliveredAt is separate.

### 2.2 OrderProductionStatus (integer, nullable)
- NOT_STARTED=0, IN_PRODUCTION=1, PARTIALLY_READY=2, READY_FOR_DELIVERY=3, DELIVERED=4.
- NULL for Draft; starts at NOT_STARTED on confirmed order. Computed and stored, **never client writable**.
- Later Production computes it from item stages. No direct status changes via Orders CRUD.
- A cancelled order never appears on the production board. A delivered order has deliveredAt set by Deliveries.
- ItemProductionStage reserved for later Production: NOT_STARTED, DESIGN_IN_PROGRESS, DESIGN_COMPLETED, PRINTING, READY_FOR_DELIVERY. The responsible designer is assigned on START; only that person or admin may finish/move/undo while in design; undo to NOT_STARTED frees ownership; undo back to design retains the original responsible designer; printing stage is not person-owned. Moving design-complete to printing requires selecting an active printing location, changeable later. Undo reason optional. All can view; assigned worker/admin can move design. Admin unrestricted.

### 2.3 OrderDelayStatus (integer, calculated, never stored)
- NO_DUE_DATE=0, LATE=1, ON_TIME=2.
- For Draft with no production schedule: expose null. For a confirmed order with no due date: NO_DUE_DATE.
- If delivered, LATE when delivery local date > due date, otherwise ON_TIME **permanently**. Store `delivered_local_date` (`Africa/Cairo`, set by Deliveries in the same transaction as `delivered_at`) so the listing filter matches the historic status even around UTC date boundaries.
- If undelivered, LATE when today's date in Africa/Cairo > due date, otherwise ON_TIME, even if all items are READY; this measures actual delivery.
- Cancellation freezes evaluation at cancellation date for historical audit; `lateOnly=1` includes both overdue **undelivered** confirmed orders and **delivered-late** confirmed orders, excluding cancelled orders.

## 3. Order fields / normalization

- `orders.id` BIGINT PK; `order_number` nullable unique; confirmed format `ORD-yymmddNNN` (daily sequence >=001, expands to 1000+ when needed). Date and reset use Africa/Cairo **confirmation** day, not creation day. No reuse on cancellation/soft delete. Generate under transaction and daily sequence-row lock; no generated `ORD` number for Draft.
- Required `customer_id`, immutable once confirmed. Source integer enum BRANCH=0, ONLINE=1; optional `notes` plain text and `due_date` DATE (today/future on user input, Africa/Cairo; null allowed). Dates can be extended on later permitted edits, with important audit event.
- `customer_name_snapshot`, `customer_phone_snapshot`, `customer_type_id_snapshot`, `customer_type_name_snapshot` copied from trusted customer and Settings type at draft save/reassignment; **immutable after Confirm**.
- `total_amount` DECIMAL(12,2) sum of active item agreed totals; EGP only, string money. `created_by`, `updated_by`, `confirmed_at`, `delivered_at`, `cancelled_at`, `cancelled_by`, `cancellation_reason`, timestamps, Soft Deletes.
- `order_items`: order_id, printing_service_id, service_name_snapshot, customer_type_id/name_snapshot, quantity unsigned positive integer, specifications nullable text, `agreed_total` DECIMAL(12,2), `suggested_total` DECIMAL(12,2) nullable, `price_source` nullable, `was_manually_priced` boolean, `production_stage` integer, designer_id nullable, printing_location_id nullable, created/updated by, timestamps and soft deletes.
- Repeated service IDs among order items allowed. Changing an existing Draft item's **serviceId** creates a replacement item and Soft Deletes the old item, transferring its active file references, so historic service references and price snapshots are preserved; the returned replacement item has a new ID. Unit price displayed as **derived informational value** agreed_total / quantity (do not trust submitted unit price), total is authoritative.
- `order_item_files`: order_item_id, private disk/path, original filename, extension, MIME, byte size, uploader id, soft deletes, timestamps. Supported images, PDF, doc/docx, xls/xlsx, psd, ai, exported Canva PNG/PDF. Max 100 MiB (104857600 bytes) **each**; protected authorized download, never public link. Reject content whose file magic/signature is inconsistent with the supplied extension; this is not a substitute for antivirus scanning. All downloads are forced attachments.
- `order_activities`: order_id, actor_id, event_type, structured metadata with relevant old/new values, immutable created_at. No every-keypress audit.
- No order files stored in public storage and never serve inline.

## 4. Pricing and snapshots

- All price previews derive from customer.customer_type_id in trusted DB and PrintingServices.PriceResolver; never accept customerTypeId from frontend to determine actual item pricing.
- Frontend displays suggested pricing separately; **user-input agreedTotal is the authoritative agreed price**. Input DECIMAL(12,2), >=0 on Draft; no discount field and no arbitrary frontend source.
- Preserve `agreed_total`, suggested_total, source and service/name/type snapshots from the moment of the saved draft revision. Catalog price changes do not automatically change saved Draft or Confirmed items; user must explicitly edit/refresh. When Draft customer changes, new suggested prices come from the new customer's type but edited agreed totals remain as entered; the UI may prompt the user to adopt new suggestions.
- A confirmed order's snapshots are frozen. Confirm without a proper Finance record remains blocked until gateway installation.
- Money math on totals uses integer cents / string decimal, never floating-point. Enforce total_amount <= 9,999,999,999.99. If the PrintingServices suggested total exceeds Orders' DECIMAL(12,2) range, reject with localized 422 and roll back the Draft; never allow an internal DB overflow/500.

## 5. Orders Core CRUD API contracts (v1.1)

All under `auth:sanctum` with Spatie permission. Standard `ApiResponse` success/message/data and 422 validation/409 business rules.

| Endpoint | Permission | Behavior |
|---|---|---|
| `GET /api/v1/orders` | `orders.view` | `data.orders` array + `data.pagination`; search by orderNumber/customer.name/customer.phone; exact `filter[status]`, `filter[productionStatus]`, `filter[lateOnly]=1`; sort id/created_at/due_date default `-id`; page/perPage max100. All includes Draft and Cancelled; Draft orderNumber = null. |
| `GET /api/v1/orders/{order}` | `orders.view` | order Resource incl. items/files and relevant history; no historical Finance values until Finance exists |
| `POST /api/v1/orders` | `orders.create` (plus `orders.confirm` when `status=1`) | Requires `Idempotency-Key` header. Accepts `status=0` Draft (default if omitted) or `status=1` Confirmed; accepts optional `depositAmount` **only** with `status=1`. Always creates products from `items` (min 1) and returns HTTP 201 plus full order resource. Direct confirmation is atomic: Finance ledger + generated ORD number + status; currently `status=1` returns 409 and rolls back **all** draft/idempotency/sequence writes until Finance is available. Replaying the same key + same payload returns existing order without posting again; changed payload returns 409. |
| `PUT/PATCH /api/v1/orders/{order}` | `orders.update` (plus `orders.confirm` when `status=1`) | Draft header plus optional `status=0/1`, `depositAmount` only with `status=1`. Omitted status or 0 retains Draft. Status 1 triggers **Draft → Confirmed** in the same transaction as header updates and Finance; requires `Idempotency-Key` header. Any Finance failure returns 409 and **rolls back all edits**. Customer change allowed only while Draft; confirmed/cancelled orders cannot be edited/downgraded via this endpoint. HTTP 200 localized message and full order resource. |
| `POST /api/v1/orders/{order}/items` | `orders.update` | Add item to Draft. |
| `PUT/PATCH /api/v1/orders/{order}/items/{item}` | `orders.update` | Update Draft item serviceId/quantity/specifications/agreedTotal; recalculate suggested metadata and order total. |
| `DELETE /api/v1/orders/{order}/items/{item}` | `orders.update` | Remove Draft item (Soft Delete); reject removing final item. Retain historic service references. |
| `POST /api/v1/orders/{order}/items/{item}/files` | `orders.update` | multipart `file` single <=100MiB, private storage, record metadata. |
| `GET /api/v1/orders/{order}/items/{item}/files/{file}` | `orders.view` | Authorized forced download; all nested resources must match their owner. |
| `DELETE /api/v1/orders/{order}/items/{item}/files/{file}` | `orders.update` | Soft Delete attachment metadata for Draft; retain stored bytes for possible audited recovery. |
| `GET /api/v1/orders/{order}/activities` | `orders.view` | Paginated important history ordered newest first. |
| `DELETE /api/v1/orders/{order}` | `orders.delete` + Admin role | Soft Delete **Draft only** (no Finance effects). Keep all history and refs. |
| `POST /api/v1/orders/{order}/confirm` | `orders.confirm` | **Explicitly blocked HTTP 409** until Finance adapter is provided. Later requires Idempotency-Key, atomic order numbering and ledger liability/deposit posting. |
| `POST /api/v1/orders/{order}/cancel` | `orders.cancel` | **Explicitly blocked HTTP 409** until Finance adapter implements reversal. Future cancel requires reason, stops production, leaves deposit as refundable credit until separate explicit Refund. |

Create shape (illustrative):
```json
{
  "customerId": 15,
  "source": 0,
  "status": 0,
  "dueDate": "2026-10-25",
  "notes": "ملاحظة",
  "items": [
    {"serviceId": 4, "quantity": 100, "specifications": "كارت وجهين", "agreedTotal": "250.00"}
  ]
}
```

`status` is **client-selectable only as an explicit requested transition** (`0` Draft, `1` Confirmed) in create/update; `2` Cancelled is rejected with 422 and requires its own cancel action. Omitted `status` defaults to Draft on create, and keeps Draft on update. `depositAmount` is an optional decimal string only with `status=1` (and may be null); it is never an automatic credit deduction. The server owns the **persisted** status after enforcing permissions, Finance and transaction semantics. Price source, customer type, total, production stage, delivery and timestamps are always backend-owned; reject unknown input fields. Confirming through create/update additionally requires `orders.confirm` and a valid `Idempotency-Key`. The dedicated `/confirm` endpoint remains available.

## 6. Lifecycle integration and invariants

- Lock customer row before committing new Draft or reassigning it; only ACTIVE and non-deleted allowed. Register Orders customer usage/deactivation guard immediately when persisted Drafts are enabled. This supersedes old Customers "only undelivered" rule.
- Lock PrintingService row for every item service reference, ensuring not deleted and active; register Orders PrintServiceUsageGuard **at the same time**.
- Draft deletion preserves history; only Admin with `orders.delete` can do it. Soft Delete of an Order does not enable removal of referenced Customers/Services.
- A confirmed/other non-Draft order is read-only via current Core CRUD; future Orders+Finance/Production features will introduce controlled modifications and logged reversal effects.
- A single shared `OrderConfirmationService` performs DRAFT → CONFIRMED whether invoked from POST create, PATCH/PUT update, or the dedicated `/confirm` route. The Finance gateway must post the immutable ledger **before** generating the order number or committing; confirmation adds the `order_confirmed` activity event. Status 1 requested through POST/PATCH without available Finance returns HTTP 409 with **no partial Draft, header modification, number, idempotency row, or activity**. Creation and confirmation run inside one outer transaction.
- Production/Deliveries later lock orders and items while changing stages/status, then recompute and persist production status. No client route may set production_status.
- No fake finance or temporary ledger; default Finance gateway is unavailable (409) for Confirm/Cancel; do not claim success without journal posting.
- Draft `Idempotency-Key` values are case-sensitive and stored only as SHA-256 digests to enforce exact uniqueness even with MySQL case-insensitive text collations.
- The future Refund is **explicit** from Orders detail, posted by Finance when the user confirms payout; cancellation alone must not debit bank, cash, wallet or claim refund happened.

## 7. Testing and permissions

- Permissions: orders.view, orders.create, orders.update, orders.delete, orders.confirm, orders.cancel.
- PHPUnit feature coverage: auth, permission isolation, safe draft create/update, customer reassignment & pricing snapshots, multiple same-service items, search/filter/pagination, immutable confirmed state, safe draft-only soft-delete, file authorization and size, guard behavior, failure of Confirm/Cancel without Finance, **status=0 and status=1 request contracts** (including atomic rollback and idempotency), localization.
- MySQL CI: migration constraints, concurrency on customer and service locks, future daily sequence uniqueness under InnoDB. Never run destructive migrate:fresh tests against a non-CI database.
- Use `lang/ar/orders.php` and `lang/en/orders.php` for every user-visible message. API fields camelCase. Do not return money as JSON floats.

## 8. Open integration decisions deferred to owning modules

- Finance adapter will coordinate confirmation, debt ledger, optional deposit, idempotency, credit allocation, correction and refund; Orders must not generate a confirmed numbered order on a failed finance posting.
- Production owns transitions, lock ownership enforcement and undo; Deliveries owns delivery itself.
- Any proposed edit/cancel on already confirmed order needs integration with immutable money movements; not unblocked in the draft-only core.
