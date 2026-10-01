# SponsorSafe — project brief and handover

SponsorSafe (working name) is a SaaS for small UK sponsor licence holders. It does
UKVI compliance only, not payroll or general HR. It keeps every record the Home
Office expects, tells the business when something must be reported on the Sponsor
Management System (SMS), and gives employees a simple portal.

Target customer: up to 10 employees (Starter £20 / 5, Standard £35 / 10; Corporate
by agreement above that), payroll done by an accountant, no technical knowledge.
Keep every screen minimal and plain-English.

Owner: Shaf (Enovtec, Southampton). Claude is the developer; Shaf reviews each stage.

## Status

- **Stage 1 (foundation): done and tested** — built in a Claude chat.
- **Stage 2 (employees): done and tested.**
- **Stage 3 (documents + absence): done and tested.**
- **Stage 4 (Home Office reports + end of employment): done and tested.**
- **Stage 5 (employee portal + Requests inbox): done and tested.**
- **Stage 6 (compliance check, compliance pack PDF, retention review): done and tested** — 193 PHPUnit tests passing.
- **Stage 7a (public website, pricing, enquiries): done and tested** — 203 PHPUnit tests passing.
- **Stage 7b part 1 (sign-up + Stripe billing): done and tested** — 219 PHPUnit tests passing.
- **Stage 7b part 2 (PayPal + price moves with 30 days' notice): done and tested** — 229 PHPUnit tests passing.
- **Plans by team size (client change, 1 Oct 2026): done and tested** — Starter / Standard / Corporate,
  upgrades and downgrades; 238 PHPUnit tests passing. Waiting for review.
- **Polish stage part 1 (toasts, Ctrl+K palette): done and tested** — 240 PHPUnit tests passing.
- **Polish stage part 2 (business details, admin logins, employee privacy notice): done and tested** — 248 PHPUnit tests passing.
- **Stage 8a (reminders and alerts, §12): done and tested** — 256 PHPUnit tests passing.
- **Stage 8b (unexplained absences, §11): done and tested** — 265 PHPUnit tests passing; all 13 go-live
  acceptance tests in compliance-rules §13 now have passing tests. Waiting for review.
- **Go-live preparation:** payments checked against real Stripe test mode and PayPal sandbox (3 fixes); full
  Privacy policy, Terms of service (with Article 28 data processing terms) and employee privacy notice written;
  company details from `.env` (`COMPANY_*`, `SUPPORT_EMAIL`); hosting SMTP in `deploy/production.env.example`.
  268 PHPUnit tests passing.
- **Change password and super admins (after go-live): done and tested** — Change password for admins, employees and
  super admins; super admins add other super admins from the super admin area. 281 PHPUnit tests passing.
- **Next:** Stage 7c (AI chat assistant).
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
   php.new's PHP ships without trusted certificates, so Laravel's HTTPS calls (PayPal, gov.uk)
   fail with "cURL error 60". Fix: download https://curl.se/ca/cacert.pem into the herd-lite `bin`
   folder and add `curl.cainfo` and `openssl.cafile` pointing at it (forward slashes) to its
   `php.ini`. Done on this laptop (backup: `php.ini.before-cacert`). Stripe is unaffected either way.
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
- `config/sponsorsafe.php` — ops path/IPs, plan defaults (Starter £20 / 5, Standard £35 / 10, £49
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
- Toasts and the Ctrl+K palette were built in the polish stage (see below); dashboard count caching in Stage 4.

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

## What Stage 6 built (follow these conventions)

- `App\Services\ComplianceCheck` is compliance-rules §8: rows done|check|missing|manual, plus
  `summary()` for the profile header ("Compliance: N to fix"). Needs documents, reportTasks and
  business loaded. Pending portal uploads do not count.
- `App\Services\CompliancePack`: one PDF per worker (`resources/views/pdf/compliance-pack.blade.php`):
  check, details (secrets last 4 only), documents list, absences, Home Office reports, change
  history. Export is audited (`employee.pack_exported`). PDFs use font subsetting (small files).
- `App\Services\Retention`: two-step deletion reviewed by the admin at `/app/retention`
  (linked from Settings and a dashboard notice): after `delete_after` everything except
  right-to-work evidence and the core record; after `rtw_delete_after` the whole record and
  login. Files are erased; the audit entry holds only the id and dates (no personal data).
  Stage 8 adds the monthly reminder email.

## What Stage 7a built (follow these conventions)

- Stage 7 is split in three, with a review after each: **7a** website + pricing + enquiries (done),
  **7b** sign-up and billing (Stripe via Cashier, PayPal, webhooks, grace period, gateways),
  **7c** AI chat assistant.
- Public website at `/` (`pages/Website/*`, `layouts/website-layout.tsx`): home (hero, features,
  who it is for, pricing and training, FAQ, contact), `/signup` placeholder until 7b, draft
  `/privacy` and `/terms` (final wording needed from Enovtec before go-live). Only Website pages
  may be indexed, and only in production (`app.blade.php`).
- `App\Support\Pricing`: the live plan (price, employee limit, training price) from
  `platform_settings` key `plan`, falling back to config. Super admin edits it (Plans and pricing,
  audited). Existing businesses keep the price and limit stored on them.
- Contact form → `enquiries` table + `NewEnquiry` email to `SUPPORT_EMAIL`. Bot protection: hidden
  honeypot field, signed start time (at least 3 seconds), 5 per 10 minutes per IP.
- Super admin layout has the sidebar (Businesses, Plans and pricing, Payment gateways (Soon),
  Enquiries and training with a new count, AI chat assistant (Soon)); shared `ops.newEnquiries`.
- On the website use `$request->user('web')`, never the default guard (the super admin guard
  may be active).

## What Stage 7b part 1 built (follow these conventions)

- Stripe via Laravel Cashier; **the Business is the billable model** (`Cashier::useCustomerModel`).
  `subscriptions.business_id`; Stripe customer columns on `businesses`. Currency GBP.
- Business statuses: `active`, `suspended` (with `suspended_reason` manual | payment | cancelled) and
  `pending` (signed up, not paid; nobody can sign in). `grace_ends_on` set = payment failed, still open.
- `App\Billing\Gateways`: keys entered by the super admin (Payment gateways), each encrypted in
  `platform_settings`, only ever shown as `••••` + last 4. Falls back to `STRIPE_*` in `.env`.
  Routes that talk to Stripe use the `stripe` middleware (`ApplyStripeKeys`); commands call
  `Gateways::applyStripe()`.
- **Every Stripe API call is in `App\Billing\StripeGateway`** (tests bind `Tests\Fakes\FakeStripeGateway`).
  It creates the monthly Price in Stripe on first use per amount and mode (`platform_settings.stripe_prices`).
- **Every subscription state change goes through `App\Billing\Subscriptions`**: `start` (pending business +
  admin; an unpaid repeat sign-up with the same email is reused), `paid` (idempotent: activates, sends
  `WelcomeSubscriber` with a 7-day set-password link, clears grace, reopens a payment/cancel suspension but
  never a manual one), `failed` (grace once, `Pricing::current()['grace_days']`, default 7, `PaymentFailed`
  email), `cancelled`, `daily` (`billing:check` at 06:00: suspend after grace, remove sign-ups unpaid for
  `abandoned_signup_days`). All audited (`billing.*`).
- Sign-up: `/signup` → pending business → Stripe Checkout (hosted) → `/signup/done?session_id=` which
  confirms the payment with Stripe (never trusts the URL). Honeypot + `App\Support\FormToken` + throttle.
- Webhook `POST /stripe/webhook` (`StripeWebhookController` extends Cashier's): signature always required,
  403 while no secret is saved. Handles checkout.session.completed, invoice.paid, invoice.payment_failed,
  customer.subscription.deleted, plus Cashier's own sync events.
- Admin: Settings → Subscription (Manage billing = Stripe customer portal, Book 1-to-1 training) and a
  red "payment failed" banner (`billing` shared prop). Super admin: Payment gateways, grace days in Plans
  and pricing, billing states in Businesses. PayPal shows "Coming soon" until part 2.
- Local testing: Stripe test keys in Payment gateways; the return page activates without webhooks. For
  failed payments locally, forward webhooks with the Stripe CLI
  (`stripe listen --forward-to 127.0.0.1:8000/stripe/webhook`, then save its `whsec_` secret).

## What Stage 7b part 2 built (follow these conventions)

- PayPal subscriptions with plain HTTP (no package). **Every PayPal call is in `App\Billing\PayPalGateway`**
  (tests use `Http::fake` + `Http::preventStrayRequests`); failures throw `PayPalException` (safe message).
  Keys in `Gateways::paypal()` (client ID, secret, webhook ID, sandbox|live), encrypted like Stripe's.
  The billing plan is created automatically per amount and mode (`platform_settings.paypal_plans`), so the
  prototype's "plan ID" box became "Webhook ID" (PayPal needs it to verify webhooks).
- Sign-up with PayPal: pending business stores `paypal_subscription_id` + `paypal_plan_id` → PayPal approval →
  `/signup/paypal/done?subscription_id=` activates only if PayPal says ACTIVE and it is that business's
  subscription. Webhook `POST /paypal/webhook` (`PayPalWebhookController`): verified with PayPal's
  verify-webhook-signature first; ACTIVATED / PAYMENT.SALE.COMPLETED → `paid`, PAYMENT.FAILED / SUSPENDED →
  `failed`, CANCELLED / EXPIRED → `cancelled`. Manage billing for PayPal → PayPal's automatic payments page.
- Price moves: super admin Plans and pricing → Existing subscribers → "Email N and move them on {date}" →
  `Subscriptions::schedulePriceChange()` sets `price_change_pence/limit/on` (+30 days) and sends
  `PriceChangeNotice`. `billing:check` applies due moves: Stripe `swap` without proration, PayPal
  update-pricing-schemes once per shared plan; a gateway error leaves it scheduled for the next day.
  Admins see "From {date}: £X per month" on the subscription card.

## Plans by team size (client change, 1 Oct 2026; follow these conventions)

- `App\Support\Pricing`: tiers `starter` (£20 / 5) and `standard` (£35 / 10) in `platform_settings.plan.tiers`
  (super admin edits both); above the largest limit is **Corporate** (`Pricing::CORPORATE`), a price and
  limit set per business by the super admin. `Pricing::tier()`, `tierFor($employees)`, `forDisplay()`.
- `businesses.plan`: starter | standard | corporate | null = the original single plan (£20 / 15), kept
  until moved. The business's own `plan_price_pence` / `employee_limit` are what it pays and may use.
  `Business::limitMessage()` / `nextTier()` drive the "plan full" message (upgrade, or Corporate).
- Sign-up: "Number of employees" picks the tier (1–5 Starter, 6–10 Standard, more than 10 → Corporate
  contact). Website: three plan cards; contact topic "Corporate package".
- `Subscriptions::changePlan()` (Settings → Subscription): limit now, price from the next payment
  (Stripe swap without proration; PayPal `revise` → customer approves → `confirmPaypalPlan()` only when
  PayPal shows the new plan; invoiced businesses just change). Downgrade only if current employees fit.
  `setPlan()` = super admin Businesses → Set plan (tiers or Corporate); refuses a PayPal price change.
- Price moves are per tier: `moveCandidates()` targets today's price for the business's tier, or for the
  original plan the tier that fits its current employees (none above 10 → "Needs a Corporate price").

## What the polish stage built (follow these conventions)

- **Toasts** (`components/toaster.tsx`, mounted once in `app.tsx`): every Inertia visit's `flash.success` /
  `flash.error` pops up bottom-right; success fades after 5 s, errors stay until closed. Keep using
  `->with('success'|'error', …)` on the server; no banner component any more. `Auth/*` pages keep their
  inline messages; the website contact form keeps its own "message sent" card (`flash.contactSent`).
- **Ctrl+K / ⌘K palette** (`components/command-palette.tsx`, in `app-layout.tsx` with a header Search
  button): employees from `GET /app/palette` (JSON, this business only, fetched on open), screens and actions.
  New admin screens or actions belong in its `screens` / `actions` lists. `/app/reports?create=1` opens the
  create-report form.
- **Business details** (Settings → Business → Change): name, licence number, phone, `registered_address`.
  A new name, or a change to an existing address, creates a company task via `ReportTasks::forBusinessChange`
  (source `business`, company deadline); a first address, licence or phone does not. Audited.
- **Admin logins** (Settings → Admin logins, `App\Services\BusinessAdmins`): invite another admin (`AdminInvite`
  email, 7-day set-password link, 2FA on first sign-in), resend until they first sign in, remove — never
  yourself, never the last admin. Routes scope `{admin}` to the signed-in admin's business (404 otherwise).
- **Employee privacy notice** at `/me/privacy` (`Portal/Privacy`), linked at the foot of every portal page;
  retention periods read from the business's rules. Draft wording until Enovtec supplies the final text.

## What Stage 8a built (follow these conventions)

- `App\Services\Reminders` is compliance-rules §12: `current($business)` = everything needing attention now
  (visa / permission expiry at each `expiry_alert_days` lead time and when expired; follow-up check
  `follow_up_alert_days` before and when overdue; passport `passport_alert_days` before and when expired; pending
  Home Office tasks `task_alert_working_days` working days before and when overdue; leavers' records due for
  deletion, monthly). Lead times are business rules (config defaults 90/60/30, 30, 90, 5).
- `reminders` table records each stage emailed (`type`, `subject` "employee:12", `stage` "60@2026-12-10"), so every
  stage is sent once; a new expiry date starts again. `reminders:send` (daily 07:00) sends one `ComplianceDigest`
  email per active business to its active admins. Dashboard "Coming up" card shows the expiry/check items.

## What Stage 8b built (follow these conventions)

- `employees.work_days` (["mon".."sun"], null = Monday to Friday): set on Add employee and on the profile
  (Details → Employment → Working days, logged in the history). `Employee::scheduledOn($date, $wd)` = a usual
  day that is not a bank holiday.
- `clock_ins` (one row per person per day, earliest time) and `unexplained_absences` (open | absence | worked).
  **`App\Services\UnexplainedAbsences`**: `importCsv` (columns email, date, optional time; Y-m-d or d/m/Y),
  `scan($business, $date)` flags scheduled people with no clock-in and no absence — only when the check is on
  (`clock_in_check` rule, Settings → Clock-in check) **and** the business has clock-in data for that day; a late
  clock-in clears the alert. `AbsenceRecorder::record` calls `resolveCoveredBy()`, so recording an absence for the
  day classifies it. "Worked – clock-in missed" = `markWorked`.
- The live clock-in integration is stubbed: `App\Services\ClockIns\ClockInSource` (bound to `NoClockInSource` in
  AppServiceProvider); `absences:check-clock-ins` (daily 20:00) fetches from it and checks today.
- Dashboard "Unexplained absences" (prototype wording): **Classify absence** opens Record absence with
  `?unexplained=ID` (pre-filled unauthorised for that day, with the prototype's note); badge red after
  `unexplained_red_after_days` (2) working days. Open alerts are in the next morning's reminder email.

## Go-live conventions (payments and legal)

- Saved Stripe prices / PayPal plans are re-checked with the gateway before use (gone, inactive or wrong
  amount → a new one is created). Stripe plan changes find the live subscription through Stripe's API, never
  Cashier's local table (only webhooks fill that). PayPal returns amounts like "35.0": compare in pence.
- Cancelling keeps access until the end of the paid period (terms of service): `Subscriptions::cancelled()` sets
  `businesses.access_ends_on` = next payment date when there is paid time left (PayPal ends subscriptions at
  once; Stripe at period end); `billing:check` suspends on that date. Settings and super admin show "Cancelled".
- `/privacy` and `/terms` (`Website/Legal.tsx`) and the employee notice (`Portal/Privacy.tsx`) take the company
  name, number, address, ICO number, contact email and "last updated" date from `config('sponsorsafe.company')`
  (`WebsiteController::company()`), and prices / grace days live. If the service changes what it stores, who it
  shares with, retention or billing, update this wording and `legal_updated` in the same change.

## Change password and super admins (follow these conventions)

- **Every signed-in password change goes through `App\Services\PasswordChange`**: current password, plus the authenticator
  code when two-step sign-in is on (always for admins and super admins); new password min 8 (super admins 12), confirmed,
  not the current one; 5 wrong tries per 5 minutes. New remember-me token, audited (`auth.password_changed`), and a
  `PasswordChanged` email. Screens share `components/change-password.tsx`: Settings → Your password (`PUT
  /app/settings/password`, palette "Change my password"), My details → Change password (`PUT /me/security/password`),
  super admin My account in the header (`PUT /{ops}/account/password`).
- `EndSessionsAfterPasswordChange` (web group, prioritised before `auth`) keeps a fingerprint of the password hash per
  guard (web, ops) in the session; any password change (Change password, reset link, `ops:create-admin`) signs out every
  other session on its next request, while the session that made the change keeps going.
- Super admins page (`/{ops}/super-admins`, `App\Services\SuperAdmins`): invite by email (`SuperAdminInvite`, 7-day
  single-use link to `/{ops}/set-password/{token}`, hash stored on `super_admins.password_token`), resend until first
  sign-in, remove — never yourself, never the last. New super admins still need their IP in `OPS_ALLOWED_IPS` (the page
  shows the list and your current IP). `php artisan ops:create-admin` still works as the server-side fallback.

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
- Enforce the plan's employee limit (Starter 5, Standard 10, Corporate as agreed) when adding employees.
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
6. ~~End of employment, Compliance check tab (§8), compliance pack PDF~~ — done (plus the retention review).
7. Public website + AI chat assistant + sign-up + billing (Stripe via Cashier,
   PayPal, webhooks, suspension on failed payment) + the rest of the super-admin
   area (pricing, gateways, enquiries, AI assistant settings). See details below.
8. Reminders: scheduled jobs for expiries, deadlines and unexplained absences.

### Stage 7 details

- Pricing: Starter £20/month up to 5 employees, Standard £35 up to 10, Corporate agreed; optional 1-to-1 training £49 per
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
2. ~~Grace period after a failed payment~~ — built as 7 days by default, editable in Plans and pricing.
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
