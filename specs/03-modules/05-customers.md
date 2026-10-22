# Customers Module — Functional Spec & Integration Plan

> **Project:** طَبعة / Tabaa  
> **Branch:** `feature/customers`  
> **Target:** `develop`  
> **Status:** Decisions in progress; **no Customers implementation exists yet**. This module-named file `05-customers.md` is the requirements/spec artifact to finalize and approve before writing code.  
> **Stack:** Laravel 13 API, MySQL, nwidart/laravel-modules, stateful Sanctum, Spatie Permissions, Spatie Query Builder.  
> **Language convention:** All translatable/error/validation messages belong in `lang/ar/customers.php` and `lang/en/customers.php`; use translation keys only in code.

## 1. Role and modular boundaries

Customers owns the **registered customer profile**, current Settings customer-type assignment, and its own authenticated CRUD/search/select interfaces. It is not an Identity user account; staff register and manage customers without providing customer-login credentials.

- **Settings** owns the `customer_types` lookup group in `parameters` / `parameter_values`; Customers only stores a foreign key to `parameter_values.id` and enforces membership, ACTIVE status, and non-deletion at assignment time.
- **PrintingServices** owns tiers and proposed pricing; Customers does **not** store a price list, tier or historical order price.
- **Orders** must link each new order to a **registered ACTIVE** customer (reject INACTIVE/Soft Deleted); it derives customer type from trusted Customers data. Orders snapshots customer/type display names and agreed item prices at the agreed commercial freeze point. Editing name, phone or type does not retroactively rewrite saved historical data. Existing orders remain accessible if a customer later becomes INACTIVE.
- **Finance** alone owns customer account entries, balance, opening debt/credit and money posting. The customer profile does **not** store a writable running balance or opening_balance column. Finance must permit authorized collection of existing debt for INACTIVE customers. Customers can later expose a read-only statement/summary backed by a Finance-owned contract, without writing accounting transactions itself.
- **Core modules are permanent** by the owner's architecture decision; there is no runtime module enable/disable workflow.

## 2. Grounded business decisions

| ID | Decision |
|---|---|
| CUS-001 | No printing order without an existing registered customer. |
| CUS-002 | Customer profile includes **name, phone, address, and current customer type**. |
| CUS-003 | Customer type is **required** on customer registration and is selected from Settings' `customer_types` values. |
| CUS-004 | It must be **ACTIVE**, not Soft Deleted, and belong to the exact customer_types group when assigned. A foreign key alone is insufficient. |
| CUS-005 | Changing a customer's type affects **future order pricing only**, not previously agreed order prices or type-name snapshots. It remains permitted while old order items are in production. |
| CUS-006 | A customer type **currently assigned to any current customer** cannot become INACTIVE. Pricing tiers alone do not block deactivation. |
| CUS-007 | Customer profiles are separate from Identity login users; use authenticated employee permissions, not role-name bypasses. |
| CUS-008 | Customer account statements are chronological; a positive balance represents customer debt, negative is customer credit. Orders increase liability and posted collections decrease it. |
| CUS-009 | Advance/direct general customer payments, historic debt, credit application and financial corrections belong to the **Finance/Orders integration**, not customer CRUD side effects. |
| CUS-010 | Every customer/order/payment operation follows shared API conventions: camelCase JSON, exact decimal strings for money, and standardized success/error/pagination shapes. |
| CUS-011 | Customer phone is **required and unique among live customers**, and may be reused after Soft Delete. Only trim leading/trailing whitespace; no country-specific validation or removal of interior spaces/punctuation. |
| CUS-012 | Customer address is **optional** and is represented by one field for now. |
| CUS-013 | Customer removal uses **Soft Delete**, allowed only if no Orders and no financial movements refer to the customer; future modules own the historical reference guards. |
| CUS-014 | Customer **status is supplied by the frontend** on creation and is persisted as an integer-backed ACTIVE/INACTIVE enum; do not silently assign default ACTIVE when status is omitted. |
| CUS-015 | Customer phone **format is unrestricted** in v1: treat it as trimmed text with a reasonable storage length, without Egypt-only mobile, international-dialing or landline regex. Phone uniqueness remains enforced. |
| CUS-016 | A Soft Deleted customer **releases its phone** for reuse by another live customer; live rows with the same trimmed phone remain prohibited via DB uniqueness and validation. |
| CUS-017 | Customer listing has one **name-or-phone** search, plus separate customer-type and status filters. Customer-type name is *not* part of free-text search. |
| CUS-018 | An **INACTIVE** customer cannot be selected for or assigned to **new Orders**, but existing receipts/debt collection remain allowed by Finance. Existing order/financial history remains readable. |
| CUS-019 | Customer profile fields (including name, phone and customer type) **remain editable when the customer is INACTIVE**; valid type assignment and phone uniqueness checks still apply. |
| CUS-020 | Staff may edit customer **name and phone even after prior Orders or financial movements**. Historical Orders must retain appropriate order-time display snapshots, and financial records must preserve their posted transaction facts; editing a current customer profile never silently changes saved transaction data. |
| CUS-021 | **Customer names may repeat**. Do not add a unique constraint on `customers.name`; distinguish customers using their unique phone and ID. |
| CUS-022 | An ACTIVE customer **must not be deactivated while any persisted order has not yet been delivered**, including not-started, design, printing, partially ready and ready-for-delivery orders. Orders will own the check via a Customers-owned transition-guard contract when implemented. Cancellation semantics still need an explicit decision. |
| CUS-023 | **Phone equality uses the trimmed input as-is**, without stripping internal spaces, hyphens or prefixes. For example, `01012345678` and `010 12345678` are **distinct** values. Database uniqueness and server validation must follow the same comparison policy. |
| CUS-024 | A **Soft Deleted customer with no Orders or financial movements** does not alone prevent Soft Deleting their former Settings customer type. **Current customers** and other surviving/historical references (e.g. PrintingServices price tiers) must still be checked by their respective guards. |

