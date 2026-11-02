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

---

# Phase 2 endpoints

## Phone signup and login

| Method | Path | Auth | Body | Notes |
| --- | --- | --- | --- | --- |
| POST | /auth/phone/request-otp | no | `phone` (E.164, or 10-digit Indian number) | 3 per 15 min per number. Same answer whether or not an account exists. |
| POST | /auth/phone/register | no | `phone, code, password, display_name, dob, accept_terms, email?, relationship_status?` | 201 → `{token, user}` with `phone_verified: true`. |
| POST | /auth/phone/login | no | `phone` + `code` **or** `password` | Locked after 5 failures. |
| POST | /me/phone | yes | `phone, code` | Add or change the phone on an account. |

## Games

Modes: `prompt` (a question, truth/dare or challenge), `match` (both pick an option), `guess` (one answers about themself, the other guesses), `quiz` (has a correct answer).

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /games | – | `{games: {free: [...], adult: [...]}, premium, adult: {available, my_opt_in, partner_opt_in, premium}}`. Each game lists packs with `locked`. |
| GET | /games/{slug} | – | One game with packs. |
| POST | /games/sessions | `game (slug), pack_id?, level?, rounds?` | Invites the partner (`game.invited` event). Adult levels: flirty, romantic, bold, intimate, spicy. Free levels: easy, medium, hard. |
| GET | /games/sessions | `?status=` | History (last 50). |
| GET | /games/sessions/{id} | – | Session state (below). |
| POST | /games/sessions/{id}/join | – | Partner accepts; round 1 starts. |
| POST | /games/sessions/{id}/decline | – | |
| POST | /games/sessions/{id}/answer | `answer` | See answer formats. Partner's answer stays hidden until the round is revealed. |
| POST | /games/sessions/{id}/skip | – | Either partner, any round, no penalty. |
| POST | /games/sessions/{id}/next | `kind?` (`truth`/`dare` for Truth or Dare) | Next round, or finishes the session after the last one. |
| POST | /games/sessions/{id}/judge | `correct: bool` | Guess mode with free text: the person the question was about decides. |
| POST | /games/sessions/{id}/stop | – | Either partner, any time. |
| GET/POST | /games/{slug}/custom-questions | `text, options?, kind?` | The couple's own questions; used in that game's random picks. |
| DELETE | /games/custom-questions/{id} | – | |
| POST | /games/adult-consent | `opt_in: bool, confirm_adult: true` | Each partner opts in separately. Opting out stops any running 18+ game. |

Answer formats: `{"choice": "<option text>"}`, `{"user_id": <id>}` (when `question.pick_partner`), `{"text": "..."}`, `{"done": true}` (challenges and dares), Two Truths: subject sends `{"statements": [a, b, c], "lie": 0-2}`, guesser sends `{"pick": 0-2}`.

Session state:

```json
{
  "id": 12, "game": {"slug": "this-or-that", "title": "This or That", "section": "free", "mode": "match"},
  "status": "invited|active|finished|stopped|declined", "level": null,
  "round_no": 2, "total_rounds": 10, "scores": {"1": 0, "2": 1, "sync": 3},
  "round": {
    "id": 40, "round_no": 2, "status": "open|revealed|skipped",
    "question": {"id": 9, "kind": "question", "text": "Pick one", "options": ["Coffee", "Tea"], "pick_partner": false},
    "subject_id": null, "your_turn": true, "my_answer": null,
    "partner_answered": true, "partner_answer": null, "result": null
  },
  "can_skip": true, "can_stop": true,
  "summary": {"rounds_played": 9, "rounds_skipped": 1, "compatibility_percent": 67, "top_category": {"1": "time"}}
}
```

18+ games require, in this order: the section enabled by the admin, both partners 18+ by date of birth, both partners opted in, and Premium for the couple. Error codes: `adult_disabled`, `adult_age`, `adult_consent_required` (403), `premium_required` (402).

## Daily question

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /daily | – | `{day, question, my_answer, partner_answered, partner_answer, streak}`. You see your partner's answer only after answering. Day boundary: `APP_TIMEZONE`. |
| POST | /daily | `answer` | Can be edited the same day. |
| GET | /daily/history | – | Past days you both answered. |

## Store and payments

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /store/products | – | Products with `owned`, plus `entitlements`. |
| POST | /store/orders | `sku` | 201 → `{order_id, checkout: {key, order_id, amount, currency, name, description, prefill}}`. Pass `checkout` to the Razorpay SDK. Reuses an unpaid order from the last 30 minutes. 409 `already_owned`. |
| POST | /store/orders/{id}/verify | `razorpay_order_id, razorpay_payment_id, razorpay_signature` | From the Razorpay success callback. Unlocks only if the HMAC signature is valid. Safe to call twice. |
| GET | /store/purchases | – | Paid/refunded orders. |
| POST | /store/restore | – | Re-reads lifetime entitlements for this account (new phone, reinstall). |
| POST | /webhooks/razorpay | raw Razorpay JSON | No auth; verified with `X-Razorpay-Signature`. Handles `payment.captured`, `order.paid`, `refund.processed`. Replays are ignored by event id. |

Entitlements: `{"premium": {"owned": true, "via_partner": false}, "themes": {...}}`.

