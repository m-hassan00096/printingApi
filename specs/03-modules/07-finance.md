# Finance Module — Tabaa v1 (2026-10-24)

> Branch: `feature/finance` from merged `develop`. Finance owns monetary facts. The frontend owns UX, never financial business invariants.

## 1. Confirmed decisions

- One customer account: signed balance positive = customer owes business, negative = credit in customer's favor.
- Credit automatically offsets new order liabilities in the account calculation. No explicit "apply credit" request.
- Any positive collection, even above outstanding debt, is allowed without a special flag or confirmation. Extra remains credit.
- Only one payment method per collection/deposit/refund: `CASH=0`, `BANK_TRANSFER=1`, `E_WALLET=2`.
- `paymentDestinationId` is null/omitted for CASH, required for bank/wallet. Finance resolves via Settings-owned contract and stores the appropriate FK and a destination label snapshot.
- No cashier till, general ledger, payout processor, multi-currency or multi-method payment in v1.
- Money: EGP `DECIMAL(12,2)` persistence and decimal-string HTTP transport, arithmetic with integer cents.
- Opening balance: one customer-scoped debit or credit entry, created by Finance endpoint. May be entered immediately after customer creation in UI or later. Never an attribute on Customers' profile.
- Collection's optional `orderId` is a reference to an order owned by the same customer, *not allocation* of funds to order dues.
- Collection date can be backdated, but `createdAt` always records actual posting timestamp. Refund is effective on posting date.
- Reversing a collection never mutates its original ledger entry; writes an equal opposite movement and records metadata on the collection (no re-reversal). May create a debit even if the customer had subsequently spent credit.
- A refund moves money out only when available customer credit covers it; no automatic refunds on order cancellation.
- Orders create liability on confirmation, reverse liability on cancellation before delivery, keep collected deposits as credit against customer total; delivery may proceed without collection.
- Integer-backed enums expose numeric value + localized `*Label` from `lang/{ar,en}/finance.php`.
- IDs must be registered references and persisted amounts cannot be deleted or updated in place.

## 2. Ownership and contracts

Finance owns: `finance_ledger_entries`, `finance_collections`, `finance_refunds`, `finance_idempotency_keys` and service/enum/API code.
Customers owns profile and `CustomerFinanceReference` for locked existence and display snapshots; Finance supplies a customer lifecycle usage guard adapter.
Settings owns bank/wallet tables and `PaymentDestinationLookup` validation; Finance consumes only this contract.
Orders owns `OrderFinanceGateway` consumer contract and `ConfirmOrderFinanceData` / `CancelOrderFinanceData` readonly DTOs. Finance supplies `FinanceOrderGateway`, Orders supplies `OrderFinanceReference`. Neither Finance business service imports Orders Eloquent models nor Orders business logic imports Finance models. All posting and status changes for confirmation/cancellation share a transaction.
Deliveries later will consume an internal Finance posting contract and call it in the delivery transaction, not two HTTP calls.

## 3. Response conventions

- Success: `{"success":true,"message":"","data":...}` for GET; writes localized message.
- Validation: HTTP 422; business conflict: HTTP 409; permission: HTTP 403.
- `BalanceType ZERO=0, DEBIT=1, CREDIT=2`; `CollectionSource GENERAL=0, ORDER_DEPOSIT=1, DELIVERY=2`; `CollectionStatus POSTED=1, REVERSED=2`.
- `LedgerEntryType ORDER_DEBT=1, COLLECTION=2, ORDER_CANCELLATION=3, COLLECTION_REVERSAL=4, REFUND=5, OPENING_BALANCE=6`.
- `paymentMethod` & `paymentDestinationId` consistent across collections, refunds and order deposits.

## 4. New Finance HTTP API

| Verb | Path | Request | Successful data | Permission |
|---|---|---|---|---|
| GET | /api/v1/finance/customers/{customer}/balance | URL customer ID | customerId, amount, type, typeLabel | finance.balances.view |
| GET | /api/v1/finance/customers/{customer}/ledger | page, perPage; filter[dateFrom], filter[dateTo], filter[type] | entries[], balance, pagination | finance.ledger.view |
| POST | /api/v1/finance/customers/{customer}/opening-balance | amount(string), type(1/2), note? | null (201) | finance.opening_balances.create |
| GET | /api/v1/finance/collections | page, perPage, sort, filter[search,dateFrom,dateTo,paymentMethod,source,status] | collections[], pagination | finance.collections.view |
| GET | /api/v1/finance/collections/summary | relevant collection filters | totalCollected, collectionsCount, cashTotal, bankTotal, walletTotal | finance.collections.view |
| GET | /api/v1/finance/collections/{collection} | path id | collection Resource | finance.collections.view |
| POST | /api/v1/finance/collections | customerId, amount, paymentMethod, paymentDestinationId?, orderId?, collectedOn?, note? | id, receiptNumber (201) | finance.collections.create |
| POST | /api/v1/finance/collections/{collection}/reverse | reason (3–3000 chars) | null | finance.collections.reverse |
| GET | /api/v1/finance/refunds | page, perPage, filter[search,dateFrom,dateTo,paymentMethod] | refunds[], pagination | finance.refunds.view |
| GET | /api/v1/finance/refunds/{refund} | path id | refund Resource | finance.refunds.view |
| POST | /api/v1/finance/refunds | customerId, amount, paymentMethod, paymentDestinationId?, reason | id, refundNumber (201) | finance.refunds.create |

- Header `Idempotency-Key` required for collection create, reversal and refund create.
- `amount` must be a positive decimal string with maximum 2 fraction digits; never a JS float.
- `collectedOn` is optional and cannot be future dated.
- Summary includes only POSTED collections, not refunded cash (refund is separate).

