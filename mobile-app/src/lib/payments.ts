import { Platform } from 'react-native';
import { api } from './api';

export type Checkout = { key: string; order_id: string; amount: number; currency: string; name: string; description: string; prefill?: Record<string, string> };
type Success = { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string };

export class PaymentCancelled extends Error {}

/**
 * Opens Razorpay with an order the server created, then has the server verify the signature.
 * The app never sees the Razorpay secret and never decides on its own that a payment succeeded.
 */
export async function buy(sku: string, color: string): Promise<void> {
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
