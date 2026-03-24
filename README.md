## BlogFuel

Laravel app for generating and publishing AI-written articles from a single-page workflow, with a one-draft guest trial, a five-post free tier for registered users, one-time Stripe credit packs, and Stripe-powered Pro subscriptions after the free quota is exhausted.

### What it does

- Offers login and registration, with article generation available to signed-in users whose email address has been verified.
- Lets guests generate 1 draft before registration, then prompts them to create an account to publish it.
- Gives each verified user 5 free blog generations in total.
- Offers paid 25-article and 100-article credit packs for occasional usage after the free quota runs out.
- Offers recurring Stripe Pro subscriptions with monthly and annual choices for unlimited usage.
- Keeps GPT-5.4 reserved for Pro subscribers, while free and credit-pack access uses GPT-5 mini and GPT-5.2.
- Accepts a topic, keywords, tone, audience, article depth, and optional SEO enhancement from a single-page generator.
- Sends that brief to OpenAI from the Laravel backend.
- Stores the generated post in the database.
- Displays recent generated posts on the homepage and individual article pages.

### Local setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Install frontend dependencies:

   ```bash
   npm install
   ```

3. Configure your environment:

   - Copy `.env.example` to `.env` if needed.
   - Add your API key to `OPENAI_API_KEY=...`
   - Add your reCAPTCHA Enterprise configuration when you are ready to turn it on:

     ```env
     RECAPTCHA_ENTERPRISE_ENABLED=false
     RECAPTCHA_ENTERPRISE_SITE_KEY=
     RECAPTCHA_ENTERPRISE_API_KEY=
     RECAPTCHA_ENTERPRISE_PROJECT_ID=
     RECAPTCHA_ENTERPRISE_MIN_SCORE=0.5
     ```

     Keep it disabled locally unless you have a working key setup for your local domain. Once enabled, BlogFuel protects guest article generation, login, registration, and verification-email resend.

   - Configure email verification delivery. The app now defaults to the `failover` mailer, which tries SMTP first and falls back to the Laravel log if SMTP is unavailable:

     ```env
     MAIL_MAILER=failover
     MAIL_SCHEME=smtp
     MAIL_HOST=smtp.your-provider.com
     MAIL_PORT=587
     MAIL_USERNAME=
     MAIL_PASSWORD=
     MAIL_FROM_ADDRESS=no-reply@yourdomain.com
     MAIL_FROM_NAME="BlogFuel"
     MAIL_REPLY_TO_ADDRESS=support@yourdomain.com
     MAIL_REPLY_TO_NAME="BlogFuel Support"
     ```

     For local development, you can either:
     - point those SMTP settings at a local inbox tool such as Mailpit, or
     - leave SMTP unavailable and read the fallback verification email from `storage/logs/laravel.log`

   - Add your Stripe configuration:

     ```env
     STRIPE_SECRET=
     STRIPE_WEBHOOK_SECRET=
     STRIPE_PRICE_MONTHLY=
     STRIPE_PRICE_ANNUAL=
     STRIPE_PRICE_PACK_25=
     STRIPE_PRICE_PACK_100=
     BLOGFUEL_GUEST_FREE_GENERATIONS=1
     STRIPE_MONTHLY_PRICE_LABEL="£19 / month"
     STRIPE_ANNUAL_PRICE_LABEL="£190 / year"
     STRIPE_PACK_25_PRICE_LABEL="£12 one-off"
     STRIPE_PACK_100_PRICE_LABEL="£39 one-off"
     ```

   - Set the seeded admin credentials you want to use locally:

     ```env
     BLOGFUEL_ADMIN_EMAIL=admin@example.com
     BLOGFUEL_ADMIN_PASSWORD=change-me-admin-password
     ```

   - The project defaults to SQLite and `gpt-5-mini`. Free and credit-pack access can use `gpt-5-mini` and `gpt-5.2`, while `gpt-5.4` unlocks on Pro subscription plans.

4. Run the database migration:

   ```bash
   php artisan migrate
   ```

