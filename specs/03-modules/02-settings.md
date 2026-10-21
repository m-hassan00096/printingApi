# Tabaa — 02 Settings & Reference Data

> **Document type:** Module Reference — functional requirements and technical contracts only  
> **Location:** `specs/03-modules/02-settings.md`  
> **Status:** Draft for review — confirmed business decisions documented; remaining decisions isolated in §18  
> **Scope:** Backend REST API · internal printing-business settings  
> **Stack:** Laravel 13 · Sanctum stateful SPA authentication · Spatie Laravel Permission · MySQL · nwidart/laravel-modules  
> **Language:** Arabic (`ar`) currently; technical identifiers and API fields remain English  
> **Related standards:** Project Overview, Backend Architecture, approved API and code standards, and Identity module reference (see §20).

## 1. Purpose and boundaries

The **Settings** module maintains four groups of reusable business configuration for **one printing business**:

1. **Printing locations** — simple names selected when an individual order item moves from design to printing.
2. **Customer types** — a categorized customer attribute used by the pricing system.
3. **Bank accounts** — destinations for customer receipts and advance payments.
4. **E-wallet accounts** — alternative destinations for customer receipts and advance payments, including manually named options such as InstaPay.

Printing locations and customer types use a controlled, two-table lookup model (`parameters` and `parameter_values`). Bank accounts and wallets use their **own tables** because their required fields, uniqueness rules, and default-account behavior differ from generic lookup values.

The module owns definitions and management rules. **Production** owns the assignment of printing locations to order items. **Customers** owns customer-type association. **Services/Orders** own price calculation and order price snapshots. **Finance** owns actual monetary entries, receipt/advance-payment records, and payment-destination snapshots. Settings never posts money or mutates historical financial entries.

This is the **business and technical reference** from which feature specifications, plans, and tasks may be derived later. It intentionally contains **no implementation task lists, sprint statuses, or prescribed class inventories**. A documented contract must not be described as implemented until the code and tests are verified.

### Responsibilities

- Maintain authorized CRUD for printing locations, customer types, bank accounts and e-wallets.
- Keep `parameters` as **backend-defined technical lookup groups**; no user-facing parameter-group management API.
- Expose paginated management lists and small, authorized dropdown options through the existing shared Select Aggregator.
- Apply active/inactive and soft-deletion rules without corrupting customer, production, or financial references.
- Support at most one active default bank account and one active default wallet, independently.
- Ensure fields and numeric identifiers are validated in the owning module before other modules accept them.
- Provide idempotent starter data for the two lookup groups and approved sample values, but **not real or fabricated bank/wallet credentials**.

### Explicitly out of scope for v1

- Printing location phone, address, supplier profile, production capacity, services offered, pricing, or printing cost.
- A printing-location change-history/audit table, previous location, and the identity of a staff member who changed a location.
- Customer pricing rules themselves, editing existing order prices, or a customer-type pricing engine in Settings.
- Bank balances, cashbox balances, accounting entries, reconciliation, outgoing vendor payments, transfers, payment gateway integration, and a general ledger.
- User-defined parameter groups, dynamic schemas, arbitrary key/value metadata, a universal JSON settings engine, or a settings table for every other future module.
- Restore/undelete endpoints, advanced auditing screens, and management of previously deleted records.
- A frontend application, external/public APIs or customer self-service management.

## 2. Users, actors, and access

| Actor | Settings-level behavior |
|---|---|
| Unauthenticated visitor | Cannot read options or manage any Settings record. |
| Authenticated staff with `settings.selects.view` | May request approved active dropdown options for **all four Settings groups** through the shared selects endpoint; receives only approved option fields. |
| Staff with a section's `.view` permission | May use that section's management list and detail APIs; bank account numbers and wallet phone numbers are displayed **in full** to those authorized viewers. |
| Staff with `settings.create_parameter_value` | May add a value to an existing technical parameter group using one shared create endpoint; this does not permit creating new parameter groups. |
| Staff with other approved management write permissions | May perform their authorized updates/deletions and bank/wallet writes, subject to validation and state/in-use protections. |
| Authorized production administrator | May change an order item's assigned printing location only while its printing stage is still open. This permission belongs to **Production**, not a name-based bypass in Settings. |
| Finance staff with relevant Finance permission | May choose eligible destinations when recording customer receipts/advances. Actual postings and snapshots are governed by Finance. |

All protected routes reuse Identity's Sanctum authentication, active-account/active-role enforcement and server-side Spatie permission checks. Authorization comes from **permissions**, not an implicit `admin` role-name bypass. The exact Production permission that grants the currently admin-only printing-location override is owned by the Production reference.

## 3. Approved business decisions

The following rows record **owner-confirmed decisions** from the Settings discussion. Items still awaiting a choice are **not** silently assumed; they are listed in §18.

