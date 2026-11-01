# Customer accounts V2: deployment and acceptance checks

## Branches

Both repositories use `feature/customer-accounts-settlement-v2`, created after the area-pricing work was merged into develop. The new financial feature remains separate from develop pending review.

## Deploy safely

1. Back up the existing database and test the new branch against a staging copy. Never use `migrate:fresh` on business data.
2. Deploy the Backend code, install locked dependencies if necessary, then run `php artisan migrate`. The additive migration creates `finance_account_settlements`; it does not rewrite ledger history or create any financial movement.
3. Deploy the matching Frontend only after the new API is available. Existing deposits, delivery payments, general collection and refund endpoints remain unchanged.
4. Review role permissions. Read-only account statements and settlement history require `finance.ledger.view`. Financial confirmation additionally requires `finance.collections.create`. This version deliberately does not silently broaden existing production roles.
5. For the standard reception role, after ordinary staff provisioning, optionally run `php artisan db:seed --class="Modules\Finance\Database\Seeders\ReceptionAccountAccessSeeder"`. This is additive and grants ledger reading only when reception already has balance viewing and collection creation. It grants no refund/opening-balance/administrative permissions. Reapplying the old authoritative staff-role seeder resets its role permission set; apply this additive seeder afterward or configure the role explicitly in the UI.

## What a settlement means

The cutoff limits delivered WORK. All actual customer funds through the confirmation date are considered once under the existing customer running-account policy. Deposits referenced to outside work are disclosed and already included in net funds; they are not deducted twice. This is not an invoice-specific earmarking/allocation subsystem. The system must not claim every invoice is individually paid from a zero aggregate balance.

A zero scope balance can be documented without new cash. Partial payments remain partial. A scope credit stays on account and is not refunded automatically. Separate full-account refund eligibility and permissions still apply. All subsequent collections and orders continue normally: a settlement does not lock or delete a customer account.

History records are immutable checkpoints as of creation. A later refund/reversal/canceled covered delivery flags review; subsequent ordinary new work does not rewrite prior evidence. A partial historical checkpoint stays a record of that partial operation even if a later checkpoint fully covers it.

## Acceptance run with the real UI

- One 1,000 order: confirm with deposit 200, deliver with payment 500, settle 300. Verify three receipts total, one original order-debt entry, balance zero, and repeat confirmation does not collect again.
- Pay 100 rather than 300 and verify a partial result with 200 remaining.
- Open a preview, record a payment in another session, then confirm the old preview. Expect 409 with no duplicate money and a required refresh.
- Open a historical work cutoff after receiving a later payment; verify that later payment is considered and new cash is dated today.
- Verify 0 + 5,000 opening adjustment + 50 delivered work - 100 net funds = 4,950 appears coherently on the period report and exports.
- Confirm already-paid work: evidence record only, no new receipt or ledger entry.
- Reverse a captured collection or cancel a covered delivery; re-open and print evidence, which must clearly flag review without rewriting the original snapshot.
- Check a positive full-account balance with a credit in the delivered scope: no automatic refund.
- Test reception, read-only ledger staff, collection-only staff and administrator separately. Do not infer operator permissions from an administrator screenshot.
- Review desktop/mobile RTL layout, keyboard navigation, currency direction, popup printing and Excel in an actual browser/Excel installation. Automated API/UI tests do not replace these visual and device checks.

## Rollback caution

The additive migration's down() drops settlement evidence only; it does not reverse real receipts. Never roll it back after live financial settlements merely to remove a UI feature. Disable the new UI and retain evidence/ledger history while diagnosing an issue.
