# Customers Module — Functional Spec & Integration Plan

> **Project:** طَبعة / Tabaa  
> **Branch:** `feature/customers`  
> **Target:** `develop`  
> **Status:** Owner-reviewed business rules and API contract being finalized on `feature/customers`. No Customers code implementation or tests have been committed yet; `05-customers.md` is the module requirements artifact.  
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
| CUS-022 | ACTIVE → INACTIVE is **blocked when any persisted order is present**, including Draft, Confirmed, Cancelled, Delivered and Soft Deleted history. Orders registers a Customers-owned transition guard. This stronger October 22, 2026 decision supersedes the former undelivered-only rule. |
| CUS-023 | **Phone equality uses the trimmed input as-is**, without stripping internal spaces, hyphens or prefixes. For example, `01012345678` and `010 12345678` are **distinct** values. Database uniqueness and server validation must follow the same comparison policy. |
| CUS-024 | A **Soft Deleted customer with no Orders or financial movements** does not alone prevent Soft Deleting their former Settings customer type. **Current customers** and other surviving/historical references (e.g. PrintingServices price tiers) must still be checked by their respective guards. |
| CUS-025 | An **opening customer debt or credit** is recorded as a **separate Finance operation**, never in customer registration/update. The eventual Finance ledger owns the opening movement and its authorization; Customers CRUD has no opening-balance input or money side effects. |
| CUS-026 | POST create returns the **complete newly created CustomerResource** in `data`, including `id`, so the order form can select the customer immediately, plus localized `تم إضافة العميل بنجاح` (201). PUT/PATCH update still returns `data: null` with localized `تم تعديل العميل بنجاح` (200). |
| CUS-027 | Shared Select Aggregator exposes **two independent keys**: `customers` (all non-Soft Deleted customers, both ACTIVE and INACTIVE) and `activeCustomers` (only ACTIVE non-Soft Deleted customers), both with `label = customer name + " - " + phone` and `value = customer ID`. Both keys are controlled by `customers.selects.view`. |

## 3. Proposed minimal Customers v1 data model

`customers` — approved core fields; Orders/Finance modules will later implement their own historical-reference and balance integrations:

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
- **CUS-OPEN-04 — Resolved (revised):** frontend provides status on creation. INACTIVE blocks new Orders but still allows profile edits and old debt collections. ACTIVE → INACTIVE is blocked if **any persisted order ever referenced the customer**, including draft, cancelled, delivered and Soft Deleted history.
- **CUS-OPEN-05 — Resolved:** `filter[search]` matches customer name OR phone only. `filter[customerTypeId]` and `filter[status]` are separate filters; do not search customer-type name via text. QueryBuilder must allowlist only supported filters.
- **CUS-OPEN-06 — Resolved:** opening customer debt/credit is a **separate Finance operation** and an authorized ledger movement, not a field in customer registration or a mutable Customer column. The Customers API does not accept opening balance or perform implicit money posting. Finance will define the entry API, approval/permissions and ledger record later.
- **CUS-OPEN-07 — Resolved (revised):** all persisted Orders, regardless of delivery, cancellation or Soft Delete, block deactivation and customer Soft Delete. The actual Orders guard was implemented by Orders Core.
- **CUS-OPEN-08 — Resolved:** a customer previously Soft Deleted (and therefore never referenced by Orders or Finance) does **not** reserve the former customer type. Settings may Soft Delete that type if no live customer, price tier or other referencing module blocks it. Keep the physical FK for historical identity; the protection is business-level Soft Delete, not hard delete.

## 3.1 UI reference — owner's six screenshots, 2026-10-22

The screenshots are requirements references, not evidence that dependent Orders/Finance APIs currently exist:

- **Customers listing (now):** name, phone, optional address, customer type, active/inactive status, view/edit actions, new customer modal, name-or-phone search, customer-type filter and status filter. The displayed **order count** and **account balance** are **later integrations**, not columns managed by Customers.
- **Customer create/edit modals (now):** required name/phone and current customer type, optional address, and explicit frontend status. Inactive customers remain editable; Orders must reject inactive customers on new drafts. Any persisted Order history blocks ACTIVE → INACTIVE.
- **Customer details profile tab (now):** read-only profile plus edit modal; tabs for orders and account statement may be represented in the frontend later but must not trigger placeholder backend implementations.
- **Customer orders tab (later Orders):** order numbers, dates, item counts, totals and production/delivery status are read from Orders-owned data.
- **Finance cards and statement tab (later Finance):** total liabilities, collections/settlements, current debt, credit, chronological debit/credit movements and running balance are Finance-owned calculated/posted values, **not writable fields on customers**. The new-collection button is Finance functionality.
- Customers v1 **must not invent** dummy financial balances, ledger entries, order counts, orders tables or collection endpoints just to mimic the visual design. **Opening debt/credit entry is explicitly separate in Finance** and is not exposed in the customer create/update requests.

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

