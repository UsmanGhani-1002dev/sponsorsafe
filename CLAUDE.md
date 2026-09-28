# SponsorSafe — project brief and handover

SponsorSafe (working name) is a SaaS for small UK sponsor licence holders. It does
UKVI compliance only, not payroll or general HR. It keeps every record the Home
Office expects, tells the business when something must be reported on the Sponsor
Management System (SMS), and gives employees a simple portal.

Target customer: up to 15 employees, payroll done by an accountant, no technical
knowledge. Keep every screen minimal and plain-English.

Owner: Shaf (Enovtec, Southampton). Claude is the developer; Shaf reviews each stage.

## Status

- **Stage 1 (foundation): done and tested** — built in a Claude chat, 20 PHPUnit tests passing.
- **Next: Stage 2 (employees).** See "Build order" below.
- On the very first session on this laptop: complete "Local setup" below, run the
  tests, then `git init` and commit everything as "Stage 1 baseline" before
  changing any code.

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
   php artisan test
   ```
   `composer run setup` installs packages, creates `.env`, runs migrations with
   demo data, and builds the frontend.
5. Run the app: `php artisan serve` in one terminal and `npm run dev` in another
   (or `composer run dev`). Open http://127.0.0.1:8000/login

Demo logins (password `password`, local only):
- hr@demo-retail.example — business admin (Demo Retail Ltd)
- aisha.rahman@demo-retail.example — employee
- hr@demo-cafe.example — suspended business (shows the paused message)
- Super admin: http://127.0.0.1:8000/ops-local/login — owner@sponsorsafe.example.
  First sign-in shows a key for an authenticator app (Google/Microsoft Authenticator).

## Reference material

- `docs/compliance-rules.md` — every rule, threshold and deadline. Source of truth.
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
- UK English. Dates shown as "24 Sep 2026". Accessible: real labels, focus rings,
  44px targets. No N+1 queries (eager-load).

## Build order (one stage at a time; stop for Shaf's review after each)

1. ~~Foundation~~ — done.
2. **Employees**: work sites (Settings → Work sites); "Add employee" form driven by
   right-to-work basis (compliance-rules.md §1, prototype "Add employee" screen);
   Employees list; profile with Details tab; change history; encrypted fields;
   15-employee limit; employee gets a portal login (invite email via log mailer
   locally; set-password link). Sidebar: switch on Employees and Settings.
3. Documents + absence: document categories (§2), private uploads with expiry
   dates, "Request from employee"; absence types (§3), absence log,
   `AbsenceRules` service (unpaid limit, unauthorised streak) with tests,
   record-absence screen with the live Home Office check.
4. Home Office reports: task model, auto-creation from absences, reportable record
   changes, site changes and leavers (§4); "Mark reported" / "Not required";
   dashboard counts and deadlines.
5. Employee portal: home, my documents, leave and sickness, update my details,
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
