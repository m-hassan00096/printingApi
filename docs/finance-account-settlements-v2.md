# Customer accounts and financial settlement V2

## Scope and invariants

A settlement is an immutable **cumulative customer-account checkpoint**, not an invoice payment-allocation system. It covers all currently valid delivered work up to `dateTo` plus the opening balance. All actual customer collections, reversals and refunds through the Cairo date of confirmation are included ONCE from the existing ledger. Earlier settlement snapshots are NEVER added to charges or credits.

This preserves the application's existing running-account policy. An order reference on a collection is not a new restriction earmarking that cash. `advancesForOtherWork` explicitly discloses advances referenced to confirmed work outside the delivered scope; these funds are already included in `netPayments`, not deducted twice. Invoice-specific reservation/allocation is not implemented by this version and must not be inferred from a settlement record.

- Confirmation of an order remains the only source of order debt. Delivery and settlement do not post that debt again.
- A historical delivered-work report is READ ONLY. Financial settlement uses current funds, not a historical closing balance as a payment instruction.
- Settlement payments use the existing `CollectionService::post` and its receipt, destination validation and immutable ledger entry.
- The selected cutoff limits WORK only. Payments/opening balances are current through `paymentsThrough`. New cash is dated on actual confirmation, never backdated to `dateTo`.
- A user-authorized FIXED discount is recorded as its own negative `SETTLEMENT_DISCOUNT` ledger movement, linked to the existing settlement evidence; it is not a receipt and never contributes to `netPayments`. No automatic discount, forced zero, cash refund or history deletion occurs.
- `balanceAfter = openingBalance + deliveredTotal - netPayments - priorSettlementDiscounts - discountAmount - amountCollected`.
- `accountBalanceAfter` also decreases by the fixed discount and actual collected amount; discounts may not exceed both the delivered-scope due and the real customer account debit, preventing artificial customer credit.
- `accountBalanceAfter` may remain positive because of confirmed work outside scope.
- A credit in the delivered scope is NOT automatically refundable credit. Refund permissions and the existing full-account refund validation remain authoritative.
- Zero new payment is allowed for partial, fully covered or credit scopes. It saves immutable evidence but **does not eliminate unpaid debt**. A positive discount with zero cash posts only the discount ledger movement, never a collection.
- Identical idempotency keys replay the original record. Stale fingerprints return 409. An unchanged already-covered scope reuses its last record rather than creating a second receipt.
- Records preserve their original snapshots. Later reversals, refunds or cancellation of covered deliveries/orders flag records `needs_review`; they do not silently rewrite the signed evidence or leave an unconditional closed badge.

## API

All endpoints use `/api/v1/finance/customers/{customer}` and existing response envelopes.

- `GET /settlements/preview?dateTo=YYYY-MM-DD`: current settlement preview, component totals, source references and `previewHash`.
- `POST /settlements`: requires `Idempotency-Key`, `dateTo`, `previewHash`, decimal-string `amount`; optionally accepts decimal-string `discountAmount` plus required `discountReason` (minimum 3 characters) for nonzero discount. Positive real payments require `paymentMethod` and the corresponding destination. Optional `note`.
- `GET /settlements?page=1`: paginated evidence records.
- `GET /settlements/{id}`: immutable snapshot and current review flag, customer-scoped.
- Existing `GET /settlement?dateFrom=...&dateTo=...` remains a read-only period report.

Read: `finance.ledger.view`. Confirm: both `finance.ledger.view` and `finance.collections.create`. Applying a nonzero discount additionally requires `finance.settlements.discount`. Other refund/opening-balance/collection permissions remain unchanged.

## Deployment and verification

Deploy Backend and run the additive migration before enabling the new Frontend. Never run migrate:fresh against business data. No migration creates or rewrites financial ledger entries.

Test deposits, delivery payments, external collections, partial/full/zero-payment settlement, different-key double submit, stale previews, reversals, refunds, canceled deliveries, old cutoff with new payments, customer isolation and insufficient permissions. Compare the stored evidence against the actual collection and customer ledger; do not use a green UI mock test as evidence of real cash posting.
