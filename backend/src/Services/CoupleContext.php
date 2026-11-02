<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;

/** Resolves the caller's active couple. Every private endpoint goes through here. */
final class CoupleContext
{
    public function __construct(
        public readonly array $couple,
        public readonly int $userId,
        public readonly int $partnerId,
    ) {}

    public static function require(array $user): self
    {
        if ($user['active_couple_id'] === null) {
            throw new ApiException(409, 'not_paired', 'Connect with your partner first.');
        }
        $couple = Database::one(
            "SELECT * FROM couples WHERE id = ? AND status = 'active' AND (user_a_id = ? OR user_b_id = ?)",
            [$user['active_couple_id'], $user['id'], $user['id']]
        );
        if ($couple === null) {
            throw new ApiException(409, 'not_paired', 'Connect with your partner first.');
        }
        $uid = (int)$user['id'];
        $partner = (int)$couple['user_a_id'] === $uid ? (int)$couple['user_b_id'] : (int)$couple['user_a_id'];
        return new self($couple, $uid, $partner);
    }

    public function id(): int
    {
        return (int)$this->couple['id'];
    }
}
