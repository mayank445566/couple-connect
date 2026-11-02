# Couple Connect

A private space for couples: chat, games, long-distance activities and calls.

| Folder | What it is | Status |
| --- | --- | --- |
| `backend/` | PHP 8.2 REST API for cPanel (no framework, PDO, PHPMailer) | Phase 1: auth, devices, pairing, chat, sync, encrypted media. Phase 2: games engine, 18+ consent, ₹199 Razorpay unlock, themes, long-distance hub, phone OTP |
| `database/` | Migrations (`backend/bin/migrate.php`) and starter content: 31 games, 300 questions, 14 themes (`backend/bin/seed.php`) | |
| `tests/` | End-to-end API tests with real accounts | Phase 1: 95 checks, Phase 2: 105 checks, all passing |
| `documentation/` | API reference and cPanel deployment guide | |
| `mobile-app/` | React Native (Expo) Android app | Phase 3 |
| `relay/` | WebSocket relay + coturn config for the VPS | Phase 4 |

Start with `documentation/CPANEL_SETUP.md`.

## Local development

```
cd backend && composer install && cp .env.example .env   # set APP_ENV=local, MAIL_DRIVER=log, keys, DB
php bin/migrate.php && php bin/seed.php
php -S 127.0.0.1:8088 -t public public/index.php
API=http://127.0.0.1:8088 php ../tests/api_test.php && API=http://127.0.0.1:8088 php ../tests/phase2_test.php
```
