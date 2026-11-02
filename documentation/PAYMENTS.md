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

## Google Play

Google Play requires Play Billing for digital goods sold inside apps distributed through Play (India also allows user-choice billing alongside it). The `products.play_product_id` column is ready; the Play Billing purchase-token verification endpoint is part of Phase 6 and writes to the same `entitlements` table, so Premium bought on the web and in the Play build are the same thing.

## Cashfree

The order/verify/webhook flow is isolated in `src/Services/Razorpay.php` and `StoreController`. A Cashfree driver can be added the same way if you prefer it; Razorpay is the only one wired today.
