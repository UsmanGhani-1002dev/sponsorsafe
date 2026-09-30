# SponsorSafe — project brief and handover

SponsorSafe (working name) is a SaaS for small UK sponsor licence holders. It does
UKVI compliance only, not payroll or general HR. It keeps every record the Home
Office expects, tells the business when something must be reported on the Sponsor
Management System (SMS), and gives employees a simple portal.

Target customer: up to 15 employees, payroll done by an accountant, no technical
knowledge. Keep every screen minimal and plain-English.

Owner: Shaf (Enovtec, Southampton). Claude is the developer; Shaf reviews each stage.

## Status

- **Stage 1 (foundation): done and tested** — built in a Claude chat.
- **Stage 2 (employees): done and tested.**
- **Stage 3 (documents + absence): done and tested.**
- **Stage 4 (Home Office reports + end of employment): done and tested.**
- **Stage 5 (employee portal + Requests inbox): done and tested** — 182 PHPUnit tests passing. Waiting for Shaf's review.
- **Next: Stage 6 (Compliance check tab, compliance pack PDF, retention review).** See "Build order" below.
- Local setup on this laptop is done (git repo, MySQL databases, PHP 8.4).

## Local setup (Windows)

1. PHP 8.3+ and Composer. If `php -v` or `composer -V` fails, install them with
   php.new (Laravel's installer: PHP, Composer and the Laravel CLI) in PowerShell:
   ```powershell
   Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
   ```
   Then the terminal must be reopened so PATH updates. If you (Claude) can't see
   the new PATH, tell Shaf to restart Claude Code and resume.
2. Node.js 20+ and Git, if missing:
   `winget install --id OpenJS.NodeJS.LTS -e` and `winget install --id Git.Git -e`.
   On this laptop XAMPP's own PHP is 8.2 (too old); php.new's PHP 8.4 lives in
   `C:\Users\Dell User\.config\herd-lite\bin` and is first on the user PATH.
   From Git Bash, call Composer as `php "C:/Users/Dell User/.config/herd-lite/bin/composer.phar"`.
3. Database: XAMPP's MySQL (MariaDB 10.4), user `root`, no password. Start MySQL in
   the XAMPP Control Panel, then create the databases once:
   `C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE sponsorsafe; CREATE DATABASE sponsorsafe_test"`.
   Tests (`phpunit.xml`) use `sponsorsafe_test` and wipe it on every run.
4. From the project root:
   ```
   composer run setup
   php vendor/bin/phpunit
   ```
   (`php artisan test` needs the `nunomaduro/collision` package, which is not installed.)
   `composer run setup` installs packages, creates `.env`, runs migrations with
   demo data, and builds the frontend.
5. Run the app: `php artisan serve` in one terminal and `npm run dev` in another
   (or `composer run dev`). Open http://127.0.0.1:8000/login

Demo logins (password `password`, local only):
- hr@demo-retail.example — business admin (Demo Retail Ltd). Admins need 2FA: the
  first sign-in shows a setup key for an authenticator app. `migrate:fresh --seed`
  resets it, so the key must be added again after a reseed.
- aisha.rahman@demo-retail.example — employee (no 2FA)
- hr@demo-cafe.example — suspended business (shows the paused message)
- Super admin: http://127.0.0.1:8000/ops-local/login — owner@sponsorsafe.example.
  First sign-in shows a key for an authenticator app (Google/Microsoft Authenticator).

## Reference material

- `docs/product-decisions.md` — **every product decision from the design chat**:
  scope, sign-in, plans and billing, website, AI chat, each area's screens and
  menus, design, security/GDPR, and open questions. Read it before each stage.
- `docs/compliance-rules.md` — every rule, threshold, deadline, alert and the
  go-live acceptance tests. Source of truth for rules.
- `docs/prototype-website.dc.html`, `docs/prototype-app.dc.html`,
  `docs/prototype-superadmin.dc.html` — the clickable prototype Shaf approved. The
  `<script type="text/x-dc">` block in each holds working JavaScript for the rules
  and flows (working days, 4-week unpaid limit, 10-day unauthorised streak,
  deadlines, right-to-work form logic, compliance checklist). Port logic to PHP and
  match the screens, wording and look. The markup is prototype-only; don't copy it.
- `README-DEPLOY.md` and `deploy/production.env.example` — production install on Shaf's
  cPanel/WHM server.
- Out of scope (removed on purpose): payroll, messages, notices,
  letters/contract acknowledgements, pay reviews. Payslips are uploaded by the
  business only as evidence of pay.

## What Stage 1 built (follow these conventions)

- Laravel 13, Inertia v3 + React 19 + TypeScript, Tailwind v4, Vite. PHPUnit tests.
- Areas and routes (`routes/web.php`):
  - `/login` — shared sign-in for admins and employees. Email → Next → account card
    (name, email, business, role, "Not you?") → password. The looked-up email is
    kept in the session, not the URL. Lookups and password attempts are rate-limited.
  - `/app` — business admins (middleware `auth:web`, `business.active`, `role:admin`).
  - `/me` — employee portal (`role:employee`), mobile-first.
  - `/{OPS_PATH}` — super admin. Separate `super_admins` table and `ops` guard,
    IP allow-list (`OPS_ALLOWED_IPS`, others get 404), password + TOTP code
    (pragmarx/google2fa). `php artisan ops:create-admin email` creates one.
  - `/` redirects to `/login` until the public website is built (Stage 7).
- Tenancy: `businesses` table; `users.business_id` + `users.role`
  (`admin` | `employee`). Always scope queries through `$request->user()->business`;
  never trust a business_id from the request. Suspended business → users are signed
  out by `EnsureBusinessActive`.
- `config/sponsorsafe.php` — ops path/IPs, plan defaults (£20, 15 employees, £49
  training) and rule defaults. `Business::rule('key')` reads a per-business override
  from `businesses.settings` and falls back to the config default. Never hard-code
  thresholds in logic.
- `App\Services\WorkingDays` — Mon–Fri minus England & Wales bank holidays
  (`bank_holidays` table, seeded 2025–27; `php artisan bank-holidays:sync` pulls
  gov.uk monthly via the scheduler). Use `WorkingDays::fromDatabase()` for all
  deadline and absence maths.
- `App\Support\Audit::log($action, $subject, $meta)` → `audit_logs`. Call it for
  every sensitive action (sign-ins, document views/downloads, record changes,
  suspensions).
- Frontend: `resources/js/pages/{Auth,App,Portal,Ops}`, layouts in
  `resources/js/layouts` (app, portal, ops), small UI kit in
  `resources/js/components/ui` (Button, Field/Input, Badge, Card, Alert).
  Design tokens in `resources/css/app.css`: canvas #F9FAFB, lines #EAECF0/#D0D5DD,
  ink #101828/#344054, muted #667085, single accent indigo #4F46E5. Font Geist
  (self-hosted via @fontsource). Status badge tones: red, amber, green, grey, blue.
  Sidebar items not built yet show a "Soon" badge — switch them on as stages land.
- Seeders: `BankHolidaySeeder` always; `DemoSeeder` only outside production
  (Demo Retail Ltd, Demo Catering Ltd, suspended Demo Cafe Ltd, demo super admin).

## What Stage 2 built (follow these conventions)

- Tables: `work_sites`, `employees` (a person; `user_id` is their portal login, if any),
  `employee_changes` (change history), `document_requests`, `key_personnel`; users
  gained `password_token*`, `invited_at`, `two_factor_*`.
- `App\Enums\RightToWorkBasis` is compliance-rules §1 (time-limited, share code,
  sponsored, hint); the Add employee form takes its options from it.
  `DocumentCategory` is §2, `ChangeType` is §5.
- `App\Services\EmployeeRules` validates Add employee (§1), "Correct personal
  details" (name, DOB, nationality, NI, passport only) and "Record a change" (§5).
  Job, pay, hours, site and right-to-work change only through Record a change or
  Settings → Move employee, so reportable changes are never silent.
- **Every write to an employee goes through `App\Services\EmployeeRecorder`**: it logs
  each changed field (old, new, who, when, masked for secrets) and audits it.
  Stage 4 hooks report-task creation in here (`employee_changes.report_task_id`).
- `App\Services\PasswordLinks`: single-use set-password links (hashed): portal invite
  7 days, password reset 60 minutes. `App\Support\SignIn` finishes every sign-in;
  admins (and employees who opted in) pass `/login/verify` (TOTP) first.
- `Model::preventLazyLoading()` is on outside production: eager-load, or it throws.
- Shared UI: `components/data-table.tsx` + `App\Support\Table` (server-side sort,
  search, filters, pagination via partial reloads), `PageHeader`, `EmptyState`,
  `ConfirmDialog`, `Tabs` (unbuilt tabs show "Soon"), `Field/Input/Select/Textarea`.
  Colours are CSS tokens with a `.dark` override (`ThemeToggle`); use `bg-surface`,
  not `bg-white`, and `bg-accent-fill` for solid indigo with white text.
  Pages are lazily loaded (one chunk each).
- Not built yet (later stages): toasts (flash messages are banners), Ctrl+K palette,
  dashboard count caching.

## What Stage 3 built (follow these conventions)

- Tables: `documents` (private, encrypted files), `absences` (with the stored Home Office
  check: `check_status`, `report_trigger_on`, `report_deadline`, `report_event`);
  `document_requests.document_id` + statuses awaiting/received/cancelled.
- `App\Services\AbsenceRules` is compliance-rules §3 (pure, unit-tested): unpaid limit per
  leave year (`unpaid_leave_year` calendar|rolling), unauthorised streak joined across
  records, deadlines, warnings (self-cert > 7 calendar days, reduced pay on non-exempt
  types), overlaps. Returns an `AbsenceCheck`. Reporting applies to sponsored workers only.
- **Every absence is written through `AbsenceRecorder`** (re-runs the check at save time);
  Stage 4 creates the report task there from the stored trigger/deadline/event.
  The live check on "Record absence" is `GET /app/absence/check` (same PHP rules, JSON).
- **Every file goes through `DocumentVault`**: stored at `documents/{business}/{employee}/`
  on the `local` disk, contents encrypted with `Crypt`. Served only by
  `DocumentController` (view/download), each audited. Uploads: PDF/JPG/PNG, 10 MB
  (`config/sponsorsafe.php` → `documents`).
- Rule settings (§9) are editable in Settings → Compliance rules and stored in
  `businesses.settings`; always read them with `Business::rule()`.
- Absence export: CSV (formula-injection safe) and PDF via barryvdh/laravel-dompdf
  (`resources/views/pdf/absences.blade.php`). Exports are audited.
- `DataTable` supports `dateRange` and a `toolbar` slot; `tableQuery(state)` builds export
  links with the same filters. `Table::from(..., dir: 'desc')` sets the default order.
- Decisions: calendar leave year by default; self-cert sickness counted in calendar days;
  fit notes optional at save and flagged "Fit note missing"; no second trigger once a
  limit or streak has already been passed (a warning instead).
- Demo: Rahul's reported 10-day unauthorised absence (Stage 4 seeds it with its reported task).

## What Stage 4 built (follow these conventions)

- `report_tasks` (compliance-rules §4): level worker|company, event, `trigger_on`, `deadline`,
  `source` (absence, change, work_site, key_personnel, leaver, manual), polymorphic `subject`
  (morph map in `AppServiceProvider`), status pending|reported|not_required, reported on/by,
  `notes` (SMS reference or reason). `employees.delete_after` / `rtw_delete_after`.
- **Every task is created by `App\Services\ReportTasks`**, called from the recorders:
  `AbsenceRecorder::record` (stored check), `EmployeeRecorder::update` (pass the `ChangeType`
  for "Record a change"; a `work_site_id` change is a site move), `EmployeeRecorder::end`
  (End employment, §10), Settings (site added/closed, key personnel added/changed/removed),
  and `ReportTasks::manual`. Worker tasks only for sponsored workers. Deadlines always from
  `Business::rule('worker_report_deadline_days' | 'company_report_deadline_days')` in
  working days; key personnel use the company deadline.
- `ReportTask::badge()` is the §4 badge (overdue / due today red, ≤ 5 working days amber,
  pending blue, reported green, not required grey). `ReportTaskController::row()` is the
  one task shape for the reports list, profile tab and dashboard.
- Mark reported: date (not future, not before the trigger) and reported-by required; SMS
  reference optional (prototype + acceptance test 6). Not required: reason required.
  Reopen for mistakes. All audited. Removing an absence removes its pending task; a
  completed task blocks removal (`ReportTasks::guardRemoval`).
- `ReportTasks::backfill()` ran in the migration for records made before Stage 4.
- Dashboard tiles via `App\Support\DashboardCounts` (cached 5 minutes per business and
  day; `forget()` on every task/employee write).
- End employment (pulled forward from Stage 6): last day + reason, portal off, delete-after
  dates, open document requests cancelled, P45 reminder. Stage 6 still has the
  Compliance check tab, the PDF pack and the monthly "due for deletion" review.

## What Stage 5 built (follow these conventions)

- `employee_requests` (compliance-rules §6): kind leave|sickness|change|document, dates or
  new value, employee note, linked upload, status pending|approved|declined, HR note, the
  absence created on approval. Employees see "Waiting for HR / Approved / Declined", plus
  "Action needed" for documents HR requested (`document_requests` still awaiting).
- **`App\Services\EmployeeRequests`** submits, previews and decides. Approving runs through
  the usual recorders (`AbsenceRecorder`, `EmployeeRecorder`, `DocumentVault`), so absences,
  change history, audit and Home Office tasks behave exactly as when HR enters them.
  The inbox shows `preview()` first (e.g. "Approving needs a Home Office report…").
- Portal uploads are stored with `documents.review_status = pending`; they do not count as
  on file until HR approves (`DocumentVault::file`). Declining deletes the file and HR's
  original request goes back to "Action needed".
- Sickness from the portal: over `self_cert_max_days` calendar days becomes fit-note
  sickness; an attached fit note is filed on approval. Visa change: updates visa expiry and
  follow-up check and reminds HR to do a new right-to-work check (time-limited bases only).
- Portal controllers extend `Portal\PortalController` (`employee()` = the signed-in user's
  own record; 403 if none). Employees see their own documents except recruitment evidence;
  every view is audited. `App\Support\LeaveBalance` = annual leave allowance/taken/pending.
- Employees can opt in to two-step sign-in under My details (turning it off needs the
  password). Dashboard "Employee requests" tile is live.
- Out of scope, not built: pay reviews and free-text "other" requests from the prototype.

## UI and performance rules ("modern and very fast")

- Build shared pieces once and reuse them: DataTable (server-side sort, filter,
  paginate), form fields, status Badge, page header, empty states, confirm dialog,
  toasts.
- Inertia link prefetch on hover; partial reloads (`only`) for tables and counts;
  optimistic UI for ticks; code-split per area.
- Eager-load; `Model::preventLazyLoading()` outside production. Cache dashboard
  counts per business for a short TTL and bust on write.
- Ctrl+K command palette (employees, screens, actions). Light and dark mode.
- Target: page changes feel instant; table requests under 200 ms server time.

## Non-negotiables

- Passport number, NI number, share code: `encrypted` casts; UI shows last 4 only.
- Documents on the private `local` disk (`storage/app/private`), served only through
  an authorised controller that writes an audit entry for every view/download.
  Never public URLs. (`serve` is off for the local disk.)
- Every change to an employee record is logged (field, old, new, who, when).
- Reportable events create a Home Office report task automatically; deadlines are
  in working days from the trigger date. Reportable rules apply to sponsored
  workers only; for everyone else, log only.
- Enforce the plan's employee limit (default 15) when adding employees.
- Tests for every rule in compliance-rules.md before the UI that uses it.
- Business admins must use 2FA (authenticator app, set up on first sign-in);
  optional for employees. Self-service password reset by email.
- No medical detail stored: absence reasons are short text, fit notes are files.
- Retention: delete-after dates when employment ends (end + 1 year; right-to-work
  evidence end + 2 years) and a monthly "due for deletion" review.
- UK English. Dates shown as "24 Sep 2026". Accessible: real labels, focus rings,
  44px targets. No N+1 queries (eager-load).

## Build order (one stage at a time; stop for Shaf's review after each)

1. ~~Foundation~~ — done.
2. ~~Employees~~ — done: work sites (Settings → Work sites); "Add employee" form driven by
   right-to-work basis (compliance-rules.md §1, prototype "Add employee" screen);
   Employees list; profile with Details tab; change history; encrypted fields;
   15-employee limit; employee gets a portal login (invite email via log mailer
   locally; set-password link). Sidebar: switch on Employees and Settings.
3. ~~Documents + absence~~ — done: document categories (§2), private uploads with expiry
   dates, "Request from employee"; absence types (§3), absence log,
   `AbsenceRules` service (unpaid limit, unauthorised streak) with tests,
   record-absence screen with the live Home Office check.
4. ~~Home Office reports~~ — done (plus End employment from Stage 6): task model, auto-creation from absences, reportable record
   changes, site changes and leavers (§4); "Mark reported" / "Not required";
   dashboard counts and deadlines.
5. ~~Employee portal~~ — done: home, my documents, leave and sickness, update my details,
   my requests, my details; admin "Requests" inbox with approve/decline side
   effects (§6).
6. End of employment, Compliance check tab (§8), compliance pack PDF.
7. Public website + AI chat assistant + sign-up + billing (Stripe via Cashier,
   PayPal, webhooks, suspension on failed payment) + the rest of the super-admin
   area (pricing, gateways, enquiries, AI assistant settings). See details below.
8. Reminders: scheduled jobs for expiries, deadlines and unexplained absences.

### Stage 7 details

- Pricing: £20/month for up to 15 employees; optional 1-to-1 training £49 per
  person; both editable by the super admin. Gateway keys entered by the super
  admin, encrypted at rest, shown masked (last 4) afterwards.
- AI chat on the public website: streamed replies from the Anthropic Messages API
  via a Laravel endpoint (key server-side only; fast, low-cost Claude model by
  default — check docs.claude.com for current model names). System prompt = fixed
  guardrails + super-admin-editable knowledge + live prices. Guardrails: product
  and general sponsor-duty questions only; no advice on individual immigration
  cases (refer to a regulated adviser); never invent features or prices; offer
  human handover. Lead capture creates an Enquiry ("From AI chat") with the
  transcript. Super admin controls: on/off, model, daily spend cap, knowledge
  text, recent conversations. Rate limits, bot protection, 90-day transcript
  retention.

### Also planned (fit into the stages above)

- Stage 2: invite emails with set-password link, self-service password reset,
  2FA for business admins, key personnel (Authorising Officer, Key Contact,
  Level 1 User) in Settings.
- Stage 3: absence export (CSV/PDF by employee, date range, type).
- Stage 4: manual "Create Home Office report" button; key-personnel and company
  changes create company-level tasks.
- Stage 7: failed-payment grace period, then suspension; price changes emailed
  30 days ahead to existing subscribers.
- Stage 8: alerts in compliance-rules §12; unexplained-absence alerts (§11) with
  the clock-in import stubbed.
- Before go-live: every acceptance test in compliance-rules §13 passes, and Shaf
  checks the rule defaults against current gov.uk sponsor guidance.

### Open questions (ask Shaf when the stage needs them)

1. Unpaid-leave year: calendar year from 1 January (default) or rolling 12 months?
   Build it as a setting either way.
2. Grace period after a failed payment (suggest 7 days).
3. Final product name and domain ("SponsorSafe" is a working name).

## Working agreement

- Before each stage, list the migrations, models, routes and pages you will add.
- After each stage: `php artisan migrate:fresh --seed`, `php artisan test`,
  `npm run build` (runs the TypeScript check), commit, then summarise what changed
  and how Shaf can try it (which demo login, which page).
- Extend `DemoSeeder` so every new screen has realistic content matching the
  prototype (Aisha Rahman, Rahul Mehta, Kasia Nowak, etc.).
- Ask before adding a package. Pre-approved if needed: barryvdh/laravel-dompdf
  (compliance pack), laravel/cashier (Stripe), anthropic-ai/sdk or plain HTTP for
  the chat.
- Deploying: `composer install --no-dev -o`, `npm run build`, zip without
  `node_modules`, `.env`; follow README-DEPLOY.md.
