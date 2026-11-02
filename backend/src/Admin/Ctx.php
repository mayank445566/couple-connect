<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;

/** The current admin request: signed-in admin, session row and form helpers. */
final class Ctx
{
    public const ROLES = [
        'owner' => ['*'],
        'moderator' => ['users.view', 'users.moderate', 'reports'],
        'finance' => ['users.view', 'payments', 'entitlements'],
        'content' => ['content'],
    ];
    public const ROLE_LABELS = [
        'owner' => 'Owner (everything)',
        'moderator' => 'Moderator (users and reports)',
        'finance' => 'Finance (orders, refunds, grants)',
        'content' => 'Content (games, questions, themes)',
    ];

    public function __construct(public Request $req, public ?array $admin, public ?array $session) {}

    public function can(string $perm): bool
    {
        if ($this->admin === null) {
            return false;
        }
        $perms = self::ROLES[$this->admin['role']] ?? [];
        return in_array('*', $perms, true) || in_array($perm, $perms, true);
    }

    public function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function checked(string $key): bool
    {
        return isset($_POST[$key]) && $_POST[$key] !== '0';
    }

    public function q(string $key, string $default = ''): string
    {
        $v = $this->req->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function page(): int
    {
        return max(1, (int)$this->q('page', '1'));
    }

    public function id(string $name = 'id'): int
    {
        return (int)($this->req->params[$name] ?? 0);
    }
}
