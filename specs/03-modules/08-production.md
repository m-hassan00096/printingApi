# Production Module — API / Data Design (draft)

Date: 2026-10-24
Branch: feature/production
Status: Static Production v1 implemented on feature/production; API contracts below remain the source of truth for review prior to PR. Future dynamic workflows remain deferred.


## Implementation choice (2026-10-24): STATIC production workflow

**APPROVED:** Keep Production simple and static for the initial release. The proposed database-configurable / fully dynamic workflow engine is **DEFERRED**; see [09-production-dynamic-workflow-future.md](./09-production-dynamic-workflow-future.md).

- The authoritative stages are the existing integer-backed `Modules/Orders/app/Enums/OrderItemStage.php`: 0 NOT_STARTED, 1 DESIGN_IN_PROGRESS, 2 DESIGN_COMPLETED, 3 PRINTING, 4 READY_FOR_DELIVERY. DESIGN_IN_PROGRESS and DESIGN_COMPLETED occupy the same design board column.
- Transitions, available buttons, role checks and any required input are explicitly defined in Laravel Actions/FormRequests, **not** loaded from database-configured stage/transition definitions.
- Only the known required input is `printingLocationId` for sending to printing (single, combined complete-and-send, or batch), and for printer reassignment; the existing Settings `printingLocations` select is reused.
- Existing `order_items.production_stage`, `order_items.designer_id`, `order_items.printing_location_id` and `orders.production_status` remain authoritative. **Do not create `production_stages`, `production_transitions`, `production_transition_fields` or `production_item_states` now.**
- Production adds only necessary history/idempotency persistence. Design actions, sending, reassignment, marking ready, undo, reporting and filters retain their approved static contracts in this document.
- No dynamic admin stage editor, generic handler registry or `board-config` action-definition API in v1. We can reconsider after the production workflow proves stable.

## 1. Adopted user flow

- Production starts only for CONFIRMED Orders; Draft and CANCELLED never enter the board.
- Each Order Item moves independently; the board visually groups items by order within each column.
- Every item MUST go through design; no direct NOT_STARTED -> PRINTING.
- The existing Orders integer enum already holds the authoritative stages:
  - NOT_STARTED = 0
  - DESIGN_IN_PROGRESS = 1
  - DESIGN_COMPLETED = 2
  - PRINTING = 3
  - READY_FOR_DELIVERY = 4
- In the UI, stages 1 and 2 both appear under the DESIGN column, with distinct "in progress" and "completed" states. No duplicate design-status column is needed.
- Starting design records the authenticated employee as designer and the starting event.
- Completing design can finish in place (stage 2) OR complete-and-send (1 -> 3) after requiring an active printing location.
- An item already in stage 2 can be sent to printing individually or with other stage-2 items (including across different orders). A batch always chooses exactly one active printing location for all items, and must be atomic.
- Printing location selection is mandatory for *every* transition into stage 3, and may later be changed in stage 3 without moving stage; changes logged without requiring a reason.
- Completing printing moves the item to stage 4, *not* a physical delivery to the customer.
- The user can undo the preceding operation while an item has not been physically delivered. Rules: 4 -> 3; 3 -> 1 (requires design rework; clear active printing location, preserve history); 2 -> 1; 1 -> 0 (release designer assignment). Additional undos can be performed successively. Undo from 0 or from physically delivered items is forbidden.
- Undo and all stage/printing changes append an immutable event containing operator and before/after state, not a free-text note. When a design is reworked, existing designer/completion audit remains intact in history, even if current designer changes.
- The design *starter* and *completer* are separately audited. A user with appropriate design-management permission may complete another employee's work (latest product requirement).
- Never enter partial quantities or production notes in the board.
- Items within printing are not assigned to a printing employee; only to a printing LOCATION (a vendor or place, not a printer machine).
- Items ready for delivery are displayed in Deliveries in a later module. Production never posts customer collection or marks the order delivered.

## 2. Minimal data changes

DO NOT introduce a duplicate item stage/status table. The existing Orders-owned:
- order_items.production_stage (TINYINT)
- order_items.designer_id (user who currently started design)
- order_items.printing_location_id (currently assigned location)
- orders.production_status (rolled-up state)

are retained, updated only through Orders-owned gateway implementation, called by the Production domain service using contracts and DTOs.

