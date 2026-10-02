# SponsorSafe – full test plan

A step-by-step checklist for testing the whole system: the public website, the super admin, business admins,
employees, every email, scheduled jobs and security. Work through the sections in order: later sections use
the accounts and data created in earlier ones.

For each check, follow the **Steps**, compare with **Expected**, and tick **Pass** (✅) or write what happened
instead (❌ + note). Report failures with the check ID (for example `BA-24`), a screenshot, the time, and the
email address you were signed in with.

Last updated: 2 Oct 2026.

---

## 0. Before you start

### 0.1 Which site are you testing?

| | Live site | Local / test copy |
|---|---|---|
| Address | `https://sponsersafe.codely.quest` (the "sponser" spelling) | `http://127.0.0.1:8000` |
| Demo data | None. You create everything yourself (sections 2–3). | Demo businesses and people (0.4) |
| Emails | Real emails to real inboxes | Not sent: written to `storage/logs/laravel.log` |
| Payments | Use **test / sandbox mode** keys until launch (0.3) | Test mode keys |

**Never test with real customer data, and never use a real card on the live site while testing.**

### 0.2 What you need

- A phone with an authenticator app: **Google Authenticator** or **Microsoft Authenticator**.
- 4–5 email inboxes you can open. One Gmail account is enough: Gmail delivers `you+anything@gmail.com` to
  `you@gmail.com`, so use for example:
  - `you+owner@gmail.com` – super admin
  - `you+admin@gmail.com` – business admin (the person who signs up)
  - `you+admin2@gmail.com` – second business admin
  - `you+emp1@gmail.com`, `you+emp2@gmail.com` – employees
  - `you+super2@gmail.com` – second super admin
- Two different browsers (for example Chrome and Firefox), or one normal and one private window, so you can be
  signed in as two people at once.
- A few small test files: a PDF, a JPG and a PNG under 10 MB, and one file over 10 MB (or a `.docx`) to check that
  wrong files are refused.
