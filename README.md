# Couple Connect

A private space for couples: chat, games, long-distance activities and calls.

| Folder | What it is | Status |
| --- | --- | --- |
| `backend/` | PHP 8.2 REST API for cPanel (no framework, PDO, PHPMailer) | Phase 1 done: auth, devices, pairing, chat, sync, encrypted media |
| `database/migrations/` | MySQL/MariaDB schema, applied by `backend/bin/migrate.php` | Core tables |
| `tests/` | End-to-end API test with real accounts | 95 checks passing |
| `documentation/` | API reference and cPanel deployment guide | |
| `mobile-app/` | React Native (Expo) Android app | Phase 3 |
| `relay/` | WebSocket relay + coturn config for the VPS | Phase 4 |

Start with `documentation/CPANEL_SETUP.md`.

## Local development

```
cd backend && composer install && cp .env.example .env   # set APP_ENV=local, MAIL_DRIVER=log, keys, DB
php bin/migrate.php
php -S 127.0.0.1:8088 -t public public/index.php
API=http://127.0.0.1:8088 php ../tests/api_test.php
```
