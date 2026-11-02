<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Redirect;
use App\Admin\View;
use App\Core\Config;
use App\Core\Database;
use App\Services\Settings;

/** Business settings, pricing and a read-only server health check (never shows secret values). */
final class SettingsPage
{
    private const TOGGLES = [
        'adult_section_enabled' => ['18+ section', 'When off, the 18+ games disappear for everyone. Purchases are kept.'],
        'premium_shared_with_partner' => ['Share Premium with partner', 'One purchase unlocks Premium for both partners while they are paired.'],
        'themes_in_premium' => ['Themes included in Premium', 'Shown to the app so it can explain what Premium includes.'],
    ];

    public function show(Ctx $ctx): string
    {
        $values = [];
        foreach (array_keys(self::TOGGLES) as $k) {
            $values[$k] = Settings::bool($k);
        }
        $packs = Database::all('SELECT p.slug, p.title, g.title AS game FROM question_packs p JOIN games g ON g.id = p.game_id WHERE p.couple_id IS NULL AND p.slug IS NOT NULL AND g.section = \'free\' ORDER BY g.sort_order, p.sort_order');
        return View::render($ctx, 'Settings', 'settings', [
            'toggles' => self::TOGGLES, 'values' => $values, 'packs' => $packs,
            'dailyPack' => Settings::get('daily_question_pack'), 'health' => $this->health(),
        ]);
    }

    public function save(Ctx $ctx): string
    {
        $changes = [];
        foreach (array_keys(self::TOGGLES) as $k) {
            $new = $ctx->checked($k) ? '1' : '0';
            if (Settings::get($k) !== $new) {
                Settings::set($k, $new);
                $changes[$k] = $new;
            }
        }
        $daily = $ctx->post('daily_question_pack');
        if ($daily !== Settings::get('daily_question_pack')) {
            if (!Database::value('SELECT 1 FROM question_packs WHERE slug = ? AND couple_id IS NULL', [$daily])) {
                throw new Redirect('/admin/settings', 'Pick an existing pack for the daily question.', 'error');
            }
            Settings::set('daily_question_pack', $daily);
            $changes['daily_question_pack'] = $daily;
        }
        if ($changes) {
            Audit::log($ctx, 'settings_updated', 'settings', null, $changes);
        }
        throw new Redirect('/admin/settings', $changes ? 'Settings saved.' : 'Nothing changed.');
    }

    public function products(Ctx $ctx): string
    {
        $products = Database::all('SELECT p.*, (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = \'paid\') AS sold FROM products p ORDER BY p.id');
        return View::render($ctx, 'Pricing', 'products', compact('products'));
    }

    public function saveProduct(Ctx $ctx): string
    {
        $p = Database::one('SELECT * FROM products WHERE id = ?', [$ctx->id()]);
        if (!$p) {
            throw new HttpError(404, 'Product not found.');
        }
        $title = mb_substr($ctx->post('title'), 0, 100);
        $desc = mb_substr($ctx->post('description'), 0, 400);
        $rupees = $ctx->post('price');
        if ($title === '' || !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $rupees) || (float)$rupees < 1) {
            throw new Redirect('/admin/products', 'Enter a title and a price of at least ₹1.', 'error');
        }
        $paise = (int)round((float)$rupees * 100);
        $active = $ctx->checked('active') ? 1 : 0;
        Database::run('UPDATE products SET title = ?, description = ?, price_paise = ?, active = ? WHERE id = ?', [$title, $desc, $paise, $active, $p['id']]);
        Audit::log($ctx, 'product_updated', 'product', (int)$p['id'], [
            'price_paise' => ['from' => (int)$p['price_paise'], 'to' => $paise], 'active' => ['from' => (int)$p['active'], 'to' => $active], 'title' => $title,
        ]);
        throw new Redirect('/admin/products', 'Saved. New orders use the new price; people who already paid keep Premium.');
    }

    private function health(): array
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $cron = @file_get_contents(Config::storage('cache') . '/cron_last_run');
        $cronAge = $cron ? time() - (int)$cron : null;
        $set = fn(string $k) => Config::get($k) !== null;
        return [
            ['Environment', Config::get('APP_ENV') === 'production', 'APP_ENV=' . Config::get('APP_ENV')],
            ['HTTPS', $https, $https ? 'On' : 'This request was not HTTPS'],
            ['Cron job', $cronAge !== null && $cronAge < 300, $cronAge === null ? 'Never ran. Add the cron line from the setup guide.' : 'Last ran ' . intdiv($cronAge, 60) . ' min ago'],
            ['Razorpay keys', $set('RAZORPAY_KEY_ID') && $set('RAZORPAY_KEY_SECRET') && $set('RAZORPAY_WEBHOOK_SECRET'), 'Key ID, secret and webhook secret ' . ($set('RAZORPAY_WEBHOOK_SECRET') ? 'set' : 'missing')],
            ['Live mode', str_starts_with((string)Config::get('RAZORPAY_KEY_ID'), 'rzp_live_'), str_starts_with((string)Config::get('RAZORPAY_KEY_ID'), 'rzp_live_') ? 'Live keys' : 'Test keys (no real money)'],
            ['Email', Config::get('MAIL_DRIVER') === 'smtp', 'MAIL_DRIVER=' . Config::get('MAIL_DRIVER', 'log')],
            ['Push notifications', Config::get('PUSH_DRIVER') === 'fcm' && is_readable((string)Config::get('FCM_SERVICE_ACCOUNT')), 'PUSH_DRIVER=' . Config::get('PUSH_DRIVER', 'off')],
            ['Instant relay', $set('RELAY_URL') && $set('RELAY_SECRET'), $set('RELAY_URL') ? 'Configured' : 'Not set (app falls back to polling)'],
            ['TURN for calls', $set('TURN_URLS') && $set('TURN_SECRET'), $set('TURN_URLS') ? 'Configured' : 'Not set (some calls on mobile data will fail)'],
            ['Admin two-step sign-in required', Config::bool('ADMIN_REQUIRE_2FA', true), Config::bool('ADMIN_REQUIRE_2FA', true) ? 'Yes' : 'No'],
        ];
    }
}