| ID | Decision |
|---|---|
| SET-001 | Four settings categories: printing locations, customer types, bank accounts and e-wallet accounts. |
| SET-002 | `parameters` contains backend-controlled technical names; `parameter_values` stores printing locations and customer types only. |
| SET-003 | Bank accounts and e-wallets have separate relational tables; no generic JSON attributes for these fields. |
| SET-004 | Printing location business data is **name only**. |
| SET-005 | Each **order item** may be assigned a different printing location. Assignment happens when closing design and moving that item to printing. |
| SET-006 | During an open printing stage, an administrator may change that item's printing location. Changes are forbidden after printing is closed. |
| SET-007 | Do **not** retain a history of printing-location changes, prior locations or changer identities. |
| SET-008 | The final printing-location name is retained as a historical snapshot so later renames cannot rewrite completed historical data. Snapshot capture timing still needs a final choice (§18). |
| SET-009 | Customer types are freely managed, with a required **name** and optional **description**. Names cannot be duplicated within their type. |
| SET-010 | Customer type is a property of the **customer**, required at customer creation; it is not selected independently for each order. |
| SET-011 | Customer type currently influences pricing; orders keep the agreed prices and a historical type-name snapshot. Changing a customer's type must not retroactively reprice existing orders. |
| SET-012 | Changing a customer's type remains allowed even if that customer already has items in design/printing; existing order prices remain unchanged. |
| SET-013 | A customer type cannot be deactivated while **currently associated with customers**. |
| SET-014 | Bank account fields: bank name and account number only; **no IBAN** or extra account nickname. |
| SET-015 | More than one account may belong to the same bank; account numbers are globally unique within the bank accounts table. |
| SET-016 | Bank accounts are currently intended for **incoming customer payments only**; account balances belong to Finance later. |
| SET-017 | E-wallet fields: free-text name, mobile number, account holder and an **optional free-text** payment link. |
| SET-018 | Wallet names may repeat; wallet phone numbers must be unique. v1 wallet numbers are Egyptian 11-digit mobile numbers. InstaPay is treated as an e-wallet in v1. |
| SET-019 | Bank and wallet defaults are independent: at most one default in each group, and the group may have no default if there is no suitable active record. |
| SET-020 | The first newly added **active** bank account/wallet becomes its group's default. Selecting another default unsets the prior one. Disabling a default switches to another active default if available. |
| SET-021 | Bank/wallet details may be edited even after receiving money; the **receipt or advance-payment operation** preserves the payment-destination snapshot from the posting time. |
| SET-022 | Management `view` permissions are enough to see **full bank account numbers and wallet numbers**; no special masking permission is required. |
| SET-023 | Settings records support active/inactive state; the frontend explicitly sends the state and the backend validates it. |
| SET-024 | A record with no historic references may be soft-deleted; once used by a historic operation, deletion is blocked and deactivation is the normal alternative, subject to other restrictions such as SET-013. |
| SET-025 | No restore endpoint in v1. |
| SET-026 | All four management lists offer search, status filter and pagination. |
| SET-027 | The implemented parameter-value Create API uses the **shared** `settings.create_parameter_value` permission; other lookup-management and bank/wallet permissions are specified separately as their features are implemented. The shared selects permission remains `settings.selects.view`. |
| SET-028 | The four Settings options are added to the existing `GET /api/v1/selects`; no new generic Selects module. |
| SET-029 | Duplicate printing-location and customer-type names are rejected after trimming surrounding spaces. |
| SET-030 | Seed starter parameter groups and illustrative customer-type/printing-location values; **do not seed actual or dummy bank accounts or wallets**. |
| SET-031 | A `parameter_values.name` remains permanently reserved within its `parameter_id` after Soft Delete. Enforce this with a database unique constraint on `(parameter_id, name)`, as well as request validation. Names remain reusable across **different** groups. |

## 4. Lookup model and parameter ownership

### 4.1 Backend-controlled `parameters`

`parameters.name` is a stable English technical identifier. Initial groups:

| name | Managed values | Input shown to staff |
|---|---|---|
| `printing_locations` | `parameter_values` | Printing location name only |
| `customer_types` | `parameter_values` | Customer type name and optional description |

- Technical group rows are inserted by an **idempotent seeder** and cannot be created, renamed or deleted through an API.
- No arbitrary group creation from client-supplied `parameterId` or `name`.
- The application resolves the group from a **fixed backend registry** for each public management endpoint and each external relation.
- Adding a future simple group is a deliberate code/seed change and requires defined permissions and valid cross-module behavior; it is **not** runtime user configuration.

### 4.2 Value ownership and cross-type safety

- Each `parameter_values` row belongs to exactly one parameter group via `parameter_id`.
- `parameter_id` cannot be modified after creating a value.
- Names are unique **within their own group**; the same text in `printing_locations` and `customer_types` is allowed.
- A caller may **not** pass a customer-type value ID as a printing location or vice versa. Validation must check the expected group, active state, and non-deleted state for **new selections**.
- Database FK to `parameter_values.id` establishes existence, but does **not by itself** enforce the expected group type. The owning Customers/Production action must make that check.
- The generic table is a storage optimization, not permission inheritance: operations are still split into distinct business APIs and policies per group.

## 5. Printing locations

### 5.1 Fields and management

- Management input contains **`name`** and **`status`**. No user-facing `description` input for printing locations; the shared column stays `NULL` for this group.
- `name` is required and unique within the group after normalized/trimmed comparison.
- Management list supports text search, active/inactive filter, page size and sorting under the normal API standard.
- Selected printing locations must exist, belong to `printing_locations`, be active and not be deleted for **new assignment**.

### 5.2 Production lifecycle (cross-module contract)

```text
Individual order item
    design stage open
      -> close design / move to printing
      -> choose an active printing location for this item
      -> printing stage open
            -> authorized admin may replace its current location
      -> close printing stage
            -> location locked; historical name retained
```

- Order A may contain items using locations X, Y and Z at the same time.
- The reference is attached to the **individual order item or its Production-owned state**, never as a single shared `orders.printing_location_id`.
- During open printing, an allowed replacement **overwrites the current assignment**; do not preserve old location IDs or log a location-change history.
- Printing stage closure locks changes. A future reopen/undo process, if any, must be specified by Production before changing that rule; Settings does not introduce such a workflow.
- A final name snapshot ensures historical display remains stable after a Settings rename. **The precise snapshot capture point is awaiting confirmation** (§18); don't assume both start and finish events require persistent copies.
- The Production action decides the permissible state transition and authorization. A Settings CRUD update does not change an order item's workflow stage.

## 6. Customer types and pricing relationship

- Staff may add new named types without a fixed user-editable catalog limit.
- `description` is optional free text; no tier percentages or pricing amounts are stored on the type itself by Settings.
- A registered customer has exactly one selected customer type for the currently approved creation workflow. The authoritative customer association belongs to **Customers**.
- The type selection is mandatory on customer creation; the type must be active, correctly categorized, and not soft-deleted.
- A type associated with current customers **cannot be disabled**; a historically used type cannot be soft-deleted under the general in-use rule.
- When the customer type changes, the customer record takes the new type and pricing for **future orders** uses the current type. Existing orders retain their own stored final prices; no retroactive bulk update or automatic repricing of existing order items.
- A type's historical display name is recorded by **Orders** when the order is created. Do not read the current type label to reconstruct old prices or order-time labels.
- Whether customers with soft-deleted customer records count toward the deactivation restriction requires the customer model's final policy (§18); do not cascade-remove type rows.