Production owns:
- production_item_events: BIGINT id, BIGINT order_item_id, BIGINT actor_id, TINYINT event_type (integer enum), TINYINT from_stage nullable, TINYINT to_stage nullable, BIGINT old_printing_location_id nullable, BIGINT new_printing_location_id nullable, optional batch_key/hash, timestamp created_at; append only; indexes (order_item_id, id) and (actor_id, event_type, order_item_id).
- production_idempotency_keys: actor_id, operation, SHA-256 key_hash, request_hash, result info for safe retry; UNIQUE(actor_id, operation, key_hash). All writes require Idempotency-Key.
- User-display name can be supplied by an Identity contract/immutable snapshot rather than importing Identity models into Production business services.

Event types (integer-backed enum with label()): DESIGN_STARTED, DESIGN_COMPLETED, SENT_TO_PRINTING, PRINTER_CHANGED, PRINTING_COMPLETED, UNDONE. For composite "complete and send", append BOTH design-completed and sent-to-printing events in one transaction so the completion filter and audit work.

## 3. Cross-module interfaces and locks

Production consumes a Production-owned Orders gateway interface implemented *inside Orders* that:
- Retrieves trusted snapshots for confirmed, undelivered, non-cancelled orders and their non-deleted items;
- Locks order first, then sorted item IDs, and checks transition preconditions inside one transaction;
- Updates existing Orders-owned production_stage, designer_id and printing_location_id; recalculates orders.production_status with all active items atomically;
- Exposes reads via DTOs; Production service must NOT directly use Modules\\Orders\\Models\\Order / OrderItem.

Production consumes Settings-owned printing-location lookup contract:
- validates selected printingLocationId belongs to printing_locations and is active/not soft-deleted;
- resolves a human-readable snapshot for event audit;
- protects against a location becoming unavailable before transaction commit.

Auth: Sanctum session. The server computes actor identity from authenticated request; frontend never submits actorId/designerId.

Business conflicts (wrong stage, changed concurrent state, cancelled/delivered order, duplicate event, invalid undo, etc.) => localized BusinessException HTTP 409.
Malformed body, missing/invalid method/destination, ID arrays, invalid filter, invalid header => Request Validation HTTP 422.
No direct cross-module Eloquent writes outside the Orders-owned adapter.

## 4. Order production-status rollup

After EVERY stage change or undo (computed under the same order transaction):
- all items NOT_STARTED => OrderProductionStatus.NOT_STARTED (0)
- all items READY_FOR_DELIVERY => READY_FOR_DELIVERY (3)
- some items READY_FOR_DELIVERY and some earlier => PARTIALLY_READY (2)
- otherwise => IN_PRODUCTION (1)
- DELIVERED (4) is set only by the future Deliveries module and blocks production transitions.

A confirmed order with zero active items should be rejected as an invalid production target; item sets are frozen after confirmation.

## 5. Read API (suggested)

All under /api/v1/production with auth:sanctum + permission.

1. GET /items
   - Query: filter[column]=0|1|2 (board-column enum NOT_STARTED/DESIGN/PRINTING; 1 maps to item stages 1 and 2), filter[customerId], filter[search], filter[myDesignWork]=1|2 (started by me / completed by me; omitted=all), filter[lateOnly]=1, page/perPage<=100, sort=dueDate|-dueDate|id|-id.
   - Response: data.items[] + pagination. Each item contains: id/orderItemId, orderId/orderNumber, customerId/customerName/customerPhone, serviceId/serviceName, quantity, dueDate, isLate, productionStage int + productionStageLabel, designerId + designerName, printingLocationId + printingLocationName, designCompletedById + designCompletedByName (from last matching event), and relevant timestamps where known.
   - Client groups returned items visually by order. For a Kanban board, React can request the three columns in parallel with independent pagination.

2. GET /summary
   - Filters: customerId, search, myDesignWork, lateOnly. Response: data.notStartedCount, designInProgressCount, designCompletedCount, printingCount, readyCount, lateOrdersCount. Counts represent matching items except lateOrdersCount, which is distinct orders. When counts do not match visible columns, annotate their scope.

3. GET /items/{orderItem}/history
   - Query page/perPage. Response data.events[] + pagination, with integer eventType + eventTypeLabel, actorId/name, fromStage/toStage, old/new printing location, createdAt.

GET response success/message/data always conforms to global ApiResponse; GET message empty.

## 6. Write API (suggested)

All writes use Idempotency-Key and return HTTP 200 {"success":true,"message":"<translated>","data":null}. Wrong stage or cancelled/delivered is a domain 409, never a ValidationException.

4. POST /items/{orderItem}/start-design
   Body {}. Requires stage 0; changes 0 -> 1, designerId = auth user, event DESIGN_STARTED.

5. POST /items/{orderItem}/complete-design
   Body {}. Requires stage 1; changes 1 -> 2 without leaving design column; event DESIGN_COMPLETED with current actor.