## 3. Proposed minimal Customers v1 data model

`customers` — approved core fields; status-transition blocking statuses and cross-module references still require final decisions:

| DB field | Role / proposed validation |
|---|---|
| `id` | BIGINT primary key |
| `name` | Required string, trimmed, **duplicates allowed**; length limited by storage |
| `phone` | **Required and unique among non-Soft Deleted customers**. Compare only the value after trimming its ends: keep interior spaces, dashes, punctuation, `+` and leading zero unchanged. No country-specific regex; reuse after Soft Delete allowed. Enforce identical live-only comparison in MySQL and application validation; never cast to integer. |
| `address` | **Optional/nullable** customer address; one address field in v1. |
| `customer_type_id` | Required foreign key to `parameter_values.id`, checked against active `customer_types` |
| `status` | Required integer-backed ACTIVE/INACTIVE enum (`1`/`0`) supplied by the frontend at creation. INACTIVE disallows new Orders but **does not** block profile edits or Finance collections of existing dues. |
| `created_by` / `updated_by` | Nullable audit actor FKs using existing shared trait |
| `created_at` / `updated_at` / `deleted_at` | Standard timestamps and **Soft Delete**. Deletion is permitted **only when no Orders and no posted financial movements have ever referenced this customer**. Those modules own the reference guards when built. |

No extra personal fields, company/tax entities, contact persons, multiple addresses, images, or customer self-login without a separate decision. Do not add `balance`, `credit`, `old_debt`, or `opening_balance` writable customer columns.

### Pending data decisions — do not silently assume

- **CUS-OPEN-01 — Resolved:** phone is unique among live customers and can be reused after Soft Delete. Only trim leading/trailing whitespace, then compare literally: no removal of interior spaces or punctuation, no national/international regex. Persist as a string; ensure the DB unique constraint has the same comparison behavior.
- **CUS-OPEN-02:** **Optional single address approved.** A single required phone is approved for v1; no multiple contact fields are requested.
- **CUS-OPEN-03 — Resolved:** customer is Soft Deleted **only if never linked to Orders or financial movements**; its phone becomes available after Soft Delete. Real Orders/Finance usage guards are deferred to those modules, and must be implemented before those modules persist references.
- **CUS-OPEN-04 — Resolved core behavior:** frontend supplies explicit status on creation. INACTIVE blocks new Orders but permits profile updates, historical viewing and collecting existing dues. ACTIVE → INACTIVE is blocked if the customer has **any not-yet-delivered order** (not-started, design, printing, partially ready, ready for delivery). Orders must implement this guard when orders are first persisted. Treatment of a cancelled undelivered order is a separate lifecycle clarification.
- **CUS-OPEN-05 — Resolved:** `filter[search]` matches customer name OR phone only. `filter[customerTypeId]` and `filter[status]` are separate filters; do not search customer-type name via text. QueryBuilder must allowlist only supported filters.
- **CUS-OPEN-06:** Does opening account debt/credit get entered during registration or as a separate authorized Finance action? Current customer create/edit screenshots have no debt field. In either workflow, the eventual transaction must be a **Finance ledger movement**, not a mutable Customer column. No initial-balance posting will be implemented as part of Customers CRUD without an approved separate Finance contract.
- **CUS-OPEN-07 — Resolved:** **any undelivered order** blocks customer deactivation, including not-started and ready-to-deliver (not limited to the active production stages). The future Orders status model must define terminal cancellation treatment separately.
- **CUS-OPEN-08 — Resolved:** a customer previously Soft Deleted (and therefore never referenced by Orders or Finance) does **not** reserve the former customer type. Settings may Soft Delete that type if no live customer, price tier or other referencing module blocks it. Keep the physical FK for historical identity; the protection is business-level Soft Delete, not hard delete.

