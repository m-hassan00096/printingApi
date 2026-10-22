# Customers Module — Functional Spec & Integration Plan

> **Project:** طَبعة / Tabaa  
> **Branch:** `feature/customers`  
> **Target:** `develop`  
> **Status:** Initial spec for the next module; **no Customers implementation exists yet**.  
> **Stack:** Laravel 13 API, MySQL, nwidart/laravel-modules, stateful Sanctum, Spatie Permissions, Spatie Query Builder.  
> **Language convention:** All translatable/error/validation messages belong in `lang/ar/customers.php` and `lang/en/customers.php`; use translation keys only in code.

## 1. Role and modular boundaries

Customers owns the **registered customer profile**, current Settings customer-type assignment, and its own authenticated CRUD/search/select interfaces. It is not an Identity user account; staff register and manage customers without providing customer-login credentials.

- **Settings** owns the `customer_types` lookup group in `parameters` / `parameter_values`; Customers only stores a foreign key to `parameter_values.id` and enforces membership, ACTIVE status, and non-deletion at assignment time.
- **PrintingServices** owns tiers and proposed pricing; Customers does **not** store a price list, tier or historical order price.
- **Orders** must link each order to a **registered** customer and derive customer type from trusted Customers data. Orders snapshots the customer type's name and agreed item prices when its commercial snapshot is committed. Editing a customer profile or type never retroactively changes saved order prices.
- **Finance** alone owns customer account entries, balance, opening debt/credit and money posting. The customer profile does **not** store a writable running balance or opening_balance column. Customers can later expose a read-only statement/summary backed by a Finance-owned contract, without writing accounting transactions itself.
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

## 3. Proposed minimal Customers v1 data model

`customers` — **proposal**, pending field/uniqueness policy approval:

| DB field | Role / proposed validation |
|---|---|
| `id` | BIGINT primary key |
| `name` | Required string, trimmed, length limited by storage |
| `phone` | Required contact number, normalized before lookup/persistence; uniqueness policy **not finalized** |
| `address` | Customer address; whether empty is permitted needs final confirmation |
| `customer_type_id` | Required foreign key to `parameter_values.id`, checked against active `customer_types` |
| `created_by` / `updated_by` | Nullable audit actor FKs using existing shared trait |
| `created_at` / `updated_at` / `deleted_at` | Standard timestamps and optional Soft Delete, subject to reference restrictions |

No extra personal fields, company/tax entities, contact persons, multiple addresses, images, or customer self-login without a separate decision. Do not add `balance`, `credit`, `old_debt`, or `opening_balance` writable customer columns.

### Pending data decisions — do not silently assume

- **CUS-OPEN-01:** Is `phone` unique across customers, and what normalizing/format policy applies (Egypt-only versus international numbers)?
- **CUS-OPEN-02:** Is `address` required, optional or nullable? Are multiple phones/addresses needed? The current approved minimum is one phone and address.
- **CUS-OPEN-03:** Are customers soft-deletable if never referenced, and what references (Orders, Finance ledger, historic activity) prohibit deletion? A soft-deleted customer must not break snapshots.
- **CUS-OPEN-04:** Is a customer `status` (ACTIVE/INACTIVE) needed in addition to Soft Delete? Do not invent it from Settings' status enum.
- **CUS-OPEN-05:** What can staff filter/search by (name, phone, type, status), and is type filtering needed on first release? QueryBuilder must use an explicit allowlist.
- **CUS-OPEN-06:** Does opening account debt/credit get entered during registration or as a separate authorized Finance action? In either case it must become a **Finance ledger movement**, not a mutable Customer column.

## 4. Settings integration — implement in Customers

### 4.1 CustomerTypeUsageGuard (deletion)

- Implement Settings' existing `ParameterValueUsageGuard`, registered **from the Customers service provider**, never imported by Settings.
- For parameter group `customer_types`: if any current or historically retained Customer row references the type, block Soft Delete with a localized BusinessException (HTTP 409).
- Unlike a physical FK, this guard protects Settings Soft Delete. If Customers itself uses Soft Delete, define historical associations via `withTrashed()` so deleting a customer cannot erase the reference check.

### 4.2 CustomerTypeDeactivationGuard (deactivation)

- Implement Settings' existing `ParameterValueDeactivationGuard` and register from Customers.
- For group `customer_types`, block ACTIVE → INACTIVE when the type is assigned to a **current, non-Soft Deleted customer**. Historic-only assignment does not automatically block deactivation; any stronger historical policy must be explicitly approved.
- Return a localized HTTP 409 using `__('settings.customer_type_has_current_customers')` or a Customers-owned translation key; no direct English/Arabic message in code.

### 4.3 Concurrency and atomicity

- During customer **creation** and **customer-type reassignment**, acquire `lockForUpdate()` on the target `parameter_values` row *inside the same transaction* as the customer write. Check type group, ACTIVE and not Soft Deleted under that lock.
- Settings Update/Delete Actions already lock the same parameter-value row before invoking registered guards, so this serializes assignment against deactivate/delete.
- For type reassignment, lock the existing customer row before mutating it. Decide and consistently apply the **customer row / type row lock order**, including failure/retry behavior; test concurrent reassignment and Settings deactivation on MySQL before production reliance.
- Reassignment does **not** reprice historical order snapshots.

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
5. Current and historically Soft Deleted customer associations block Settings type Soft Delete (409), without affecting other lookup groups.
6. Customer create/reassign and Settings deactivate/delete use the **same locked type row**; cover MySQL concurrency with independent DB connections.
7. Customer management endpoints enforce `auth:sanctum` and explicit create/view/update/delete permissions; unauthorized identities return 401/403.
8. All validation/BusinessException text comes from `lang/ar/customers.php`, `lang/en/customers.php` (or the owning Settings language file); Arabic/English tests verify messages.
9. Search and pagination conform to agreed QueryBuilder allowlists, and do not expose unsupported filters.
10. No customer CRUD action posts Finance ledger entries implicitly or writes a denormalized account balance.

## 8. Suggested implementation order

1. Confirm outstanding profile constraints (especially phone uniqueness, address optionality, and deletion rules).
2. Add module skeleton, model/migration, authorized CRUD, requests/resources, Arabic/English catalogs and module tests.
3. Implement/verify Settings deletion and deactivation guards, with transaction locking.
4. Add customer select registration and permission seeders; rerun entire SQLite suite, MySQL migration/concurrency probes and code review.
5. Integrate Orders and Finance only in their **own future modules** using the explicit contracts.
