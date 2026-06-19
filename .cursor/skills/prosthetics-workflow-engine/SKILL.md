---
name: prosthetics-workflow-engine
description: Event-driven status transitions for CaseRecord and related entities. Use when implementing workflow services, listeners, stage changes, SLA, or when the user mentions محرك التدفق, event-driven, status transition, or SLA breaches.
---

# Workflow Engine

## Design principle

**Event-driven, not button-driven.** UI actions dispatch domain events; a WorkflowService applies transitions inside DB transactions.

## Transition map (from analysis §3)

| Event | From → To | Side effects |
|-------|-----------|--------------|
| `ExamApproved` | exam → technical | Queue spec task for technician |
| `SpecSaved` | technical → cost_calc | Invoke pricing engine |
| `PricingCompleted` (civilian) | cost_calc → waiting_return / quote | Generate quote PDF + QR; freeze financially |
| `PricingCompleted` (military) | cost_calc → manufacturing prep | Skip quote; notify operations |
| `ApprovalScanned` | waiting_return → manufacturing | Unlock work order; `approval_confirmed_at` |
| `BarcodeDispensed` | manufacturing (warehouse) → workshop | Deduct stock; notify workshop |
| `ProductionFinished` | manufacturing → ready delivery | SMS/notify patient + entity |
| `DeliveryScanned` | ready → delivered | Archive EMR slice; final accounting |

## SLA tracking

- Configurable SLA days (default 21) from quote date for civilian open cases
- `getSlaSummary`: avg turnaround, breached list, open count — for admin BI board 1
- Breached = open case where turnaround > SLA limit

## Implementation pattern

```php
// Service method sketch — no controller logic
DB::transaction(function () use ($case, $event) {
    $case = CaseRecord::lockForUpdate()->find($case->id);
    $this->assertTransitionAllowed($case, $event);
    $case->update(['stage_key' => $targetStage]);
    $this->audit->log($case, $event, $before, $after);
    event(new $event($case));
});
```

## Reference

- Stage keys: `CaseRecord::STAGE_*`, `CaseRecord::MFG_*`
- Full table: `docs/new_analysis_by_client.md` lines 91–106
