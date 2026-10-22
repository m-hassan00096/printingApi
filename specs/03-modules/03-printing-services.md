# Tabaa — 03 Printing Services & Pricing

> **Document type:** Module Reference — functional requirements and proposed technical contracts only  
> **Location:** specs/03-modules/03-printing-services.md  
> **Branch:** feature/printing-services  
> **Status:** Requirements draft — owner-confirmed rules recorded; remaining decisions explicitly isolated in §19 (five latest owner answers incorporated)  
> **Scope:** Internal printing-service catalog, customer-type/quantity pricing, and pricing contracts for future order items  
> **Stack:** Laravel 13 · React SPA · Sanctum stateful authentication · Spatie Laravel Permission · MySQL · nwidart/laravel-modules  
> **Language:** Arabic API locale currently; technical identifiers and API fields in English  
> **Source of customer types:** Settings technical parameter group customer_types, through parameter_values

This reference records the business rules agreed with the project owner, distinguishes technical proposals from approved behavior, and provides examples and acceptance tests. It is **not** a claim that Printing Services code, routes, or migrations already exist. Implementation plans and tasks are separate artifacts.

## 1. Purpose and ownership

Printing Services maintains the services the printing business can sell, their optional base unit prices, and customer-type/quantity-specific unit-price tiers. It supplies a deterministic **suggested price** when an order item is entered; an authorized order-entry staff member may replace the suggested **line total**.

### In scope

- Manage named printing services with an active/inactive state and optional base unit price.
- Define optional pricing tiers against **all customer types** or one existing Settings customer type, with nullable inclusive quantity boundaries.
- Enforce tier-price positivity, quantity-range validity, same-bucket overlap restrictions, and one catch-all tier per bucket.
- Resolve the proposed unit price using an ordered customer-type and quantity fallback.
- Support services with no configured price (manual order-line total required).
- Allow changes to service prices and tiers without repricing historical order items.
- Expose authorized management operations, approved selects and a proposed pricing-preview contract.
- Restrict service deletion to never-used services and use Soft Delete for deleted services/tiers.

### Outside v1

- A selectable pricing-system mode (unit/package/quantity). **There is no pricing-mode field.**
- Fixed-price packages, bundles, price per bundle, progressive marginal tier billing, tax, VAT, coupon, discount, promotion or supplier/production cost engines.
- A separate customer-tier-category table or a new customer-type lookup list.
- Service code, description, image, category, printing machine, material, dimensions or production-location fields.
- Orders, customers, stage transitions, printing assignment, cash collection, payment, stock and purchasing ownership.
- Repricing old orders in response to service/tier/customer-type changes.
- Real-time public catalogs, customer self-service, or a multi-tenant pricing engine.
- Service/tier restoration UI unless specified in a future version.

## 2. Actors and module boundaries

| Actor or module | Responsibility |
|---|---|
| Staff with catalog-view permission | View existing service and tier management data. |
| Staff with catalog-write permission | Create/edit/deactivate and conditionally soft-delete services; manage price tiers. |
| Staff using an order-entry workflow | Receive a suggested line total, then optionally override it according to Orders permissions. |
| Settings | Own the technical customer_types parameter group and its parameter_values records. |
| Customers | Assign a customer type to each customer and enforce association rules from Settings. |
| Printing Services | Own service definitions, tier rules and deterministic price resolution. |
| Orders | Own line quantity, manually agreed final line total, persisted price/service/type snapshots and repricing triggers. |
| Production | Own the progress of each order item through design/printing and its printing-location choice. |

All protected endpoints must use the application's existing Sanctum and Spatie permission model. A role named admin is not an implicit authorization bypass. Service/tier permissions are separate from Settings' customer-type management permission.

## 3. Owner-approved business decisions

The following are decisions made in the Printing Services discussion; they must not be silently replaced by UI assumptions.

