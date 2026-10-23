# Tabaa — Orders Core Specification (v1.5)

> **Implementation scope:** Orders Core on `feature/orders` only: persisted quotations (Draft), products, pricing snapshots, upload/download of order-item files, list/search/filter, activities, safe deletion, customer/service reference guards, and lifecycle/finance contracts. **NO actual Finance, Production, or Deliveries mutations in this module.** Confirm and Cancel may not post without a real Finance adapter. This specification records business decisions reviewed October 22, 2026.

## 1. Module boundaries

- Orders owns `orders`, `order_items`, `order_item_files`, `order_activities`, `order_daily_sequences` and all associated permissions.
- Customers owns customer records; Orders locks `customers` row on creation/reassignment and uses only ACTIVE live customers. Customers `POST` returns created CustomerResource including `id` to immediately choose the customer from the Add Customer modal. Order `customer_id` can change while DRAFT only.
- PrintingServices owns active services, pricing tiers, and the separate pricing preview API for the frontend. Orders locks `printing_services` before referencing them, but does not calculate or persist suggested prices; it implements/registers `PrintingServiceUsageGuard` to block deletion for all historically referenced services.
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
- `order_items`: order_id, printing_service_id, service_name_snapshot, customer_type_id/name_snapshot, quantity unsigned positive integer, specifications nullable text, `agreed_total` DECIMAL(12,2), `production_stage` integer, designer_id nullable, printing_location_id nullable, created/updated by, timestamps and soft deletes.
- Repeated service IDs among order items allowed. Changing an existing Draft item's **serviceId** creates a replacement item and Soft Deletes the old item, transferring its active file references, so historic service references and price snapshots are preserved; the replacement item has a new internal ID. Unit price displayed as **derived informational value** agreed_total / quantity (do not trust submitted unit price), total is authoritative.
- `order_item_files`: files are stored on Laravel's `public` disk with UUID filenames. API resources return `Storage::disk('public')->url($file->path)`. Original metadata, extension, MIME, size and uploader are saved; uploaded types and the 100 MiB limit remain unchanged. The database keeps `uploaded_by` for audit but API file responses omit `uploadedBy`.
- `order_activities`: order_id, actor_id, event_type, structured metadata with relevant old/new values, immutable created_at. No every-keypress audit.
- New, active files are public and viewable through `/storage/...` after `php artisan storage:link`. File, item and Draft-order deletions relocate their still-public attachments to private `local` storage, preserving each file's exact relative path and bytes. Stored file disk becomes `local`. Previously private files retain `url: null`.

## 4. Pricing and snapshots

- Suggested pricing is calculated/displayed separately via the PrintingServices preview API in the frontend, based on the selected customer's type. Orders never derives its agreed price from the catalog or records whether a user manually changed that suggestion.
- Frontend displays suggested pricing separately; **user-input agreedTotal is the authoritative agreed price**. Input DECIMAL(12,2), >=0 on Draft; no discount field and no arbitrary frontend source.
- Preserve `agreed_total` and service/name/customer-type snapshots from the saved Draft revision. Catalog price changes never modify saved Draft or Confirmed items. When a Draft customer changes, update customer-type snapshots but keep each `agreedTotal` unchanged. The frontend can refresh the preview and send a different `agreedTotal` only when the employee chooses it.
- A confirmed order's snapshots are frozen. Confirm without a proper Finance record remains blocked until gateway installation.
- Money math on totals uses integer cents / string decimal, never floating-point. Enforce total_amount <= 9,999,999,999.99. Catalog suggestions, regardless of their amount, do not affect Orders validation or persistence.

## 5. Orders Core CRUD API contracts (v1.1)

API routes use Sanctum and granular permissions; the file URLs are ordinary public storage assets served directly by the web server. Signed preview routes are removed.