## 7. Bank accounts

### 7.1 Required fields

| API field | Database field | Required | Rules |
|---|---|---|---|
| `bankName` | `bank_name` | Yes | Free-text bank name. |
| `accountNumber` | `account_number` | Yes | String, unique across bank accounts; preserve leading zeros. |
| `isDefault` | `is_default` | Yes / managed | User may nominate a default; invariant also enforced server-side. |
| `status` | `status` | Yes | Integer `1` active / `0` inactive. |

No IBAN, account holder name, internal alias, opening balance or additional banking profile fields are required in v1.

### 7.2 Behavioral rules

- Multiple distinct accounts at the same bank are permitted.
- The recorded account may be modified after use, including its account number, but **already posted** receipts and deposits preserve their captured bank details.
- Finance is responsible for storing/selecting an account for a receipt/advance, deciding if one is required, and recording the actual money movement.
- Changing Settings records does not re-post, cancel or modify customer balances.

## 8. E-wallet accounts

### 8.1 Required fields

| API field | Database field | Required | Rules |
|---|---|---|---|
| `name` | `name` | Yes | Manually entered; may repeat. |
| `phone` | `phone` | Yes | Egyptian mobile, 11 digits, unique; persist as string to keep leading zero. |
| `accountHolder` | `account_holder` | Yes | Free-text person/account holder. |
| `paymentLink` | `payment_link` | No | Optional free text; **do not** enforce URL format in v1. |
| `isDefault` | `is_default` | Yes / managed | Default request subject to shared invariant. |
| `status` | `status` | Yes | Integer `1` active / `0` inactive. |

### 8.2 Behavioral rules

- Two wallets can share a display name (e.g., two Vodafone Cash wallets) but **cannot share the same normalized phone number**.
- InstaPay is entered just like another wallet name for now; do not invent a bank-linked InstaPay subsystem.
- Wallet bank balance, transfer, external provider verification and automatic payment confirmation are out of scope.
- Editing wallet information does not rewrite existing customer receipt/deposit snapshots.

## 9. Default payment destination rules

Bank account defaults and wallet defaults form **independent sets**. Both sets now use transactional management through `PaymentDestinationDefaultService` and dedicated per-type lock rows in `settings_payment_default_locks`.

1. At any time, each set has **at most one active, non-deleted default**.
2. The **first active** account/wallet added becomes default automatically, even if the create payload does not nominate it.
3. When an existing active record is explicitly made default, the previous default in that set loses the flag atomically.
4. A record cannot remain a usable default if it is inactive or deleted.
5. When the current default is disabled and other active records are available, another active record becomes default automatically. If no active candidate exists, **no default** is valid.
6. The user has not chosen the **selection priority** when there are multiple candidates (§18). A deterministic oldest-ID rule is proposed but not yet approved.
7. Concurrent administrative requests must not leave two defaults in one group. Enforce through a safe database transaction/serialization approach, with an appropriate DB-level uniqueness design where feasible; do not rely only on a UI toggle or simple pre-save counting query.
8. Defaults are **convenience suggestions** for Finance UI/flows, not evidence of a payment or authorization to post money.

## 10. Delete, deactivate, uniqueness and history boundaries

### 10.1 Active/inactive behavior

- Status is provided by the frontend for create/update; invalid or missing required values fail validation.
- Inactive Settings entries remain visible in management lists and historical views.
- Inactive or deleted entries are **excluded** from new-operation selects.
- A printing location can be inactive even when historical items refer to it, subject to Production's handling of an item currently in progress. The exact rule for an **already assigned but still active printing item** is not expanded into supplier workflow logic in Settings.
- A customer type associated with **current customer records cannot be disabled**. Return a business conflict without partial changes.

### 10.2 Soft deletion

- `parameter_values`, `bank_accounts` and `e_wallets` use `deleted_at`.
- `DELETE` is permitted only for records **never referenced** by a relevant Customer, Production, receipt or advance-payment operation; otherwise the API returns a business conflict and recommends deactivation when applicable.
- Do not physically delete historical records or cascade-delete related customers, items, or receipts.
- No restore endpoint in v1. Deleted records are not included in normal management lists or selects.
- Whether unique names/account numbers/phone numbers stay **reserved even after Soft Delete** is deliberately **not yet approved** (§18). The chosen behavior must be enforced coherently by the DB indexes and application validation.

### 10.3 Historical snapshots

- **Orders:** type label and final agreed prices remain as they were at order creation/confirmation under Orders' domain contract.
- **Production:** final printing-location label remains stable after a settings rename; no change-history rows are created. Precise capture/lock timing remains open.
- **Finance:** the payment destination's details are copied to the receipt/advance **when money is recorded**, not preemptively on an unrelated order.
- Historical operations must read their snapshots rather than assume current settings names/numbers equal historical values.
- No universal `settings_snapshots` table; each owning module stores the fields it actually needs.

## 11. Data model and database constraints

All Settings tables belong to the same MySQL database as the rest of Tabaa. The schema below is the **proposed concrete structure derived from approved fields**. Column lengths and index implementation are technical recommendations until migrations are reviewed.

### 11.1 `parameters`

| Column | Type | Constraint / meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK, auto-increment. |
| `name` | `VARCHAR(80)` | Required, globally unique technical key. |
| `created_at`, `updated_at` | Timestamps | Conventional Laravel timestamps. |

No `deleted_at` or user-managed status is required for backend-owned group definitions. Do not create public REST CRUD routes for this table.

### 11.2 `parameter_values`