| ID | Approved requirement |
|---|---|
| PS-001 | A service has only a required name, optional base unit price, active/inactive state, and optional price tiers. |
| PS-002 | There is **no** selectable pricing mode, package mode, service description, or service-category field in v1. |
| PS-003 | Base unit price is nullable; null means **no configured base price**. Numeric zero means a legitimately free base unit price. |
| PS-004 | Each price tier has a required **unit price strictly greater than zero**, to two decimal places; tier prices cannot be zero or null. |
| PS-005 | A tier targets **all customer types** or exactly one existing customer type from Settings' customer_types parameter_values. Do not create another type table. |
| PS-006 | A tier may have optional quantity-from and quantity-to fields; either, both or neither may be filled. |
| PS-007 | A missing lower bound means all positive quantities down to 1. A missing upper bound means there is no upper limit. Both missing means a catch-all tier. |
| PS-008 | Quantity bounds are **inclusive**. Order quantities must be positive whole numbers, not fractions. |
| PS-009 | Tiers are priced **per unit**; suggested line total equals the matched unit price multiplied by the order quantity. |
| PS-010 | Customer-type-specific pricing wins over generic pricing in the approved precedence order in §7. |
| PS-011 | For the **same service and customer-type bucket**, different quantity-constrained tiers must not overlap. |
| PS-012 | One entirely open catch-all tier per service/type bucket may coexist with constrained tiers and acts as their fallback. |
| PS-013 | Overlap between different customer-type buckets (including the all-types bucket) is allowed and resolved by precedence, not rejected. |
| PS-014 | Staff may manually override the **suggested line total** in an order. |
| PS-015 | Changing an order-item quantity automatically recalculates its proposed total and **discards any earlier manual total override**. |
| PS-016 | A service can be used even when it has no applicable tier and its base price is null; the staff member supplies a manual total. |
| PS-017 | Service names must be unique among non-deleted services. |
| PS-018 | A service name becomes available again after that service is soft-deleted. |
| PS-019 | A service that has ever been used in an order cannot be deleted; it may instead be deactivated. |
| PS-020 | A never-used service may be soft-deleted. |
| PS-021 | Tiers may be added, modified or soft-deleted, including after the service was used in orders. |
| PS-022 | Historical order totals and agreed prices are immutable under later catalog/tier edits. |
| PS-023 | Service/tier money amounts have two decimal places (e.g. 5.75). |
| PS-024 | Previously approved Settings behavior applies: the customer type is chosen on the customer, not independently on each order item; changing it affects future orders, not old prices. |
| PS-025 | Any **persisted order item** referencing the service prevents deleting it, including items belonging to draft or cancelled orders and soft-deleted order/item records. Unsaved frontend forms are not persisted order items. |
| PS-026 | Soft-deleting a never-used service must also **soft-delete all its live price tiers atomically**, in the same transaction. |
| PS-027 | The pricing preview API accepts **customerTypeId**, not customerId; Orders must derive the actual customer's type from server-trusted customer data. |
| PS-028 | Price-tier management belongs to **service/product management permissions**; no separate tier create/update/delete permissions. |
| PS-029 | Uniqueness of live service names is **case-sensitive**. For example, Business Cards and business cards are distinct, while two live copies of Business Cards are not permitted. |
| PS-030 | All service prices and amounts are denominated in **Egyptian pounds (EGP)** only; no per-service currency choice or currency-conversion logic in v1. |
| PS-031 | The business does **not impose arbitrary maximum quantity, unit-price or line-total limits**; staff may enter values up to the actual validated numeric/storage limits. All quantities remain positive integers, prices remain two-decimal money amounts, and arithmetic/storage overflow must be rejected explicitly. |
| PS-032 | Normalize service names on create and update: trim outer whitespace and **collapse runs of two or more whitespace characters between words to a single ordinary space**; preserve letter case. The normalized name is stored and used for case-sensitive live uniqueness checks. |
| PS-033 | Service status is an integer-backed enum: `PrintingServiceStatus` with `INACTIVE=0` and `ACTIVE=1`. API requests and resources use numeric 0/1 (like Settings). |
| PS-034 | Service data and price-tier mutations are saved via **one service update API** and one atomic transaction; do not provide separate tier CRUD endpoints. |
| PS-035 | For tier entries submitted in the service update, an integer-backed `PriceTierActionStatus` enum distinguishes `UNCHANGED=0`, `CREATE=1`, `UPDATE=2`, `DELETE=3`; `priceTierId` is **required for UPDATE and DELETE** and prohibited on CREATE. |
| PS-036 | The service management list supports **only name search and service status filtering** (besides pagination and standard sorting). |

### Clarifications from these decisions

- PS-003 and PS-004 are **intentionally different**: free base price is valid; a zero-price tier is not.
- PS-011 and PS-012 are **not contradictory**: the sole unconstrained catch-all tier is a deliberate fallback exception to the overlap restriction.
- A customer-specific catch-all may be selected **before** a generic quantity-specific tier. This is deliberate customer-type-first priority.
- A zero suggested total is **not** the same as a missing suggested total. Never use truthiness checks or silently convert null into zero.
- All amounts are **EGP** with two fractional digits. The application must not invent a business maximum; numeric database capacity and safe arithmetic still bound representable values.
- Service names are normalized before persistence **and** duplicate checking: leading/trailing whitespace is removed and multiple whitespace characters between words become one normal space, without changing letter case.

## 4. Printing service definition

| Public/API field | Meaning | Required on create | Business validation |
|---|---|---|---|
| name | Display name for the service | Yes | Required, trim outer whitespace and collapse repeated internal whitespace to one space; case-sensitive unique among live services. |
| basePrice | Optional base **unit** price | No | Null or nonnegative decimal EGP amount with max two fractional digits; no arbitrary business ceiling, but subject to safe DB decimal capacity. |
| status | Service availability | Yes | Integer-backed `PrintingServiceStatus` enum: `INACTIVE=0`, `ACTIVE=1`, numeric values in API and enum cast on model. |
| priceTiers | Child tier definitions | No | Zero or more entries; validated per §5 and §6. |

- A service may exist without any tiers.
- A service may exist with basePrice=null and no tiers, and still be available for manual-price order entry.
- Inactive services remain visible in authorized management lists but must not be selectable for **new** order lines.
- Normalize service names before validation and saving: trim leading/trailing whitespace and collapse every run of repeated whitespace between words to exactly one ordinary ASCII space. Reject names that become blank after normalization.
- Compare normalized names **case-sensitively**: Business Cards and business cards may coexist, but `  Business    Cards  ` normalizes to `Business Cards` and duplicates the live `Business Cards` service.
- Apply the **same normalization on create and update**, and ensure database live-only unique enforcement agrees with validation. Unicode canonical-equivalence handling (NFC/NFD) remains a separate implementation detail (§19).
- Existing saved order lines continue to display the name captured when they were created, even after catalog rename or service deactivation.
- Do not add printing locations, customer types, or account details directly to service records.

## 5. Price tiers and customer-type reference

| Public/API field | Meaning | Required | Business validation |
|---|---|---|---|
| customerTypeId | Settings customer type, or null for all types | No | Null or a valid parameter_values ID belonging specifically to customer_types. |
| minQuantity | Lower inclusive quantity bound | No | Null or positive integer >= 1. |
| maxQuantity | Upper inclusive quantity bound | No | Null or positive integer >= 1. |
| price | Unit price to apply when this tier wins | Yes | Positive EGP decimal **> 0**, max two decimal places; no artificial business maximum, with storage-overflow checks. |

These tiers belong to a service; they are not standalone products and do not have a separate pricing-mode selector. There is **no per-tier active/inactive switch** in the approved input; Soft Delete removes a tier from future matches.

The update-only `actionStatus` field is a **command enum**, never a persisted tier status. `priceTierId` identifies the existing tier by its real database `id` when a command updates or deletes it. Creating a service may include initial tier definitions without update commands.

### Settings integration contract

