# Couple Connect REST API (Phase 1)

Base URL: `https://api.yourdomain.com` (wherever `backend/public` is served).

- All requests and responses are JSON (`Content-Type: application/json`), except media upload (multipart) and media download (binary).
- Authenticated endpoints need `Authorization: Bearer <token>`. Tokens are per device, last 90 days of inactivity, and can be revoked.
- Success: `{"ok": true, "data": ...}`. Error: `{"ok": false, "error": {"code": "...", "message": "...", "fields": {...}}}`.
- Times are UTC ISO 8601 (`2026-11-02T07:12:27.993Z`).
- `429 rate_limited` responses include a `Retry-After` header.

Common error codes: `validation_failed` (422, see `fields`), `unauthenticated` (401), `not_paired` (409), `not_found` (404), `rate_limited` (429), `email_not_verified` (403), `account_suspended` / `account_banned` (403).

## Auth

| Method | Path | Auth | Body | Notes |
| --- | --- | --- | --- | --- |
| POST | /auth/register | no | `email, password, display_name, dob (YYYY-MM-DD), accept_terms: true, relationship_status?, device_name?, platform? (android/ios/web)` | 201 → `{token, user}`. Emails a 6-digit verification code. Minimum age = `MIN_AGE` (default 18). Password: 8+ chars with a letter and a number. |
| POST | /auth/login | no | `email, password, device_name?, platform?` | → `{token, user}`. Locked for 15 min after 5 failures per email or 30 per IP. Signing in cancels a pending account deletion. |
| POST | /auth/verify-email | yes | `code` | 6 digits, 15 minutes, 5 attempts. |
| POST | /auth/resend-verification | yes | – | Max 3 per 15 min. |
| POST | /auth/forgot-password | no | `email` | Always returns `{sent: true}` (does not reveal accounts). |
| POST | /auth/reset-password | no | `email, code, password` | Signs out every device. |
| POST | /auth/logout | yes | – | Revokes this device's token. |
| POST | /auth/logout-all | yes | – | Revokes every token. |
| GET | /auth/sessions | yes | – | Devices: `id, device_name, platform, ip, created_at, last_used_at, current`. |
| DELETE | /auth/sessions/{id} | yes | – | Sign out one device. |

## Profile

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /me | – | Own profile incl. `paired`, `couple_id`, `privacy`. |
| PATCH | /me | `display_name?, bio?, relationship_status?, last_seen_visibility? (partner/nobody), read_receipts? (bool), avatar_media_id?` | Upload the avatar with `POST /media kind=avatar` first. |
| POST | /me/password | `current_password, new_password` | Signs out other devices. |
| POST | /me/delete | `password` | Unpairs, signs out, purges after 7 days (cron). |
| GET | /me/export | – | JSON export of the user's data. |

## Couple pairing

Pairing needs two separate approvals: the invitee enters the code (and sees who it belongs to), then the inviter accepts (and sees who joined).

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| POST | /couple/invite | – | 201 → `{request_id, code (6 digits), qr_payload, share_text, expires_at}`. 15 minutes, single use, replaces earlier invites. Requires verified email and no partner. |
| POST | /couple/join | `code` or `qr_token` (raw token or full `coupleconnect://pair?t=...`) | → `{request_id, status: "pending_approval", inviter}`. 10 attempts / 15 min per user. |
| GET | /couple/requests | – | `{open_invite, incoming: [{request_id, from}], outgoing: {request_id, status, to}}` |
| POST | /couple/requests/{id}/accept | – | Inviter only. Creates the couple → same shape as `GET /couple`. |
| POST | /couple/requests/{id}/reject | – | Inviter only. |
| POST | /couple/requests/{id}/cancel | – | Either side. |
| GET | /couple | – | `{couple_id, couple_code, partner, paired_at, relationship_start, anniversary_date, next_meeting_at, days_together, settings}` |
| PATCH | /couple | `relationship_start?, anniversary_date?, next_meeting_at? (ISO), disappearing_seconds? (0/3600/86400/604800), wallpaper?` | Applies to both partners. |
| POST | /couple/unpair | `password, block? (bool)` | Ends the couple for both. `block` prevents re-pairing with that person. |

## Messages

Message object:

