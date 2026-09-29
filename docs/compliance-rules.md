# Compliance rules (source of truth)

Defaults reflect UK sponsor guidance as understood in mid-2026. Every number here
must be a per-business setting. Shaf will confirm against current gov.uk guidance
(Workers and Temporary Workers sponsor guidance, Appendix D) before go-live.

## 1. Right-to-work basis (Add employee form)

| Basis | Time-limited | Share code | Sponsored | Follow-up check |
| --- | --- | --- | --- | --- |
| British or Irish citizen | No | No (manual passport check or IDVT) | No | Not required |
| EU Settlement Scheme – settled | No | Yes | No | Not required |
| EU Settlement Scheme – pre-settled | Yes | Yes | No | Before expiry |
| Indefinite leave to remain | No | Yes | No | Not required |
| Skilled Worker – sponsored by this business | Yes | Yes | Yes | Before expiry |
| Other visa (Graduate, Dependant, Student, Other) | Yes | Yes | No | Before expiry |

All employees: full legal name, DOB, nationality, email (portal login), phone, NI
number, UK address, passport number + expiry, check method (online share-code check /
manual original passport / IDVT), check date, checked by, job title, salary, start
date, work site, working days per week, contracted hours, contract type, portal invite.
Time-limited: visa start, visa expiry, work restrictions. Other visa: visa type.
Sponsored: CoS number, CoS assigned date, SOC code; job title, salary and hours must
match the CoS.

Validation: check date must be on or before start date; visa expiry must be after the
start date; share code required when the basis uses one.
On save: follow-up check due = visa expiry; if portal invite, create a document
request for passport / ID; log "Employee record created".

## 2. Document categories

rtw (right-to-work check result), passport, cos, contract, jd, recruit (advert,
shortlist, interview notes), payroll, absence (fit notes, approvals), other.
Required for everyone: rtw, passport, contract, jd, payroll (a shared payslip counts).
Also required for sponsored workers: cos, recruit.
HR can "Request from employee" for any missing category → appears in the portal.

## 3. Absence types

| Type | Pay | Counts to unpaid limit | Report when |
| --- | --- | --- | --- |
| Annual leave | Paid | No | Never |
| Bank holiday | Paid | No | Never |
| Sickness – self-certified (1–7 days) | SSP / sick pay | No | Never (warn if > 7 days: needs fit note) |
| Sickness – fit note (8+ days) | SSP / sick pay | No | Never (fit note upload) |
| Maternity / paternity / adoption / shared parental | Statutory | No | Never |
| Compassionate / bereavement | Per policy | No | Never |
| Other paid leave | Paid (full salary) | No | Never |
| Unpaid leave | Unpaid | Yes | Unpaid + unauthorised days in calendar year exceed 4 × working days per week |
| Unauthorised absence | Unpaid | Yes | 10 consecutive working days reached |
| Jury service | Per policy | No | Never |
| Training / study leave | Paid | No | Never |

Unpaid limit: trigger date = the working day on which the limit is crossed.
Unauthorised: trigger date = the 10th consecutive working day.
Deadline = trigger + 10 working days. Show the check live before saving/approving.
Warn before approving a booking that would push unpaid days over the limit.
Any leave paid below the CoS salary that is not an exempt type shows: "Reduced pay
may be a reportable salary change". Paid leave of any length at full salary is fine.

## 4. Home Office report tasks (sponsored workers; company-level for business events)

| Trigger | Level | Deadline |
| --- | --- | --- |
| Did not start work | Worker | 10 working days |
| Left / dismissed / contract ended / redundancy | Worker | 10 working days from last day |
| Unauthorised absence reaches 10 consecutive working days | Worker | 10 |
| Unpaid leave exceeds 4 weeks in the year | Worker | 10 |
| Job title, duties or SOC code change | Worker | 10 |
| Salary reduced | Worker | 10 (increase: log only) |
| Contracted hours change | Worker | 10 |
| Moved to another work site | Worker | 10 |
| Suspected breach of visa conditions (manual) | Worker | 10 |
| New work address added / closed | Company | 20 |
| Registered or trading address, name, ownership, directors change | Company | 20 |
| Business stops trading | Company | 20 |
| Key personnel change (AO, Key Contact, Level 1 User) | Company | Update SMS promptly |