| Column | Type | Constraint / meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK, auto-increment. |
| `parameter_id` | `BIGINT UNSIGNED` | Required FK to `parameters.id`, immutable after creation. |
| `name` | `VARCHAR(150)` | Required, unique within the same group after normalization. |
| `description` | `TEXT NULL` | Optional for customer types; `NULL` for printing locations. |
| `status` | `TINYINT` | Required, `0` or `1`. |
| `created_by`, `updated_by` | `BIGINT UNSIGNED NULL` | Optional FKs to `users.id`; populated with authenticated actor on model create/update; nullable for seeders/CLI. |
| `created_at`, `updated_at` | Timestamps | Laravel timestamps. |
| `deleted_at` | Nullable timestamp | Soft Delete. |

Indexes: the original `(parameter_id, name)` index is replaced by a **unique** `(parameter_id, name)` constraint in the subsequent `2026_10_21_000002_add_unique_index_to_parameter_values.php` migration; `(parameter_id, status, deleted_at)` remains indexed. Soft-deleted rows retain the reserved name; request validation also enforces this. Before migrating an existing database, check for duplicate `(parameter_id, name)` pairs and resolve them, otherwise the unique-index migration will fail.

### 11.3 `bank_accounts`

| Column | Type | Constraint / meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK. |
| `bank_name` | `VARCHAR(150)` | Required. |
| `account_number` | `VARCHAR(50)` | Required, unique after approved normalization. |
| `is_default` | `BOOLEAN` | At most one eligible default across active accounts. |
| `status` | `TINYINT` | Required, `0` or `1`. |
| `created_by`, `updated_by` | `BIGINT UNSIGNED NULL` | Optional FKs to `users.id`; populated with authenticated actor on model create/update; nullable for seeders/CLI. |
| `created_at`, `updated_at` | Timestamps | Laravel timestamps. |
| `deleted_at` | Nullable timestamp | Soft Delete. |

No IBAN column. The current database unique index on `account_number` includes soft-deleted rows; retaining that behavior is **provisional** pending SET-OPEN-01.

### 11.4 `e_wallets`

| Column | Type | Constraint / meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | PK. |
| `name` | `VARCHAR(150)` | Required; not unique. |
| `phone` | `VARCHAR(20)` | Required, unique, exact Egyptian 11-digit mobile after normalization. |
| `account_holder` | `VARCHAR(150)` | Required. |
| `payment_link` | `TEXT NULL` | Optional free text. |
| `is_default` | `BOOLEAN` | At most one eligible default across active wallets. |
| `status` | `TINYINT` | Required, `0` or `1`. |
| `created_by`, `updated_by` | `BIGINT UNSIGNED NULL` | Optional FKs to `users.id`; populated with authenticated actor on model create/update; nullable for seeders/CLI. |
| `created_at`, `updated_at` | Timestamps | Laravel timestamps. |
| `deleted_at` | Nullable timestamp | Soft Delete. |

The current database unique index on `phone` also covers soft-deleted wallets; this reservation policy is **provisional** pending SET-OPEN-01.

**Shared audit columns for Settings business tables:** `parameter_values`, `bank_accounts` and `e_wallets` (and future user-managed business tables) require Laravel `softDeletes()` plus nullable `created_by` and `updated_by` foreign keys to Identity `users.id`. Use the reusable migration trait `App\\Database\\Migrations\\Concerns\\HasCreatedUpdatedByColumns` for the actor columns and the model trait `App\\Models\\Concerns\\CreatedUpdatedBy` for automatic actor stamping and `createdBy`/`updatedBy` relationships. `created_by` is stamped on insert and `updated_by` on later updates when an authenticated actor is available; otherwise the fields remain nullable. The actor relationships include soft-deleted users; physical deletion of referenced users is restricted. **Do not add these audit columns or Soft Deletes to backend-only `parameters`.** No deletion-actor history is requested.

### 11.5 Relationship outline (cross-module, not new Settings tables)

```text
parameters (id)
   └─ 1:N parameter_values (parameter_id)
          ├─ customer_types rows ─> Customers.customer_type_id [future/owner: Customers]
          └─ printing_locations rows ─> each Order Item's Production location reference

bank_accounts (id) ─> Finance receipt / advance payment reference + snapshot

e_wallets (id) ────> Finance receipt / advance payment reference + snapshot
```

- `customer_type_id` and `printing_location_id` are **semantic** reference names even though both refer to `parameter_values.id`.
- Customer and order-item references live in **their owning modules**, not as new Settings columns or migrations.
- Finance may choose a polymorphic payment-destination reference or separate optional FKs later; **that schema is deliberately not fixed here**. It must be able to retain historical snapshots and reject cross-type mismatches.
- Foreign keys preserve referential integrity; deletion restrictions and lookup-group correctness still require owning-module/domain checks.

## 12. API contract — proposed endpoints for approval

All routes are relative to `/api/v1`. Body fields use `camelCase`; database columns remain `snake_case`. Successful GET responses use the shared `{ "success": true, "message": "", "data": ... }` envelope. The implemented parameter-values index mirrors the Identity `UserCollection` structure, returning `data.parameters` and `data.pagination`; other proposed Settings management lists may use `data.items`. Routes use `auth:sanctum` and Spatie permissions.

The generic parameter-value **Index, Show, Create, Update and Soft Delete** endpoints below are implemented, each protected by the relevant shared permission. Bank/wallet management routes remain proposed until implemented. No public CRUD for `parameters` technical groups is exposed.

### 12.1 Parameter values — generic index, show, create, update and delete (implemented)

| Method | Path | Required permission | Behavior |
|---|---|---|---|
| `GET` | `/settings/parameter-values?parameterId={id}` | `settings.view_parameter_values` | Paginate non-deleted values within the required existing group ID. |
| `GET` | `/settings/parameter-values/{parameterValue}` | `settings.view_parameter_values` | Show a non-deleted value by ID. |
| `POST` | `/settings/parameter-values` | `settings.create_parameter_value` | Create a value within an existing `parameters.id` group, using the shared CreateParameterValueAction. |
| `PUT` / `PATCH` | `/settings/parameter-values/{parameterValue}` | `settings.update_parameter_value` | Update a non-deleted value with UpdateParameterValueAction; its `parameter_id` cannot change. |
| `DELETE` | `/settings/parameter-values/{parameterValue}` | `settings.delete_parameter_value` | Soft-delete a non-deleted parameter value using DeleteParameterValueAction; attempts to delete the same value again return 404. |