6. POST /items/{orderItem}/complete-and-send-to-printing
   Body {"printingLocationId":3}. Requires stage 1, validates active location, changes 1 -> 3, stores location, emits DESIGN_COMPLETED + SENT_TO_PRINTING.

7. POST /items/{orderItem}/send-to-printing
   Body {"printingLocationId":3}. Requires stage 2, changes 2 -> 3, stores location, emits SENT_TO_PRINTING.

8. POST /items/send-to-printing
   Body {"orderItemIds":[101,102,103],"printingLocationId":3}.
   Requires 1..100 unique IDs, each at stage 2 and eligible; rejects entire batch if any invalid; locks sorted items and all corresponding orders, writes events in one transaction, sets all to same location, recomputes all affected order statuses. No partial success.

9. PATCH /items/{orderItem}/printing-location
   Body {"printingLocationId":5}. Requires stage 3; checks new active location and that it differs from current one; remains stage 3; logs old/new location, actor and date. No reason required.

10. POST /items/{orderItem}/mark-ready
    Body {}. Requires stage 3; changes 3 -> 4; logs PRINTING_COMPLETED and recomputes order status.

11. POST /items/{orderItem}/undo
    Body {}. Stepwise undo: stage 4 -> 3, stage 3 -> 1 (design needs rework; unset active location), stage 2 -> 1, stage 1 -> 0 (release designer). Logs UNDONE with the relevant old/new values. Preserve prior history. No undo after physical delivery.

Rejected/disabled items and invalid business transitions are not 422 merely because they violate business state; they produce 409.

## 7. Permissions (suggested)

- production.items.list
- production.summary.view
- production.history.view
- production.design.start
- production.design.complete
- production.printing.send
- production.printing.change_location
- production.printing.complete
- production.items.undo

These permissions govern actions. "My design work" reads authenticated user ID, never a client-provided worker ID. Admin gets all permissions via existing Identity RoleSeeder.

## 8. Testing prior to merge

- New confirmed order items list as 0; drafts and cancelled orders excluded.
- Complete design in place vs complete-and-send, including person who started vs person who finished; history and "my work" filters survive undo.
- Single and batch send require active printing location and are atomic; duplicate item IDs fail 422.
- Reassignment in printing, no reason; history previous/current printer.
- No stage skipping or work on delivered/cancelled orders; no direct item mutations by users lacking permissions.
- Rollup correctly transitions 0 / 1 / 2 / 3; undo ready item rolls ready order back to partially ready / in production.
- Idempotent retries and concurrent attempts cannot duplicate events or partially apply a batch.
- Full PHPUnit SQLite suite and MySQL 8 production invariants, including transaction/locking cases.

## 9. Implementation order

1. Production module skeleton, migrations, enum/resources/requests: implemented.
2. Orders-owned gateway and Settings printing-location contract: implemented.
3. Static stage Actions/Services and 11 endpoints: implemented.
4. SQLite full regression and MySQL 8 production suite: automated in `.github/workflows/production-ci.yml`; keep green before proposing PR.
5. Code review and user review; only then Pull Request to `develop`. No merge during implementation.

Note: older Orders spec said only the *starter* or admin may finish design. The newly approved requirement captures a separate finisher and permits another properly authorized employee to finish. The later approved rule supersedes that ownership restriction.

## 10. Implemented files / running locally

- `Modules/Production/app/Services/ProductionWorkflowService.php`: Idempotent, transactional action orchestration and immutable audit events.
- `Modules/Orders/app/Services/OrdersProductionAdapter.php`: all item transitions and order status rollup. Orders remains the sole writer of its stage/status columns.
- `Modules/Settings/app/Services/PrintingLocationResolver.php`: validates active printing locations and locks selected location during handoff.
- `Modules/Production/app/Services/ProductionQueryService.php`: board filters, current-user design history filters, summary, event listing.
- `Modules/Production/app/Http/Controllers/ProductionItemController.php` and `Modules/Production/routes/api.php`: 11 endpoints, camelCase fields, integer enums, `data:null` on successful writes.
- `Modules/Production/tests/Feature/ProductionApiTest.php`: static flow, batch atomicity, location reassignment, undo, user-specific work filters, authorization and idempotency.

Run after pulling the branch:

```bash
composer dump-autoload
php artisan migrate
php artisan module:seed Production
php artisan optimize:clear
php artisan test
```

**Migration caution:** never use `migrate:fresh` on a populated environment.

**Known intentional scope boundary:** physical delivery is owned by a future Deliveries module and is not implemented here. Undo after actual delivery is blocked by the Orders-owned gateway.