## 3.1 UI reference — owner's six screenshots, 2026-10-22

The screenshots are requirements references, not evidence that dependent Orders/Finance APIs currently exist:

- **Customers listing (now):** name, phone, optional address, customer type, active/inactive status, view/edit actions, new customer modal, name-or-phone search, customer-type filter and status filter. The displayed **order count** and **account balance** are **later integrations**, not columns managed by Customers.
- **Customer create/edit modals (now):** required name/phone and current customer type, optional address, and explicit frontend `status` (ACTIVE/INACTIVE). Inactive customers remain editable, including type/name/phone; Orders must reject them for new orders. Once Orders exists, the transition to INACTIVE must additionally be blocked for customers with an order still in production.
- **Customer details profile tab (now):** read-only profile plus edit modal; tabs for orders and account statement may be represented in the frontend later but must not trigger placeholder backend implementations.
- **Customer orders tab (later Orders):** order numbers, dates, item counts, totals and production/delivery status are read from Orders-owned data.
- **Finance cards and statement tab (later Finance):** total liabilities, collections/settlements, current debt, credit, chronological debit/credit movements and running balance are Finance-owned calculated/posted values, **not writable fields on customers**. The new-collection button is Finance functionality.
- Customers v1 **must not invent** dummy financial balances, ledger entries, order counts, orders tables or collection endpoints just to mimic the visual design.

## 4. Settings integration — implement in Customers

### 4.1 CustomerTypeUsageGuard (deletion)

- Implement Settings' existing `ParameterValueUsageGuard`, registered **from the Customers service provider**, never imported by Settings.
- For parameter group `customer_types`: if **any non-Soft Deleted customer** currently references the type, block its Soft Delete with a localized BusinessException (HTTP 409). The Settings registry will separately ask PrintingServices (and future owners) to verify other references.
- Unlike a physical FK, this guard protects Settings Soft Delete. **Do not use `withTrashed()` in the Customers usage guard:** the owner explicitly permits freeing a type when its only prior customer has been Soft Deleted with no Orders/financial history. Other modules remain responsible for their historical references. No physical deletion/cascading of type or customer rows.

### 4.2 CustomerTypeDeactivationGuard (deactivation)

- Implement Settings' existing `ParameterValueDeactivationGuard` and register from Customers.
- For group `customer_types`, block ACTIVE → INACTIVE when the type is assigned to a **current, non-Soft Deleted customer**. Historic-only assignment does not automatically block deactivation; any stronger historical policy must be explicitly approved.
- Return a localized HTTP 409 using `__('settings.customer_type_has_current_customers')` or a Customers-owned translation key; no direct English/Arabic message in code.

### 4.3 Concurrency and atomicity

- During customer **creation** and **customer-type reassignment**, acquire `lockForUpdate()` on the target `parameter_values` row *inside the same transaction* as the customer write. Check type group, ACTIVE and not Soft Deleted under that lock.
- Settings Update/Delete Actions already lock the same parameter-value row before invoking registered guards, so this serializes assignment against deactivate/delete.
- For type reassignment, lock the existing customer row before mutating it. Decide and consistently apply the **customer row / type row lock order**, including failure/retry behavior; test concurrent reassignment and Settings deactivation on MySQL before production reliance.
- Reassignment does **not** reprice historical order snapshots.

### 4.4 Customer deactivation and Orders (future integration)