| Endpoint | Permission | Behavior |
|---|---|---|
| `GET /api/v1/orders` | `orders.list` | `data.orders` array + `data.pagination`; search by orderNumber/customer.name/customer.phone; exact `filter[status]`, `filter[productionStatus]`, `filter[lateOnly]=1`; sort id/created_at/due_date default `-id`; page/perPage max100. All includes Draft and Cancelled; Draft orderNumber = null. |
| `GET /api/v1/orders/{order}` | `orders.view` | order Resource incl. items/files and relevant history; no historical Finance values until Finance exists |
| `POST /api/v1/orders` | `orders.create` (plus `orders.confirm` when `status=1`) | Requires `Idempotency-Key` header. Accepts `status=0` Draft (default if omitted) or `status=1` Confirmed; accepts optional `depositAmount` **only** with `status=1`. Always creates products from `items` (min 1) and returns HTTP 201 with localized `message` and `data: null`. Direct confirmation is atomic: Finance ledger + generated ORD number + status; currently `status=1` returns 409 and rolls back **all** draft/idempotency/sequence writes until Finance is available. Replaying the same key + same payload returns existing order without posting again; changed payload returns 409. |
| `PUT/PATCH /api/v1/orders/{order}` | `orders.update` (plus `orders.confirm` when `status=1`) | Draft header plus optional `status=0/1`, `depositAmount` only with `status=1`. Omitted status or 0 retains Draft. Status 1 triggers **Draft → Confirmed** in the same transaction as header updates and Finance; requires `Idempotency-Key` header. Any Finance failure returns 409 and **rolls back all edits**. Customer change allowed only while Draft; confirmed/cancelled orders cannot be edited/downgraded via this endpoint. HTTP 200 localized message and `data: null`. |
| `POST /api/v1/orders/{order}/items` | `orders.items.create` | Add item to Draft. HTTP 200, localized message and `data: null`. |
| `PUT/PATCH /api/v1/orders/{order}/items/{item}` | `orders.items.update` | Update Draft item serviceId/quantity/specifications/agreedTotal; recompute totals. HTTP 200, localized message and `data: null`. |
| `DELETE /api/v1/orders/{order}/items/{item}` | `orders.items.delete` | Remove Draft item (Soft Delete), archive any public files in private storage. Reject removing final item. HTTP 200 `data: null`. |
| `POST /api/v1/orders/{order}/items/{item}/files` | `orders.files.upload` | Upload file to public disk; return metadata including direct public URL. |
| `GET /api/v1/orders/{order}/items/{item}/files` | `orders.files.list` | Return file metadata with public URL (or null for legacy private files); no expiry. |
| `GET /api/v1/orders/{order}/items/{item}/files/{file}` | `orders.files.view` | Authenticated forced download (binary); validate nested ownership. |

| `DELETE /api/v1/orders/{order}/items/{item}/files/{file}` | `orders.files.delete` | Move file from public to private under the same relative path, mark record disk `local` and Soft Delete. HTTP 200 `data: null`. |
| `GET /api/v1/orders/{order}/activities` | `orders.activities.view` | Paginated important history ordered newest first. |
| `DELETE /api/v1/orders/{order}` | `orders.delete` only | Soft Delete a Draft, moving related public files to private storage while preserving history and refs; any role with this permission. |
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
- Draft deletion preserves history; `orders.delete` permission alone authorizes a Draft Soft Delete regardless of the user's role. Soft Delete of an Order does not enable removal of referenced Customers/Services.
- A confirmed/other non-Draft order is read-only via current Core CRUD; future Orders+Finance/Production features will introduce controlled modifications and logged reversal effects.
- A single shared `OrderConfirmationService` performs DRAFT → CONFIRMED whether invoked from POST create, PATCH/PUT update, or the dedicated `/confirm` route. The Finance gateway must post the immutable ledger **before** generating the order number or committing; confirmation adds the `order_confirmed` activity event. Status 1 requested through POST/PATCH without available Finance returns HTTP 409 with **no partial Draft, header modification, number, idempotency row, or activity**. Creation and confirmation run inside one outer transaction.
- Production/Deliveries later lock orders and items while changing stages/status, then recompute and persist production status. No client route may set production_status.
- No fake finance or temporary ledger; default Finance gateway is unavailable (409) for Confirm/Cancel; do not claim success without journal posting.
- Draft `Idempotency-Key` values are case-sensitive and stored only as SHA-256 digests to enforce exact uniqueness even with MySQL case-insensitive text collations.
- The future Refund is **explicit** from Orders detail, posted by Finance when the user confirms payout; cancellation alone must not debit bank, cash, wallet or claim refund happened.

## 7. Testing and permissions

