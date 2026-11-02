# Launch testing checklist

Do these on **two real Android phones** (one per partner), signed in to two different accounts, using a build from the `production` profile installed through Play internal testing.

The automated suites already cover the server logic: 365 API checks, plus browser and relay tests. This list covers what only real phones, real networks and real stores can prove.

## Server

- [ ] `https://api.yourdomain.com/health` shows `"status":"ok"`.
- [ ] Admin → **Settings → Server health** is all **OK**: production, HTTPS, cron, Razorpay live, email, push, relay, TURN, Play Billing and 2FA.
- [ ] `http://` redirects to `https://`.

## Accounts

- [ ] Register with email. The verification email arrives, not in spam.
- [ ] Register with phone. The OTP SMS arrives (DLT template approved).
- [ ] A date of birth under 18 is refused.
- [ ] Forgot password works, and other devices are signed out afterwards.
- [ ] Settings → Devices lists both sessions, and "sign out" on one works.

## Pairing

- [ ] Partner A shares the code, B joins, and A accepts.
- [ ] The QR scan pairs too.
- [ ] Unpair, then pair again.

## Chat (on mobile data and on Wi-Fi)

- [ ] Text arrives in about 1 second with the relay. Turn off the relay to check that polling still delivers within about 15 seconds.
- [ ] Photo, video, voice note and file all send. Each opens for the partner, and fails to open when the link is pasted into a browser.
- [ ] Replies, reactions, editing, delete for everyone, pin, star and search all work.
- [ ] Airplane mode: a message queues, then sends by itself when you go back online.
- [ ] Disappearing messages are gone for both after the timer.

## Notifications (app closed, phone locked)

- [ ] A new message shows a notification **without the message text** (unless previews are turned on).
- [ ] Quiet hours hold messages back, but calls still ring.
- [ ] Tapping a notification opens the right screen.

## Calls (one phone on mobile data, the other on Wi-Fi)

- [ ] Voice call: ring, accept, two-way audio, mute, speaker, hang up.
- [ ] Video call: both videos show, switch camera, turn video off and on.
- [ ] Decline, missed call (no answer for 45 s) and call history all work.
- [ ] Locked phone: the call notification rings. A full lock-screen call UI (CallKeep) is not included yet.

## Games and hub

- [ ] Play 3 free games to the end, and skip a card.
- [ ] Daily question: both answer, then both see each other's answer.
- [ ] Countdown, bucket list, calendar, notes, memories, mood and love tap all sync to the partner.
- [ ] Draw together works live.

## Premium and 18+

- [ ] Play build: buy ₹199 as a **license tester**. Premium unlocks for both partners.
- [ ] Close the app during payment, reopen it, and Restore unlocks.
- [ ] Refund the order in Play Console. Premium is removed within a minute (RTDN).
- [ ] Direct APK: buy with Razorpay test mode, then with one real ₹199 live payment, and refund it from the admin panel.
- [ ] 18+ games stay locked until both partners are 18+, both turn on 18+ separately, and Premium is owned.
- [ ] Any 18+ card can be skipped, and either partner can stop the game.

## Admin

- [ ] Sign in with 2FA. A moderator account can't open Orders.
- [ ] Report the partner with two messages attached. The moderator sees only those two.
- [ ] Case access: open, view, close, then check the audit log.
- [ ] Turn off the 18+ section and confirm it disappears in the app. Turn it back on.

## Privacy

- [ ] Settings → Export data downloads a file.
- [ ] Delete account: after 7 days, the cron job removes the content.
- [ ] The privacy policy URL works and matches **Who can see your chats** in Settings.