- From the site owner:
  - the super admin address, `https://…/<OPS_PATH>/login` (secret; not linked anywhere);
  - your IP address added to `OPS_ALLOWED_IPS` (find yours at https://whatismyipaddress.com);
  - your super admin login, or ask to be invited (SA-30).
- For server checks (section 6): cPanel → Terminal access, or someone who has it.

### 0.3 Test payment details (test / sandbox mode only)

| What | Value |
|---|---|
| Stripe card that works | `4242 4242 4242 4242`, any future date (e.g. `12/34`), any CVC (`123`), any postcode |
| Stripe card that is declined | `4000 0000 0000 0002` |
| Stripe card that needs 3-D Secure | `4000 0025 0000 3155` (approve on the test screen) |
| PayPal | A **sandbox personal account** from developer.paypal.com → Testing tools → Sandbox accounts |

### 0.4 Demo logins (local / test copy only; password `password`)

| Who | Email | Notes |
|---|---|---|
| Business admin | `hr@demo-retail.example` | Demo Retail Ltd, Standard plan. Authenticator set up on first sign-in |
| Business admin | `hr@demo-catering.example` | Demo Catering Ltd, Starter plan, payment failed (grace period) |
| Suspended business | `hr@demo-cafe.example` | Shows the "paused" message |
| Employee | `aisha.rahman@demo-retail.example` | Sponsored worker |
| Employee | `kasia.nowak@demo-retail.example` | Has a passport request waiting |
| Super admin | `owner@sponsorsafe.example` at `/ops-local/login` | Authenticator set up on first sign-in |

### 0.5 Where emails go

- **Live site:** the inbox of the address used. Also check the spam folder. If an email goes to spam, report it:
  the hosting email's SPF/DKIM may need fixing (cPanel → Email Deliverability).
- **Local copy:** open `storage/logs/laravel.log` and search for the subject line. Links in the email work if you
  copy them into the browser.

---

## 1. Public website

| ID | Steps | Expected | Pass |
|---|---|---|---|
| WEB-01 | Open the home page. | Page loads quickly. Header: logo, Features, Pricing, Training, Contact, **Log in**, **Start subscription**. | |
| WEB-02 | Click Features, Pricing, Training, Contact in the header. | The page scrolls smoothly to each section. | |
| WEB-03 | Look at Pricing. | Three cards: **Starter £20 / up to 5**, **Standard £35 / up to 10**, **Corporate** (more than 10, contact us). Training card **£49 per person**. | |
| WEB-04 | Open each FAQ question. | Each answer opens and closes. | |
| WEB-05 | Footer. | "Not legal advice" line, "A service by Enovtec, Southampton", links to Privacy and Terms. | |
| WEB-06 | Open **/privacy** and **/terms**. | Full wording, table of contents, company name/number/address, contact email and "Last updated". No blank placeholders. | |
| WEB-07 | Phone check: open the home page on a phone (or narrow the browser to phone width). | Nothing cut off, no sideways scrolling, menu usable. | |
| WEB-08 | Light/dark: switch your computer to dark mode and reload. | Site follows the setting and stays readable. | |
| WEB-09 | **Contact form**, empty: press Send with nothing filled in. | Clear messages under name, email and message. Nothing sent. | |
| WEB-10 | Contact form with a wrong email (`abc`). | "Email" error. | |
| WEB-11 | Fill the form properly (topic **Book a free demo**) and send. Wait at least 3 seconds after the page loads before sending. | "Message sent" card with your first name. | |
| WEB-12 | Check the support inbox (`SUPPORT_EMAIL`). | Email **"New enquiry: Book a free demo – {name}"** with the message. (Email E-01) | |
| WEB-13 | Send the contact form 6 times within 10 minutes. | The 6th is refused ("too many requests"). | |
| WEB-14 | Click **Corporate → Contact us** on the pricing card. | Contact form opens with topic **Corporate package** already chosen. | |
| WEB-15 | Click **Book 1-to-1 training**. | Contact form with topic **1-to-1 training**. | |
| WEB-16 | Search engines: on the live site, view page source (Ctrl+U) of the home page and of `/login`. | Home, Sign-up, Privacy and Terms have a description and no `noindex`; `/login`, the app, portal and super admin have `<meta name="robots" content="noindex">`. | |

### 1.1 Sign-up and payment

Use test / sandbox mode (0.3). Each sign-up creates a new business.

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SU-01 | Click **Start subscription**. | Sign-up form: business name, sponsor licence number, your name, your email, phone, number of employees, card or PayPal, agree to terms and privacy. | |
| SU-02 | Press continue with nothing filled in. | Clear errors on each required field; terms tick required. | |
| SU-03 | Number of employees: **More than 10**. | Summary says "Corporate · price agreed with you", a note links to the contact form (topic Corporate package), and the pay button is switched off. | |
| SU-04 | Fill everything: business **Test Shop Ltd**, email `you+admin@gmail.com`, employees **1–5**, **Card**. Continue. | Stripe's secure payment page opens showing **£20.00 per month**. | |
| SU-05 | On Stripe, use the declined card `4000 0000 0000 0002`. | Stripe shows "card declined". Nothing is activated. | |
| SU-06 | Use `4242 4242 4242 4242` and pay. | You return to a "You're all set" page telling you to check your email. | |
| SU-07 | Inbox `you+admin@gmail.com`. | Email **"Welcome to SponsorSafe, set your password"**. (E-02) | |
| SU-08 | Click the link, set a password (8+ characters, typed twice). | You're asked to set up the authenticator app: scan the QR code or type the key, then enter the 6-digit code. You land on the dashboard. | |
| SU-09 | Click the same email link again. | "This link has expired or was already used" (links work once). | |
| SU-10 | Second sign-up: **Test Cafe Ltd**, `you+paypal@gmail.com`, employees **6–10**, **PayPal**. | PayPal sandbox opens showing **£35.00 per month**. Approve with the sandbox personal account. You return to the "all set" page and get the welcome email. | |
| SU-11 | Start a sign-up, open the Stripe page, then close it without paying. | No email; you can't sign in with that email ("set your password" never arrived). The unpaid sign-up is removed automatically after 7 days. | |

---

## 2. Super admin

Sign in at `https://…/<OPS_PATH>/login` from an allowed IP address.

### 2.1 Access and sign-in

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SA-01 | Open the super admin address from an IP **not** in the allow-list (e.g. phone on mobile data, Wi-Fi off). | Plain **404 Not Found**. Nothing reveals that a login exists there. | |
| SA-02 | From an allowed IP, open the address. | "Restricted access" sign-in page. | |
| SA-03 | Enter a wrong password. | "Those details are not right." | |
| SA-04 | Enter a wrong password 6 times. | "Too many attempts. Try again later." | |
| SA-05 | Correct email and password, first time. | Authenticator setup screen with a QR code / key. Add it, enter the code. You land on **Businesses**. | |
| SA-06 | Log out, sign in again with a **wrong** 6-digit code. | "That code is not right." | |
| SA-07 | Sign in with the right code. | Businesses page. Header: "Super admin · 2FA verified", **My account**, **Log out**. | |
| SA-08 | Sign in as a business admin in the same browser, then open the super admin address. | You see the super admin sign-in page: a business login does not give super admin access. | |

### 2.2 Businesses

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SA-10 | Open **Businesses**. | Tiles: active, suspended, monthly revenue, employees managed. Table with Test Shop Ltd and Test Cafe Ltd from section 1: admin, staff used / limit, payment method, next due, plan, status. | |
| SA-11 | **Suspend** Test Cafe Ltd. | Confirmation; status **Suspended**. Its admin and employees are signed out on their next click and can't sign in ("Access for Test Cafe Ltd is paused…"). | |
| SA-12 | **Activate** it again. | Status **Active**; sign-in works again. | |
| SA-13 | **Set plan** on Test Shop Ltd → **Standard**. | Plan Standard, limit 10. Their Settings → Subscription shows Standard. | |
| SA-14 | Set plan → **Corporate**, price **£60**, limit **25**. | Saved; their limit is 25. (With Stripe this changes the price from their next payment.) | |
| SA-15 | Set it back to **Starter**. | Saved. | |

### 2.3 Plans and pricing

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SA-20 | Open **Plans and pricing**. | Starter £20 / 5, Standard £35 / 10, training £49, grace period 7 days. | |
| SA-21 | Try invalid values: price `0`, limit `abc`, Standard limit lower than Starter's, grace `60`. | Clear errors; nothing saved. | |
| SA-22 | Change Starter to **£22**, save. Open the website pricing in another tab. | Website shows £22 straight away. Existing businesses keep their price. | |
| SA-23 | **Existing subscribers** section. | Lists businesses paying less than today's price, with "Email N and move them on {date}" (30 days ahead). | |
| SA-24 | Press the move button (test businesses only). | Their admins get **"Your plan changes on {date}"** (E-08). Their Settings → Subscription shows "From {date}: £22 per month". | |
| SA-25 | Set Starter back to **£20**. | Website shows £20 again. | |

### 2.4 Payment gateways

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SA-26 | Open **Payment gateways**. | Stripe and PayPal cards. Saved keys show only `••••` + the last 4 characters, never the full key. | |
| SA-27 | Stripe: press **Save and test connection** with the current keys. | "Connected" (test or live as chosen). | |
| SA-28 | Enter a wrong Stripe secret key and test. | A clear failure message; the site still works with the old keys after you put the right one back. | |
| SA-29 | PayPal: **Save and test connection**. | "Connected" (sandbox or live). | |

### 2.5 Enquiries, super admins, my account

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SA-30 | Open **Enquiries and training**. The sidebar shows a count of new ones. | Your WEB-11 enquiry is at the top with name, topic, message and email link. | |
| SA-31 | **Mark handled**. | Shows as handled; the sidebar count goes down. | |
| SA-32 | Open **Super admins**. | Your login with "(you)", no Remove button on yourself. Allowed IP addresses listed, plus your current IP. | |
| SA-33 | Add a super admin: name, `you+super2@gmail.com`. | "Invite sent". Listed as "Invite sent {date}" with **Resend invite** and **Remove**. | |
| SA-34 | Inbox `you+super2@gmail.com`. | **"You've been added as a SponsorSafe super admin"** (E-11). | |
| SA-35 | Add the same email again. | "This email is already a super admin." | |
| SA-36 | **Resend invite**. | New email; the link in the first email no longer works. | |
| SA-37 | In a private window (allowed IP), open the newest invite link and set a password of fewer than 12 characters. | Error: at least 12 characters. | |
| SA-38 | Set a 12+ character password. | "Password saved." Sign in → authenticator setup → Businesses page. | |
| SA-39 | Back as the first super admin: **Remove** the second one. | Confirmation; removed. The second super admin is signed out and can't sign in. | |
| SA-40 | **My account** → Change password with a wrong current password. | "That password is not right." | |
| SA-41 | Change password with the right current password, a new one (12+), and the authenticator code. | "Password changed…". Email **"Your SponsorSafe password was changed"** (E-10). You stay signed in. | |
| SA-42 | Sign in as the same super admin in a second browser *before* SA-41; after SA-41, click anything there. | The second browser is signed out. | |
| SA-43 | **Log out**. | Back to the sign-in page; Back button doesn't show super admin pages. | |

---

## 3. Business admin

Sign in at `/login` as `you+admin@gmail.com` (Test Shop Ltd), or `hr@demo-retail.example` on the local copy.

### 3.1 Sign-in, password and two-step sign-in

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-01 | Open **Log in**. Enter an unknown email, press Next. | "We could not find an account with that email…". | |
| BA-02 | Enter your admin email, press Next. | Account card: your name, email, business, role (Admin), "Not you?". | |
| BA-03 | Click **Not you?**. | Back to the email step. | |
| BA-04 | Wrong password. | Error. After several wrong tries: "too many attempts". | |
| BA-05 | Right password. | Asked for the 6-digit authenticator code (admins always need it). | |
| BA-06 | Right code. | Dashboard. | |
| BA-07 | **Forgot password?** on the password step. | Message saying an email is on its way. Email **"Reset your SponsorSafe password"** (E-03). | |
| BA-08 | Use the reset link; set a new password. | Then the authenticator code; then the dashboard. The link stops working after use, and after 60 minutes. | |
| BA-09 | Settings → **Your password**: change it (current password, new one twice, authenticator code). | "Password changed…". Email **"Your SponsorSafe password was changed"** (E-10). | |
| BA-10 | Do BA-09 while also signed in on a second browser. Click anything in the second browser. | Second browser is signed out. | |
| BA-11 | Ctrl+K (⌘K on a Mac) → type "password" → **Change my password**. | Opens Settings at the Your password section. | |

### 3.2 Layout and general

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-12 | Look at the sidebar. | Exactly six items: Dashboard, Employees, Absence, Home Office reports, Requests, Settings. No "Soon" items. | |
| BA-13 | Press **Ctrl+K**. Type an employee's name. | Palette shows matching employees, screens and actions. Enter opens it. Esc closes. | |
| BA-14 | Theme toggle (light/dark). | Whole app switches; text stays readable. | |
| BA-15 | Save any change. | A green message pops up bottom-right and fades. Errors pop up red and stay until closed. | |
| BA-16 | Use the app on a phone. | Usable: menu, tables and forms fit. | |

### 3.3 Settings (do this first on a new business)

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-20 | Settings → **Business** → Change. Add a phone number and registered address (first time). | Saved. **No** Home Office task created (first address is not a change). | |
| BA-21 | Change the business **name** (or change the address again). | Saved, and a company-level Home Office task appears in Home Office reports (deadline 20 working days). | |
| BA-22 | **Work sites** → add a site with name and address. | Site listed. A company Home Office task "new work address" (20 working days). | |
| BA-23 | Add a second site; **rename** it. | Name updated. | |
| BA-24 | **Key personnel**: add Authorising Officer, Key Contact, Level 1 User. | Listed. Each add/change/remove creates a company Home Office task. Authorising Officer and Key Contact can only be one person each. | |
| BA-25 | **Admin logins** → add an admin: name, `you+admin2@gmail.com`. | "Invite sent". Email **"You've been added as an admin for Test Shop Ltd"** (E-04). | |
| BA-26 | Resend the invite. | New email; the old link stops working. | |
| BA-27 | Try to remove **yourself**. | "You cannot remove your own login…". | |
| BA-28 | As the second admin: open the link, set a password, set up the authenticator. | Lands on the same business's dashboard. | |
| BA-29 | Remove the second admin. | Removed; they can no longer sign in. | |
| BA-30 | **Subscription** card. | Plan name, £/month, "N of 5 employees" bar, next payment date, payment method, Manage billing, Book 1-to-1 training. | |
| BA-31 | **Manage billing** (card business). | Opens Stripe's customer portal (change card, invoices, cancel). For PayPal: PayPal's automatic payments page. | |
| BA-32 | **Change plan** to Standard. | Limit becomes 10 straight away; new price from the next payment. PayPal: you approve the new price on PayPal first. | |
| BA-33 | Change back to Starter while you have more than 5 current employees. | Refused with a reason. | |
| BA-34 | **Compliance rules**: change a value (e.g. worker report deadline), save; then put it back. | Saved. Invalid values (e.g. negative) are refused. | |
| BA-35 | **Clock-in check**: switch on, import a CSV (columns `email,date,time`). | Import summary; "last import" shown. Switch off again afterwards if not needed. | |

### 3.4 Employees

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-40 | **Employees** → **Add employee**, right-to-work basis **Skilled Worker visa – sponsored by this business**. | Form shows the sponsored fields (CoS number, SOC code, salary, visa dates, etc.) and the checks you need. | |
| BA-41 | Set the right-to-work check date **after** the start date. | Blocked with a clear message. | |
| BA-42 | Set the visa expiry **before** the start date. | Blocked. | |
| BA-43 | Fill properly (email `you+emp1@gmail.com`, visa expiring in about **60 days** for the reminder test later). Save. | Employee saved. Portal invite **"Set up your Test Shop Ltd employee portal"** sent (E-05). | |
| BA-44 | Add a second employee, basis **British or Irish citizen** (`you+emp2@gmail.com`). | Saved, no visa fields needed. | |
| BA-45 | Keep adding employees until the plan is full (Starter: 5). Try the 6th. | Blocked: "Upgrade to Standard … in Settings". On Standard the 11th says "Contact us about a Corporate package". | |
| BA-46 | Employees list: search, sort by a column, filter. | Results update quickly; page links work. | |
| BA-47 | Open an employee. | Header: name, job, site, start date; badges; **Export compliance pack (PDF)**; **End employment**. Tabs: Details, Compliance check, Documents, Absence, Home Office, History. | |
| BA-48 | Details: passport number, NI number, share code. | Only the **last 4** characters shown. | |
| BA-49 | **Correct personal details**: fix a name or date of birth spelling. | Saved; shown in History with old → new, who and when. | |
| BA-50 | **Record a change** → Salary – reduction (below the CoS salary) on the sponsored worker. | Before saving it says this **must be reported**. After saving: a worker Home Office task (10 working days). | |
| BA-51 | Record a change → Salary – increase. | Logged in History; **no** Home Office task. | |
| BA-52 | Record a change → Job title on the sponsored worker. | Reportable → Home Office task. On the British worker: logged only, no task. | |
| BA-53 | Settings → Work sites → **Move employee**: move the sponsored worker to the other site. | Worker task (10 working days). Moving the British worker: logged only. | |
| BA-54 | Details → Employment → **Working days**: set Mon, Wed, Fri. | Saved; logged in History. | |
| BA-55 | **Compliance check** tab. | Rows marked done / check / missing / manual; header shows "Compliance: N to fix". | |
| BA-56 | **Export compliance pack (PDF)**. | PDF downloads: check, details (last 4 only for secrets), documents list, absences, Home Office reports, change history. | |
| BA-57 | Resend the portal invite for an employee who hasn't signed in. | New invite email; old link stops working. | |

### 3.5 Documents

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-60 | Documents tab → upload a **PDF** as Passport / identity with an expiry date. | Listed with uploader, date and expiry badge. Compliance check updates. | |
| BA-61 | Upload a JPG and a PNG. | Accepted. | |
| BA-62 | Upload a `.docx`, and a file over 10 MB. | Both refused with a clear message. | |
| BA-63 | **View** and **Download** a document. | Opens / downloads correctly. | |
| BA-64 | Copy a document's view link, sign out, paste it in the browser. | You're sent to the login page; the file does not open. | |
| BA-65 | **Request from employee** → Passport / identity for `you+emp1`. | Shows as waiting; Requests → "Waiting for employees" lists it. | |
| BA-66 | Cancel a request. | Removed from waiting. | |
| BA-67 | Delete a document. | Confirmation; removed. | |

### 3.6 Absence

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-70 | **Absence** → **Record absence** → choose the sponsored worker, Annual leave, 3 days. | Home Office check panel: **No report needed**. Save → in the log. | |
| BA-71 | Sponsored worker, **Unpaid leave**, 21 working days in this leave year. | Panel shows a warning **before** saving (over 4 weeks' unpaid leave = report). After saving: Home Office task with deadline. | |
| BA-72 | Sponsored worker, **Unauthorised absence**, 10 working days in a row (span a weekend). | Panel says **Report to Home Office** with the deadline (10 working days after the 10th day). Task created. | |
| BA-73 | Same as BA-72 on the British worker. | Logged, **no** report (reporting is for sponsored workers only). | |
| BA-74 | Sickness – self-certified for 9 calendar days. | Warning: more than 7 days needs a fit note. | |
| BA-75 | Sickness – fit note without a file. | Saved, flagged **Fit note missing**; upload the fit note from the log afterwards. | |
| BA-76 | Record an absence overlapping days already recorded for the same person. | Refused: "This overlaps … Change the dates or remove the other entry first." | |
| BA-77 | Filter the log by employee, type and date range. **Export CSV** and **Export PDF**. | Files contain only the filtered rows. | |
| BA-78 | Delete an absence that has a **pending** Home Office task. | Absence and its pending task removed. | |
| BA-79 | Try to delete an absence whose task is **already reported**. | Refused (reopen the task first). | |

### 3.7 Home Office reports

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-80 | Open **Home Office reports**. | Pending first, sorted by deadline, then done. Badges: overdue / due today red, due within 5 working days amber, pending blue, reported green, not required grey. | |
| BA-81 | **Mark reported** without the date or "reported by". | Both required. A future date, or a date before the event, is refused. SMS reference optional. | |
| BA-82 | Mark reported properly. | Badge turns **green**; the absence in the log also shows reported. | |
| BA-83 | **Not required** without a reason, then with a reason. | Reason required; then grey. | |
| BA-84 | **Reopen** a done task. | Back to pending. | |
| BA-85 | **Create Home Office report** (manual), e.g. "Suspected breach of visa conditions". | Task created with a deadline. | |
| BA-86 | Dashboard tiles. | Reports pending, due within 5 working days, employee requests, visas expiring in 90 days: the numbers match the lists. "Reports due" and "Visas expiring" numbers in red. | |

### 3.8 Requests inbox (after section 4 sends some)

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-90 | Open **Requests**. | "Waiting for you", "Waiting for employees", "Recently decided". | |
| BA-91 | Open a leave request. | Preview says whether approving needs a Home Office report. | |
| BA-92 | **Approve** the leave. | Absence created in the log; employee sees "Approved". | |
| BA-93 | **Decline** a request with a note. | Employee sees "Declined" and your note. | |
| BA-94 | Approve an **address change**. | Employee's address updated; logged in History. | |
| BA-95 | Approve a **visa extension**. | Visa expiry updated; reminder to do a new right-to-work check. | |
| BA-96 | Approve a **document upload** (passport). | Filed on the Documents tab; the original request is marked received. | |
| BA-97 | Decline a document upload. | File deleted; the request goes back to "Action needed" for the employee. | |

### 3.9 End of employment and records due for deletion

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-100 | **End employment** on the sponsored worker: last day + reason. | Ended; portal login switched off; P45 reminder; worker Home Office task (leaver); delete-after dates set (end + 1 year; right-to-work evidence end + 2 years). | |
| BA-101 | The leaver tries to sign in to the portal. | Can't sign in. | |
| BA-102 | Settings → **Review records due for deletion** (local copy: Priya Shah is due). | List of leavers past their date. Delete → confirmation → personal data and files erased. | |

### 3.10 Dashboard

| ID | Steps | Expected | Pass |
|---|---|---|---|
| BA-110 | **Coming up** card. | Visa/permission expiries, follow-up checks and passports that are coming up. | |
| BA-111 | **Unexplained absences** (local copy has two; on live, needs clock-in data: BA-35 + section 6). | Each row: **Classify absence** opens Record absence pre-filled as unauthorised for that day; **Worked – clock-in missed** clears it. Red after 2 working days. | |
| BA-112 | Payment failed banner (local copy: `hr@demo-catering.example`). | Red banner with "update payment details by {date}". | |

---

## 4. Employee portal

Sign in at `/login` as `you+emp1@gmail.com` (or `aisha.rahman@demo-retail.example` locally). Best tested on a phone.

| ID | Steps | Expected | Pass |
|---|---|---|---|
| EM-01 | Open the portal invite email (E-05), set a password. | Lands on the portal **Home**. No authenticator needed (optional for employees). | |
| EM-02 | Menu. | Home, My documents, Leave and sickness, Update my details, My requests, My details. Privacy notice link at the bottom of every page. | |
| EM-03 | **Home**. | Annual leave left, documents HR needs, right-to-work status, quick actions, recent requests. | |
| EM-04 | Type `/app` in the address bar. | Sent back to the portal: employees can't open the admin area. | |
| EM-05 | **My documents** → the passport HR requested (BA-65) → upload a PDF → Send. | Shows "Waiting for HR". HR sees it in Requests. | |
| EM-06 | Send another document (e.g. Other). | Waiting for HR. | |
| EM-07 | View your own documents on file (e.g. payslips). | Opens. Recruitment evidence is never shown to employees. | |
| EM-08 | **Leave and sickness** → annual leave for next week. | Request sent; My requests shows "Waiting for HR". | |
| EM-09 | Ask for more annual leave than you have left. | Warning shown. | |
| EM-10 | Unpaid leave (sponsored worker). | Warning that unpaid leave can affect sponsorship. | |
| EM-11 | **Report sickness**: 10 days. | Told a fit note is needed after 7 days; can attach one. | |
| EM-12 | **Update my details** → new address. | Sent to HR; HR approves (BA-94); My details then shows the new address. | |
| EM-13 | Update my details → **New visa or extension** (sponsored worker). | Asks for the new expiry date; sent to HR. | |
| EM-14 | **My requests**. | Each request with status: Waiting for HR / Approved / Declined / Action needed, plus HR's note. | |
| EM-15 | **My details**. | Personal, job and right-to-work sections. NI number last 4 only. | |
| EM-16 | My details → **Change password** (current, new twice). | "Password changed…". Email E-10. Other devices signed out. | |
| EM-17 | My details → **Two-step sign-in** → Set up → add to the authenticator → enter the code. | "On". Next sign-in asks for the code. | |
| EM-18 | Change password again with two-step sign-in on. | The authenticator code is now also required. | |
| EM-19 | Turn two-step sign-in off (needs your password). | "Off". | |
| EM-20 | **Privacy notice**. | Full notice with the company name and the business's retention periods. | |
| EM-21 | Employee of Test Shop Ltd opens a document link belonging to another employee (ask the tester for a URL). | Not allowed (404 / forbidden). | |

---

## 5. Emails – complete list

Tick each email once you've seen it arrive, read properly (logo/name, greeting, clear text), with links that work.

| ID | Subject | Sent to | How to trigger | Pass |
|---|---|---|---|---|
| E-01 | New enquiry: {topic} – {name} | `SUPPORT_EMAIL` | Website contact form (WEB-11) | |
| E-02 | Welcome to SponsorSafe, set your password | New subscriber | Successful sign-up and payment (SU-06, SU-10). Link lasts 7 days. | |
| E-03 | Reset your SponsorSafe password | Admin or employee | "Forgot password?" on the login page (BA-07). Link lasts 60 minutes, works once. | |
| E-04 | You've been added as an admin for {business} | New business admin | Settings → Admin logins (BA-25). Link lasts 7 days. | |
| E-05 | Set up your {business} employee portal | New employee | Add employee, or resend invite (BA-43, BA-57). Link lasts 7 days. | |
| E-06 | Payment failed for {business} | Business admins | A renewal payment fails (6.3). Starts the grace period. | |
| E-07 | Access paused for {business} | Business admins | Grace period ended unpaid, or subscription cancelled and paid time used up (6.3). *Not* sent when the super admin suspends by hand. | |
| E-08 | Your plan changes on {date} | Business admins | Super admin → Plans and pricing → Existing subscribers → move (SA-24). 30 days' notice. | |
| E-09 | N compliance reminder(s) for {business} | Business admins | Daily 07:00 reminder job when something is due (6.2). | |
| E-10 | Your SponsorSafe password was changed | Whoever changed it | Change password: admin (BA-09), employee (EM-16), super admin (SA-41). | |
| E-11 | You've been added as a SponsorSafe super admin | New super admin | Super admin → Super admins → Add (SA-33). | |

Also check for each email:
- Arrives within a minute or two, and **not in spam**.
- The sender is the site's `no-reply@…` address and name SponsorSafe.
- Links open the live site (not `localhost`) over `https://`.

---

## 6. Scheduled jobs and billing events

These run by themselves through the cron job (every minute; check it exists in cPanel → Cron Jobs). To test now
instead of waiting, run the command in cPanel → Terminal from the project folder:

```bash
cd ~/sponsersafe.codely.quest
```

### 6.1 Cron job

| ID | Steps | Expected | Pass |
|---|---|---|---|
| JOB-01 | `php artisan schedule:list` | Four jobs: `billing:check` 06:00, `reminders:send` 07:00, `absences:check-clock-ins` 20:00, `bank-holidays:sync` monthly (times in UTC). | |
| JOB-02 | Wait a day; check the reminder email arrived without running anything by hand. | Proves the cron job runs. | |

### 6.2 Reminders

| ID | Steps | Expected | Pass |
|---|---|---|---|
| JOB-10 | Make sure an employee's visa expires in about 60 days (BA-43), then run `php artisan reminders:send`. | Admins get **"1 compliance reminder for Test Shop Ltd"** (E-09) listing the expiry. | |
| JOB-11 | Run `php artisan reminders:send` again. | **No** second email for the same thing (each stage is sent once). | |
| JOB-12 | A pending Home Office task due within 5 working days, or overdue, then run the job. | Included in the reminder email. | |

### 6.3 Payments over time (Stripe test mode)

| ID | Steps | Expected | Pass |
|---|---|---|---|
| JOB-20 | Stripe Dashboard (test mode) → the test customer → change their card to `4000 0000 0000 0341`, then make the next invoice run (use a **test clock** to move time forward a month). | Webhook arrives; business shows **Payment failed** in super admin; admins get **E-06**; red banner in the app. | |
| JOB-21 | Move the test clock past the grace period (7 days), then run `php artisan billing:check`. | Business **suspended** (reason payment); admins get **E-07**; nobody can sign in. | |
| JOB-22 | Pay the invoice in Stripe (change back to `4242…`). | Business active again; sign-in works. | |
| JOB-23 | Customer portal → **Cancel** subscription. Stripe ends it at the end of the paid month: move the test clock past the next payment date. | The business keeps access until the paid month ends, then is **suspended** (reason cancelled) and admins get **E-07**. (PayPal ends at once: Settings shows **Cancelled** with "access until {date}", and `billing:check` suspends it on that date.) | |
| JOB-24 | Super admin → Payment gateways: check the Stripe webhook shows recent deliveries without errors in the Stripe Dashboard (Developers → Webhooks). | All 2xx. | |

### 6.4 Clock-in check

| ID | Steps | Expected | Pass |
|---|---|---|---|
| JOB-30 | Clock-in check on (BA-35). Import a CSV for a working day that leaves one scheduled employee out (and no absence recorded for them). | Next morning's dashboard and reminder email show an **unexplained absence** for that person. | |
| JOB-31 | Record an absence for that person on that day. | The alert clears automatically. | |

---

## 7. Security and privacy checks

| ID | Steps | Expected | Pass |
|---|---|---|---|
| SEC-01 | Admin of Test Shop Ltd: open an employee page, note the number in the address (`/app/employees/12`). Sign in as admin of Test Cafe Ltd and open the same address. | **Not found** – one business can never see another's data. | |
| SEC-02 | Same with a document link and an admin-login remove action from another business. | Not found / refused. | |
| SEC-03 | Signed out, open `/app` and `/me`. | Sent to the login page. | |
| SEC-04 | Suspended business: admin and employee try to sign in. | "Access for {business} is paused. Please contact support…". | |
| SEC-05 | A sign-up that never paid tries to sign in. | Can't. | |
| SEC-06 | Wrong passwords repeatedly on `/login`. | Rate limited. | |
| SEC-07 | All pages use `https://` with a padlock. Try `http://` – it should redirect. | HTTPS only. | |
| SEC-08 | Password reset, invite and super admin links: use twice, and after expiry. | Refused the second time and after expiry. | |
| SEC-09 | Passport, NI and share code anywhere in the app or PDFs. | Last 4 only. | |
| SEC-10 | Audit log (ask the site owner to check the `audit_logs` table in phpMyAdmin). | Entries for sign-ins, document views/downloads, record changes, suspensions, price/gateway changes, exports and password changes, with who, when and IP. | |
| SEC-11 | Upload a renamed file (e.g. a `.exe` renamed to `.pdf`). | Refused. | |
| SEC-12 | Super admin address never appears on the website or in emails. | Not linked anywhere. | |

---

## 8. After testing

1. **Delete test data on the live site before launch**: test businesses (ask the site owner), test enquiries,
   test super admins. Cancel test subscriptions in the Stripe and PayPal test dashboards.
2. Switch Stripe and PayPal to **Live** keys in Payment gateways only when testing is finished.
3. Send the completed checklist with every ❌ explained (check ID, steps, what happened, screenshot).

### Not built yet (don't report as missing)

- **AI chat assistant** on the website and its super admin page (shows "Soon") – Stage 7c.
- A live connection to a clock-in system – clock-ins come from a CSV import for now.
