# 🎰 LuckyDraw — Telegram Mini App Lottery

A full-stack **Telegram Mini App** lottery game built entirely in **Laravel 13 + Livewire 4** —
so you can maintain it in PHP/Blade with almost no JavaScript.

- **Mini App UI** — Livewire + Blade + Tailwind v4, dark/gold theme, live ticket board, confetti, native Telegram theming & haptics. **Bilingual English / አማርኛ (Amharic).**
- **Telegram bot** — [Nutgram](https://nutgram.dev) (`/start`, `/balance`, `/mytickets`, `/results`, `/admin`, `/help`).
- **Database** — MySQL/MariaDB (works on any shared host). No Postgres/Supabase/Redis required.
- **Realtime** — Livewire polling (no websocket server to run).
- **Auto-draw** — cryptographically secure winner pick (`random_int`), 50/50 split tickets, referral rewards, leaderboard, multi-round history.
- **Money you can trust** — every wallet/prize calculation runs in **integer cents** (no float drift); the draw uses **atomic, row-locked state transitions** so a round can never be drawn twice or refunded *and* paid out; **per-round P&L reconciliation** in `/admin`.

> Designed to deploy on **shared hosting (cPanel)** using MySQL + cron + a Telegram webhook.

---

## 📑 Contents

- [Features](#-features)
- [Project structure](#-project-structure-laravel-monolith)
- [Install & local development](#-install--local-development)
- [Deploy to shared hosting (cPanel)](#-deploy-to-shared-hosting-cpanel--mysql--cron--webhook)
- [Post-deploy checklist](#-post-deploy-checklist)
- [Updating a live deployment](#-updating-a-live-deployment)
- [Troubleshooting](#-troubleshooting)
- [Tests](#-tests)
- [Database schema](#-database-schema)
- [Wallet & payments](#-wallet--payments)
- [Security & correctness notes](#-security--correctness-notes)
- [Environment variables](#-environment-variables)
- [Bot commands](#-bot-commands)

---

## ✨ Features

| Area | What you get |
|------|--------------|
| Admin | Create/cancel rounds, set tickets & price, **# of winners + editable prize tiers**, **auto-restart** next round after a delay, allow/disallow halves, optional channel, manual/auto draw, live dashboard (`/admin`) |
| Buying | **Tap numbers on the board** (no quantity box) — buy full or **half tickets**; two players can each own one half of a number. Name auto-fills from Telegram; phone via the verified **"Share contact"** prompt (stored & reused) |
| Multi-winner draw | Locks sales when sold out (or deadline) → 3D ball-machine animation → secure pick of N winners → tiered payouts (e.g. 70% / 15% / 1 ticket price, **rest → admin**). Half-ticket winners split their tier 50/50; an unsold half's share goes to the house |
| Wallet & payments | Real **wallet balance** — tickets are paid from it, prizes credit to it, cancellations auto-refund. **Deposits are auto-verified** against a genuine Telebirr/CBE/CBE&nbsp;Birr/M-Pesa transaction via [verify.leul.et](https://verifyapi.leulzenebe.pro) (fails *closed* on unverified / wrong-account / pending payments); **withdrawals** are reserved instantly, then shown to the player as a **Requested → Paid/Declined timeline**, and paid out + confirmed by an admin |
| Notifications | Winners get a **personal DM** with their placement & exact prize; results are posted to a **Telegram channel** (configurable). Purchase confirmations + "draw starting" DMs to holders |
| Realtime | Instant client-side board selection, live ticket board (green/amber/red/gold), live prize pool, countdown timer; wallet/tickets/history/leaderboard poll for fresh data |
| Admin reconciliation | `/admin` dashboard (KPIs, liabilities, house cut, daily chart) **plus a per-round P&L table** — sales in, prizes + refunds out, house cut, and a balance flag when a round doesn't reconcile |
| Accessibility & i18n | Full **English / Amharic** translations, typed success/error toasts with `aria-live`, keyboard focus rings, screen-reader labels on the ticket board, pinch-zoom enabled |
| Extras | Referral links + free-ticket rewards (idempotent, no self-referral), share buttons, all-time winners leaderboard, full multi-round history, animated confetti on win |

---

## 🧱 Project structure (Laravel monolith)

The original `/bot /backend /frontend /supabase` split is folded into one Laravel app:

```
app/
├── Console/Commands/CheckLotteryDeadlines.php   # deadline-driven draws (scheduled)
├── Enums/RoundStatus.php
├── Http/Middleware/                             # Telegram initData auth + admin gate
├── Jobs/                                        # SendTelegramMessage, ProcessDraw (queued)
├── Livewire/                                    # ← the Mini App "frontend" (screens)
├── Models/                                      # Round, Ticket, Player, NotificationLog
├── Services/                                    # LotteryService, DrawService, Referral, Notifier
└── Telegram/                                    # initData validator, MiniApp keyboards, auth
routes/
├── web.php          # Mini App pages + /telegram/webhook/{token}
├── telegram.php     # ← the "bot" (Nutgram command handlers)
└── console.php      # schedule
database/migrations/ # ← the "supabase" SQL, as portable Laravel migrations
resources/
├── css/app.css      # dark + gold theme
├── js/app.js        # Telegram SDK bridge (the only JS you need)
└── views/livewire/  # Blade screens
```

---

## 🚀 Install & local development

### Requirements

- **PHP 8.3+** with the usual Laravel extensions plus `pdo_sqlite` (local) / `pdo_mysql` (production)
- **Composer 2**
- **Node 18+ & npm** (only to build the front-end assets — the production server doesn't need Node)
- A database: **SQLite** is fine for local dev; **MySQL/MariaDB** for production

### Quick start (SQLite, no Telegram needed)

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
#   .env is preset for SQLite + a DEV_TELEGRAM_ID so you can test in a normal browser.

# 3. Database  (creates the file + runs all migrations + seeds one demo "Open" round)
touch database/database.sqlite
php artisan migrate --seed

# 4. Build assets + serve
npm run build        # or: npm run dev   (in a second terminal, for hot reload)
php artisan serve
# Open http://localhost:8000  — DEV_TELEGRAM_ID impersonates a Telegram user,
# and because it matches ADMIN_TELEGRAM_ID you also get the browser panel at /admin.

# 5. Process the queue (sends notifications, runs draws) in another terminal
php artisan queue:work
```

> One-shot setup: `composer run setup` does install → key:generate → migrate → npm build for you.

### Develop against MySQL locally (optional — mirrors production)

```bash
# Create a database + user, then point .env at it:
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_DATABASE=luckydraw
#   DB_USERNAME=lucky
#   DB_PASSWORD=secret
php artisan migrate:fresh --seed
```

### Watch a draw without tapping 100 numbers

```bash
php artisan lottery:demo-fill --players=10   # simulates buyers until the round sells out
php artisan queue:work                        # reveals the winners after the suspense delay
```
Then open `/` — the machine pulls each winning ball one-by-one with sound + confetti.

> Outside Telegram the app uses `DEV_TELEGRAM_ID` to fake a logged-in user. In production leave that blank — real users are authenticated by Telegram's signed `initData`.

### Connecting a real bot locally

1. Create a bot with [@BotFather](https://t.me/BotFather), copy the token into `TELEGRAM_BOT_TOKEN` and the username into `TELEGRAM_BOT_USERNAME`.
2. Set your Telegram numeric id (`@userinfobot`) into `ADMIN_TELEGRAM_ID`.
3. For local webhook testing expose your machine (e.g. `ngrok http 8000`), set `APP_URL`/`MINI_APP_URL` to the https URL, then register the webhook (see below). Or run long-polling locally: `php artisan nutgram:run`.
4. In @BotFather → **Bot Settings → Menu Button / Web App**, set the Mini App URL to your `MINI_APP_URL`.

---

## 🌐 Deploy to shared hosting (cPanel) — MySQL + cron + webhook

Shared hosting has **no long-running processes**, so the bot uses a **webhook** (not polling) and the queue/scheduler are driven by **cron**.

### 1. Create the database
cPanel → **MySQL® Databases** → create a database + user, add the user to the database with **All Privileges**. Note the names (they're usually prefixed, e.g. `cpaneluser_luckydraw`).

### 2. Build locally, then upload
Most shared hosts have no Node/Composer, so prepare everything on your machine:

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build          # creates public/build/
```

Upload the **whole project** (including `vendor/` and `public/build/`) to the server,
e.g. into `/home/cpaneluser/luckydraw` (ideally **above** `public_html`).

### 3. Point the domain at `/public`
Set the domain's **Document Root** to the project's `public/` folder
(cPanel → *Domains* → *Document Root*). Never expose the project root.

> Can't change the docroot? Put the app folder next to `public_html`, then either
> symlink (`ln -s /home/cpaneluser/luckydraw/public /home/cpaneluser/public_html`)
> or copy `public/` into `public_html` and edit its `index.php` paths to point at the app.

### 4. Configure `.env`
Copy `.env.example` → `.env` on the server and fill in:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=                      # run: php artisan key:generate  (or paste a generated key)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpaneluser_luckydraw
DB_USERNAME=cpaneluser_lucky
DB_PASSWORD=********

TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_BOT_USERNAME=YourLuckyDrawBot
ADMIN_TELEGRAM_ID=123456789
MINI_APP_URL="${APP_URL}"
# Optional: channel for public winner posts (bot must be an admin of it)
TELEGRAM_CHANNEL_ID=@yourchannel

# Browser /admin panel — REQUIRED in production. Generate a bcrypt hash with:
#   php artisan tinker --execute="echo bcrypt('your-strong-password');"
ADMIN_PANEL_USERNAME=youradmin
ADMIN_PANEL_PASSWORD_HASH='$2y$12$....'

# Payment verification (verify.leul.et). Leave VERIFY_API_KEY blank to disable deposits.
VERIFY_API_KEY=
# Your receiving account name(s)/number(s) — a deposit only counts if it was paid to one
# of these. STRONGLY recommended: without it, any verified payment is credited.
DEPOSIT_ACCOUNTS=

# Required so Telegram's embedded webview keeps the session cookie:
SESSION_SAME_SITE=none
SESSION_SECURE_COOKIE=true
```

> **Channel posts:** create a channel, add your bot as an **administrator**, and set `TELEGRAM_CHANNEL_ID` to `@channelusername` (or the numeric `-100…` id). Winner results are posted there; each winner also gets a personal DM. Leave it blank to skip channel posts.

### 5. Migrate + cache (via SSH or cPanel "Terminal")
```bash
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan storage:link
```
No SSH? Most hosts offer a **Terminal**, or you can trigger migrations once with a temporary
*Cron Job* line and then remove it.

### 6. Cron jobs (cPanel → Cron Jobs)
Add these two **every-minute** jobs (adjust the path & `php` binary, e.g. `php82`):

```cron
* * * * * cd /home/cpaneluser/luckydraw && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/cpaneluser/luckydraw && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

- The **scheduler** triggers deadline-based draws every minute.
- The **queue worker** sends Telegram notifications and runs the winner reveal.

> ⏱️ Trade-off: the 10-second suspense before the winner is announced may lag up to ~1 minute because the worker runs on a 1-minute cron. The draw result and notifications are always correct — just slightly less instant. For exact timing, run a real worker on a VPS/paid worker instead.

### 7. Register the Telegram webhook (run once)
```bash
php artisan nutgram:hook:set https://yourdomain.com/telegram/webhook/<TELEGRAM_BOT_TOKEN>
```
The `{token}` in the path must equal your bot token — it authenticates Telegram's calls.
Check it with `php artisan nutgram:hook:info`.

### 8. Register the bot command list (optional, nice menu)
```bash
php artisan nutgram:register
```

### 9. Set the Mini App button
@BotFather → your bot → **Bot Settings → Menu Button** → *Configure Web App* → enter `MINI_APP_URL`.
Now `/start` (and the menu button) opens the game. 🎉

---

## ✅ Post-deploy checklist

Run through this once after the steps above:

- [ ] `https://yourdomain.com` loads the Mini App (no 500) — check `storage/logs/laravel.log` if it doesn't.
- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- [ ] `ADMIN_PANEL_PASSWORD_HASH` is set (a bcrypt hash) — **don't ship the default password**.
- [ ] `/admin/login` works with your admin username + password.
- [ ] `php artisan nutgram:hook:info` shows your webhook URL with **no last error**.
- [ ] Sending `/start` to the bot replies and shows the **Open LuckyDraw** button.
- [ ] Both cron jobs exist and run (the schedule + the queue worker).
- [ ] `storage/` and `bootstrap/cache/` are writable by the web user.
- [ ] Deposits: `VERIFY_API_KEY` set and `DEPOSIT_ACCOUNTS` filled, or deposits intentionally disabled.
- [ ] A test round draws and pays out (use a small round; the queue cron runs the reveal within ~1 min).

---

## 🔄 Updating a live deployment

After pulling new code (or re-uploading), from the project root on the server:

```bash
composer install --no-dev --optimize-autoloader   # only if vendor changed
php artisan migrate --force                        # apply any new migrations
php artisan config:cache route:cache view:cache    # rebuild caches (re-run after ANY .env change)
php artisan queue:restart                          # let cron workers pick up new code
```

> Front-end changes must be rebuilt **locally** (`npm run build`) and the new `public/build/` re-uploaded — shared hosts have no Node. After changing `.env`, always re-run `php artisan config:cache`.

---

## 🩺 Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| **500 on every page** | Check `storage/logs/laravel.log`. Usually `APP_KEY` missing (`php artisan key:generate`) or `storage/`,`bootstrap/cache/` not writable. |
| **Mini App logs you out / "Open from Telegram"** | The session cookie is being dropped in Telegram's webview. Set `SESSION_SAME_SITE=none` **and** `SESSION_SECURE_COOKIE=true`, ensure HTTPS, then `php artisan config:cache`. |
| **Bot doesn't respond** | Webhook not set or wrong token. `php artisan nutgram:hook:info`; re-run `nutgram:hook:set …/telegram/webhook/<TOKEN>`. The path token must equal `TELEGRAM_BOT_TOKEN`. |
| **Draw never happens / winner reveal stuck** | The queue cron isn't running. Confirm the `queue:work` cron line and that `QUEUE_CONNECTION=database`. Re-run it manually once to see errors. |
| **Deposits always rejected** | `VERIFY_API_KEY` unset, or the verified receiver doesn't match `DEPOSIT_ACCOUNTS`. Check the exact failure in the toast / logs. |
| **Config changes have no effect** | Cached config. Run `php artisan config:clear` (dev) or `config:cache` (prod) after editing `.env`. |
| **`/admin` rejects the right password** | You set `ADMIN_PANEL_PASSWORD_HASH` but pasted a plaintext value — it must be a bcrypt hash (`bcrypt('…')`). |

---

## 🧪 Tests

```bash
php artisan test            # runs against an in-memory SQLite db
```

Covers `initData` signature validation, oversell protection, split tickets, referral
rewards (idempotency + no self-referral), auto-draw locking and the secure winner draw,
deposit verification (fail-closed, pending/wrong-account rejection, phone-digit matching),
the **integer-cents money helper** (`tests/Unit/MoneyTest.php`), exact prize splitting, and
the **concurrency guards** (`tests/Feature/ConcurrencyTest.php`: no double-payout, single
draw dispatch, no double-refund).

### Run the suite against MySQL (recommended before deploying)

Because the concurrency guards rely on real row locking, validate them on the engine you
actually ship on:

```bash
# create a throwaway db + user (luckydraw_test / lucky / luckypass), then:
vendor/bin/phpunit --configuration phpunit.mysql.xml
```

(`phpunit.mysql.xml` is a copy of `phpunit.xml` with the `DB_*` env pointed at MySQL.)

---

## 🗃️ Database schema

| Table | Purpose |
|-------|---------|
| `rounds` | title, total_tickets, ticket_price, currency, status, **winners_count, prize_structure (JSON tiers), allow_half_tickets, auto_restart, restart_delay_minutes, channel_id, admin_cut**, auto_draw, draw_deadline, winner_ticket_id |
| `tickets` | round_id, ticket_number, owner + co-owner (half/split), is_winner, **win_rank, prize_amount**, purchased_at |
| `players` | telegram_id (PK), name, phone, locale, referral_code, referred_by, **referral_rewarded_at** (one-shot reward guard), counters, balance, banned_at |
| `transactions` | telegram_id, type (deposit/withdrawal/purchase/winning/refund/adjustment), status, amount, balance_after, provider, reference, round_id, meta — with a **unique `(provider, reference)`** so a payment reference can only be credited once |
| `notifications_log` | every Telegram message sent, with status |

Schema is defined as portable Laravel migrations (`database/migrations/`) — run `php artisan migrate`. It works on MySQL, MariaDB, Postgres or SQLite unchanged.

---

## 💰 Wallet & payments

Players hold a **wallet balance**; tickets are bought from it, prizes are credited to it, and a cancelled round refunds everyone automatically.

- **Deposit** — the player pays your Telebirr/CBE/M-Pesa account, then pastes the **transaction reference** in the app. The backend calls the [verify.leul.et](https://verifyapi.leulzenebe.pro) API (`POST /verify`, `x-api-key`) to confirm the payment is real, reads the **actual amount**, optionally checks the **receiver matches your account** (`DEPOSIT_ACCOUNTS`), blocks reused references, and credits the wallet. Set `VERIFY_API_KEY` (from the verify.leul.et dashboard) to enable it.
- **Withdraw** — the amount is reserved (debited) immediately and queued; an admin pays it out by hand and taps **Mark paid** in `/admin` (or **Reject & refund**, which returns the funds).
- Configure with `VERIFY_API_KEY`, `DEPOSIT_ACCOUNTS`, `LOTTERY_MIN_DEPOSIT`, `LOTTERY_MIN_WITHDRAW`, `LOTTERY_DEPOSIT_INSTRUCTIONS`.

> Note: verify.leul.et verifies **payment transactions** (Telebirr/CBE/M-Pesa receipts), not SMS/OTP codes — which is exactly what's needed to confirm a deposit was actually paid.

> Local testing: `php artisan migrate:fresh --seed` funds the dev user's wallet, so you can buy tickets in a browser without a live payment API.

## 🔐 Security & correctness notes

- Mini App requests are authenticated by validating Telegram's HMAC-signed `initData` on every request (`App\Telegram\InitDataValidator`), with replay protection on `auth_date` — no spoofing a `telegram_id`.
- The browser `/admin` panel is password-protected (rate-limited, session-fixation-safe). **Set `ADMIN_PANEL_PASSWORD_HASH` (bcrypt) in production** — the plaintext `ADMIN_PANEL_PASSWORD` is only a first-run convenience. The `/admin` *bot command* is gated by `ADMIN_TELEGRAM_ID`.
- The webhook path embeds the bot token and is compared with `hash_equals`.
- Draws use `random_int` (CSPRNG). Purchases run inside a row-locked transaction to prevent overselling.
- **State transitions are atomic compare-and-sets** (`open → drawing → closed`, withdrawal approve/reject): a round can never be drawn twice, paid out twice, or refunded *and* paid out, even under concurrent triggers.
- **All money math is integer-cents** (`App\Support\Money`) — no float drift in balances, prize splits, or half-ticket payouts.
- **Deposits fail closed**: an unverified, pending/failed, reused, or wrong-account reference is rejected; the receiver check normalises phone digits (local `09…` vs international `2519…`).

---

## ⚙️ Environment variables

Full reference (see `.env.example` for the annotated template). **Bold** = you must set it for production.

### App & database
| Variable | Default | Purpose |
|----------|---------|---------|
| **`APP_KEY`** | — | Encryption key. Generate with `php artisan key:generate`. |
| **`APP_URL`** | — | Public HTTPS URL; docroot must point at `/public`. |
| `APP_ENV` | `production` | Set `production` live, `local` for dev. |
| `APP_DEBUG` | `false` | **Keep `false` in production.** |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | Default UI language (`en` or `am`). |
| **`DB_CONNECTION`** | `mysql` | `mysql` in production; `sqlite` for quick local dev. |
| **`DB_HOST` / `DB_PORT`** | `127.0.0.1` / `3306` | MySQL host/port. |
| **`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`** | — | Your cPanel MySQL credentials. |
| `SESSION_SAME_SITE` | `none` | **Must be `none`** so Telegram's webview keeps the cookie. |
| `SESSION_SECURE_COOKIE` | `true` | **Must be `true`** (HTTPS) for `SameSite=none`. |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | `database` | DB-backed — no Redis needed. |

### Telegram & admin
| Variable | Default | Purpose |
|----------|---------|---------|
| **`TELEGRAM_BOT_TOKEN`** | — | From @BotFather. Also forms the webhook URL path. |
| **`TELEGRAM_BOT_USERNAME`** | — | Used to build referral deep links. |
| **`MINI_APP_URL`** | `${APP_URL}` | URL Telegram loads as the Mini App. |
| `ADMIN_TELEGRAM_ID` | — | Telegram id(s) allowed the `/admin` **bot command** (comma-separated). |
| **`ADMIN_PANEL_USERNAME`** | `kichner` | Browser `/admin` username — **change it**. |
| **`ADMIN_PANEL_PASSWORD_HASH`** | — | bcrypt hash for `/admin`. Generate: `bcrypt('…')`. **Set this in production.** |
| `ADMIN_PANEL_PASSWORD` | `blackflag` | Plaintext fallback for first run only — leave unused in production. |
| `TELEGRAM_CHANNEL_ID` | — | Optional `@channel`/`-100…` for public winner posts (bot must be admin). |

### Gameplay & payments
| Variable | Default | Purpose |
|----------|---------|---------|
| `LOTTERY_CURRENCY` | `ETB` | Currency label shown throughout. |
| `LOTTERY_DRAW_SUSPENSE` | `10` | Seconds of suspense before the winner reveal. |
| `LOTTERY_REFERRAL_REWARD` | `1` | Free tickets a referrer earns on a referee's first buy. |
| `LOTTERY_MAX_PER_PURCHASE` | `20` | Max numbers buyable in one tap-to-buy. |
| `LOTTERY_ANNOUNCE_PLAYERS` | `false` | Also DM every player on a new round (noisy). |
| `VERIFY_API_URL` | `https://verifyapi.leulzenebe.pro` | Payment-verification API base. |
| `VERIFY_API_KEY` | — | Enables deposits. Blank = deposits disabled. |
| `DEPOSIT_ACCOUNTS` | — | Your receiving account(s); a deposit only counts if paid to one. **Strongly recommended.** |
| `LOTTERY_MIN_DEPOSIT` / `LOTTERY_MIN_WITHDRAW` | `10` / `50` | Minimum deposit / withdrawal. |
| `LOTTERY_DEPOSIT_INSTRUCTIONS` | (text) | Instructions shown on the deposit screen. |

### Local dev only
| Variable | Default | Purpose |
|----------|---------|---------|
| `DEV_TELEGRAM_ID` | — | Impersonate a Telegram user in a plain browser. **Leave blank in production.** |
| `DEV_TELEGRAM_NAME` / `DEV_TELEGRAM_PHONE` | — | Name/phone for the dev impersonated user. |

---

## 📋 Bot commands

| Command | Description |
|---------|-------------|
| `/start` | Open the Mini App (handles `?start=ref_CODE` referral deep links) |
| `/balance` | Wallet balance + open the wallet |
| `/mytickets` | List your tickets |
| `/results` | Latest draw result |
| `/admin` | Admin control panel (restricted) |
| `/help` | How to play |
