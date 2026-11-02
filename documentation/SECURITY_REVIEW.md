# Security review (Phase 6)

**Scope:** the PHP API, admin panel, installer, relay, mobile app and payment flows, as of this release.

**Method:** code review of every entry point, plus the automated end-to-end suites below, which act as regression tests for the controls listed.

## Summary

There are no open high-severity issues. One high-severity dependency issue and one hardening gap were found and fixed in this phase. The remaining items are deployment steps for you, listed at the end.

| # | Finding | Severity | Status |
| --- | --- | --- | --- |
| 1 | Relay used `ws` 8.18.3, which has a memory-disclosure flaw and a fragment-flood DoS (GHSA-58qx-3vcg-4xpx, GHSA-96hv-2xvq-fx4p) | High | **Fixed**: pinned `ws` 8.21.3. `npm audit` is clean and the relay tests pass |
| 2 | Test shortcuts (fake Razorpay orders, local Google stand-in) depended only on `APP_ENV=local`. A production server mistakenly left on `local` could have created orders without Razorpay | Medium (needs a misconfiguration) | **Fixed**: they now also require a localhost `APP_URL` (`Config::devMode()`). The admin health check flags a non-production `APP_ENV` |
| 3 | Mobile build tooling (`@expo/cli` and others, through `glob`/`minimatch`) has moderate advisories | Low | Accepted. These run on the build machine only and are not shipped in the app. Update with the next Expo SDK |
| 4 | Not end-to-end encrypted | By design | Disclosed in the app and docs. The app never claims E2EE |

## Controls checked

**Authentication and sessions**
- Passwords are hashed with Argon2id.
- Logins for unknown accounts take the same time as real ones (dummy hash), so they don't reveal which emails exist.
- Logins are rate-limited per IP and per account.
- Device tokens are 256-bit random values, stored only as SHA-256 hashes. They expire after a sliding 90 days and can be revoked per device or all at once.
- Changing or resetting a password signs out the other devices.
- Suspended or banned users are signed out at once.

**Authorisation**
- Every chat, media, game and hub query is limited to the caller's couple (`CoupleContext`).
- Media is served only to the couple, through a bearer token or a signed link valid for 15 minutes. Media ids are never enough on their own to fetch a file.
- 18+ content needs all of these: the section turned on, both partners 18+, both opted in separately, and Premium.

**Admin**
- Admin accounts are separate from app accounts, and required TOTP (two-step sign-in) protects them.
- Sessions use an `HttpOnly`, `SameSite=Strict` cookie and end after 30 minutes idle.
- Every POST carries a CSRF token and passes an origin check. Pages send a CSP that allows no scripts.
- Roles are enforced on each route, and an append-only audit log records every action.
- Moderators see only the messages a reporter attached. The owner-only **case access** feature is time-limited, needs a stated reason and a fresh 2FA code, and logs every view (`phase5_admin_test.php`).

**Injection**
- All SQL uses prepared statements. The few dynamic column and table names come from fixed allow-lists, never from request input.
- Admin templates escape all output.
- Theme colours accept only `#hex` or `rgba()` values.

**Uploads**
- The file type is checked from its bytes (`finfo`), not the name the client sends. Executable and script extensions are blocked.
- Files are stored encrypted (XChaCha20-Poly1305) outside the web root.
- Files are served with `nosniff`, `Content-Security-Policy: sandbox` and `Content-Disposition`.

**Payments**
- Razorpay: the checkout signature and the webhook HMAC are verified, and the amount and currency are checked against the server's own records.
- Google Play: the server verifies every purchase with Google, ties it to the buyer's account with an HMAC tag, and acknowledges it. RTDN messages need a secret token.
- Every payment and order id is unique, and webhook events are de-duplicated, so a payment can never be applied twice.
- Refunds (admin, Razorpay webhook or a Play void) revoke what the order granted.
- No secret ever reaches the app. The admin panel shows only whether each secret is set.

**Transport and headers**
- Production requires HTTPS and sends HSTS.
- Every response sends `nosniff`, `no-referrer` and `X-Frame-Options: DENY`. API responses send `Cache-Control: no-store`.
- CORS is limited to the origins listed in `CORS_ORIGINS`.
- Dotfiles are blocked by `.htaccess`.

**Installer**
- It refuses to run once `.env` exists.
- It accepts only a localhost database, so whoever finds the page first cannot point the server at a database they control.
- It writes `.env` with permission 0600, generates fresh keys, and deletes itself.

**Relay and calls**
- Connections use HMAC-signed tickets that expire. Publishing requires a signed request no more than 120 seconds old.
- Each message is limited to 8 KB, and each peer to 50 messages per second.
- TURN uses short-lived HMAC credentials. coturn blocks relaying to private address ranges.

**Notifications**
- Notifications carry no message text by default. Previews appear only if the user opts in.
- 18+ game names never appear in a notification.

**Privacy**
- Users can export their data and delete their account; the account is removed 7 days after the request.
- Disappearing messages and deleted media are removed from disk by the cron job.

## Automated suites

These run as regression tests for the controls above.

| Suite | Checks |
| --- | --- |
| `tests/api_test.php` | 95 |
| `tests/phase2_test.php` | 105 |
| `tests/phase4_test.php` | 34 |
| `tests/phase5_admin_test.php` | 76 |
| `tests/phase6_test.php` | 25 |
| `relay/test.js` | 11 |
| `tests/e2e/mobile_web_smoke.js` | 19 |
| `tests/e2e/call_two_browsers.js` | 11 |

## Your deployment checklist (security)

- [ ] `APP_ENV=production`, HTTPS on, and the `/health` endpoint loads over https.
- [ ] `public/install.php` is gone. The installer deletes itself; check in File Manager anyway.
- [ ] `.env`, the Firebase and Play key files, and the storage folder are all outside `public_html`. `.env` has permission 600.
- [ ] Back up `.env` (especially `APP_KEY` and `MEDIA_KEY`) and the EAS keystore somewhere offline.
- [ ] Turn on two-step sign-in for every admin (it is required by default). Consider an IP allow-list for `/admin` (see `ADMIN_PANEL.md`).
- [ ] Use Razorpay **live** keys only once the webhook is set up and shows as delivered.
- [ ] The privacy policy describes case access exactly as the app does.
- [ ] Run the cron job every minute. The admin health check shows the heartbeat.