- customerTypeId points to the **existing** Settings parameter_values table; it is not an arbitrary user-supplied type string.
- For new tier creation or reassignment, verify the referenced value belongs to parameters.name=customer_types, is active and is not soft-deleted.
- Generic/all customer types is represented by customerTypeId=null; it does **not** require a fabricated "All" Settings row.
- Updating price or bounds on a previously valid tier must not silently move it to another type.
- The relationship does not confer Settings CRUD privileges. Only eligible options from the existing shared customerTypes select can be chosen on the UI.
- Deleting a Settings customer type referenced by tier pricing needs cross-module reference protection before this module is integrated. Consult §19; Settings' current general Soft Delete API must not be assumed to know this future relation.

## 6. Quantity ranges, overlap and catch-all logic

### 6.1 Meaning of nullable bounds

| minQuantity | maxQuantity | Applies to order quantities |
|---:|---:|---|
| null | null | Any positive quantity (special catch-all fallback) |
| 100 | null | 100 and greater |
| null | 99 | 1 through 99 |
| 100 | 500 | 100 through 500, inclusive |
| 100 | 100 | Exactly 100 |

Both explicitly provided bounds must satisfy minQuantity <= maxQuantity. Zero, negatives and non-integers are invalid as quantity bounds.

### 6.2 Uniqueness and overlap

Define a **bucket** as (service ID, customer type ID), where customer type ID is nullable for the generic bucket.

- A bucket can have **at most one** non-deleted catch-all tier (both bounds null).
- A bucket may have any number of constrained tiers, provided **no two constrained tiers have a shared integer quantity**.
- Represent null min as 1 and null max as infinity **for overlap comparison only**. Do not store infinity or substitute 0 in the database.
- Two constrained intervals overlap if their inclusive ranges share any integer quantity.
- The catch-all tier can coexist with all constrained tiers in that bucket; it is checked only when none of its constrained tiers matches.
- Different buckets can overlap freely. A specific customer type can intentionally override a generic range.
- Restoring deleted tiers is out of scope; soft-deleted tiers do not take part in current overlap/catch-all checks.

**Illustrations:**

| Existing range | New range | Same bucket? | Allowed? | Why |
|---|---|---|---|---|
| 1–200 | 100–500 | Yes | No | 100–200 overlaps |
| 1–200 | 201–500 | Yes | Yes | Adjacent and disjoint |
| 1–200 | 200–300 | Yes | No | Both include 200 |
| 100–null | null–99 | Yes | Yes | No shared quantity |
| null–null | 100–500 | Yes | Yes | Catch-all exception |
| null–null | null–null | Yes | No | Duplicate catch-all |
| 1–200 | 100–500 | No | Yes | Different customer-type buckets |

Validation occurs on create **and** update and should exclude the tier being updated. Enforce concurrency safety: two simultaneous writes must not each pass a stale overlap check and commit conflicting ranges. The implementation must choose locking/serialization at the service or service/type-bucket level and recheck inside the transaction.

## 7. Approved suggested-price precedence

Given a valid active printing service, a customer with a current eligible Settings customer type, and a positive integer quantity, evaluate exactly this order:

| Priority | Matching tier |
|---:|---|
| 1 | **Specific customer type** + matching constrained quantity range |
| 2 | **Specific customer type** + catch-all (both bounds null) |
| 3 | **All customer types** + matching constrained quantity range |
| 4 | **All customer types** + catch-all |
| 5 | Service's basePrice, including numeric zero if that is its value |

If nothing matches and basePrice=null, **there is no suggested price**. The caller must request a manually entered order-line total. An all-types tier is a pricing fallback, not a separate selectable customer type.

### Deterministic resolution pseudocode

~~~text
resolve(service, currentCustomerType, positiveIntegerQuantity):
    validate service is eligible for a NEW order line
    validate customer type is eligible for that customer
    find non-deleted type-specific constrained tier covering quantity
    else find non-deleted type-specific catch-all tier
    else find non-deleted generic constrained tier covering quantity
    else find non-deleted generic catch-all tier
    else if service.basePrice is not NULL use service.basePrice
    else return NO_SUGGESTION (manual total required)
    return proposedUnitPrice, proposedTotal = unitPrice * quantity, source
~~~

Same-bucket constrained ranges are nonoverlapping, so a valid catalog can have at most one match at each constrained tier level. Do **not** use earliest-created/latest-created tier, lowest price, or narrowest range to break a conflict: conflicting overlapping tiers are invalid data.

### Pricing examples

Assume service **Business Cards** has basePrice 10.00, a generic quantity tier 100–500 at 6.00, a Wholesale customer catch-all at 8.00, and a Wholesale constrained tier 100–300 at 5.00.

| Customer type | Quantity | Chosen rule | Unit price | Proposed total |
|---|---:|---|---:|---:|
| Wholesale | 200 | Wholesale 100–300 | 5.00 | 1,000.00 |
| Wholesale | 400 | Wholesale catch-all | 8.00 | 3,200.00 |
| Retail | 200 | Generic 100–500 | 6.00 | 1,200.00 |
| Retail | 700 | Service basePrice | 10.00 | 7,000.00 |

A customer-specific catch-all at 8.00 wins for 400 Wholesale pieces **even though** the generic 100–500 tier is 6.00, because customer-type priority is higher.

If the basePrice is zero and there is no matching tier, proposed total is precisely 0.00 (free). If the basePrice is null and no tier matches, proposed total is null/not available, never an implied free price.

## 8. Monetary calculation and manual overrides

