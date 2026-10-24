# Future option: Dynamic Production Workflows (DEFERRED)

**Status:** Backlog / design notes only. **DO NOT IMPLEMENT IN PRODUCTION V1.**
**Recorded:** 2026-10-24 on `feature/production`.
**Active production plan:** [08-production.md](./08-production.md) (static stages and handlers).

## Why defer

The printing workflow currently has five well-defined product statuses and a small number of actions. A configurable workflow adds migrations, API/configuration management, runtime action/field rendering, authorization and validation complexity without a demonstrated current need. Ship the static workflow first and record real requirements before adding an engine.

## Idea for future evaluation

Separate three concepts:

1. **Stage/state:** where the order item currently is, e.g. not_started, design_in_progress, design_completed, printing, ready_for_delivery. Several states may share one board column (both design states belong to Design).
2. **Transition/action:** the available action from one state to another. For example, design_in_progress -> design_completed (finish only); design_in_progress -> printing (finish and send); design_completed -> printing (send). Each action has a stable code, label, source/target states, required inputs and permission.
3. **Action handler:** vetted Laravel business logic that runs the transition atomically. Config must never contain executable PHP or bypass authorization and business invariants.

Possible later tables (NOT for v1):

- `production_stages`: id, unique code, label/translation key, board group, sort order, active/status.
- `production_transitions`: id, unique code, from_stage_id, to_stage_id, label/translation key, handler_key, permission key, active/status.
- `production_transition_fields` or a validated JSON schema on transitions: field key, type, required, options_source, constraints; can drive generic select/textarea/date UIs.
- `production_item_states`: optional only **if** the existing Orders-owned `order_items.production_stage` is intentionally migrated away; never keep two independent authoritative states. The owner of write operations and cross-module contract must be settled before migration.
- `production_item_events`: still necessary in static v1, so future changes can reuse the audit history.

Illustrative metadata returned to React by a future `GET /api/v1/production/board-config` or per-item `availableActions`:

```json
{
  "state": "design_in_progress",
  "actions": [
    {
      "code": "complete_design",
      "label": "إنهاء التصميم",
      "targetState": "design_completed",
      "fields": []
    },
    {
      "code": "complete_and_send",
      "label": "إنهاء وإرسال للطباعة",
      "targetState": "printing",
      "fields": [
        {
          "name": "printingLocationId",
          "type": "select",
          "required": true,
          "optionsSource": "printingLocations"
        }
      ]
    }
  ]
}
```

React could render the appropriate button/modal/select based on the action metadata and load `printingLocations` from the existing Settings selects API. The server **must still revalidate** permissions, allowed transition, source state, reference validity and required fields when executing.

## Future examples

- Add a quality review stage between design and printing.
- Add a management-approval action requiring a selected reviewer or a reason.
- Add conditional transitions and different required fields per action without hardcoded frontend components, but only from an allowlisted set of supported field types and registered handlers.
- Allow enabled/disabled stages and sort order from an admin editor **only after** addressing existing in-progress items and versioned workflows.

## Questions to resolve before starting that future work

1. How do existing items transition when an administrator adds, removes or reorders a stage?
2. Are workflows versioned per order, or do all items immediately follow newly published rules?
3. Which users can configure transitions, required inputs and permissions? How do we avoid enabling a restricted action through metadata?
4. Can an action cause more than one audited event, e.g. complete design and send to printing? How are batch operations and idempotency handled atomically?
5. How are rollback/undo paths specified for branching stage graphs, especially after an item is ready but before physical delivery?
6. How do Orders and future Deliveries read reliable production progress during any schema migration?

## Important non-goals until explicitly approved

- No new workflow-related tables, enums replacement, metadata-driven FormRequest validation or dynamic stage API in v1.
- No admin stage-management UI now.
- No change to the static 11 Production APIs planned in `08-production.md` solely to support future workflow configuration.

**Decision:** Revisit the feature only when there is a concrete need for multiple configurable production pipelines or genuinely variable actions/inputs. Until then, keep the static implementation straightforward, secure and well-tested.