- Create body uses `parameterId` (existing technical group ID), `name`, optional `description`, and integer `status` (`0` or `1`). Update body supports partial changes to `name`, `description` and `status`; clients cannot change `parameterId` or `parameter_id`.
- Unknown `parameterId` values fail validation; no parameter-group creation or arbitrary group-name editing is exposed.
- Create and update permissions are intentionally common to the current and future parameter groups; a user granted each permission can perform that operation for any existing group.
- Lookup groups remain relationally distinct via `parameter_id`, and value-name uniqueness is scoped per group.
- Existing business-specific rules continue to apply, including the current requirement that printing locations have only a user-facing name and status; implementation of that group-specific description restriction should be checked in tests before production use.
- The index **requires** `parameterId` (exists in `parameters.id`); it supports `filter[search]` (searches `name` and `description` **only**), `filter[status]` (integer `0` or `1`), `page`, `perPage` (1–100, default 15), and `sort` (allowlist: `id`, `name`, `created_at`; default `-id`). The filter syntax follows Spatie QueryBuilder and the Identity users API. Other filters are rejected.
- The collection uses `data.parameters` and `data.pagination` (`currentPage`, `lastPage`, `perPage`, `total`), and excludes soft-deleted rows by default. Show uses ParameterValueResource and does not require a `parameterId` query parameter.
- Delete returns the normal `ApiResponse::success()` response (`200`, `data: null`), never hard-deletes, and permanently reserves the deleted name within its group. The prior section-specific create routes are **not** part of the API.
- **Integration constraint:** historical-use checks for Customer/Production associations must be enforced before those modules expose their associations to these values. Current Settings has no Customer or Production usage relationships; the present Delete Action does **not** yet block deletion of historically referenced values.
- Input validation (required fields, enum status, trimmed names and group-scoped uniqueness) belongs to StoreParameterValueRequest and UpdateParameterValueRequest, not duplicated in Actions. The update Action handles the transactional row-locked write; the create Action writes through the Parameter relationship.

### 12.2 Bank accounts and e-wallets — CRUD implemented (integration caveats below)

| Method | Path | Permission pattern | Behavior |
|---|---|---|---|
| `GET` | `/settings/bank-accounts` | `settings.bank_accounts.view` | Paginated management list. |
| `POST` | `/settings/bank-accounts` | `settings.bank_accounts.create` | Create account; enforce unique number/default logic. |
| `GET` | `/settings/bank-accounts/{bankAccount}` | `settings.bank_accounts.view` | Details including full account number. |
| `PUT` / `PATCH` | `/settings/bank-accounts/{bankAccount}` | `settings.bank_accounts.update` | Update fields and default/status atomically. |
| `DELETE` | `/settings/bank-accounts/{bankAccount}` | `settings.bank_accounts.delete` | Soft-delete; historical-use protection must be integrated with Finance before the account can be referenced by receipts or advances. |
| `GET` | `/settings/e-wallets` | `settings.e_wallets.view` | Paginated management list. |
| `POST` | `/settings/e-wallets` | `settings.e_wallets.create` | Create wallet; validate phone/default logic. |
| `GET` | `/settings/e-wallets/{eWallet}` | `settings.e_wallets.view` | Details including full phone. |
| `PUT` / `PATCH` | `/settings/e-wallets/{eWallet}` | `settings.e_wallets.update` | Update wallet/default/status atomically. |
| `DELETE` | `/settings/e-wallets/{eWallet}` | `settings.e_wallets.delete` | Soft-delete; historical-use protection must be integrated with Finance before the wallet can be referenced by receipts or advances. |

Both management indexes use the Identity-style `filter[search]`, `filter[status]`, `page`, `perPage` (1–100, default 15) and `sort` query parameters. Search for bank accounts includes `bank_name` and `account_number`; wallet search includes `name`, `phone` and `account_holder`. Sort allowlists are `id,bankName,created_at` (banks; `bankName` maps safely to `bank_name`) and `id,name,created_at` (wallets); default `-id`. List response data uses `bankAccounts` or `eWallets` plus `pagination` matching UserCollection. Normal management lists include inactive rows and exclude soft-deleted rows. Details return full account/phone identifiers to holders of the corresponding view permission.

Store requests require `bankName,accountNumber,status` for banks, and `name,phone,accountHolder,status` for wallets; `isDefault` is optional and is managed server-side. `paymentLink` is optional nullable plain text, not a URL validator. Phone is canonical `01[0125]` followed by eight ASCII digits. Update requests are partial (PUT/PATCH) and trim supported text fields. All mutations run through dedicated Actions and `PaymentDestinationDefaultService`.

**Default concurrency implementation:** the service acquires a transactional `SELECT ... FOR UPDATE` lock on a pre-seeded row in `settings_payment_default_locks` for each type, including when there are no accounts yet. This serializes all Settings default changes within each type in MySQL. A new active account automatically becomes default when no eligible default exists; selecting another explicit default demotes the previous one. An inactive/deleted default is replaced with an active candidate where available. **Temporary unresolved rule SET-OPEN-02:** choose the oldest eligible `id` when a replacement must be selected; this is an implementation fallback, **not** an approved policy. **Temporary unresolved SET-OPEN-01:** the current plain unique indexes also reserve numbers after Soft Delete; confirm the permanent reservation policy before production data migration. A separate database-level partial uniqueness invariant for default flags has not been added; all writes must use these Actions.

**Finance integration boundary:** payment destinations are not referenced by any Finance receipt/advance schema yet, so these Delete Actions cannot enforce the approved historical-usage restriction. Finance integration must add a reference check (including historic/soft-deleted transactions) before financial posting is enabled. No balances, transfers, or Finance snapshots are implemented here.