- Quantity is a positive integer.
- All monetary amounts are **EGP** with two fractional digits. Do not calculate monetary totals using binary floating-point math.
- **No business-defined numeric upper bound** on order quantity, unit price or line total is imposed; use positive integer and safe decimal validation. Reject values outside the actual database field range or arithmetic output capacity with a controlled 422 rather than truncating, wrapping or relying on a SQL exception.
- A matched tier **unit price** is multiplied by the entire quantity. These are not stepped or marginal pricing bands.
- proposedTotal = quantity × proposedUnitPrice, rounded/represented to two decimal places using the project's agreed money handling.
- Order staff may override the proposed **total** manually, without modifying the service basePrice or tier records.
- The agreed **final total** is authoritative on the order line. A displayed derived unit price from an override is for presentation and may not multiply back to the final total exactly after two-decimal rounding.
- If the order quantity changes, recompute the suggestion using current applicable rules and clear the old manual override; do not silently retain an override calculated for the prior quantity.
- If the recomputation has no applicable price, mark the amount as requiring manual entry rather than silently using 0.00 or the old override.
- Order-time customer-type changes or service changes should trigger fresh pricing instead of inheriting an unrelated previous service/type override; final treatment is to be confirmed in §19.
- Staff can always submit a manually agreed total in the Orders workflow when no suggestion is available. The exact override permission and manual-total minimum are owned by Orders and listed for final confirmation in §19.
- A **price preview** is not a committed order amount. Orders must recompute/validate an authoritative suggestion when saving, then persist its own final total and snapshots.

### Example: quantity change after manual override

1. Wholesale customer orders 200 pieces; suggested 200 × 5.00 = 1,000.00.
2. Staff enters manual final total 900.00; the order editor shows override state.
3. Staff changes quantity to 250.
4. The old 900.00 override is removed; the new suggestion is 250 × 5.00 = 1,250.00.
5. Staff may now explicitly enter a new manual total, if permitted.

## 9. Create/update/delete lifecycle

### 9.1 Create service and initial tiers

