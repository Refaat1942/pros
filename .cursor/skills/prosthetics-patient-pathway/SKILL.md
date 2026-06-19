---
name: prosthetics-patient-pathway
description: Implements civilian and military patient journeys from reception through delivery in the Smart Prosthetics ERP. Use when building Patient, CaseRecord, appointment, quote, approval, or delivery features; when the user mentions مسار مدني, مسار عسكري, QR, or patient workflow stages.
---

# Patient Pathway Implementation

Read `docs/new_analysis_by_client.md` §2 for full spec. Align with `CaseRecord` stage constants and `Patient::TYPE_*`.

## Checklist before coding

- [ ] `patient_type` set at registration (civilian | military)
- [ ] Military: capture rank + sovereign entity; civilian: contract company required
- [ ] QR generated on patient card (unique per patient)
- [ ] Doctor exam creates/updates `MedicalRecord` — triggers workflow event
- [ ] Spec links to `TechOrderSpec` / `PricingRequest` — technician sees codes only

## Civilian-only gates

Do not advance to manufacturing until:

1. `PricingRequest` approved by admin
2. `Quote` issued with QR
3. Contract approval scanned (OCR path or QR return)

## Military-only shortcuts

After background pricing completes:

- Skip `STAGE_QUOTE`, `STAGE_WAITING_RETURN`, payment checks
- Set path `CaseRecord::PATH_MILITARY`
- Route directly to operations/manufacturing

## Delivery close

- BOM stage must be `finished` before delivery list shows case
- Final QR scan on patient card → `STAGE_DELIVERED`, set `delivered_at`
- Civilian: update `paid` / remaining debt on `CaseRecord`
- Military: post cost to contract sovereign debt aggregate (no patient invoice)

## Anti-patterns

- Hardcoding patient names in Blade/JS seed data
- Manual "move to next department" without domain event in Service
- Showing stock prices or quantities on doctor/technician spec screens