```json
{
  "id": 41, "client_id": "uuid", "sender_id": 1, "mine": true,
  "type": "text|image|video|audio|voice|file|sticker|gif|deleted",
  "body": "text or caption", "media": {"id": 7, "url": "...", "mime": "image/jpeg", "size": 1234, "width": 1080, "height": 720, "duration_ms": null, "name": "x.jpg"},
  "meta": null, "reply_to_id": null, "forwarded": false,
  "reactions": [{"user_id": 2, "emoji": "❤️"}], "starred": false, "pinned": false,
  "created_at": "...", "edited_at": null, "deleted": false,
  "delivered_at": "...", "read_at": "...", "expires_at": null
}
```

| Method | Path | Body / query | Notes |
| --- | --- | --- | --- |
| GET | /messages | `?limit=30&before=<id>` | Newest first. Returns `{messages, has_more, cursor, unread_count}`. Start syncing from `cursor`. |
| POST | /messages | `client_id (UUID v4), type?, body?, media_id?, meta?, reply_to_id?, forward_of?` | 201 new, 200 if the same `client_id` was already stored (safe retries). 120 per minute. Stickers: `meta: {sticker_id}`; GIFs: `meta: {url (https), width?, height?}`. |
| PATCH | /messages/{id} | `body` | Own text messages, within `EDIT_WINDOW_MINUTES`. |
| DELETE | /messages/{id} | `?scope=me` or `?scope=everyone` | Everyone: own messages within `DELETE_FOR_EVERYONE_WINDOW_MINUTES`; leaves a tombstone. |
| POST / DELETE | /messages/{id}/reactions | `emoji` | One reaction per person per message. |
| POST / DELETE | /messages/{id}/pin | – | Shared by the couple. |
| POST / DELETE | /messages/{id}/star | – | Private to each person. |
| GET | /messages/starred | – | |
| GET | /messages/pinned | – | |
| GET | /messages/search | `?q=` | 2–100 characters. |
| POST | /messages/delivered | `up_to_id` | Call when messages arrive on the device. |
| POST | /messages/read | `up_to_id` | Call when the chat is visible. If the reader turned off read receipts, the sender never sees `read_at`. |

## Sync and presence

`GET /sync?since=<cursor>` returns every change since the cursor:

```json
{
  "cursor": 120, "has_more": false, "reset": false,
  "events": [{"id": 119, "type": "message.created", "actor_id": 1, "payload": {"id": 41}, "at": "..."}],
  "messages": [ /* current state of every message touched by these events */ ],
  "removed_message_ids": [ /* hidden by me or expired: remove locally */ ],
  "partner": {"online": true, "typing": false, "last_seen_at": "..."},
  "unread_count": 3
}
```

Event types: `message.created`, `message.updated` (edit, reaction, pin, delete for everyone), `message.hidden`, `message.delivered`, `message.read`, `message.expired`, `couple.paired`, `couple.updated`, `couple.unpaired`, `profile.updated`.

Client loop: poll every 3 s while the chat is open, every 15–30 s in the foreground elsewhere, and immediately on a push or relay nudge. If `has_more` is true, call again at once. If `reset` is true, reload `GET /messages`. A `409 not_paired` means the couple ended.

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| POST | /presence/typing | `typing: true/false` | Lasts 6 s; resend every 4 s while typing. |

## Media

| Method | Path | Notes |
| --- | --- | --- |
| POST | /media | multipart: `file`, `kind` (image, video, audio, voice, file, avatar), `duration_ms?`. 201 → `{id, mime, size, width, height, url}`. Type is checked from file bytes. JPEG/PNG/WebP are re-encoded (removes EXIF/GPS, max 2560 px; avatars 640 px). Encrypted at rest. |
| GET | /media/{id} | Bearer token required. Supports `Range` for video/audio seeking. Only the uploader and their current partner can read it. |
| GET | /media/{id}/link | → `{url, expires_at}`: a signed URL valid 15 minutes, for players that cannot send headers. |

Limits: images 15 MB, audio/voice 20 MB, video and files `MAX_UPLOAD_MB` (default 50; also raise `upload_max_filesize` in cPanel).

## Safety

| Method | Path | Body |
| --- | --- | --- |
| POST | /reports | `reason (harassment/abuse/underage/impersonation/spam/other), details?, reported_user_id?, message_ids? (up to 50, copied into the report so moderators see only these)` |
