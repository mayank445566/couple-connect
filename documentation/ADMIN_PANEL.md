# Admin panel

The admin panel is part of the PHP backend. Open `https://api.yourdomain.com/admin`. It uses plain server-rendered pages with no JavaScript, so it works on any cPanel host.

## First owner account

Run this in cPanel Terminal, or over SSH:

```
cd ~/couple-connect/backend
php bin/admin.php create you@yourdomain.com "Your Name" owner
```

The command prints a temporary password. When you first sign in, the panel makes you:

1. choose your own password (at least 12 characters, with a letter and a number);
2. turn on two-step sign-in with an authenticator app (Google Authenticator, Microsoft Authenticator, Authy or 1Password). Choose **Enter a setup key** in the app.

Other commands:

| Command | What it does |
| --- | --- |
| `php bin/admin.php list` | Lists all admins, their roles and whether two-step sign-in is on |
| `php bin/admin.php reset-password EMAIL` | Prints a new temporary password, unlocks the account and signs it out everywhere |
| `php bin/admin.php reset-2fa EMAIL` | Removes two-step sign-in, for example after a lost phone. The admin sets it up again at next sign-in |

Admin accounts are separate from app accounts.

## Roles

| Role | Can use |
| --- | --- |
| Owner | Everything, including case access, settings, pricing, admins and the audit log |
| Moderator | Users (view, suspend, ban, sign out of devices) and reports |
| Finance | Users (view), orders, refunds, webhook log, manual Premium grants |
| Content | Games, question packs, questions and themes |

Owners add other admins under **Admins**. Each new admin gets a temporary password, shown once. Give it to them privately.

## Security

- **Sessions.** Each sign-in is an opaque token kept in a cookie: `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS, limited to `/admin`. A session ends after 30 minutes of inactivity, and after 12 hours in any case.
- **Two-step sign-in.** It is required for every admin unless you set `ADMIN_REQUIRE_2FA=false` in `.env` (not recommended).
  - The secret is stored encrypted with a key derived from `APP_KEY`.
  - Each code works only once.
  - Five wrong codes end the sign-in attempt.
- **Lockout and limits.** Ten wrong passwords lock the account for 15 minutes. Attempts are also rate-limited per IP address and per email address.
- **Form protection.** Every form carries a CSRF token. POSTs that come from another website are refused.
- **Page headers.** Pages send a strict Content-Security-Policy (no scripts at all), `X-Frame-Options: DENY`, `Cache-Control: no-store` and `noindex`.
- **Instant sign-out.** Disabling an admin, changing their role or resetting their password signs them out everywhere.
- **Audit log.** Every change is written to an append-only audit log (`admin_audit_log`), including failed sign-ins. The panel cannot edit or delete it.
- **No secrets shown.** Secrets from `.env` (Razorpay, SMTP, Firebase) never appear in the panel. The **Settings** health check only says whether each one is set.

You can put the panel behind an IP allow-list using the host's tools. On Apache/LiteSpeed, add this to `public/.htaccess` and replace the example address:

```
<If "%{REQUEST_URI} =~ m#^/admin#">
  Require ip 203.0.113.10
</If>
```

## What admins can and cannot see

Chats are private to the couple. The panel enforces this in three layers.

| Who | Chats and photos they can see |
| --- | --- |
| Everyone in the panel | None. Users, dashboard, orders and content pages show account facts and counts only. Couples' own custom game questions are never listed. |
| Moderators (and owners) on a report | Only the messages the reporter chose to attach, as they were when reported. A photo opens only if it was attached to that report. Each view is logged. |
| An owner with **case access** | One couple's full chat and photos, for a stated case, for up to 24 hours. Details below. |

### Case access

Use it when you suspect illegal activity, or when you have a user report, a legal request (for example a police notice) or a safety emergency. To open it, choose **Open case access** from a user or a report, then give:

- **a basis:**
  - suspected illegal activity;
  - user report (the reference must be a real report from that couple);
  - legal request;
  - safety emergency;
- **a reference:** report number, notice number or internal ticket;
- **a written reason** of at least 20 characters;
- **a fresh two-step code.**

Then:

- Access lasts 24 hours, or until you click **Close case now**.
- Only the owner who opened the case can view it. Every owner can see that the case exists, with its reason.
- Every page of messages and every photo opened is written to the audit log, along with the case number.
- Messages deleted for everyone, disappearing messages that have expired, and deleted photos are gone. Case access cannot recover them.

The app tells users about this. **Settings → Who can see your chats** explains it, and your privacy policy must say the same. Suggested text:

> Your chats are encrypted in transit and photos are encrypted on our servers. Chats are not end-to-end encrypted. Our staff do not browse chats. Moderators see only messages you attach to a report. If we suspect illegal activity, or receive a legal request or a safety report, an authorised owner may open one couple's chat for up to 24 hours. Every such access is logged and reviewed. Messages you delete for everyone, and expired disappearing messages, cannot be recovered.

Note on Indian law: under the DPDP Act 2023, users must be told how their data is used, and access must be limited to that stated purpose. This disclosure and the logged, time-limited design are how the panel meets that. Have a lawyer review your final privacy policy.

## Pages

| Page | What you can do |
| --- | --- |
| **Dashboard** | See users, new sign-ups, active couples, messages and calls per day (counts only), open reports (any under-18 reports are flagged first), revenue, refunds and stuck webhooks |
| **Users** | Search by email, name, phone or ID. Suspend, ban or reactivate, with a reason; this signs the person out of every device at once. Sign them out of all devices. Grant or revoke Premium and themes, with a reason |
| **Reports** | Open reports are listed oldest first, with under-18 reports at the top. Opening a report marks it as being reviewed. Resolve or dismiss it with a note, optionally suspending or banning the reported account |
| **Orders** | Search by order, user, email, `order_…` or `pay_…`. A full refund goes through the Razorpay API and removes the Premium that order gave. It is idempotent with Razorpay's `refund.processed` webhook, and two admins cannot refund the same order twice. The webhook log is here too |
| **Games / Themes** | Edit a game's title, description, rounds, order and visibility. Add or edit packs and questions. Create or edit themes; colours are checked, and only `#hex` or `rgba()` values are allowed. 18+ games and packs are always Premium |
| **Pricing** | Change the price, title and on-sale status of the ₹199 product. New orders use the new price; people who already paid keep Premium. Google Play prices are set in the Play Console |
| **Settings** | Turn the 18+ section on or off. Choose whether Premium is shared with the partner, whether themes are part of Premium, and which pack the daily question comes from. A server health check covers HTTPS, cron heartbeat, Razorpay test or live keys, email, push, relay, TURN and 2FA |
| **Admins** | Add admins, change roles, disable or enable them, reset passwords or two-step sign-in. You cannot disable or demote the last owner, or change yourself |
| **Audit log** | Filter by action (for example `case_`), admin or target |

## Testing

```
API=http://127.0.0.1:8088 php tests/phase5_admin_test.php
```

This covers 76 checks:

- sign-in, CSRF, cross-site POST, two-step codes and replay;
- every role's access;
- report moderation (attached-only), suspension and app sign-out;
- refunds and Premium removal;
- content edits and theme validation;
- settings;
- the full case access flow and its limits;
- idle timeout.

It must run on the API machine, because it reads 2FA secrets from the database.