Task fields: business, level, employee, event text, trigger date, deadline, source,
status (Pending / Reported / Not required), reported on, reported by, SMS reference or
reason, link to source record.
Status badges: overdue (red), due today (red), due ≤ 5 working days (orange),
pending (neutral), reported (green), not required (grey).

## 5. Change history

Every edit to an employee: field, from, to, who, when, linked task if reportable.
Change types in the "Record a change" form: job title*, SOC/duties*, salary – reduction*,
salary – increase, contracted hours*, home address, phone, email (* reportable if
sponsored).

## 6. Employee portal requests → HR inbox

| Request | On approve |
| --- | --- |
| Leave (annual, unpaid, compassionate, training) | Create absence; run §3 rules; create task if triggered. Preview the result before approving. |
| Report sickness | Create absence (self-cert ≤ 7 days, fit note > 7). |
| Change of address / phone / email / name | Update record; log change (not reportable). |
| New visa or extension (new expiry) | Update visa expiry and follow-up date; log; remind HR to do a new right-to-work check. |
| Document upload (requested or unsolicited) | File into the category. |
Decline: status Declined with HR note. Statuses shown to employee: Waiting for HR,
Approved, Declined, Action needed.

## 7. Payslips

Business uploads payslips from their accountant (category payroll) as evidence of pay;
the employee can view their own in the portal. No payroll features.

## 8. Compliance check (per employee)

Right-to-work check on file and dated on/before start · follow-up check (Check ≤ 90
days, Missing if overdue) · passport/ID on file · current address and contact details ·
contract on file · sponsored only: CoS on file, job description matches CoS,
recruitment evidence, payslip uploaded in last 35 days, pay vs CoS (manual check),
no pending/overdue Home Office reports.

## 9. Settings (per business, seeded defaults)

Unpaid limit weeks (4), reset (1 January), unauthorised trigger (10 working days),
worker deadline (10 working days), company deadline (20 working days), exempt absence
types, expiry alert lead times (90/60/30 days), payslip freshness (35 days), retention
(end + 1 year; right-to-work evidence end + 2 years), annual leave (5.6 weeks pro rata).

## 10. End of employment

Last working day + reason (Resigned, Dismissed, Contract ended, Redundancy, Did not
start, Other). Sponsored → task (10 working days). Portal access off. Set delete-after
dates. Prompt HR to share P45 and final payslip. Monthly "due for deletion" review.

## 11. Unexplained absences (clock-in link)

The absence log, not the clock-in system, is the compliance record. Clock-in data is
used for one check only: each evening, a scheduled working day with no clock-in and
no absence entry becomes an "Unexplained absence" alert on the dashboard. The admin
classifies it (sickness, leave, unauthorised, or "Worked – clock-in missed").
Alerts unclassified after 2 working days turn red. The clock-in import is a later
integration: build the alert model and screens, stub the import interface.

## 12. Alerts and reminders (email + dashboard)

| Item | When |
| --- | --- |
| Visa / permission expiry | 90, 60 and 30 days before |
| Right-to-work follow-up check | 30 days before due |
| Passport expiry | 90 days before |
| Home Office task deadline | 5 working days before, and when overdue |
| Unexplained absence | Next morning, red after 2 working days |

## 13. Acceptance tests (must pass before go-live)

- [ ] 21 unpaid days in one year for a 5-day worker: warning before approval and a report task on approval.
- [ ] 10 consecutive working days of unauthorised absence across a weekend and a bank holiday: task created on the right day.
- [ ] Adding a new work site creates a company task with a 20-working-day deadline.
- [ ] Moving a sponsored worker to another site creates a worker task with a 10-working-day deadline; moving a non-sponsored worker only logs it.
- [ ] Reducing salary below the CoS salary creates a report task; an increase does not.
- [ ] "Mark reported" requires date and reported-by, and turns the absence badge green.
- [ ] A scheduled day with no clock-in and no absence creates an unexplained-absence alert.
- [ ] Visa expiry alerts fire at 90, 60 and 30 days.
- [ ] Adding a 16th active employee is blocked.
- [ ] An employee cannot open the admin area; an admin of one business cannot see another business's data.
- [ ] Documents cannot be opened by URL without signing in; every view and download is in the audit log.
- [ ] Passport and NI numbers are stored encrypted and shown as last 4 only.
- [ ] Compliance pack PDF exports correctly for one worker.