## 5. Existing endpoints integrated

- `POST /api/v1/orders` & `PUT/PATCH /api/v1/orders/{order}` with `status=1`: optional `depositAmount` string; when >0, `paymentMethod` required; `paymentDestinationId` required for non-cash.
- `POST /api/v1/orders/{order}/confirm`: same three deposit fields, or `{}` without deposit.
- `POST /api/v1/orders/{order}/cancel`: unchanged `{"reason":"..."}`.
- `POST /api/v1/customers` remains unchanged: frontend may post opening balance through Finance once it receives new CustomerResource.id.
- `GET /api/v1/selects?selects=bankAccounts,eWallets` remains unchanged and feeds destination UI.
- `Idempotency-Key` mandatory on order creation/confirmation/cancellation per Orders contract.

## 6. Example requests

```json
{"customerId":15,"amount":"4000.00","paymentMethod":1,"paymentDestinationId":3,"orderId":125,"collectedOn":"2026-10-23","note":null}
```

```json
{"customerId":15,"amount":"500.00","paymentMethod":2,"paymentDestinationId":4,"reason":"رد جزء من الرصيد الدائن"}
```

```json
{"depositAmount":"1000.00","paymentMethod":0,"paymentDestinationId":null}
```

## 7. Test checklist

- Create debit and credit opening balances; reject duplicate.
- Post cash, bank and wallet collections with destination validation; overpayment becomes credit automatically.
- Idempotency key replay returns same document; key reused with different payload fails.
- Full reversal posts opposite ledger entry and cannot reverse twice.
- Refund cannot exceed available credit and concurrent requests serialize on customer lock.
- Confirmed orders write a liability and optional deposit in the same transaction; cancellation reverses the debt, never refunds automatically.
- Payments posted for inactive customers remain supported.
- Existing Orders CRUD and permissions regressions remain green.
- Pending delivery integration deliberately not implemented in Finance v1.

## 8. Responsibility boundaries from final code review

- Form Requests own syntactic and reference validations: positive monetary decimal strings, numeric integer-backed enums, payment method/destination consistency (Settings contract), optional order ownership (Orders contract), opening balance shape, reversal reason, idempotency header and endpoint-specific query filter allowlists.
- Opening balance posting lives in `CreateOpeningBalanceAction`, not its controller. Domain service still verifies uniqueness inside the locked transaction; duplicates throw localized `FinanceException` (409).
- Ledger Eloquent model rejects updates and deletes after posting. Corrections are new opposite entries; no financial mutations through controllers.
- In rare concurrent deactivation after Request validation, Finance rechecks the selected destination under lock and returns business conflict; Request never substitutes for transaction safety.
- Cancel order reverses the amount of its original posted liability, not a second copy of the order total.


## 9. Atomic collection amount correction (October 29, 2026)

- `POST /api/v1/finance/collections/{collection}/correct`, permission `finance.collections.correct`, requires `Idempotency-Key` header. Request: `{ "amount": "300.00", "reason": "Incorrect recorded deposit", "confirmCreditBalance": false }` (the credit acknowledgment is optional and used for delivery-source corrections).
- Positive amount only (zero is rejected; use the standalone reversal action): reverse the original posted receipt, create a replacement retaining `source`, `orderId`, `paymentMethod`, destination snapshot, customer snapshot and original `collectedOn`; `corrected_from_id` and `corrected_to_id` link the full audit chain. Zero amount is rejected (HTTP 422). Full voiding must use the standalone `/reverse` endpoint.
- Financial reversal, replacement posting, correction links, and delivery `collection_id` relink occur in a single DB transaction; original receipt is never deleted. Lock order, then delivery, then customer and receipt, matching the delivery cancellation flow; retries are idempotent.
- A corrected **delivery** points to its replacement receipt so subsequent delivery cancellation reverses only the currently linked amount. Canceled deliveries and already-reversed receipts cannot be corrected.
- The correction retains the historical payment destination snapshot even if that bank/wallet has since been deactivated. No bank transfer or refund is initiated; `/refunds` handles actual customer refunds.
- `GET /finance/collections` and `GET /finance/collections/{id}` additionally expose `orderNumber` (nullable), `correctedFromId`, and `correctedToId`. Order references include soft-deleted order snapshots via read-only Eloquent lookup.
- New migration: `2026_10_29_000001_add_collection_corrections.php`. Seed `FinanceDatabaseSeeder` to add permission and sync admin role.
- Regression tests cover general/deposit/delivery and subsequent cancellation, zero corrections, idempotency, role permission, validation and historical bank destination.


## Customer accounts and statements refinement (2026-10-29)

- Existing list `GET /finance/customer-balances` already implements grouped ledger amounts, pagination, debit/credit/zero filtering, search by customer name/phone, and balance/name sorting. The frontend now uses it as the account overview without making per-customer N+1 requests.
- `GET /finance/customers/{customer}/balance` adds `openingBalance: null | {amount, type, typeLabel}` (from the one immutable opening ledger entry); `openingBalanceExists` and the current total balance remain unchanged.
- `GET /finance/customers/{customer}/ledger` adds `orderNumber`, `receiptNumber`, `refundNumber` nullable human-readable references next to existing foreign keys. They use read-only left joins and do not affect arithmetic, historical running-balance windows, sorting, filters, or posting logic.
- `GET /finance/refunds` accepts optional `filter[customerId]` (positive integer); it scopes refunds by customer **before** pagination and search. User authorization remains enforced through `finance.refunds.view`; no additional refund data is exposed.
- Regression tests assert the value and type of opening balance, true receipt reference on reversal, correct customer filtering and invalid filter rejection. Existing ledger total consistency is preserved.