- \`POST /api/v1/printing-services\` creates a service with optional \`priceTiers\` in **one transaction**.
- On **create**, every tier is a new tier definition. No \`actionStatus\` or \`priceTierId\` is required.
- All new tiers must pass customer-type checks, positive-price validation, range validity, the one-catch-all rule and nonoverlap checks.
- \`status\` uses the integer-backed \`PrintingServiceStatus\` enum; the API accepts numeric 0/1.
- Base price and initial tiers are optional; if any tier fails validation, the full service creation fails.

### 9.2 One atomic update for service data and tier operations

**\`PUT/PATCH /api/v1/printing-services/{service}\`** updates both service metadata and submitted \`priceTiers\` action commands. No standalone tier update/create/delete route is exposed.

Proposed PHP enum definitions (to be created in \`Modules\PrintingServices\Enums\` at implementation time, **not already implemented**):

~~~php
enum PrintingServiceStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;
}

enum PriceTierActionStatus: int
{
    case UNCHANGED = 0;
    case CREATE = 1;
    case UPDATE = 2;
    case DELETE = 3;
}
~~~

The service status is persisted and cast to \`PrintingServiceStatus\`. Tier \`actionStatus\` is an **input-only enum** and must not be stored in the price-tier table.

| actionStatus | Enum case | priceTierId | Tier fields | Effect |
|---:|---|---|---|---|
| 0 | UNCHANGED | Optional | No fields needed | No update/delete of this tier |
| 1 | CREATE | **Forbidden** | \`price\` required > 0; type/bounds optional | Create new tier |
| 2 | UPDATE | **Required** | Current tier's editable values, with \`price\` > 0 | Update this tier |
| 3 | DELETE | **Required** | No other tier data needed | Soft-delete this tier |

**Proposed field-level validation convention:** For an UPDATE action (=2), send \`customerTypeId\`, \`minQuantity\`, \`maxQuantity\` and \`price\` as the tier's complete edited state; the first three keys are nullable but explicitly present to allow clearing values. On CREATE action (=1), omitted type/bounds default to null and \`price\` is required. For UNCHANGED (=0), \`priceTierId\` may be sent to correlate the UI row but does not trigger a write. For DELETE (=3), only \`actionStatus\` and \`priceTierId\` are needed. This full-tier UPDATE field convention is a technical proposal for validation; the owner-approved part is the action-status enum, one API and mandatory priceTierId for actions 2/3.

- \`actionStatus\` must be an integer 0/1/2/3 validated with \`Rule::enum(PriceTierActionStatus::class)\`. String enum names and unrecognized integers are rejected. \`status\` must be integer 0/1 via \`Rule::enum(PrintingServiceStatus::class)\`.
- UPDATE and DELETE must reference an existing **non-deleted** tier belonging to the \`{service}\` in the URL. Reject unknown, soft-deleted or other-service IDs. CREATE must not send an ID.
- Reject two actionable commands targeting the **same existing tier** in one request. An invalid command fails the whole request.
- **Omitted existing tiers remain unchanged.** Missing \`priceTiers\` and \`priceTiers: []\` mean no tier changes, **not** delete-all. A tier is deleted **only** with \`actionStatus=3\`. \`actionStatus=0\` means no change even if other tier fields are supplied.
- The service metadata update may happen together with the commands, or alone; fields omitted from metadata remain unchanged.
- Start a transaction and lock the **parent service row** (plus related records as needed), then load current tiers, interpret the full batch, validate the **final** post-command active tier set, and persist all changes atomically. This permits legitimate range swaps without validating inconsistent intermediate ranges.
- Any error in any command or service field **rolls back all service and tier changes**. Historical Orders prices never change.

### 9.3 Service Soft Delete

- A service **never referenced by any persisted order item** may be soft-deleted.
- Any persisted order item reference blocks deletion, including draft/cancelled/completed or soft-deleted order histories. Deactivation is allowed. An unsaved frontend form is not a persisted order item.
- Orders must implement the historic-use check at integration time; a missing Orders table today does not mean the protection has been implemented.
- An eligible never-used service and **all its live child tiers** are soft-deleted in the same transaction.
- Soft-deleted service names become reusable; deleted services/tiers are not selectable for new orders.
- No hard-deletion of order items, tier snapshots or service rows; no restore endpoint in v1.

## 10. Historical Orders contract (future integration)

Orders, not Printing Services, must persist a sufficient snapshot **when an order item is saved/confirmed** for an old order to retain its original meaning after:

- A printing service is renamed, deactivated or repriced.
- A pricing tier is edited or soft-deleted.
- A customer's Settings customer type changes or is renamed.
- A staff member overrides the suggested total.

At minimum the Orders reference must define snapshots for service display name, customer-type display name, quantity, matched pricing source (tier/base/manual), proposed unit price/total when present, and the finally agreed line total. The exact order table/column schema belongs to the Orders module; this document does **not** create an order_items table.

Important distinctions:

- A tier FK, when kept for analytics, is **not a substitute** for historical price snapshots; the referenced tier can change or become soft-deleted.
- The final agreed total is **not** recalculated when master pricing changes.
- New orders use the customer's current type and current eligible service pricing.
- Orders decide when an item becomes an immutable commercial snapshot versus an editable draft. That timing is still an open cross-module decision.

## 11. Proposed relational schema (for design review)

**These are proposed table/column contracts, not implemented migrations.** MySQL is the project target. Keep data relational; do not store variable tiers in a JSON column. **No arbitrary business maximum** applies to numeric amounts/quantities, but all concrete numeric column types have finite storage capacity. Determine and verify a sufficiently large DECIMAL(P,2) precision, integer range and total-price multiplication limit before migrations; return validation errors rather than overflowing.

### 11.1 printing_services

| Column | Proposed type | Constraint / meaning |
|---|---|---|
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(150) | Required service name |
| base_price | DECIMAL(P,2) NULL (precision P to be selected) | Nullable, may be 0.00, EGP only; choose sufficiently wide precision from real DB arithmetic requirements, not an invented business maximum |
| status | TINYINT | Active=1, Inactive=0 |
| created_by / updated_by | Nullable FKs to users | Reuse existing audit-column convention, if applicable |
| created_at / updated_at | TIMESTAMP | Standard timestamps |
| deleted_at | TIMESTAMP NULL | Soft Delete |

**Critical MySQL uniqueness requirement:** a simple UNIQUE(name) incorrectly prevents name reuse after Soft Delete. A simple UNIQUE(name, deleted_at) also **does not** enforce uniqueness among live rows in MySQL because multiple NULL values are allowed. Implement a reviewed **live-only, case-sensitive** unique key, for example a generated column containing the trimmed name only while deleted_at IS NULL with a unique index using a case-sensitive/binary-compatible collation supported by the target MySQL version. Different capitalization must coexist, identical names must not, and deleted names must be reusable. Normalize outer and repeated internal whitespace **before writing the stored name and the generated live-only index value**, using the same normalization on updates. Verify case-sensitive collation and any Unicode canonical-equivalence treatment before migration. Database constraint and application validation must agree and be tested under concurrent creation.

### 11.2 printing_service_price_tiers (working name)

| Column | Proposed type | Constraint / meaning |
|---|---|---|
| id | BIGINT UNSIGNED | Primary key |
| printing_service_id | BIGINT UNSIGNED FK | Owning service |
| customer_type_id | BIGINT UNSIGNED FK NULL | Settings parameter_values.id; null = all types |
| min_quantity | BIGINT UNSIGNED NULL | Inclusive lower quantity |
| max_quantity | BIGINT UNSIGNED NULL | Inclusive upper quantity |
| price | DECIMAL(P,2) (precision P to be selected) | Strictly positive EGP unit price, with two decimal digits |
| created_by / updated_by | Nullable FKs to users | Audit metadata, if applicable |
| created_at / updated_at | TIMESTAMP | Standard timestamps |
| deleted_at | TIMESTAMP NULL | Soft Delete |

Proposed DB indexes include (printing_service_id, customer_type_id, deleted_at), lookup indexes for bounds as appropriate, and a FK to customer_type_id with **restrictive** deletion behavior rather than automatic cascade. Validation must additionally confirm membership in Settings' customer_types group: an FK only proves that a parameter_values ID exists.

**Overlap enforcement is not expressible as a normal MySQL unique index on quantity bounds.** Implement a database-backed locking/serialization strategy and recheck overlaps inside the write transaction. The single live catch-all per bucket also needs race-safe enforcement.

### 11.3 Relationship outline

~~~text
Settings parameters(name='customer_types')
    1 -- N parameter_values(id, name, status, deleted_at)
                      |
                      | referenced by tier customer_type_id (nullable)
                      v
Printing Services printing_services
    1 -- N printing_service_price_tiers
                      |
                      | price lookup/suggested total
                      v
Orders order items (owned by Orders, NOT created here)
    saved quantity, service/type labels, suggested and final price snapshots
~~~

Customer-type ownership is in Settings/Customers; order item ownership and snapshots are in Orders. Do not cascade-delete type records or order items through a service/tier relation.

## 12. Proposed API contract (not yet implemented)

Use the application's current conventions: camelCase input, unified \`ApiResponse\`, FormRequests, explicit mutation Actions, QueryBuilder allowlisted filters and standard pagination. The following routes are **proposed**, not currently implemented.

| Method | Proposed route | Behavior |
|---|---|---|
| GET | /api/v1/printing-services | Paginated list with **name-only search and status** filters |
| GET | /api/v1/printing-services/{service} | Service detail and its active/non-deleted tiers |
| POST | /api/v1/printing-services | Create service with optional initial tiers |
| PUT/PATCH | /api/v1/printing-services/{service} | **Single atomic update** of service data + \`priceTiers[].actionStatus\` commands |
| DELETE | /api/v1/printing-services/{service} | Soft-delete never-used service and all its tiers |
| POST | /api/v1/printing-services/price-preview | Calculate proposed unit/total price for \`serviceId\`, \`customerTypeId\`, \`quantity\` |

**Do not expose individual tier POST/PUT/PATCH/DELETE endpoints.** Create/update/delete tier actions within an existing service are authorized by \`printing_services.update\`; initial tiers in service creation use \`printing_services.create\`.

### Management list filtering

Only these **two** business filters:

| Parameter | Validation | Scope |
|---|---|---|
| \`filter[search]\` | Optional search text | Search **printing_services.name only**, not price tiers, type names, IDs, amounts or other fields |
| \`filter[status]\` | Optional \`PrintingServiceStatus\` enum 0/1 | Active/inactive services |

Pagination (\`page\`, \`perPage\`) and permitted sort fields (default \`-id\`, following current project conventions) remain available and are **not** other business filters. Reject unsupported filters. Soft-deleted services are excluded from the management list.

### Price preview

The preview input uses **customerTypeId**, not customerId. Validate membership in Settings' customer_types group and eligibility. At actual order save time, Orders must derive the real customer's type from trusted customer data, not trust an arbitrary frontend preview type.

## 13. Request/response examples (illustrative only)

### 13.1 Create a service with initial tiers

~~~json
{
  "name": "Business Cards",
  "basePrice": "10.00",
  "status": 1,
  "priceTiers": [
    {
      "customerTypeId": null,
      "minQuantity": 100,
      "maxQuantity": 500,
      "price": "7.50"
    },
    {
      "customerTypeId": 7,
      "minQuantity": null,
      "maxQuantity": null,
      "price": "6.00"
    }
  ]
}
~~~

On create, \`status: 1\` is the numeric API value of \`PrintingServiceStatus::ACTIVE\`. All tier entries are new; no \`actionStatus\` or \`priceTierId\` field is needed. Customer-type ID 7 is illustrative; a real active Settings value must be supplied.

### 13.2 Single atomic service update with all four tier action statuses

**PUT /api/v1/printing-services/12**

~~~json
{
  "name": "Business Cards",
  "basePrice": "12.00",
  "status": 1,
  "priceTiers": [
    {
      "actionStatus": 0,
      "priceTierId": 20
    },
    {
      "actionStatus": 1,
      "customerTypeId": 7,
      "minQuantity": 501,
      "maxQuantity": null,
      "price": "5.75"
    },
    {
      "actionStatus": 2,
      "priceTierId": 21,
      "customerTypeId": null,
      "minQuantity": 100,
      "maxQuantity": 500,
      "price": "7.00"
    },
    {
      "actionStatus": 3,
      "priceTierId": 22
    }
  ]
}
~~~

Interpret this as: tier 20 **unchanged**, create a new tier, update tier 21, and **soft-delete** tier 22. All listed existing tier IDs must belong to service 12. Other stored tiers **not mentioned** are retained. Validate the completed pricing-tier state and commit/rollback **as a single transaction**.

### 13.3 Update only the service's metadata

~~~json
{
  "name": "Premium Business Cards",
  "status": 0
}
~~~

\`status: 0\` is \`PrintingServiceStatus::INACTIVE\`. The omitted \`basePrice\` and omitted \`priceTiers\` stay unchanged. \`priceTiers: []\` also means no tier mutations.

### 13.4 Price preview — successful

~~~json
{
  "serviceId": 12,
  "customerTypeId": 7,
  "quantity": 200
}
~~~

Illustrative response payload **inside** the application's standard ApiResponse wrapper:

~~~json
{
  "serviceId": 12,
  "quantity": 200,
  "source": "customerTypeQuantityTier",
  "suggestedUnitPrice": "5.75",
  "suggestedTotal": "1150.00",
  "requiresManualTotal": false
}
~~~

### 13.5 Price preview — no configured price

~~~json
{
  "serviceId": 12,
  "quantity": 200,
  "source": null,
  "suggestedUnitPrice": null,
  "suggestedTotal": null,
  "requiresManualTotal": true
}
~~~

In contrast, a genuinely free base price returns source=basePrice, suggestedUnitPrice="0.00", suggestedTotal="0.00", requiresManualTotal=false. Examples are proposals, not statements that routes or code have been implemented.

## 14. Shared Selects integration

Use the existing cross-module Select Registry / GET /api/v1/selects; do not create a new generic selects system.

**Proposed new selector:** printingServices (active, non-soft-deleted services, returning value=id and label=name). Match the existing permission-aware registry behavior. Do not expose price rules or whole management records through an unprivileged dropdown.

For choosing a tier's customer type, reuse **Settings' existing customerTypes selector**. A null customerTypeId is presented in the frontend as an "All customer types" choice but is not persisted as a new Settings value.

Whether an order-entry-specific select should include service base price or current calculated price depends on quantity and customer and therefore must not be hard-coded into a static global select. The preview/service pricing contract owns that calculation using customerTypeId; actual Orders must derive the customer's type from trusted customer data.

## 15. Validation, errors and authorization semantics

- Normalize service names by trimming outer whitespace and collapsing internal consecutive whitespace into one space on both create/update; reject names blank after normalization or duplicating an existing **case-sensitive normalized** live name. Reject service status values outside the 0/1 `PrintingServiceStatus` enum, invalid basePrice and unsupported pricing-mode/package fields.
- Reject price tier price null, 0, negative, non-numeric values or more than two decimal places. All money amounts are EGP; do not add a currency selector. Validate database representability and safe multiplication rather than inventing a business maximum.
- Reject negative, fractional and zero quantities/bounds; reject minQuantity > maxQuantity. Do not impose an arbitrary maximum quantity; reject values exceeding the chosen integer representation.
- Reject overlapping constrained ranges **within the same service and customer-type bucket**.
- Reject a second live catch-all in the same bucket.
- Reject customerTypeId belonging to printing_locations, another group, an inactive/deleted type or an unknown value.
- Reject tier update/delete when tier does not belong to its parent service.
- Reject new order use of inactive/soft-deleted services; retain historical read access via Orders.
- Reject service delete if **any persisted order item** references it, including draft, cancelled or soft-deleted histories; transactionally check before marking deleted.
- Soft-delete all live child price tiers in the **same transaction** as a permitted never-used service deletion.
- Uniqueness validation must be case-sensitive for live service names and allow exact-name reuse after Soft Delete.
- Reject unauthenticated operations (401); enforce specific Spatie permissions (403); preserve 404 for nonexistent or soft-deleted items according to existing route-binding rules.
- Return 422 for ordinary validation errors and a consistent business conflict (project error contract, likely 409) for overlapping concurrent pricing-state conflicts or prohibited deletion, as decided in implementation.
- Surface errors on public camelCase fields, not internal DB column names; do not expose SQL or internal rules.
- Invalid client-provided customerTypeId must not be used to bypass current customer's pricing classification during order creation.
- For each supplied update tier, validate numeric `actionStatus` against `PriceTierActionStatus` (0/1/2/3), require `priceTierId` for 2/3, prohibit it for 1, and allow it optionally for 0.
- Reject wrong-service, nonexistent or soft-deleted `priceTierId` and duplicate actionable IDs in one batch. Never create a new tier through an UPDATE operation.
- Interpret omitted tiers as unchanged and `actionStatus=0` as a no-op, with **only** `actionStatus=3` causing tier soft deletion.
- Validate the proposed complete final tier set to catch overlaps/catch-all conflicts, and roll back **service metadata and all tier changes** on any error.
- Management filter allowlist: `filter[search]` searches the **name only**; `filter[status]` validates the service enum; reject any additional business filters.

## 16. Proposed permissions

The owner approved using service/product management permissions for all price-tier operations. **Do not introduce separate tier create/update/delete permissions or tier CRUD routes**; `printing_services.update` governs the single atomic tier-action batch.

| Permission | Purpose |
|---|---|
| printing_services.view | List/show services and their tiers |
| printing_services.create | Create a service with any initial price tiers |
| printing_services.update | Edit a service, including **adding, updating and soft-deleting tiers** on an existing service |
| printing_services.delete | Soft-delete an eligible never-used service and all its live tiers |
| printing_services.selects.view | Read selectable active services (permission name proposed) |
| printing_services.price_preview.view | Preview prices, if a separate endpoint is retained (permission name proposed) |

The management permissions above reflect the agreed boundaries. Selector and preview permissions/endpoint placement still need technical verification. Price preview never grants a pricing write. Orders owns permission for overriding agreed order totals. Reuse the existing Identity RoleSeeder after registering new permissions; no redundant admin-role seeder or role-name bypass.

## 17. Transactions, consistency and database safety

1. Use transactions for service creation with initial tiers and **all service update requests containing tier action commands**, rolling back the whole service metadata and tier update on any error.
2. In the service update transaction lock the stable **parent printing service row** with `lockForUpdate()`, reread live tiers, apply the full submitted command set logically, and validate the **final active tier set** after acquiring the lock. This serializes concurrent edits for the same service and prevents stale overlap checks; verify on real MySQL.
3. Price preview must ignore soft-deleted services/tiers and treat active/inactive correctly. Do not rely on cached frontend rules for authoritative saved orders.
4. A service delete and concurrent order-item creation must be synchronized by the appropriate Orders/Services integration contract, preventing a never-used check from racing a new order link.
5. **Case-sensitive** uniqueness of live service names requires an actual MySQL-safe constraint, not just app-level validation. Test same-case concurrent creation and coexistence of case-different names.
6. Customer-type classification must be verified against Settings before a new tier is saved, and at the Orders boundary when selecting the applicable prices.
7. Do not mutate old order snapshots when pricing changes.
8. Money calculations must remain precise and deterministic.
9. Migrations should support rollback on an empty disposable database; do not alter Settings tables unless a reviewed cross-module migration requires it.
10. Cover concurrent tier insertion and rename/create-delete edge cases on the target MySQL engine; SQLite in-memory tests alone cannot prove these concurrency rules.

## 18. Seeders and initialization

- A module seeder may establish new permission definitions and their Arabic translations, idempotently.
- If Identity's admin role has already been created, synchronize new permissions using the **existing** Identity RoleSeeder after permission creation, consistent with the approved Settings approach.
- Do **not** insert fabricated printing services, imaginary customer types, or sample prices into production installations without separate owner approval.
- Re-running seeders must not rewrite manually configured services or price tiers.
- The customerTypes Settings select already exists; add only the Printing Services select registration as needed.

## 19. Remaining open decisions and proposed defaults — not owner-approved

The owner closed **PS-OPEN-01/04/05/09** via PS-025–PS-028 and **PS-OPEN-06/10** via PS-030/031; case-sensitive uniqueness and all required whitespace normalization are confirmed in PS-029/032. Only Unicode canonical-equivalence handling remains as a technical detail of PS-OPEN-11; other open decisions below belong mostly to Orders and Settings integration.

| ID | Question needing owner confirmation | Proposed safe approach until decided |
|---|---|---|
| PS-OPEN-02 | What may an employee enter as a manual final total: is 0.00 allowed, and is an override reason required? | Allow nonnegative amount with two decimals; record override state; ask about mandatory reason in Orders. |
| PS-OPEN-03 | If an order's **customer/type or service** changes, should a previous manual override reset just as when quantity changes? | Recalculate and clear override whenever any price-determining input changes. |
| PS-OPEN-07 | When a Settings customer type is no longer associated with current customers but still has pricing tiers, may Settings deactivate or soft-delete it? | Prevent destructive type deletion while any active tier references it; define type deactivation behavior with Settings/Customers. |
| PS-OPEN-08 | At which Orders status/transition does the agreed price snapshot become immutable; can a draft be repriced explicitly? | Order lifecycle decides; pricing catalog changes never perform retroactive repricing. |
| PS-OPEN-11 | Case sensitivity and whitespace normalization are approved in PS-029/PS-032. Should canonically equivalent Unicode representations (e.g. NFC vs NFD) compare as identical? | Treat this as a technical normalization/collation decision to resolve before implementing the live-only unique index; do not change letter case. |

## 20. Acceptance criteria and feature-test scenarios

### Service catalog

1. Create active service with name and basePrice=null, no tiers.
2. Create a service with basePrice=0.00; retrieve exact zero, not null.
3. On create and update, trim outer whitespace and reduce repeated internal spaces to one: `  Business    Cards  ` becomes `Business Cards`; reject its duplicate if Business Cards exists. Permit business cards (different case) to coexist.
4. Soft-delete a never-used service and all live tiers in one transaction, then create a new service with the exact same name.
5. Reject deletion when any order item references the service, including draft, cancelled and soft-deleted order/item records.
6. Deactivate a used service; existing saved orders still display original name/price.
7. Prevent inactive or soft-deleted service from new-order selects.
8. Validate required status and reject unsupported pricing-mode/package data.
9. Paginated management list supports only `filter[search]` on **name** and `filter[status]` via enum; unsupported business filters fail, and soft-deleted rows are excluded.

### Tier definitions

10. Add customer-specific tier pointing to active Settings customer_types value.
11. Add generic tier with customerTypeId=null.
12. Reject ID from printing_locations even if the number exists.
13. Reject zero, negative, missing or null tier price; accept EGP 0.01 and 5.75 and enforce storage-range safety without arbitrary business caps.
14. Accept half-open ranges (from-only, to-only) and fully open catch-all.
15. Reject min>max, zero or fractional min/max, and a second catch-all within the same bucket.
16. Reject same-bucket overlap (1–200 with 100–500), including shared endpoints (1–200 with 200–300).
17. Accept adjacent nonoverlapping ranges (1–200 with 201–500).
18. Accept overlap for **different** customer-type buckets.
19. Accept one catch-all alongside constrained ranges in the same bucket.
20. Updating tier bounds via one service update command revalidates the final tier set without self-conflict or temporary intermediate-range conflicts.
21. Soft-delete tier and verify it disappears from future pricing matches; historical order snapshots remain unchanged.
22. Two simultaneous tier creates/updates cannot commit overlapping constrained ranges in one bucket on MySQL.

### Pricing resolution

23. Customer-specific matching constrained tier wins over customer-specific catch-all.
24. Customer-specific catch-all wins over a generic matching quantity tier.
25. Generic matching constrained tier wins over generic catch-all.
26. Generic catch-all wins over basePrice.
27. basePrice=0.00 resolves to proposedTotal=0.00 with requiresManualTotal=false.
28. basePrice=null with no matching tier resolves to a missing suggestion requiring manual total.
29. EGP tier unitPrice 5.75 with quantity 200 yields proposedTotal 1150.00; reject unrepresentable values/overflow with validation errors.
30. Inclusive quantity boundaries match exact min and exact max.
31. Employee can replace suggested order-line total without changing catalog pricing.
32. Changing quantity invalidates the old override and recalculates the suggested total.
33. Historic saved order line still shows original agreement after tier edit/delete and service rename.

### Auth, Selects and system integration

34. Unauthenticated management/select requests are rejected.
35. Authorized view/select access does not automatically grant write permissions.
36. Service create permission covers initial tiers; service update permission covers all `actionStatus` commands through the **same service-update API**; service delete covers eligible service-plus-tier soft deletion. No separate tier CRUD permissions/routes.
37. Shared select registry includes only active/nondeleted printing services and the existing customerTypes selector remains the source for tier type choices.
38. Existing Identity and Settings tests remain green; new changes do not mutate Settings customer types or customer data.
39. Settings customer-type Soft Delete is blocked where necessary by future integration references; the price-preview validates customerTypeId against the actual customer_types group.
40. A service-delete race with new order creation does not leave an order referencing a deleted service, including persisted draft orders.
41. Idempotent permission seeds do not fabricate pricing records.
42. New migrations work on the target MySQL version, including live-name uniqueness and tier concurrency invariants.
43. Service status is stored as `PrintingServiceStatus` and APIs return numeric enum values 0/1; invalid values are rejected.
44. Update tier commands reject unrecognized `actionStatus` numbers, enum-name strings and missing actionStatus.
45. UPDATE (2) and DELETE (3) without `priceTierId` fail validation; CREATE (1) with a `priceTierId` fails validation.
46. UPDATE (2) and DELETE (3) with IDs from a different service or soft-deleted tiers fail; no partial changes.
47. UNCHANGED (0) produces **no tier mutation**; omitted stored tiers also remain unchanged.
48. Both a missing `priceTiers` field and an empty array result in **no tier deletion**.
49. Mixed 0/1/2/3 commands update service and tiers atomically, including final-state-valid range swaps.
50. Any tier command validation failure rolls back service metadata and all tier mutations.
51. No independent tier POST/PATCH/PUT/DELETE endpoints are registered.
52. Searching with `filter[search]` targets **only service name**, never customer-type names or tier prices; `filter[status]` is the only other business filter.

## 21. Related documentation and implementation boundaries

- specs/03-modules/02-settings.md — authoritative Settings and customer_types definitions; see especially SET-010–SET-013 and parameter_values membership.
- Modules/Settings/app/Providers/SettingsSelectServiceProvider.php — existing customerTypes Select Registry source.
- Modules/Settings/app/Services/Selects/ParameterValueSelectService.php — active customerTypes option logic.
- Modules/Identity/database/seeders/RoleSeeder.php — reusable admin permission synchronization.
- Existing application response conventions, audit-column traits, Sanctum, Spatie permissions and QueryBuilder must be inspected in the implementation branch before finalizing API classes.
- The Settings document mentions project-wide standards under specs/00-project, specs/01-architecture and specs/02-standards; these referenced documents were not present in this branch at the time this reference was drafted. Verify them when available rather than claiming they were read.

**Non-negotiable boundaries:** numeric enum-backed service status (0/1), one atomic service update with `PriceTierActionStatus` tier commands (0/1/2/3), tier ID required for updates/deletes, name-only/status management filters; one printing business; one application and database; **EGP only**; no arbitrary business upper cap but rigorous storage/overflow validation; names trimmed/collapsed internally and case-sensitive; no new customer-type table; no selectable price-mode field; positive tier prices; nullable or free base price; no same-bucket constrained overlap; one catch-all fallback per bucket; explicit precedence; historic order price snapshots; no service deletion once used; no changes to customer or order records within the Printing Services module.

**Next phase:** Confirm outstanding §19 decisions at the relevant module boundary, approve schema/API proposals, then create separate implementation plan, tasks and tests. This reference file alone is not the implementation.
