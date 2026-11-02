import { Platform } from 'react-native';
import { api } from './api';

export type Checkout = { key: string; order_id: string; amount: number; currency: string; name: string; description: string; prefill?: Record<string, string> };
type Success = { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string };

export class PaymentCancelled extends Error {}
export class PaymentPending extends Error {}

/**
 * Which payment system this build uses. The Play Store build sets EXPO_PUBLIC_BILLING=play (Google requires
 * Play Billing for digital goods in apps from Play); the direct APK and the web app use Razorpay.
 */
export const billing: 'play' | 'razorpay' = process.env.EXPO_PUBLIC_BILLING === 'play' && Platform.OS === 'android' ? 'play' : 'razorpay';

/**
 * Starts a purchase and has the server verify it (Razorpay signature, or Google Play purchase token).
 * The app never sees a payment secret and never decides on its own that a payment succeeded.
 */
export async function buy(sku: string, color: string): Promise<void> {
  if (billing === 'play') return buyPlay(sku);
  const order = await api<{ order_id: number; checkout: Checkout }>('POST', '/store/orders', { sku });
  const options = { ...order.checkout, theme: { color } };
  const result = Platform.OS === 'web' ? await openWeb(options) : await openNative(options);
  await api('POST', `/store/orders/${order.order_id}/verify`, result);
}

async function openNative(options: Record<string, unknown>): Promise<Success> {
  let mod: typeof import('react-native-razorpay').default;
  try {
    // Native module exists only in a development/production build, not in Expo Go
    mod = require('react-native-razorpay').default;
  } catch {
    throw new Error('Payments need the installed app build. They are not available in Expo Go.');
  }
  try {
    return await mod.open(options);
  } catch (e: any) {
    // Razorpay rejects with code 0 / 2 when the user closes the sheet
    if (e && (e.code === 0 || e.code === 2 || /cancel/i.test(String(e.description ?? e.message ?? '')))) throw new PaymentCancelled('Payment cancelled.');
    throw new Error(typeof e?.description === 'string' ? e.description : 'Payment failed. You were not charged.');
  }
}

function openWeb(options: Record<string, unknown>): Promise<Success> {
  return new Promise((resolve, reject) => {
    const start = () => {
      const Rzp = (globalThis as any).Razorpay;
      if (!Rzp) return reject(new Error('Could not load Razorpay.'));
      const rzp = new Rzp({ ...options, handler: (r: Success) => resolve(r), modal: { ondismiss: () => reject(new PaymentCancelled('Payment cancelled.')) } });
      rzp.on('payment.failed', (r: any) => reject(new Error(r?.error?.description ?? 'Payment failed.')));
      rzp.open();
    };
    if ((globalThis as any).Razorpay) return start();
    const s = document.createElement('script');
    s.src = 'https://checkout.razorpay.com/v1/checkout.js';
    s.onload = start;
    s.onerror = () => reject(new Error('Could not load Razorpay. Check your connection.'));
    document.body.appendChild(s);
  });
}

// ---------------------------------------------------------------- Google Play Billing

type PlayConfig = { enabled: boolean; products: { sku: string; play_product_id: string }[]; account_tag: string };
type VerifyResult = { paid: boolean; pending: boolean; message?: string };

function iap(): typeof import('expo-iap') {
  try {
    // Native module exists only in the Play build (EXPO_PUBLIC_BILLING=play), not in Expo Go
    return require('expo-iap');
  } catch {
    throw new Error('Google Play purchases need the Play Store version of the app.');
  }
}

async function buyPlay(sku: string): Promise<void> {
  const cfg = await api<PlayConfig>('GET', '/store/google/config');
  const product = cfg.products.find((p) => p.sku === sku);
  if (!cfg.enabled || !product) throw new Error('Purchases are not available right now. Please try later.');
  const m = iap();
  await m.initConnection();
  const found = await m.fetchProducts({ skus: [product.play_product_id], type: 'in-app' });
  if (!found || (Array.isArray(found) && found.length === 0)) throw new Error('This item is not available in your Play Store country yet.');
  await new Promise<void>((resolve, reject) => {
    let done = false;
    const finish = (fn: () => void) => {
      if (done) return;
      done = true;
      ok.remove();
      bad.remove();
      fn();
    };
    const ok = m.purchaseUpdatedListener(async (purchase) => {
      if (purchase.productId !== product.play_product_id || !purchase.purchaseToken) return;
      try {
        const r = await api<VerifyResult>('POST', '/store/google/verify', { sku, purchase_token: purchase.purchaseToken });
        if (r.paid) await m.finishTransaction({ purchase, isConsumable: false }).catch(() => {});
        finish(() => (r.pending ? reject(new PaymentPending(r.message ?? 'Payment is pending.')) : resolve()));
      } catch (e) {
        finish(() => reject(e));
      }
    });
    const bad = m.purchaseErrorListener((e) => {
      finish(() => reject(m.isUserCancelledError(e) ? new PaymentCancelled('Payment cancelled.') : new Error(e.message || 'Payment failed. You were not charged.')));
    });
    m.requestPurchase({ request: { google: { skus: [product.play_product_id], obfuscatedAccountId: cfg.account_tag } }, type: 'in-app' }).catch((e: unknown) => {
      finish(() => reject(m.isUserCancelledError(e) ? new PaymentCancelled('Payment cancelled.') : e instanceof Error ? e : new Error('Payment failed. You were not charged.')));
    });
  });
}

/** Play build: sends any purchases Google still holds for this device to the server (new phone, app closed mid-payment). */
export async function restorePlay(): Promise<number> {
  if (billing !== 'play') return 0;
  const cfg = await api<PlayConfig>('GET', '/store/google/config');
  if (!cfg.enabled) return 0;
  const m = iap();
  await m.initConnection();
  const purchases = (await m.getAvailablePurchases()) ?? [];
  let applied = 0;
  for (const p of purchases) {
    const product = cfg.products.find((x) => x.play_product_id === p.productId);
    if (!product || !p.purchaseToken) continue;
    try {
      const r = await api<VerifyResult>('POST', '/store/google/verify', { sku: product.sku, purchase_token: p.purchaseToken });
      if (r.paid) {
        applied++;
        await m.finishTransaction({ purchase: p, isConsumable: false }).catch(() => {});
      }
    } catch {
      // A purchase made by another account on this phone is refused by the server; skip it
    }
  }
  return applied;
}