5. Seed the admin user:

   ```bash
   php artisan db:seed
   ```

   The seeded admin user is always named `admin`, uses the `BLOGFUEL_ADMIN_EMAIL` and `BLOGFUEL_ADMIN_PASSWORD` values from `.env`, and is pre-verified so you can use the generator immediately.

6. Build assets:

   ```bash
   npm run build
   ```

7. Start the local server:

   ```bash
   php artisan serve --no-reload
   ```

8. Open `http://127.0.0.1:8000`

If you register a new user locally and SMTP is not available, the failover mailer will still write the verification email to `storage/logs/laravel.log`.

If you want to test Stripe locally, forward Stripe webhooks to the app and point them at `/stripe/webhook`. The success redirect can unlock access straight away, but the webhook keeps recurring subscriptions, one-time credit grants, renewals, cancellations, and payment failures in sync over time.

### Useful commands

```bash
php artisan test
composer test
npm run dev
```

### Hosting notes for a subdomain

- Point the subdomain document root at the Laravel app's `public/` directory.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Update `APP_URL` to the full subdomain URL.
- Keep `OPENAI_API_KEY` on the server only.
- Add the live `RECAPTCHA_ENTERPRISE_API_KEY`, set `RECAPTCHA_ENTERPRISE_ENABLED=true`, and confirm the site key is valid for the live domain.
- Configure a real SMTP provider in the `MAIL_*` variables so email verification can send live messages.
- Keep `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` on the server only.
- Set production-safe `BLOGFUEL_ADMIN_EMAIL` and `BLOGFUEL_ADMIN_PASSWORD` values before seeding the admin user on the live site.
- If your host uses MySQL rather than SQLite, swap the database environment variables accordingly and run `php artisan migrate --force`.

### Go-live checklist

- [ ] Wait for DNS to resolve for the live site domain.
- [ ] Point the subdomain document root at the app's `public/` directory.
- [ ] Set `APP_URL` to the full live site URL.
- [ ] Set `APP_ENV=production`.
- [ ] Set `APP_DEBUG=false`.
- [ ] Add the live `OPENAI_API_KEY`.
- [ ] Add the live SMTP `MAIL_*` settings.
- [ ] Add the live Stripe values: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_MONTHLY`, `STRIPE_PRICE_ANNUAL`, `STRIPE_PRICE_PACK_25`, `STRIPE_PRICE_PACK_100`.
- [ ] Add the live reCAPTCHA Enterprise API key and set `RECAPTCHA_ENTERPRISE_ENABLED=true`.
- [ ] Set production-safe `BLOGFUEL_ADMIN_EMAIL` and `BLOGFUEL_ADMIN_PASSWORD` values.
- [ ] Rotate any secrets that were exposed during local development.
- [ ] Run `composer install --no-dev --optimize-autoloader`.
- [ ] Run `php artisan migrate --force`.
- [ ] Run `php artisan db:seed --force` if you want the seeded admin user created on the live site.
- [ ] Run `php artisan config:clear`.
- [ ] Run `php artisan route:clear`.
- [ ] Run `php artisan view:clear`.
- [ ] Run `npm run build`.
- [ ] Confirm `storage/` and `bootstrap/cache/` are writable on the server.
- [ ] Create the live Stripe webhook endpoint for `/stripe/webhook`.
- [ ] Register a brand-new user on the live site.
- [ ] Click the verification email link and confirm it lands on the live domain.
- [ ] Test one successful 25-pack purchase.
- [ ] Test one successful monthly Pro subscription purchase.
- [ ] Test `Manage billing` in the live Stripe customer portal.
- [ ] Test Facebook / LinkedIn / X / Reddit / email / copy-link sharing from a live article URL.

### Post-deploy checks

- Run `php artisan config:clear` after setting the live `APP_URL`.
- Turn on reCAPTCHA Enterprise in `.env`, clear config, and test the protected forms on the live domain.
- Register a brand-new user on the live site.
- Open the verification email and confirm the link lands on the live domain, not a local URL.
- Complete the verification flow and confirm the account can generate articles afterward.