- Customers owns ACTIVE/INACTIVE status; Orders' CustomerStatusTransitionGuard prohibits ACTIVE → INACTIVE once any Draft, Confirmed, Cancelled, Delivered, or Soft Deleted order has ever referenced the customer.
- Orders will own the authoritative check of its order statuses and expose a registered customer-status transition guard. **No Orders tables exist in Customers v1**, so don't hard-code an `orders` table check in the Customers module, and don't claim the future reference rule is already enforced by a nonexistent Orders module.
- Customers must provide the appropriate guard extension in its eventual design; when Orders is introduced, its provider must register the real guard **before writing actual orders**, and customer-status changes and order lifecycle updates must synchronize their transactions to prevent races.
- **Any persisted order**, even a Draft or a previously Soft Deleted, Cancelled or Delivered order, blocks customer deactivation **and** customer Soft Delete. Historical Finance settlements and customer profile updates remain permitted. Orders Core registers the guard.

## 5. API and authorization — proposed v1 boundary

All routes use `auth:sanctum` and explicit Spatie permissions, with camelCase request/response fields.

| Endpoint | Purpose | Proposed permission |
|---|---|---|
| `GET /api/v1/customers` | Paginated management list | `customers.view` |
| `GET /api/v1/customers/{customer}` | Customer profile (may include read-only available summaries later) | `customers.view` |
| `POST /api/v1/customers` | Create registered customer | `customers.create` |
| `PUT/PATCH /api/v1/customers/{customer}` | Update profile and type | `customers.update` |
| `DELETE /api/v1/customers/{customer}` | Only if the finalized historic-use policy permits | `customers.delete` |

Register both `customers` (all non-deleted) and `activeCustomers` (active-only) in the existing Select Aggregator from Customers' provider, with shared dedicated permission `customers.selects.view`. Both labels combine name and phone. Soft Deleted customers appear in neither.

**Do not create `POST /customers/{id}/payments` or mutate balances in this module.** A later `GET /api/v1/customers/{customer}/ledger` can expose Finance-owned read-only data once Finance is built, with a separate permission and no fake entries.

## 5.1 Approved API contracts — Customers v1

> Approved for implementing the module after the decisions above were finalized. No Orders or Finance APIs are implemented as part of this contract. The samples describe response **shapes**, not actual saved customer records.

### Authentication and permissions

Every Customers management endpoint is under `auth:sanctum`, with `customers.view`, `customers.create`, `customers.update`, `customers.delete` respectively. The shared selects aggregator exposes **both** `customers` and `activeCustomers`, using the same `customers.selects.view` permission for each. No implicit super-admin bypass; Identity RoleSeeder syncs seeded web permissions.

All responses match the app's `ApiResponse` wrapper. GET/index/select and DELETE success messages remain empty where already established; **POST create and PUT/PATCH update return a non-empty translated success `message`**. POST 201 returns the newly created customer resource (including `id`); PUT/PATCH 200 retains `data: null`. Validation: HTTP **422**, `{"success":false,"message":"...","errors":{"field":["..."]}}`; restricted business action: HTTP **409**, same error wrapper; unauthorized **401**; forbidden **403**; unknown or Soft Deleted customer **404**. All messages live in `lang/ar/customers.php` and `lang/en/customers.php`.

### POST /api/v1/customers — 201

Request (camelCase):

```json
{
  "name": "أحمد محمد",
  "phone": "01012345678",
  "address": "المنصورة",
  "customerTypeId": 2,
  "status": 1
}
```

