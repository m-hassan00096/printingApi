# Tabaa — Orders Integration Contract (future module)

> **Status:** Original integration requirements, now implemented in Orders Core on `feature/orders` (see `06-orders.md`). The table/guard implementation resides in Orders, not PrintingServices.
> **Linked modules:** PrintingServices, Customers, Settings, Identity.
> **Reference branch:** feature/orders (implementation); earlier reference was feature/printing-services.

## 1. Ownership and modular dependency

- `PrintingServices` owns `Modules\\PrintingServices\\Contracts\\PrintingServiceUsageGuard` and singleton `PrintingServiceUsageRegistry`.
- `DeletePrintingServiceAction` locks its own PrintingService row with `lockForUpdate()`, then invokes all registered usage guards **before** marking the service/tiers Soft Deleted.
- The future **Orders** module implements `OrderPrintingServiceUsageGuard` and registers it from an Orders-owned service provider. Only **Orders** queries `order_items`; PrintingServices must never import Orders models or call `Schema::hasTable('order_items')`.
- Order-item presence is checked **without the Eloquent SoftDeletes global scope**. Persisted draft, cancelled, completed, soft-deleted Orders and order items **all prohibit service deletion**. An unsaved React form does not count.
- Since Orders does not exist yet, no production Orders guard is currently registered; test-only guards demonstrate the contract. **Register and test the real guard as part of the same change that first allows persisted order items.**

## 2. Transactions and race prevention

- During new order item creation or reassignment to a new service, Orders must first lock the chosen `printing_services` row using `lockForUpdate()` **in the same transaction** that creates/updates the item; reject inactive or Soft Deleted service rows for new references.
- The service deletion path already takes that row lock. The subsequent guard read then sees all committed references after prior creators release their lock; competing order-item writers wait for the delete transaction and then refuse a deleted service. Preserve this lock order across flows and test on **real MySQL** using independent DB connections.
- Orders must also derive the customer's currently eligible type from the trusted Customers record and coordinate customer-type validation/locks as appropriate; never trust an arbitrary `customerTypeId` from a price-preview request at actual order save time.
- Do not introduce cascading hard deletion. Physical FKs are necessary but do **not** replace the soft-delete guards.

## 3. Historic immutable order price snapshots

The final Orders schema belongs to Orders. At the selected commercial freeze point, Orders must persist sufficient values to render old invoices and prevent retroactive repricing when service/type/tier definitions change:

- FK to printing service, **service name snapshot**, customer-type ID/name snapshot;
- ordered quantity, selected price-source descriptor, suggested price/total when available, **finally agreed total**;
- manual override state and, if later approved, override reason.

Orders calls authoritative pricing on save, not trusting frontend suggestions. A changed catalog price does not rewrite old item snapshots. The owner has **not yet finalized** the precise snapshot freeze transition, manual override reason requirement, or zero-manual-total policy; these stay in the future Orders spec (PS-OPEN-02/03/08).

## 4. Acceptance requirements to implement with Orders

1. Orders provider registers a production `PrintingServiceUsageGuard`; the delete endpoint returns localized 409 for any historically persisted item, including Soft Deleted histories.
2. Deleting an unused service remains possible and soft-deletes its own tiers atomically.
3. Concurrent order-item creation/service deletion cannot both succeed in a way that saves a new reference to a deleted service on InnoDB.
4. Order item creation/reassignment rejects inactive/Soft Deleted printing services and uses Customers' backend type, not a caller-provided preview override.
5. Old order price/name/type snapshots are unchanged by catalog renames, tier changes, or type deactivation.
6. Orders' actual create/cancel/draft lifecycle tests cover these reference and snapshot invariants, and new Orders migrations own all order tables.

## 5. Related Settings/Customers extension

Settings owns `ParameterValueDeactivationGuard`. When Customers is introduced it registers a guard for `customer_types` assigned to current customers, while holding the same Settings row lock during new customer/type assignment. Printing tiers alone **do not** block deactivation, but any historical tier reference blocks customer-type Soft Delete.

**Project assumption:** backend modules are permanent; module-enable/disable management is not part of this system.
