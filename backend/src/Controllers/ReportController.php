<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;

/**
 * Users can report their partner (or a pairing request). Moderators never get
 * general access to chats; they see only the messages the reporter chooses to attach.
 */
final class ReportController
{
    public function store(Request $req, array $user): array
    {
        RateLimiter::hit('report:user:' . $user['id'], 10, 86400);
        $d = Validator::check($req->json(), [
            'reason' => 'required,in:harassment|abuse|underage|impersonation|spam|other',
            'details' => 'string,max:2000',
            'reported_user_id' => 'int',
        ]);
        $coupleId = $user['active_couple_id'] !== null ? (int)$user['active_couple_id'] : null;
        $reported = $d['reported_user_id'] ?? null;
        if ($reported !== null) {
            $related = Database::value(
                "SELECT 1 FROM couples WHERE (user_a_id = ? AND user_b_id = ?) OR (user_a_id = ? AND user_b_id = ?)
                 UNION SELECT 1 FROM pair_requests WHERE (inviter_id = ? AND invitee_id = ?) OR (inviter_id = ? AND invitee_id = ?) LIMIT 1",
                [$user['id'], $reported, $reported, $user['id'], $user['id'], $reported, $reported, $user['id']]
            );
            if (!$related) {
                throw ApiException::validation(['reported_user_id' => 'You can only report someone you are or were connected with.']);
            }
        }
        $attached = [];
        $ids = $req->input('message_ids', []);
        if (is_array($ids) && $ids && $coupleId) {
            $ids = array_slice(array_map('intval', $ids), 0, 50);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $attached = Database::all(
                "SELECT id, sender_id, type, body, media_id, created_at FROM messages WHERE couple_id = ? AND deleted_at IS NULL AND id IN ($in)",
                array_merge([$coupleId], $ids)
            );
        }
        $id = Database::insert(
            'INSERT INTO reports (reporter_id, reported_id, couple_id, reason, details, attached) VALUES (?, ?, ?, ?, ?, ?)',
            [$user['id'], $reported, $coupleId, $d['reason'], $d['details'] ?? null, $attached ? json_encode($attached, JSON_UNESCAPED_UNICODE) : null]
        );
        return Router::status(201, ['report_id' => $id, 'attached_messages' => count($attached)]);
    }
}