`name` required nonblank trimmed string (up to 150 chars); duplicates allowed. `phone` required nonblank trimmed string (up to 100 chars), **unique among live customers** based on the exact trimmed string, with no stripping interior whitespace, case folding or locale-specific phone regex; its exact uniqueness is enforced at DB level and duplicate returns 422 field `phone`. `address` optional nullable string, up to 1000 chars; omitted becomes null. `customerTypeId` required positive integer from the **active and not-deleted** Settings `customer_types` group; update/creation revalidates it while holding the type's DB lock. `status` required integer enum `0=INACTIVE, 1=ACTIVE`, **provided by frontend**, no backend default. Snake_case aliases are not accepted.

**Success: HTTP 201 with localized confirmation and the complete newly created CustomerResource** (including the new customer ID), so an Orders create form can add a customer and select it immediately:

```json
{
  "success": true,
  "message": "تم إضافة العميل بنجاح",
  "data": {
    "id": 15,
    "name": "أحمد محمد",
    "phone": "01012345678",
    "address": "المنصورة",
    "customerTypeId": 2,
    "customerType": {"id": 2, "name": "عميل جملة"},
    "status": 1,
    "statusLabel": "فعال"
  }
}
```

The controller must pass `__('customers.messages.created')` to `ApiResponse::success()`; define both `lang/ar/customers.php` and `lang/en/customers.php` entries. The Arabic translation is **تم إضافة العميل بنجاح**, and the English translation should be **Customer added successfully.**

The frontend should immediately select `data.id` after successful creation; no extra GET is required. It may use the accompanying `data.name`/`data.phone` for the select label. The update endpoint's response remains `data: null`.

### GET /api/v1/customers — 200

Query parameters:
- `filter[search]`: optional single literal substring search for **customer name OR phone**, not type name; escape SQL LIKE wildcards.
- `filter[customerTypeId]`: optional exact positive integer Settings type ID; can include inactive types in historical listing.
- `filter[status]`: optional exact `0` or `1`.
- `sort`: allowlist `id`, `name`, `created_at` with optional `-` descending prefix; default `-id`.
- `page`: positive integer; `perPage`: 1–100, default 15. Unknown filters/sorts rejected.

```json
{
  "success": true,
  "message": "",
  "data": {
    "customers": [
      {
        "id": 15, "name": "أحمد محمد", "phone": "01012345678",
        "address": "المنصورة", "customerTypeId": 2,
        "customerType": {"id": 2, "name": "عميل جملة"},
        "status": 1, "statusLabel": "فعال"
      }
    ],
    "pagination": {"currentPage": 1, "lastPage": 1, "perPage": 15, "total": 1}
  }
}
```

Ordinary list excludes Soft Deleted customers. **No fake order count or monetary balances**.

### GET /api/v1/customers/{customer} — 200

Returns the same Customer Resource inside `data`. Eager-load customer type to prevent N+1. Unknown/Soft Deleted ID -> 404. No Orders/Finance nested fake data.

### PUT/PATCH /api/v1/customers/{customer} — 200

**Success: HTTP 200 with localized confirmation but no updated customer resource:**

```json
{
  "success": true,
  "message": "تم تعديل العميل بنجاح",
  "data": null
}
```

The controller must pass `__('customers.messages.updated')` to `ApiResponse::success()`. Define **تم تعديل العميل بنجاح** in Arabic and **Customer updated successfully.** in English; do not hard-code messages inside controllers/actions. Do not serialize the updated customer resource.

Partial update: any submitted subset of `name`, `phone`, `address`, `customerTypeId`, `status`; omitted keys unchanged. `name`/`phone`/`customerTypeId`/`status` cannot be null if supplied, `address` may be null. Same phone/type/enum validation as creation, with own ID ignored for phone uniqueness. Example `{"name":"أحمد محمد علي","status":0}`.

- An inactive customer remains editable, including name, phone and type. Historical Orders/Finance facts and name snapshots cannot be rewritten by customer updates.
- ACTIVE → INACTIVE is blocked with localized **409** whenever Orders reports **any persisted order history**, including drafts, cancellations, deliveries and Soft Deleted orders. Orders now registers the production guard and synchronizes creation with the customer row lock.
- Changing customer type does not recalculate old orders. Type assignment checks active Settings value **inside the DB transaction** under its row lock.

### DELETE /api/v1/customers/{customer} — 200

Soft Delete only if Orders and Finance usage registries report no historical references (including cancelled/completed/Soft Deleted order histories or financial entries). The actual Orders/Finance guards are **future module adapters**, not fake tables in Customers. Customer deletion locks the parent row; their writes must lock the same row when they first implement references.

