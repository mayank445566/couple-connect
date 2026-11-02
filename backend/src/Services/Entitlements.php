<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Validator;

/**
 * Lifetime entitlements (premium, themes). Granted only by server-verified payments
 * or an admin. Shared with the current partner when premium_shared_with_partner = 1.
 */
final class Entitlements
{
    /** @return array<string, array{owned: bool, via_partner: bool}> */
    public static function forUser(array $user): array
    {
        $own = Database::run('SELECT entitlement FROM entitlements WHERE user_id = ? AND revoked_at IS NULL', [$user['id']])->fetchAll(\PDO::FETCH_COLUMN);
        $partner = [];
        if ($user['active_couple_id'] !== null && Settings::bool('premium_shared_with_partner')) {
            $partner = Database::run(
                "SELECT e.entitlement FROM entitlements e JOIN couples c ON c.id = ? AND c.status = 'active'
                  WHERE e.user_id = IF(c.user_a_id = ?, c.user_b_id, c.user_a_id) AND e.revoked_at IS NULL",
                [$user['active_couple_id'], $user['id']]
            )->fetchAll(\PDO::FETCH_COLUMN);
        }
        // Premium always includes premium themes (an admin grant of just "premium" used to leave themes locked)
        foreach (['own', 'partner'] as $list) {
            if (in_array('premium', $$list, true)) {
                $$list[] = 'themes';
            }
        }
        $out = [];
        foreach (['premium', 'themes'] as $e) {
            $out[$e] = ['owned' => in_array($e, $own, true) || in_array($e, $partner, true), 'via_partner' => !in_array($e, $own, true) && in_array($e, $partner, true)];
        }
        return $out;
    }

    public static function has(array $user, string $entitlement): bool
    {
        return self::forUser($user)[$entitlement]['owned'] ?? false;
    }

    /** Idempotent: granting twice keeps the first grant. Re-activates a revoked grant. */
    public static function grant(int $userId, array $grants, ?int $orderId): void
    {
        foreach ($grants as $g) {
            Database::run(
                'INSERT INTO entitlements (user_id, entitlement, order_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE revoked_at = NULL, order_id = COALESCE(order_id, VALUES(order_id))',
                [$userId, $g, $orderId]
            );
        }
    }

    /**
     * Gate for every adult feature: section enabled, both partners 18+, both opted in
     * (each separately), and the couple has premium.
     */
    public static function assertAdultAllowed(array $user, CoupleContext $ctx): void
    {
        if (!Settings::bool('adult_section_enabled')) {
            throw new ApiException(403, 'adult_disabled', 'This section is not available.');
        }
        $both = Database::all('SELECT id, dob, adult_opt_in_at FROM users WHERE id IN (?, ?)', [$ctx->userId, $ctx->partnerId]);
        foreach ($both as $u) {
            $who = (int)$u['id'] === $ctx->userId ? 'You' : 'Your partner';
            if (Validator::age($u['dob']) < 18) {
                throw new ApiException(403, 'adult_age', 'Both partners must be 18 or older.');
            }
            if ($u['adult_opt_in_at'] === null) {
                throw new ApiException(403, 'adult_consent_required', "$who must turn on 18+ games first. Each partner decides separately.");
            }
        }
        if (!self::has($user, 'premium')) {
            throw new ApiException(402, 'premium_required', 'Unlock Premium to play 18+ games.');
        }
    }
}