- Permissions remain granular. `orders.files.list` controls metadata; `orders.files.view` controls the legacy authenticated download API, not public URLs.
- PHPUnit feature coverage: auth, permission isolation, safe draft create/update, customer reassignment & pricing snapshots, multiple same-service items, search/filter/pagination, immutable confirmed state, safe draft-only soft-delete, file authorization and size, guard behavior, failure of Confirm/Cancel without Finance, **status=0 and status=1 request contracts** (including atomic rollback and idempotency), localization.
- MySQL CI: migration constraints, concurrency on customer and service locks, future daily sequence uniqueness under InnoDB. Never run destructive migrate:fresh tests against a non-CI database.
- Use `lang/ar/orders.php` and `lang/en/orders.php` for every user-visible message. API fields camelCase. Do not return money as JSON floats.

## 8. Open integration decisions deferred to owning modules

- Finance adapter will coordinate confirmation, debt ledger, optional deposit, idempotency, credit allocation, correction and refund; Orders must not generate a confirmed numbered order on a failed finance posting.
- Production owns transitions, lock ownership enforcement and undo; Deliveries owns delivery itself.
- Any proposed edit/cancel on already confirmed order needs integration with immutable money movements; not unblocked in the draft-only core.

### Core pricing simplification (October 23, 2026)

- Orders does not return or persist `suggestedTotal`, `priceSource` or `wasManuallyPriced`. The source of truth is always `agreedTotal` submitted by staff, and derived `unitPrice`/`totalAmount`.
- An incremental migration drops `order_items.suggested_total`, `price_source`, and `was_manually_priced` without requiring destructive database recreation. Existing suggested price metadata is intentionally discarded; agreed prices and totals remain intact.
- Any employee with `orders.delete` can soft-delete a Draft; no fixed role-name check is permitted.

### Order create/update response contract (October 23, 2026)

- `POST /api/v1/orders` returns `201` with `success: true`, the existing localized `draft_created` or `confirmed` `message`, and `data: null`.
- `PUT/PATCH /api/v1/orders/{order}` returns `200` with `success: true`, the existing localized `draft_updated` or `confirmed` `message`, and `data: null`.
- This applies to Draft and Confirmed statuses; `409` while Finance is unavailable stays unchanged. GET order APIs still return full data. Item add/update/delete now also return only a localized message and `data: null`; file upload still returns File Resource.
- The frontend cannot infer a newly created order/item ID from POST anymore; after creation, retrieve it via list/details APIs if navigation or file upload requires its ID.

### Approved integration decisions (October 24, 2026)

- **Finance/deposit:** Payments are manual registrations of actual cash, bank transfers or e-wallet receipts; there is **no external payment gateway** such as Stripe. When Finance is built, the confirmation operation must register both order liability and any real deposit with amount, selected method and bank/wallet account ID (where applicable) in one transaction. Money belongs to Finance, not Orders; no temporary ledger, balance, or payment record now. Existing `depositAmount` request remains a future-finance placeholder and direct confirmation remains blocked (`409`) until real Finance is implemented. No extra payment-method fields are activated in Orders Core yet.
- **Separate permissions:** Each Orders API action has its own Spatie permission, sharing permission only between PUT and PATCH for the same action. Run OrdersPermissionSeeder and RoleSeeder for admin; custom employee roles need explicit new grants, not implicit inheritance from `orders.update`.
- **Active public files / private archives:** The file resource returns a direct public URL for active public files. Deleting the file, its Draft item or its Draft order moves the bytes into private `local` storage, retaining the relative path. The public URL then stops working. Legacy private records still return `url: null`.

### File archive and write response cleanup (October 24, 2026)

- FileResource omits `uploadedBy`, but `uploaded_by` is still saved in DB for audit.
- All item mutation endpoints (create, update, delete) return `{ "success": true, "message": "<existing translated message>", "data": null }`. GET order details continues returning the current item list and totals.
- On deletion of a file, Draft item or Draft order, each public file moves from `storage/app/public/orders/{orderId}/{itemId}/{filename}` to `storage/app/private/orders/{orderId}/{itemId}/{filename}`. Filename, bytes and relative path stay unchanged. The `order_item_files.disk` column becomes `local`; no database migration needed.
- Deleted records keep their existing Soft Delete behavior. Old private uploads remain private, with no extra move. Archive paths are not made public.
- Move operations participate in a controlled single-attempt database transaction. Missing public files, conflicting private targets, or database failures prevent committing the delete; already-moved files are restored to their public locations on rollback.