**Do not** expose user-facing `POST /settings/parameters` or `PUT /settings/parameters/{id}`. The generic create endpoint accepts only an ID that already exists in the backend-seeded `parameters` table. The parameter groups themselves are not user-editable.

## 13. Example request and response payloads

These are **proposed wire contracts**, not assertions about currently implemented endpoints.

### 13.1 Create printing location

```http
POST /api/v1/settings/parameter-values
```

```json
{
  "parameterId": 1,
  "name": "مطبعة النور",
  "status": 1
}
```

**Example `201` response:**

```json
{
  "success": true,
  "message": "تم إضافة مكان الطباعة بنجاح.",
  "data": {
    "id": 7,
    "parameterId": 1,
    "name": "مطبعة النور",
    "status": 1
  }
}
```

### 13.2 Create customer type

```json
{
  "parameterId": 2,
  "name": "شركة",
  "description": "عملاء الشركات",
  "status": 1
}
```

`parameterId` is required for the single generic endpoint; IDs `1` and `2` above are illustrative and must come from the actual database. `description` may be omitted or `null`.

### 13.3 Create bank account

```json
{
  "bankName": "البنك الأهلي المصري",
  "accountNumber": "001234567890",
  "isDefault": true,
  "status": 1
}
```

- The bank number remains a **string** in API and MySQL; there is no `iban` field.
- A request that makes one account default must atomically demote the old default.
- The first active account will be default even when `isDefault` is `false`, because the invariant takes precedence.

### 13.4 Create e-wallet

```json
{
  "name": "Vodafone Cash",
  "phone": "01012345678",
  "accountHolder": "اسم صاحب الحساب",
  "paymentLink": "تحويل على رقم المحفظة",
  "isDefault": false,
  "status": 1
}
```

`paymentLink` is optional plain text and may be null. Because these are sample payloads, bank numbers/phone numbers shown are illustrative, **not seed data**.

### 13.5 Paginated parameter values list

```json
{
  "success": true,
  "message": "",
  "data": {
    "parameters": [
      {
        "id": 7,
        "name": "مطبعة النور",
        "status": 1
      }
    ],
    "pagination": {
      "total": 1,
      "currentPage": 1,
      "perPage": 15,
      "lastPage": 1
    }
  }
}
```

Standard query input follows existing project `page`, `perPage`, permitted filter/search/sort names. Exact allowed filter/sort names require endpoint review; do not accept arbitrary database column names from the client.

## 14. Shared Selects integration

Settings registers all **four** Settings select keys in the existing shared `GET /api/v1/selects` aggregator. No new endpoint is introduced:

```http
GET /api/v1/selects?selects=printingLocations,customerTypes,bankAccounts,eWallets
```

| Select key | Value | Label | Eligibility | Permission | State |
|---|---|---|---|---|---|
| `printingLocations` | `parameter_values.id` | Printing location name | Active + not soft-deleted + `printing_locations` group | `settings.selects.view` | **Implemented** |
| `customerTypes` | `parameter_values.id` | Customer type name | Active + not soft-deleted + `customer_types` group | `settings.selects.view` | **Implemented** |
| `bankAccounts` | `bank_accounts.id` | Bank name + masked last four digits | Active + not soft-deleted | `settings.selects.view` | **Implemented** |
| `eWallets` | `e_wallets.id` | Wallet name + masked last four digits | Active + not soft-deleted | `settings.selects.view` | **Implemented** |

