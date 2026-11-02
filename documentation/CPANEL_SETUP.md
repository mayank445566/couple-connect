# Deploying the Couple Connect API on cPanel

This covers Phase 1 (accounts, pairing, chat, media). No Node.js, Docker or root access is needed. An installer wizard arrives in Phase 6; until then the steps below take about 15 minutes.

## Requirements

- PHP 8.2 or newer (cPanel → **MultiPHP Manager**), with extensions: `pdo_mysql`, `sodium`, `fileinfo`, `mbstring`, `gd`, `curl`, `openssl` (cPanel → **Select PHP Version → Extensions**).
- MySQL 8.0+ or MariaDB 10.6+.
- An SSL certificate on the API domain (cPanel → **SSL/TLS Status → Run AutoSSL**).
- Terminal access is convenient but optional.

## 1. Create a subdomain

cPanel → **Domains** → create `api.yourdomain.com`. Set its **document root** to `couple-connect/backend/public` (not `public_html`). Only the `public` folder is web-reachable; code, `.env` and storage stay private.

## 2. Upload the code

Upload `couple-connect-phase1.zip` with **File Manager** to your home directory (`/home/USER/`) and extract it. You should have:

```
/home/USER/couple-connect/backend/public/index.php
/home/USER/couple-connect/backend/vendor/...
/home/USER/couple-connect/database/migrations/001_core.sql
```

The `vendor` folder is included, so Composer is not required on the server.

## 3. Create the database

cPanel → **MySQL Databases**: create a database and a user with a strong password, then add the user to the database with **ALL PRIVILEGES**.

## 4. Configure `.env`

Copy `backend/.env.example` to `backend/.env` and fill in:

- `APP_URL` = `https://api.yourdomain.com`
- `APP_KEY` and `MEDIA_KEY`: two different 64-character hex strings. In Terminal: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` (run it twice). Keep a safe offline copy of `MEDIA_KEY`: if it is lost, uploaded media cannot be decrypted.
- `DB_NAME`, `DB_USER`, `DB_PASS` (cPanel prefixes them, e.g. `user_cc`).
- `STORAGE_PATH=/home/USER/couple-connect-storage` (outside `public_html`).
- SMTP settings: use an email account created in cPanel → **Email Accounts** (host `mail.yourdomain.com`, port 465, SSL).

Set the file permission of `.env` to `600`.

## 5. Create the tables

Terminal:

```
cd ~/couple-connect/backend
php bin/migrate.php
```

Without Terminal: open phpMyAdmin, select the database, **Import** `database/migrations/001_core.sql`.

## 6. Cron job

cPanel → **Cron Jobs** → every minute:

```
/usr/local/bin/php /home/USER/couple-connect/backend/bin/cron.php >/dev/null 2>&1
```

(Check the PHP path in MultiPHP; on some hosts it is `/opt/cpanel/ea-php82/root/usr/bin/php`.)

## 7. Upload limits

cPanel → **MultiPHP INI Editor** for the API domain: `upload_max_filesize = 64M`, `post_max_size = 70M`, `memory_limit = 256M`. The included `.user.ini` sets these where the host allows it.

## 8. Check it

Open `https://api.yourdomain.com/health`. You should see `{"ok":true,"data":{"status":"ok",...}}`.

Run the end-to-end test from any computer with PHP (it creates test accounts, so use a staging database or delete them afterwards):

```
API=https://api.yourdomain.com MAIL_LOG=/path/to/mail.log php tests/api_test.php
```

The test reads verification codes from the mail log, so run it on a staging copy with `MAIL_DRIVER=log`.

## Backups

cPanel → **Backup** (or JetBackup) for the database daily, and include `couple-connect-storage`. Media files are encrypted, so backups are safe to store off-site; keep `MEDIA_KEY` separately from them.

## Troubleshooting

- **401 on every request** although the token is right: the host strips the `Authorization` header. The included `.htaccess` restores it; make sure `.htaccess` files are allowed (AllowOverride) and that mod_rewrite is on.
- **500 with a ref code**: details are in `STORAGE_PATH/logs/php-error.log`.
- **Emails not arriving**: test with `MAIL_DRIVER=log` first, then check SMTP credentials and SPF/DKIM in cPanel → **Email Deliverability**.