- Customers owns the customer's ACTIVE/INACTIVE field and the customer status transition. The new business rule prevents ACTIVE → INACTIVE when the customer has an **in-production order**.
- Orders will own the authoritative check of its order statuses and expose a registered customer-status transition guard. **No Orders tables exist in Customers v1**, so don't hard-code an `orders` table check in the Customers module, and don't claim the future reference rule is already enforced by a nonexistent Orders module.
- Customers must provide the appropriate guard extension in its eventual design; when Orders is introduced, its provider must register the real guard **before writing actual orders**, and customer-status changes and order lifecycle updates must synchronize their transactions to prevent races.
- **Any order not delivered blocks deactivation**, including not-started, design, printing, partially ready and ready to deliver. An already delivered order does not block a customer's transition to INACTIVE; it **does** prevent deleting the customer. A cancelled undelivered order's terminal-state semantics remain to be clarified. Inactive customers remain editable and can settle old financial debts.

## 5. API and authorization — proposed v1 boundary

All routes use `auth:sanctum` and explicit Spatie permissions, with camelCase request/response fields.

| Endpoint | Purpose | Proposed permission |
|---|---|---|
| `GET /api/v1/customers` | Paginated management list | `customers.view` |
| `GET /api/v1/customers/{customer}` | Customer profile (may include read-only available summaries later) | `customers.view` |
| `POST /api/v1/customers` | Create registered customer | `customers.create` |
| `PUT/PATCH /api/v1/customers/{customer}` | Update profile and type | `customers.update` |
| `DELETE /api/v1/customers/{customer}` | Only if the finalized historic-use policy permits | `customers.delete` |

Use existing Select Aggregator for active customer options; register from Customers provider with a dedicated read permission if one is required, rather than creating a separate Selects module. Define whether inactive/soft-deleted customers should be selectable after CUS-OPEN-03/04.

**Do not create `POST /customers/{id}/payments` or mutate balances in this module.** A later `GET /api/v1/customers/{customer}/ledger` can expose Finance-owned read-only data once Finance is built, with a separate permission and no fake entries.

## 6. Proposed code structure

```
Modules/Customers/
  app/
    Actions/                   # create/update; delete only when policy approved
    Exceptions/
    Http/Controllers/
    Http/Requests/
    Http/Resources/
    Models/Customer.php
    Providers/
    Services/
      CustomerTypeUsageGuard.php
      CustomerTypeDeactivationGuard.php
  database/
    migrations/
    seeders/
  routes/api.php
  tests/Feature/
```

Reuse existing Settings' `ParameterValueUsageRegistry` (do not duplicate it), Identity's role-permission seed synchronization, shared audit traits and shared API response/errors. Models refer to `Modules\\Settings\\Models\\ParameterValue`; Settings does **not** import Customers.

## 7. Minimum executable acceptance scenarios

1. Authenticated permitted staff creates a registered customer, with a valid ACTIVE Settings customer type.
2. Reject missing/invalid/wrong-group/inactive/Soft Deleted customer-type IDs; no customer row persists after failed validation.
3. Changing customer type succeeds for a valid type, without touching orders or pricing tier records.
4. Registered customer prevents Settings customer-type **deactivation** (409); an unassigned type may deactivate even if PrintingServices has only tier references.
5. Current (non-Soft Deleted) customer associations block Settings type Soft Delete (409). If every customer referencing a type was Soft Deleted (and no other module references it), the type is eligible for Soft Delete; other lookup groups are unaffected.
6. Customer create/reassign and Settings deactivate/delete use the **same locked type row**; cover MySQL concurrency with independent DB connections.
7. Customer management endpoints enforce `auth:sanctum` and explicit create/view/update/delete permissions; unauthorized identities return 401/403.
8. All validation/BusinessException text comes from `lang/ar/customers.php`, `lang/en/customers.php` (or the owning Settings language file); Arabic/English tests verify messages.
9. Search and pagination conform to agreed QueryBuilder allowlists, and do not expose unsupported filters.
10. No customer CRUD action posts Finance ledger entries implicitly or writes a denormalized account balance.

## 8. Suggested implementation order

1. Finish remaining decisions and **approve this module-named requirements file** (`05-customers.md`) before writing any Customers code.
2. Add module skeleton, model/migration, authorized CRUD, requests/resources, Arabic/English catalogs and module tests.
3. Implement/verify Settings deletion and deactivation guards, with transaction locking.
4. Add customer select registration and permission seeders; rerun entire SQLite suite, MySQL migration/concurrency probes and code review.
5. Integrate Orders and Finance only in their **own future modules** using the explicit contracts.