The shared aggregator returns an **ordered array** of `{ "label": key, "options": [...] }`. Only the bank and wallet options have the extra `isDefault` property; the lookup groups remain unchanged. Example response (illustrative IDs and labels):

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "label": "printingLocations",
      "options": [{ "value": 7, "label": "مطبعة النور" }]
    },
    {
      "label": "customerTypes",
      "options": [{ "value": 3, "label": "شركة" }]
    },
    {
      "label": "bankAccounts",
      "options": [
        { "value": 11, "label": "البنك الأهلي - ****7890", "isDefault": 1 },
        { "value": 12, "label": "بنك مصر - ****1122", "isDefault": 0 }
      ]
    },
    {
      "label": "eWallets",
      "options": [
        { "value": 21, "label": "Vodafone Cash - ****5678", "isDefault": 1 }
      ]
    }
  ]
}
```

- `SettingsSelectServiceProvider` registers all four keys through the shared `SelectRegistry`. `ParameterValueSelectService` handles the two lookup keys, while `PaymentDestinationSelectService` supplies bank/wallet options using active-only Eloquent queries; SoftDeletes excludes deleted values.
- The payment options contain **exactly** `value` (record ID, integer), `label` (string), and `isDefault` (**integer** `0` or `1`, not JSON boolean). This property reflects `is_default` as currently stored; it is independent for banks and wallets. Lookup-group options still contain only `value` and `label`.
- Bank and wallet labels display the user-facing bank/wallet name followed by `****` and the final four digits; very short bank identifiers (four characters or less) are fully masked. Full account numbers, wallet phones, account-holder data and payment links are **not** exposed through the shared Selects API.
- All four keys accept the optional `params[key]=active` filter or no filter. Other values (`all`, `inactive`) return `422`, consistent with the existing Identity convention.
- `settings.selects.view` grants access to the four Settings select keys but does **not** grant management-view or write permissions. Corresponding management-view permissions alone do not grant access to selects.
- Requests may mix Settings and Identity keys when the caller has every required permission. Unknown keys return `422`; unauthorized keys return `403` without a partial data response.
- Historical selections are rendered by the owning module's stored snapshot even when a resource later becomes inactive. Inactive and soft-deleted records must never appear as newly selectable options.

## 15. Validation, authorization and failure semantics

### 15.1 Input validation

- Trim leading/trailing whitespace from names and meaningful identifiers before uniqueness checks. Treat blank-after-trim required fields as invalid.
- `name` is unique **within its own parameter group**, not across both groups. Do not accept `parameterId` as an editable public property.
- The bank account number is a string with uniqueness validation and no coercion to numeric values.
- The wallet phone number is exactly **11 ASCII digits** in an Egyptian mobile format beginning with `01`, stored as a string. A final decision on accepted punctuation/+20 input normalization can be made in the Form Request contract; stored canonical format remains 11 digits.
- `paymentLink` is nullable free text, **not** URL-only validation.
- `status` is exactly `0` or `1`; `isDefault` is a boolean for payment accounts. User-supplied IDs must not be trusted without group and eligibility checks.
- Update payloads must not mutate technical parameter group identity or bypass `isDefault` invariants.

### 15.2 State and business error examples

| Condition | Proposed status | Meaning |
|---|---|---|
| Invalid required fields / name or number duplicate | `422` | Field-level validation error. |
| No session | `401` | Authentication required. |
| Missing relevant permission | `403` | Forbidden. |
| Unknown/deleted normal resource | `404` | Record not found in public management scope. |
| Deactivate customer type currently assigned to customers | `409` | Business conflict. |
| Delete Settings value/account historically used | `409` | Business conflict; preserve references. |
| Assign wrong parameter group / inactive value for new operation | `422` | Invalid selection; controlled by the owning module. |
| Change item printing location after stage closed | `409` | Production workflow conflict. |
| Staff without printing-location override permission | `403` | Production authorization failure. |

The error envelope remains `{ "success": false, "message": "...", "errors": {...} }` with Arabic presentation messages and optional `errors.code` for meaningful business conflicts. These exact custom error codes are proposed, not approved API literals.

## 16. Permission catalog and security boundaries

The following **proposed technical permissions** are owned by Settings and seeded as **read-only permission definitions** through the existing Identity/Spatie catalog workflow:

| Permission | Meaning |
|---|---|
| `settings.view_parameter_values` | **Implemented read permission:** index/show parameter values through the shared API. |
| `settings.create_parameter_value` | **Implemented create permission:** add values to any existing parameter group through the shared API. |
| `settings.update_parameter_value` | **Implemented update permission:** edit values of any existing parameter group through the shared API. |
| `settings.delete_parameter_value` | **Implemented delete permission:** Soft Delete values from any group, subject to future historical-reference checks. |
| `settings.printing_locations.view` | Proposed: view printing locations in management APIs. |
| `settings.printing_locations.update` | Update printing location name/status. |
| `settings.printing_locations.delete` | Soft-delete eligible printing locations. |
| `settings.customer_types.view` | View customer types. |
| `settings.customer_types.update` | Update customer type name/description/status. |
| `settings.customer_types.delete` | Soft-delete eligible customer types. |
| `settings.bank_accounts.view` | **Implemented:** view bank accounts, including full account numbers. |
| `settings.bank_accounts.create` | Create bank accounts. |
| `settings.bank_accounts.update` | Update bank account fields/default/status. |
| `settings.bank_accounts.delete` | Soft-delete eligible bank accounts. |
| `settings.e_wallets.view` | **Implemented:** view e-wallets, including full phone numbers. |
| `settings.e_wallets.create` | Create e-wallets. |
| `settings.e_wallets.update` | Update wallet fields/default/status. |
| `settings.e_wallets.delete` | Soft-delete eligible e-wallets. |
| `settings.selects.view` | **Implemented:** read all four active Settings option sources (`printingLocations`, `customerTypes`, `bankAccounts`, `eWallets`) via the shared Selects API. |

- The generic parameter-value Index/Show/Create/Update/Delete APIs and the separate bank/wallet Index/Show/Create/Update/Delete APIs are implemented, with permission checks per operation. The common `settings.selects.view` permission now registers all four keys (`printingLocations`, `customerTypes`, `bankAccounts`, `eWallets`) with the numeric `isDefault` extension on bank/wallet options only. Historic-use deletion checks await owning-module integration.
- Neither `admin` role name nor frontend visibility alone grants permission to perform an operation.
- A separate **Production-owned** permission governs administrator-only changes to an in-progress item's printing location; Settings `.update` is insufficient for changing production assignments.
- Use parameterized/allowlisted filtering, safe database writes, CSRF-protected stateful API access and standard anti-leak handling. Payment destination account identifiers are not secret API keys but still must not be exposed to users without appropriate management authorization.

## 17. Seeders and initialization

- Seed technical `parameters.name` rows **idempotently** for `printing_locations` and `customer_types`.
- Seed illustrative, editable `parameter_values` (e.g., customer types `عميل عادي`, `شركة`, `جملة` and location `مطبعة رئيسية`). These are **examples approved to be chosen by implementation**, not verified real business places.
- Re-running seeders must not silently undo user edits, reactivate disabled rows, restore soft-deleted values or create duplicates.
- Bank accounts and e-wallets are **empty after fresh installation** until authorized users enter real details.
- Seed required Settings technical permissions idempotently with appropriate Arabic display labels. Do not silently overwrite customized role assignments in an existing installation.
- Setup must allow zero bank accounts, zero wallets and therefore no default payment account while Finance is not configured.

## 18. Open decisions requiring final owner approval

These questions are **not authorization to implement guessed behavior**. They remain open even though earlier drafts included recommendations.

| ID | Open decision | Suggested default for discussion |
|---|---|---|
| SET-OPEN-01 | After a never-used **bank account or e-wallet** is soft-deleted, can its unique account number / phone be reused? | **Still pending.** Implementation temporarily retains deleted numbers in unique indexes. The owner must confirm before the behavior is treated as final. Parameter-value names are permanently reserved under SET-031. |
| SET-OPEN-02 | When a disabled default has multiple eligible replacement accounts, which becomes default? | **Still pending.** Implementation currently selects oldest active (`id ASC`) as an explicitly provisional deterministic fallback, not an owner-approved priority. |
| SET-OPEN-03 | When the printing location's display name changes during active printing, should the final item snapshot reflect the name at printing **start** or **stage close**? | Capture/finalize at **stage close**, consistent with “final location”; pending approval. |
| SET-OPEN-04 | Confirm existing orders (even not-yet-designed orders) always retain their originally stored price if customer type changes. | **Yes**, based on the latest discussion; earlier statement mentioned automatic repricing, so this needs explicit confirmation for the Services/Orders references. |
| SET-OPEN-05 | Define historic usage checks precisely: references from current or soft-deleted customers/items, canceled receipts, reversed/deleted financial records, etc. | Preserve deletion restrictions whenever historic association exists; settle final scope with owning modules. |
| SET-OPEN-06 | If the first payment account is created **inactive**, should it have no default until an active account exists? | Yes; never make inactive records a selectable default. |
| SET-OPEN-07 | Payment destination Select label and exposure of full identifiers. | Implemented label is name + masked last four digits; Selects never expose full identifiers. Numeric `isDefault` (`0`/`1`) is included. Format can be revised in a future UI contract if needed. |
| SET-OPEN-08 | Final REST update method (`PUT` versus `PATCH`) and exact CRUD permission name notation to match current repository conventions. | Use the established current module conventions, not older sample documents. |
| SET-OPEN-09 | If a printing location is made inactive while assigned to an item in an **open printing stage**, whether to allow completion without reassignment. | Preserve the existing assignment to avoid blocking work; new selections must require active locations. This is ultimately a Production workflow decision. |

Until the remaining decisions are approved, related future-module tests and indexes are preliminary. The parameter-values unique index and soft-deleted-name reservation are approved under SET-031. The bank/wallet soft-delete uniqueness policy and replacement-default priority remain open. The payment-destination Selects currently use masked-last-four labels with numeric isDefault (0/1); the exact label presentation can be refined without changing the approved masking requirement.

## 19. Testing and acceptance criteria

Feature specifications derived from this reference must be able to demonstrate the following outcomes. These are **required behaviors and acceptance observations**, not implementation tasks.

### Lookup values

1. Only backend-seeded technical parameter groups exist; unauthorized callers cannot invent a new group through an API.
2. Printing locations accept only name and status; customer types accept name, optional description and status.
3. Duplicate names in **one group** fail after trim; the same name in **different groups** is allowed.
4. A `parameter_value` cannot change to another `parameter_id` through client input.
5. An inactive/wrong-group/deleted value is rejected for **new** customer-type or printing-location assignment.
6. Customer types already assigned to current customers cannot be disabled or soft-deleted.
7. Each item may select a different printing location when moving from design to printing.
8. An authorized production administrator may replace an item's current location only while printing remains open; no prior-assignment history is recorded.
9. Historical item display and type labels survive unrelated Settings renames according to the final snapshot rule.

### Bank accounts and wallets

10. Bank accounts require bank name and a unique string account number; no IBAN is accepted or stored as a field.
11. Wallets require name, Egyptian mobile number and account holder; optional plain-text payment link is accepted.
12. Two wallets may have the same name but not the same number; numbers starting with zero preserve that zero.
13. The first active account/wallet in an empty set becomes default; setting a new default removes the prior one.
14. Deactivating a default selects another eligible default (when one exists) without ever creating two defaults in the same set.
15. Two concurrent default-switch operations cannot persist more than one eligible default.
16. No bank account/wallet balance or financial movement is created by Settings mutations.
17. Finance receipts and advance payments preserve bank/wallet details as of **money posting time** despite later edits.

### CRUD, authorization, integration

18. All four management lists support search, status filtering and pagination; inactive rows remain visible in management views.
19. Soft deletion fails for ever-used records; soft-deleted rows disappear from normal selects and lists. Restoration is not exposed.
20. Shared `settings.view_parameter_values`, `settings.create_parameter_value`, `settings.update_parameter_value`, and `settings.delete_parameter_value` permissions independently authorize read, create, update, and soft delete through generic endpoints; other management permissions enforce their applicable reads/writes when implemented.
21. `settings.selects.view` alone enables the four active Settings option sources but does not grant full management view or write access.
22. The shared aggregator rejects unknown keys with `422` and unauthorized requested keys with `403`; no partial leakage.
23. GET success and all errors use the current project response conventions; validation errors keep public `camelCase` field names.
24. Re-running seeders remains idempotent and does not silently overwrite customized values/defaults.
25. Other module test suites continue to pass when Settings is introduced; cross-module writes remain under the owning module's Actions.

## 20. Authoritative related documents and implementation boundaries

- `specs/00-project/project-overview.md` — project and business scope.
- `specs/01-architecture/backend-architecture.md` — modular ownership, Sanctum strategy and cross-module interactions.
- `specs/02-standards/code-standards.md` — Actions, Controllers, Eloquent and tests.
- `specs/02-standards/api-standards.md` — naming, response format and list contracts.
- `specs/02-standards/database-standards.md` — **use the actual Tabaa-approved version**; do not accidentally apply unrelated multi-tenant commerce database standards.
- `specs/02-standards/error-handling-standards.md` — centralized error mapping.
- `specs/02-standards/permissions-standards.md` — use the actual Tabaa-approved permission catalog rules.
- `specs/02-standards/localization-standards.md` — use the actual Tabaa-approved localization rules; Arabic is the current API language.
- `specs/02-standards/ai-workflow-rules.md` — review and change control.
- `specs/03-modules/01-identity-auth.md` — authenticated staff access, Spatie guards and Select Aggregator integration.

**Non-negotiable boundaries:**

- One Laravel application and one MySQL database for one printing business; no tenant/platform architecture.
- Each mutation goes through an explicit Action; reads use ordinary Eloquent/query helpers where appropriate.
- Finance alone owns money postings; Production alone owns printing-stage transitions; Customers owns the current customer type and Orders/Services own saved prices.
- No generic client-editable parameter groups, no payment provider or financial ledger added to Settings, and no previous-printing-location history.
- No role-name bypass of server-side permission checks.
- Historical financial/order references cannot be destroyed by Settings deletion.
- Treat sample API routes, some permission names, and open decisions as proposals pending verification/approval. Documented behavior is not proof of implemented behavior.

**Spec Kit outputs** (`spec.md`, `plan.md`, `tasks.md`) are produced separately when implementation begins. This file remains the consolidated **Settings Module Reference** from which those artifacts are derived.