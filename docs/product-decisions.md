# Product decisions (full record)

Everything Shaf and Claude agreed while designing SponsorSafe in chat
(24–28 Sep 2026), in one place. `CLAUDE.md` is the working brief;
`compliance-rules.md` holds the rule numbers; this file holds the product
decisions behind them. When they disagree, ask Shaf.

## 1. Positioning and scope

- A SaaS for small UK sponsor licence holders. **Purely UKVI compliance.**
- Customer: up to 15 employees; payroll and accounts are done by an outside
  accountant; no HR department; no technical knowledge.
- Every screen minimal: only what is really needed. Plain English, UK spelling,
  dates as "24 Sep 2026".
- It records, reminds and tells the business what to report and by when. The
  business reports on the Sponsor Management System (SMS) itself and ticks it off
  here. It never files anything with the Home Office.
- It is not legal advice. The website footer and the AI chat say so. Rule
  defaults reflect sponsor guidance as understood in mid-2026 and must be checked
  against the current gov.uk guidance (Workers and Temporary Workers sponsor
  guidance, Appendix D) before go-live. Every threshold is a setting.
- **Out of scope (removed on purpose):** payroll, pay reviews/increments,
  messages, notices, letters, contract acknowledgements, general HR
  (disciplinary, grievance, appraisals), timesheets.
- Payslips: the business uploads the payslips its accountant sends, as evidence
  of pay. Employees can view their own. No payroll features.
- Working name "SponsorSafe" (may change). Operated by Enovtec, Southampton.

## 2. Areas and who uses them

| Area | URL | Users |
| --- | --- | --- |
| Public website | `/` | Visitors: features, pricing, training, contact, sign-up, AI chat |
| Business admin | `/app` | The business's admin(s) |
| Employee portal | `/me` | Employees, mobile-first |
| Super admin | secret path (`OPS_PATH`) | Platform owner only |

Each business is a separate tenant: its own admin login(s), employees, documents,
absences, requests, work sites and Home Office tasks. Nobody sees another
business's data.

## 3. Sign-in

- Businesses and employees share one page: enter email → **Next** → the account
  card shows name, email, business and role with a **"Not you?"** link → enter
  password → go to their own dashboard (admin → `/app`, employee → `/me`).
- Unknown email: clear message ("ask your employer to invite you"). Lookups and
  passwords are rate-limited.
