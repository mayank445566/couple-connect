# Couple Connect

A private space for couples: chat, games, long-distance activities and calls.

| Folder | What it is | Status |
| --- | --- | --- |
| `backend/` | PHP 8.2 REST API for cPanel (no framework, PDO, PHPMailer) | Phase 1: auth, devices, pairing, chat, sync, encrypted media. Phase 2: games engine, 18+ consent, ₹199 Razorpay unlock, themes, long-distance hub, phone OTP. Phase 5: admin panel at `/admin` |
| `database/` | Migrations (`backend/bin/migrate.php`) and starter content: 31 games, 300 questions, 14 themes (`backend/bin/seed.php`) | |
| `tests/` | End-to-end API tests with real accounts, plus browser tests | Phase 1: 95, Phase 2: 105, Phase 4: 34, Phase 5 admin: 76 checks; web app 19 and two-browser call 11 checks; relay 11. All passing |
| `documentation/` | API reference, cPanel deployment, payments, mobile app build guide, admin panel | |
| `mobile-app/` | React Native (Expo SDK 58) Android app, also runs on web | Phase 3: every screen for the Phase 1 and 2 API. 19-step browser test passing |
| `relay/` | WebSocket relay (Node, no database) + coturn and nginx config for a small VPS | Phase 4: instant sync, call signalling nudges, live drawing |

Start with `documentation/CPANEL_SETUP.md`, then `documentation/VPS_SETUP.md` for calls and instant sync.

## Local development

```
cd backend && composer install && cp .env.example .env   # set APP_ENV=local, MAIL_DRIVER=log, keys, DB
php bin/migrate.php && php bin/seed.php
php -S 127.0.0.1:8088 -t public public/index.php
API=http://127.0.0.1:8088 php ../tests/api_test.php && API=http://127.0.0.1:8088 php ../tests/phase2_test.php && API=http://127.0.0.1:8088 php ../tests/phase4_test.php && API=http://127.0.0.1:8088 php ../tests/phase5_admin_test.php
php bin/admin.php create you@example.com "You" owner   # then open http://127.0.0.1:8088/admin
```

Mobile app (see `documentation/MOBILE_APP.md`):

```
cd mobile-app && npm install
EXPO_PUBLIC_API_URL=http://127.0.0.1:8088 npx expo start --web     # quickest way to try it
```