## Themes and preferences

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /themes | – | `{themes: [{slug, name, premium, locked, tokens, wallpaper_url, active}], preferences}` |
| GET / PATCH | /me/preferences | `reset_colors? (true clears custom colours), theme_slug?, color_mode? (system/light/dark), accent?, bubble_mine?, bubble_theirs? (#RRGGBB, premium), font_scale? (0.8–1.6), wallpaper?, notify_messages?, notify_games?, notify_reminders?, show_preview?, quiet_start?, quiet_end? (HH:MM)` | Premium theme or custom colours without the entitlement → 402. |

## Long-distance hub and shared space

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| GET | /home | – | Dashboard: partner (with today's mood), days together, next milestone, next meeting, next countdown, anniversary and partner birthday countdowns, upcoming events (30 days), memory of the day, daily-question status, streak, game invites, last tap, unread count, bucket progress. |
| POST | /taps | `kind` (hug, kiss, heartbeat, miss_you, thinking_of_you, good_morning, good_night) | 30 per minute. Emits `tap.sent`. |
| GET | /taps | – | Recent taps and 24-hour counts. |
| GET / POST | /moods | `mood` (happy, loved, excited, calm, tired, stressed, sad, missing_you, angry), `note?` | One per person per day. |
| GET | /notes | `?kind=journal|letter|note|wish` | Scheduled letters from your partner are hidden until delivery; `sealed_letters_waiting` counts them. |
| POST | /notes | `kind, title?, body, deliver_at?` (letters only) | |
| GET | /notes/{id} | – | Opening marks the note as read for the author. |
| PATCH / DELETE | /notes/{id} | `title?, body?` | Author only. |
| GET / POST | /hub/countdowns | `title, target_at (ISO), emoji?` | |
| GET / POST | /hub/bucket | `title, notes?, category?` | PATCH with `done: true/false` to tick. |
| GET / POST | /hub/calendar | `title, kind, starts_at, ends_at?, all_day?, repeat_yearly?, remind_minutes?, notes?` | `?from=` filters. |
| GET / POST | /hub/memories | `title, happened_on (YYYY-MM-DD), body?, media_id?, is_milestone?, is_favorite?` | Upload photos with `POST /media` first. |
| PATCH / DELETE | /hub/{list}/{id} | same fields | Either partner can edit shared lists. |

New sync event types: `game.invited`, `game.updated`, `daily.answered`, `premium.unlocked`, `tap.sent`, `mood.updated`, `note.created`, `note.opened`, `hub.changed`.

---

# Phase 4 endpoints

## Real-time relay and push

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| POST | /realtime/ticket | – | `{url, ticket, expires_at}`: connect to `url?ticket=...` within 5 minutes. `url` is null when no relay is configured (keep polling). |
| POST | /me/push-token | `token, platform?` | FCM device token for this signed-in session. Signing out of the session stops its pushes. |
| DELETE | /me/push-token | – | Stop pushes to this device. |

Relay messages to the app: `{"t":"hello","partner_online":true}`, `{"t":"presence","online":false}`, `{"t":"nudge","type":"message.created","event_id":120}` (call `GET /sync`), `{"t":"nudge","type":"call.signal","call_id":5}` (fetch call signals), `{"t":"peer","d":{...}}` (live data from the partner). The app may send `{"t":"peer","d":{...}}` (8 KB max, 50 per second) and `{"t":"ping"}`.

Push rules: the partner gets a push for new messages, love taps, game invites, delivered letters, incoming and missed calls. Message text is included only if they enabled `show_preview`. 18+ games are never named. Category switches and quiet hours apply (calls ignore quiet hours). No push while the partner has the app open (seen in the last 20 seconds), except calls.

## Calls

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| POST | /calls | `kind` (audio/video) | 201 → call + `ice_servers`. Emits `call.ringing`. 409 `call_in_progress` (with `fields.call_id`) if one is ringing or active. Rings for 45 s, then `missed`. |
| GET | /calls | – | Last 50 calls. |
| GET | /calls/{id} | – | `{id, kind, status (ringing/active/ended/declined/missed/cancelled), outgoing, caller_id, callee_id, end_reason, created_at, answered_at, ended_at, duration_seconds}` |
| POST | /calls/{id}/accept | – | Callee only → call + `ice_servers`. Emits `call.accepted`. |
| POST | /calls/{id}/decline | – | Callee only. |
| POST | /calls/{id}/end | `reason?` (hangup/failed/network) | Either side. Caller hanging up while ringing = `cancelled` (partner gets a missed-call notice). Deletes the call's signalling data. |
| POST | /calls/{id}/signal | `kind` (offer/answer/ice/renegotiate), `payload` | ICE may be an array of up to 50 candidates. Nudges the partner through the relay. |
| GET | /calls/{id}/signals | `?after=<id>` | Partner's signals after an id, plus the call `status`. Poll every second during setup if the relay is not connected. |
| GET | /calls/turn | – | `{ice_servers, ttl}`: STUN plus TURN credentials valid for `TURN_TTL_SECONDS` (coturn REST API format). |

New sync event types: `call.ringing`, `call.accepted`, `call.ended` (`reason`: declined, cancelled, missed, hangup, failed, network).
