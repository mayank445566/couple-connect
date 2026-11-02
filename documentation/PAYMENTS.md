# Payments: ₹199 lifetime Premium

## How it works

1. The app calls `POST /store/orders {sku: "premium_lifetime"}`. The server creates a Razorpay order for 19900 paise and returns only the public key id and order id.
2. The app opens Razorpay Checkout with that data (react-native-razorpay on Android).
3. On success Razorpay gives the app `razorpay_payment_id`, `razorpay_order_id`, `razorpay_signature`. The app sends them to `POST /store/orders/{id}/verify`.
4. The server recomputes `HMAC_SHA256(order_id + "|" + payment_id, key_secret)`. Only a match unlocks Premium.
5. Independently, Razorpay calls `POST /webhooks/razorpay`. The server checks `X-Razorpay-Signature` with the webhook secret and unlocks Premium if the app never got to step 4 (closed app, lost network).

Both paths are idempotent: payment ids are unique in the `payments` table, webhook event ids are unique in `webhook_events`, and an order can only move from `created` to `paid` once. A user who already owns Premium cannot create a new order. Refunds (`refund.processed`) mark the order refunded and revoke the entitlement it granted.

A successful payment message in the app never unlocks anything by itself.

## Razorpay setup

1. Create a Razorpay account and complete KYC. Start in **Test mode**.
2. Dashboard → **Account & Settings → API Keys** → generate keys. Put them in `backend/.env`: `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`.
3. Dashboard → **Account & Settings → Payment capture** → set **automatic capture**.
4. Dashboard → **Webhooks → Add new webhook**:
   - URL: `https://api.yourdomain.com/webhooks/razorpay`
   - Secret: a long random string; put it in `.env` as `RAZORPAY_WEBHOOK_SECRET`
   - Events: `payment.captured`, `order.paid`, `refund.processed`
5. Test with Razorpay test cards/UPI, then switch to live keys.

`RAZORPAY_FAKE_ORDERS=true` exists only for local automated tests (it is ignored unless `APP_ENV=local`).

## Business rules (admin-configurable, `settings` table)

| Setting | Default | Meaning |
| --- | --- | --- |
| `premium_shared_with_partner` | 1 | While paired, the buyer's partner also has Premium. The buyer keeps it for life; the partner loses it after unpairing. |
| `themes_in_premium` | 1 | The ₹199 product grants both `premium` and `themes`. To sell themes separately, add a product granting only `themes` and remove it from `premium_lifetime`. |
| `adult_section_enabled` | 1 | Hides the whole 18+ section when 0. |

## Google Play Billing (Play Store build)

Apps distributed through Google Play must use Play Billing for digital goods. So the two builds pay differently:

| Build | How users pay |
| --- | --- |
| Play Store build (`eas build --profile production`) | Google Play Billing (`EXPO_PUBLIC_BILLING=play`) |
| Direct APK (`preview`) and the web app | Razorpay |

Both write to the same `entitlements` table. Premium bought either way is the same Premium, and it shows on every device after **Restore purchases**.

### How a Play purchase is verified

1. The app asks `GET /store/google/config` for the Play product id and an **account tag**. The tag is the user's id plus an HMAC made with `APP_KEY`.
2. The app opens the Play purchase sheet with that tag as `obfuscatedAccountId`.
3. The app sends the purchase token to `POST /store/google/verify`.
4. The server asks Google for the purchase (`purchases.products.get`) and checks three things:
   - it was really bought (not pending or cancelled);
   - the account tag belongs to the user calling;
   - the Google order (`GPA.…`) has not been used before.

   It then grants Premium and **acknowledges** the purchase. Google refunds purchases that are not acknowledged within 3 days.
5. Real-time developer notifications (RTDN) reach `POST /webhooks/google-play?token=PLAY_RTDN_TOKEN`:
   - a pending payment that completes later unlocks Premium;
   - a **voided** purchase removes Premium. Voided means refunded in the Play Console, or charged back.

   Duplicate notifications are ignored.

### Setup

1. **Create the product.** Play Console → your app → **Monetize → In-app products** → create product id `premium_lifetime` (it must match `products.play_product_id`). Set the price to ₹199 and activate it. You can only do this after uploading one build that includes Play Billing (the production profile).
2. **Create the service account.**
   - Google Cloud Console → create a service account and download its JSON key.
   - Upload the key to the server **outside public_html**, for example `/home/USER/keys/play.json`.
   - Play Console → **Users and permissions** → invite the service account's email. Give it **View financial data** and **Manage orders and subscriptions** for this app. It can take up to 24 hours to start working.
3. **Fill in `.env`:**
   ```
   PLAY_PACKAGE_NAME=com.coupleconnect.app
   PLAY_SERVICE_ACCOUNT=/home/USER/keys/play.json
   PLAY_RTDN_TOKEN=<long random string>
   ```
   You can make the random string with `php -r "echo bin2hex(random_bytes(24));"`.
4. **Turn on refund notifications.**
   - Google Cloud → **Pub/Sub** → create topic `play-rtdn`.
   - Grant `google-play-developer-notifications@system.gserviceaccount.com` the **Pub/Sub Publisher** role on that topic.
   - Create a **push** subscription to `https://api.yourdomain.com/webhooks/google-play?token=<PLAY_RTDN_TOKEN>`.
   - Play Console → **Monetize setup → Real-time developer notifications** → enter the topic name, then click **Send test notification**.
5. **Test with license testers.** Play Console → **Settings → License testing** → add your Gmail. Testers can buy without being charged.

The admin **Settings** page shows whether Play Billing is configured. Refunds for Play orders are issued in the Play Console. The voided-purchase notification then removes Premium automatically.

## Cashfree

The order/verify/webhook flow is isolated in `src/Services/Razorpay.php` and `StoreController`. A Cashfree driver can be added the same way if you prefer it; Razorpay is the only one wired today.