- Suspended business: its users cannot sign in ("Access for X is paused. Please
  contact support…") and are signed out if already signed in.
- Forgot password: self-service reset link by email (not built yet).
- Employees are invited by their employer (invite email with a set-password link).
- **Super admin**: completely separate table, guard and login at a secret URL
  that is never linked anywhere; IP allow-list (others get 404); password +
  authenticator-app code (mandatory); every action audit-logged.
- **Business admins: 2FA required** (authenticator app), set up on first sign-in.
  Optional for employees.

## 4. Plans, billing and sign-up

- One plan: **£20 per month for up to 15 employees**. No setup fee, no contract,
  cancel any time. Adding a 16th active employee is blocked with "Your plan covers
  up to 15 employees. Contact us to add more."
- Optional **1-to-1 training: £49 per person** (online session: set up the business,
  add first employees, show the monthly routine). Booked from the website contact
  form or from Settings → Subscription.
- Payments by **Stripe** (card, via Laravel Cashier) or **PayPal** subscriptions.
- Super admin can change the price, employee limit and training price. New
  prices show on the website straight away; existing subscribers keep their price
  until moved and are emailed 30 days before any change.
- Super admin enters Stripe keys (publishable, secret, webhook secret, test/live)
  and PayPal credentials (client ID, secret, plan ID, sandbox/live). Secrets are
  encrypted at rest and only the last 4 characters are ever shown again.
- Sign-up form: business name, sponsor licence number, your name, your email
  (becomes the login), phone, number of employees (1–5, 6–10, 11–15, more than
  15 → "contact us"), pay by card or PayPal, agree to terms and privacy policy →
  secure payment → email with a set-password link.
- Webhooks activate the business; a failed payment starts a **grace period**
  (length is an open question, see §12), then the business is suspended. Data is
  kept while suspended.
- Super admin can suspend or activate any business by hand.

## 5. Public website (see `prototype-website.dc.html`)

- Header: logo, Features, Pricing, Training, Contact, **Log in**, **Start subscription**.
- Hero: "Keep your sponsor licence safe, without the paperwork." Buttons: start for
  £20 a month, book a free demo. Ticks: up to 15 employees, no setup fee, cancel any time.
- Features (6): right-to-work checks; absence rules built in; Home Office
  deadlines; document vault; employee app; audit-ready in one click.
- Who it's for: small sponsors whose accountant runs payroll. States clearly
  that it is not payroll or accounts software.
- Pricing: the plan card and the training card.
- FAQ: does it report for me (no); do I need payroll software (no); is our data
  safe (UK, encrypted, logged); more than 15 employees (contact us).
- Contact form: name, email, phone (optional), topic (general question, book a free
  demo, 1-to-1 training, existing customer support), message → creates an Enquiry
  in the super admin area; reply promised within one working day.
- Footer: not-legal-advice line; "A service by Enovtec, Southampton".
- AI chat bubble (§6).

## 6. AI chat assistant

- "Ask a question" bubble on the website. Suggested questions, streamed replies,
  "AI assistant. General information only, not legal advice."
- Laravel endpoint → Anthropic Messages API with streaming; API key server-side
  only. Fast, low-cost Claude model by default (check docs.claude.com for current
  model names and prices).
- System prompt = fixed guardrails + knowledge text the super admin edits + live
  price, employee limit and training price from settings.
- Guardrails: only the product and general sponsor-duty questions; **no advice on
  individual immigration cases** ("Can I sponsor my cousin?" → refer to a regulated
  immigration adviser or solicitor); never invent features or prices; offer a
  human when unsure.
- Buying intent → show a **Start subscription** button. Demo/training/person →
  collect name, then email → create an Enquiry with topic "From AI chat" and the
  transcript.
- Super admin page: on/off, model (fast / smarter), **daily spending cap** (when
  reached, stop answering and show the contact form), knowledge text, stats (chats
  today, leads, spend), recent conversations with outcome (answered / lead
  captured / referred).
- Per-IP and per-session rate limits, bot protection (e.g. Cloudflare Turnstile),
  maximum message length, transcripts deleted after 90 days, mentioned in the
  privacy policy.

## 7. Super admin area (see `prototype-superadmin.dc.html`)

Menu: **Businesses** (stats: active, suspended, monthly revenue, employees
managed; table with admin, staff used/limit, payment method, next due, status,
Suspend/Activate) · **Plans and pricing** · **Payment gateways** · **Enquiries and
training** (mark handled) · **AI chat assistant**.

## 8. Business admin area (see `prototype-app.dc.html`)

Menu (six items only): **Dashboard · Employees · Absence · Home Office reports ·
Requests · Settings**.

- **Dashboard**: stat tiles (reports pending, due within 5 working days,
  employee requests waiting, visas expiring in 90 days); unexplained absences
  from the clock-in system with "Classify absence" / "Worked – clock-in missed";
  right-to-work watchlist (time-limited permission, expiry, unpaid days used);
  next Home Office deadlines.
- **Employees**: list (name, site, job title, right-to-work status, expiry badge,
  documents x of y, Home Office status) and **+ Add employee**.
  - Add employee form is driven by the right-to-work basis (compliance-rules §1),
    with "Fill with dummy data" in demo/local only. Blocks saving if the check date
    is after the start date or the visa expires before the start date. Shows the
    documents this employee will need. Sends the portal invite and a passport/ID
    request.
  - Profile header: name, job, site, start date; badges for status, documents,
    expiry, Home Office, compliance; **Export compliance pack (PDF)**; **End
    employment**.
  - Profile tabs: **Details · Compliance check · Documents · Absence · Home
    Office · History**. Documents tab: required/optional categories, files with
    uploader, date and expiry badge, "Request from employee", upload with
    category and expiry. History tab: every change, plus "Record a change" that
    says before saving whether the change is reportable.
- **Absence**: absence log and **Record absence** with a live "Home Office check"
  panel (No report needed / No report yet / Report to Home Office with the
  deadline). "Tick as reported" from the log. Export CSV/PDF by employee, date
  range and type.
- **Home Office reports**: all tasks (pending first by deadline, then done);
  "Mark reported" (date reported, reported by, SMS reference — required) or
  "Not required" (reason required). Manual "Create Home Office report" for
  events the rules don't catch (e.g. suspected breach of visa conditions).
- **Requests**: "Waiting for you" (approve/decline, with a preview of whether
  approving triggers a Home Office report), "Waiting for employees" (document
  requests not yet uploaded), "Recently decided".
- **Settings**: business details (name, licence number, Authorising Officer,
  **key personnel: Authorising Officer, Key Contact, Level 1 User(s)** — changes
  create a company-level task), subscription card (price, employees used of limit
  with a bar, next payment, payment method, Manage billing, Book 1-to-1 training),
  **Work sites** (list with SMS status; add new work address; move an employee
  to another site), rule settings (§9 of compliance-rules).

## 9. Employee portal (mobile-first)

Menu: **Home · My documents · Leave and sickness · Update my details · My
requests · My details**.

- Home: annual leave left (5.6 weeks pro rata minus taken and pending), documents
  HR needs, right-to-work status and reminder, quick actions (request leave,
  report sickness, update my address), recent requests.
- My documents: requested by HR (upload and send), on file, send another document
  (passport/ID, fit note, other). Payslips uploaded by the business appear here.
- Leave and sickness: annual, unpaid, compassionate, training, report sickness.
  Warns about unpaid leave affecting sponsorship, fit note needed after 7 days,
  more annual leave than left.
- Update my details: address, phone, email, name, **new visa or extension** (new
  expiry; HR does a new right-to-work check with the share code).
- My requests: status (Waiting for HR / Approved / Declined / Action needed) and
  HR's response.
- My details: personal, job, right to work.

## 10. Design (approved)

- Clean SaaS: white surfaces on #F9FAFB, borders #EAECF0/#D0D5DD, text
  #101828/#344054/#667085, **one accent: indigo #4F46E5** (strong #3730A3, soft
  #EEF2FF). Font **Geist** (Geist Mono for numbers/file names).
- Soft outlined status badges: red (overdue/missing), amber (due soon/check),
  green (done/reported), grey (not required/info), blue (neutral).
- **Modern and very fast**: Inertia link prefetch on hover; partial reloads for
  tables and counts; optimistic ticks; code-split per area; server-side
  sort/filter/paginate tables; no N+1 (`preventLazyLoading` in local); cache
  dashboard counts per business briefly and bust on write; target page changes
  that feel instant and table responses under 200 ms.
- **Ctrl+K command palette** (jump to any employee, screen or action).
- Light and dark mode.
- Accessible: real buttons and labels, visible focus rings, 44px touch targets,
  4.5:1 contrast, no emoji icons (Lucide).

## 11. Security and GDPR

- Passport number, NI number, share code: encrypted casts; UI shows last 4 only.
- Documents on the private disk only, served through an authorised controller;
  every view and download written to the audit log. No public URLs.
- Every employee-record change logged (field, old, new, who, when).
- Audit log also covers sign-ins, suspensions, price/gateway changes, exports.
- Data minimisation: absence reasons are short text; **no medical detail or
  diagnosis fields**; fit notes are files only.
- Retention: when employment ends, set delete-after dates (sponsor records
  end + 1 year, right-to-work evidence end + 2 years; settings). Monthly
  "due for deletion" list for the admin to review and permanently delete.
- Hosting in the UK/EEA; HTTPS only; secure, encrypted sessions; backups encrypted
  and off-server, and subject to the same deletions. A small VPS is recommended
  over shared cPanel hosting for this data; cPanel works if isolated.
- Privacy notice for employees and a website privacy policy (mention the AI chat).

## 12. Open questions for Shaf

1. Unpaid-leave limit year: calendar year from 1 January (current default) or a
   rolling 12 months? Keep it a setting either way.
2. Grace period after a failed payment before suspension: suggest 7 days.
3. Final product name and domain.
4. 2FA for employees: currently optional.
