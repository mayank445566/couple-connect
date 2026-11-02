# Couple Connect Android app

React Native with Expo SDK 58 and expo-router (TypeScript). The same code also runs in a browser, which is handy for testing.

## What's in it

| Area | Screens |
| --- | --- |
| Account | Welcome, sign up (email), sign in, phone number sign-in/sign-up with SMS code, forgot password, email verification |
| Pairing | Show a 6-digit code and QR, enter a code, scan a QR with the camera, approve or reject who joined |
| Home | Partner card with online/typing/last seen and today's mood, days together and next milestone, love taps, daily question, mood, game invites, next meeting, countdowns, anniversary and birthday, memory of the day, upcoming events |
| Chat | Text, photos, videos, voice notes, files, replies, reactions, edit, delete for me or everyone, pin, star, search, starred and pinned lists, receipts, typing, disappearing-message indicator, report a message. Messages typed offline are queued and sent later without duplicates |
| Games | Free and 18+ sections, game detail with level, pack and rounds, invite partner, play every mode (pick, match, guess, quiz, truth or dare, two truths and a lie, free text with judge), skip and stop, results, your own custom questions |
| 18+ | Separate consent screen. Each partner opts in on their own phone, can opt out any time, and opting out stops any running 18+ game |
| Together | Mood, daily question with history, letters (scheduled "open when" letters stay sealed until their date), shared notes, journal, wishes, countdowns, bucket list, shared calendar, memories with photos, relationship dates, disappearing messages |
| Us | Profile and photo, partner, links to themes, Premium, 18+ and settings |
| Premium | ₹199 one-time unlock through Razorpay (native SDK on Android, Razorpay Checkout on web). The server creates the order and verifies the signature; the app never holds a secret and never unlocks on its own. Restore purchases |
| Themes | Light, dark or system, 14 themes (premium ones locked), text size, custom accent and bubble colours, reset |
| Settings | Last seen and read receipts, notification choices (saved now, used when push arrives in Phase 4; previews off by default), quiet hours, change password, add phone, download my data, signed-in devices, sign out everywhere, report, unpair (optionally block), delete account |

Calls and push notifications come in Phase 4, because they need the small relay server. The app says so instead of showing buttons that do nothing.

## Security notes

- The session token is stored in the Android Keystore through `expo-secure-store` (browser: localStorage, for testing only).
- Private photos and voice notes are fetched with the token in a request header. Videos and files use a 15-minute signed link from `GET /media/{id}/link`. No permanent media URLs.
- The Razorpay key in the checkout is the public key ID; the secret stays in the server's `.env`.
- Chats are encrypted in transit (HTTPS) and media is encrypted at rest on the server. The app does not claim end-to-end encryption.

## Run it

```
cd mobile-app
npm install
EXPO_PUBLIC_API_URL=http://<your-computer-ip>:8088 npx expo start
```

- Browser: press `w`. Add the browser origin (for example `http://localhost:8081`) to `CORS_ORIGINS` in `backend/.env`.
- Android phone: the app uses native modules (camera, Razorpay) that Expo Go does not include, so install a development build once: `npx eas build --profile development --platform android`, then `npx expo start --dev-client`.

## Build an APK or Play Store bundle

1. Put your API address in `eas.json` (`EXPO_PUBLIC_API_URL` under `preview` and `production`), for example `https://api.yourdomain.com`.
2. Replace the placeholder icons in `assets/` (`icon.png` 1024×1024, `android-icon-foreground.png`, `android-icon-background.png`, `android-icon-monochrome.png`) with your own.
3. `npm install -g eas-cli && eas login` (free Expo account).
4. APK to install directly or share: `eas build --profile preview --platform android`.
5. Play Store bundle (AAB): `eas build --profile production --platform android`. EAS creates and keeps the upload key; download a backup from expo.dev.
6. Upload with `eas submit --platform android` (internal testing track first), or upload the `.aab` in Play Console.

Building locally without EAS also works: `npx expo prebuild --platform android` then `cd android && ./gradlew bundleRelease` (needs Android Studio and your own signing key).

Play Store notes: declare the app as 18+ in the content rating, describe the optional 18+ section honestly, and link your privacy policy. Google Play requires Play Billing for digital goods sold inside apps distributed on Play; Razorpay is fine for the web and direct APK. Phase 6 adds a Play Billing path for the Play Store build.

## Testing

`npx tsc --noEmit` type-checks the app. The browser test drives the exported web build against the local API: it registers, verifies, pairs, sends a love tap, chats both ways, reacts, plays a game round, answers the daily question, adds a bucket item and a letter, switches to dark mode, and opens Premium, Settings and Us, checking each result on the server.

```
cd mobile-app && EXPO_PUBLIC_API_URL=http://127.0.0.1:8088 npx expo export -p web --output-dir /tmp/ccweb
node ../tests/e2e/serve_static.js /tmp/ccweb &          # serves on 127.0.0.1:8081
node ../tests/e2e/mobile_web_smoke.js /tmp/shots        # needs Playwright, MAIL_DRIVER=log and CORS_ORIGINS=http://127.0.0.1:8081
```