Success: `{"success":true,"message":"","data":null}`. A prohibited deletion -> localized 409, and the row/phone remains active. A permitted Soft Delete nulls the live-only phone hash and frees the original phone while keeping historical row metadata.

### GET /api/v1/selects?selects=customers,activeCustomers

Use the **existing authorized shared Select Aggregator**; do not create separate Customers-select routes.

| Key | Dataset | Permission |
|---|---|---|
| `customers` | **All non-Soft Deleted customers:** ACTIVE and INACTIVE | `customers.selects.view` |
| `activeCustomers` | **Only ACTIVE**, non-Soft Deleted customers | `customers.selects.view` |

Both produce option objects with the **customer name and phone together**, to distinguish duplicate names: `{"value":15,"label":"أحمد محمد - 01012345678"}`. `value` is always the numeric customer ID. Sort by name then ID consistently. No `params[customers]=active` is needed; request `activeCustomers` to get the active-only set.

The existing Select Aggregator returns a list of `{label,options}` groups, each identified by its key:

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "label": "customers",
      "options": [
        {"value": 15, "label": "أحمد محمد - 01012345678"},
        {"value": 16, "label": "منى علي - 01111111111"}
      ]
    },
    {
      "label": "activeCustomers",
      "options": [
        {"value": 15, "label": "أحمد محمد - 01012345678"}
      ]
    }
  ]
}
```

The values above are illustrative, with customer 16 assumed INACTIVE; neither group contains Soft Deleted records. **Use `activeCustomers` for new Order entry**; `customers` is available for administrative workflows and historical collections where an inactive customer must remain selectable. Both selects are read-only and never imply that inactive customers can receive new Orders.

### Cross-module contracts to implement now / later

- **Customers now:** `CustomerStatusTransitionGuard` and `CustomerUsageGuard` registries; Orders Core now registers real guards for historic order references, while Finance will later register its own guard for financial movements.
- **Customers → Settings now:** register Customers-owned `ParameterValueUsageGuard` for types referenced by **non-deleted** customers; register Customers-owned `ParameterValueDeactivationGuard` for types assigned to current customers. A deleted, never-used customer alone does not block the former type's Soft Delete.
- **Orders later:** actual order create/reassignment locks customer row and checks ACTIVE; Orders provider registers status/deletion usage guards, handles order snapshots, and serializes delete/deactivate races.
- **Finance later:** actual customer collection/debt opening entries are separate Finance operations, never part of customer create/update; registers historical-use deletion guard and permits collection from inactive customers.

---

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
9a. Create returns HTTP 201 (`message: تم إضافة العميل بنجاح`) with `success: true` and the newly created CustomerResource in `data` including its numeric `id`, suitable for immediate selection in Orders. Update returns HTTP 200 (`message: تم تعديل العميل بنجاح`) with `success: true` and `data: null`. GET endpoints still return complete CustomerResources. Verify both `ar` and `en` translation catalogs and responses.
9b. Select keys `customers` and `activeCustomers` share `customers.selects.view`; the first contains active and inactive live customers, the second active only, both exclude Soft Deleted records, and both format labels as `name - phone`.
10. No customer CRUD action posts Finance ledger entries implicitly or writes a denormalized account balance; opening debt/credit is a separate future Finance operation.
11. ACTIVE → INACTIVE returns localized 409 if Orders reports **any historical order** (including Draft, Cancelled, Delivered and Soft Deleted); such orders also block customer Soft Delete. The real Orders adapter is registered from Orders Core.
12. A customer with no Orders/Finance references can be Soft Deleted, releasing the phone. Its old Settings customer-type relation by itself does not block type Soft Delete, while a current customer or Pricing-tier relation still does.

## 8. Suggested implementation order

1. Finish remaining decisions and **approve this module-named requirements file** (`05-customers.md`) before writing any Customers code.
2. Add module skeleton, model/migration, authorized CRUD, requests/resources, Arabic/English catalogs and module tests.
3. Implement/verify Settings deletion and deactivation guards, with transaction locking.
4. Add customer select registration and permission seeders; rerun entire SQLite suite, MySQL migration/concurrency probes and code review.
5. Integrate Orders and Finance only in their **own future modules** using the explicit contracts.
