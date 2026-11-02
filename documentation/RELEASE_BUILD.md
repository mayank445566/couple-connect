# Signed release build and Play Store upload

The app is built on Expo's servers (EAS). They also create and keep your signing key, so you don't need Android Studio.

## 1. Before the first build

1. In `mobile-app/eas.json`, replace `https://api.yourdomain.com` with your real API address in the `production` and `preview` profiles.
2. Replace the placeholder icons in `mobile-app/assets/`:
   - `icon.png` (1024×1024);
   - `android-icon-foreground.png`, `-background.png` and `-monochrome.png` (432×432, artwork inside the centre 66%);
   - `splash-icon.png`.
3. Upload Firebase's `google-services.json` for push as an EAS file environment variable named `GOOGLE_SERVICES_JSON` (expo.dev → project → **Environment variables**). See `MOBILE_APP.md`.
4. On expo.dev → project → **GitHub**, set the base directory to `mobile-app`. The project is already linked through `extra.eas.projectId`.

## 2. Build the signed bundle (AAB)

From expo.dev (**Build from GitHub**, profile `production`, platform Android), or on a computer:

```
cd mobile-app
npx eas-cli build --profile production --platform android
```

What the `production` profile does:

- builds an **app bundle (.aab)**, which Play requires;
- turns on **Google Play Billing** (`EXPO_PUBLIC_BILLING=play`);
- raises the version code by 1 on every build (`autoIncrement`);
- signs the bundle with an **upload key that EAS creates on the first build and stores** (`credentialsSource: remote`).

Download a backup of the keystore once: expo.dev → project → **Credentials → Android → Download**. Keep it with your `.env` backup. Do not commit it to git.

Use **Play App Signing**, which is the default for new apps. Google holds the final app-signing key, and the EAS key is only the upload key. If the upload key is ever lost, Google can reset it.

## 3. First upload to Play Console

1. Create the app in Play Console:
   - default language English (India);
   - type App;
   - Free;
   - accept the declarations.
2. **Testing → Internal testing** → create a release → upload the `.aab` → add your Gmail as a tester → roll out.
   - Or run `npx eas-cli submit --profile production --platform android`. This needs a Play service-account key with release permissions, uploaded in expo.dev **Credentials**. You can use the same Google Cloud project as Play Billing.
3. Create the `premium_lifetime` in-app product at ₹199 (see `PAYMENTS.md`). Play only allows this after a build with the billing permission has been uploaded.
4. Fill in **App content**:

   | Item | What to enter |
   | --- | --- |
   | Privacy policy | Its URL. It must match `ADMIN_PANEL.md` (case access) and the in-app text under Settings → Who can see your chats |
   | Data safety | Collected: email, phone, name, date of birth, messages, photos and videos, audio, app interactions, purchase history. Encrypted in transit: yes. Users can request deletion: yes (in the app and by email). Shared with third parties: no, apart from processors (Razorpay or Google Play for payments, Firebase for push) |
   | Content rating | Answer honestly. The 18+ section is suggestive, not explicit; this usually gives Mature 17+ / 18+. Mark the app as having user-to-user communication |
   | Target audience | 18 and over only |
   | Ads | None |
   | Account deletion URL | A page that explains Settings → Delete account and gives a support email |

5. Promote to **Closed testing**. New personal developer accounts need 12 testers for 14 days before production. Then promote to **Production**.

## 4. Direct APK (outside Play)

To share an APK directly, for example for testing or for users who pay with Razorpay:

```
npx eas-cli build --profile preview --platform android
```

The `preview` profile uses Razorpay and has no Play Billing. Do not upload this APK to Play.

## 5. Updates

- **Code or UI changes:** run a new `production` build and upload it to the same track. The version code goes up automatically. Change `version` in `app.json` for user-visible version names (1.0.1, 1.1.0 and so on).
- **Backend:** upload the changed files, then run `php bin/migrate.php`. The admin Settings page shows the cron heartbeat and configuration health.
