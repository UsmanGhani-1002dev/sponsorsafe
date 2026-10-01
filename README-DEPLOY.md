# SponsorSafe – Stage 1 deployment (cPanel / WHM)

Everything is pre-built: `vendor/` (PHP packages) and `public/build/` (the React
frontend) are included, so the server only needs **PHP 8.3 or 8.4** with:
pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, bcmath, curl, zip.
No Composer or Node needed on the server.

## 1. Site and database
1. Create a subdomain, e.g. `app.yourdomain.co.uk`.
2. Set its **document root to the app's `public` folder**, e.g. `/home/USER/sponsorsafe/public`.
   Nothing outside `public` may be reachable from the web.
3. Create a MySQL database + user with all privileges (cPanel → MySQL Databases).
4. Turn on SSL (AutoSSL / Let's Encrypt). The app expects HTTPS.

## 2. Upload
Upload the zip to `/home/USER/` and extract it, giving `/home/USER/sponsorsafe/`.

## 3. Configure (cPanel → Terminal, or SSH)
```bash
cd ~/sponsorsafe
cp deploy/production.env.example .env
nano .env                 # APP_URL, DB_*, MAIL_*, OPS_PATH, OPS_ALLOWED_IPS
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force            # bank holidays (demo data is skipped in production)
php artisan ops:create-admin you@yourdomain.co.uk --name="Your name"
php artisan config:cache && php artisan route:cache && php artisan view:cache
chmod -R 775 storage bootstrap/cache
```
If `php` on the command line is an older version, use the full path, e.g.
`/opt/cpanel/ea-php83/root/usr/bin/php artisan ...`.

## 4. Cron (cPanel → Cron Jobs, every minute)
```
* * * * * cd /home/USER/sponsorsafe && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler also runs `billing:check` daily at 06:00 (suspends businesses whose payment grace period has
ended, removes sign-ups that never paid) and `reminders:send` at 07:00 (emails each business its new
expiry, follow-up check and Home Office deadline reminders). Without this cron job no reminders go out.

## 4a. Stripe (card payments)
1. In the Stripe dashboard (live mode): Developers → API keys. Copy the publishable and secret keys.
2. Developers → Webhooks → Add endpoint: `https://app.yourdomain.co.uk/stripe/webhook`, with the
   events listed on the super admin's **Payment gateways** page. Copy the signing secret (`whsec_…`).
3. Settings → Billing → Customer portal: turn it on (lets customers change card and cancel).
4. Super admin → **Payment gateways**: paste the three values, choose **Live**, **Save and test connection**.
   Keys are stored encrypted; `.env` needs no Stripe values.

## 4b. PayPal
1. developer.paypal.com → Apps & Credentials (Live) → create an app. Copy the Client ID and secret.
2. In the app, add a webhook: `https://app.yourdomain.co.uk/paypal/webhook`, with the events listed on
   the super admin's **Payment gateways** page. Copy the webhook ID.
3. Super admin → **Payment gateways** → PayPal: paste the three values, choose **Live**, **Save and test
   connection**. The monthly plan is created in PayPal automatically on the first subscription.

## 5. Sign in
- Businesses and employees: `https://app.yourdomain.co.uk/login`
- Super admin: `https://app.yourdomain.co.uk/<OPS_PATH>/login` (first sign-in shows a key for your authenticator app)

### Demo data on a test site only
Set `APP_ENV=local`, run `php artisan db:seed --force`, then set it back to `production`.
Demo logins, password `password`:
- hr@demo-retail.example – business admin
- aisha.rahman@demo-retail.example – employee
- hr@demo-cafe.example – suspended business (shows the paused message)
- Super admin: owner@sponsorsafe.example (authenticator set up on first sign-in)

**Never load demo data on the live site.**

## Updating to the next stage
Upload the new zip over the old folder (keep `.env` and `storage/`), then run
`php artisan migrate --force` and the three `cache` commands again.
