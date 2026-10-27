# طَبعة V1 — Acceptance and release checklist

This checklist is NOT a completed staging acceptance report. Run on a disposable
staging environment with both Frontend and Backend at the candidate commit.
Never run migrate:fresh on production or customer datasets.

## Confirmed V1 decisions
- Files remain accessible using public storage links by explicit business decision.
- Financial amounts in Deliveries remain visible to delivery viewers for now.
- Production operators may undo stages if they have production.items.undo.
- Current overdue = confirmed + not delivered + due date before Cairo today.
  The original lateOnly filter is retained for historic late-delivered work.
- Filesystem crash recovery remains manual. Run the read-only command
  php artisan orders:audit-files to detect missing, duplicated or displaced files.

## P0 workflow scenarios — mark PASS/FAIL on staging
1. Admin creates customer, draft order with two items and attachments, confirms with
   a deposit: single order debt + single deposit collection; no duplicate ledger rows.
2. user1 starts design, user2 finishes, sends items to printing and marks ready.
   Validate order status, designer attribution, activity log and printer location.
3. Authorized production operator undoes a stage; status roll-up and action history
   are correct. Reception should not gain any production permission.
4. Deliver only after ALL items ready. Optional collection must be atomic with
   delivery. Retry same Idempotency-Key after lost response; no second delivery.
5. Authorized cancellation reverses a delivery-linked collection once, saves
   the reason, and restores readiness. Unauthorized cancellation returns 403.
6. General collection overpays into credit, reversal restores debt, refund cannot
   exceed the customer's current credit. Concurrent refunds must not overdraw.
7. Test routes directly as designer and reception; disallowed mutations yield 403.
8. Dashboard's overdue shortcut must exclude historically late delivered orders.
   Historical lateness stays discoverable without openOnly.
9. Search collections with literal percent, underscore and exclamation mark.
10. Delete draft and item file; audit must confirm database disk and actual bytes
    agree. Simulate crash between move and DB update only on disposable staging.
11. Repeat POST / confirm / deliver and production operations after network timeout
    or refresh. Same idempotency key and payload must not double post.

## Release and resilience
- Backend: php artisan migrate:status; php artisan test against isolated MySQL 8.
- Frontend: npm ci; npm test; npm run typecheck; npm run lint; npm run build.
- On staging: php artisan orders:audit-files must report zero inconsistencies.
- Smoke test: /up responds OK; unauthenticated protected endpoints return 401;
  verify cookie authentication + CSRF with a real browser.
- Responsive RTL and keyboard/manual flow at desktop and mobile width.
- At 1k and 10k finance ledger entries for a single test customer, measure
  query duration, transaction time, slow query logs and EXPLAIN ANALYZE.
- Keep database and BOTH public and private file disks in the same backup plan.
  Actually restore a disposable backup and verify customers, order and attachments.
- Verify APP_DEBUG=false, HTTPS, file upload limits, error logs and recovery owners.
- Owner sign-off after P0/P1 checks; GitHub CI is not proof of live browser acceptance.

## Future: independent delivery financial-field permission (proposal only)
Current response includes monetary values for authorized Deliveries viewers.
Keep the current API behavior for V1. Later introduce a separate permission
such as deliveries.amounts.view, distinct from finance.collections.view.
Gate orderTotal, receivedAmount, effectiveReceivedAmount and customerBalance
in a single backend serializer/presenter. Return null for protected amounts,
and ensure React renders null gracefully. When first introduced, grant to
existing Delivery viewers, then reduce as requested by the client. Decide
whether to split customerBalance and per-delivery amounts into two permissions
before implementation. CSS-only hiding does NOT provide access control.

No deployment or release tag is created by this checklist.
