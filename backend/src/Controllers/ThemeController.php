<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Services\Entitlements;
use App\Services\Present;

final class ThemeController
{
    public function index(Request $req, array $user): array
    {
        $hasThemes = Entitlements::has($user, 'themes');
        $prefs = $this->prefs((int)$user['id']);
        return [
            'themes' => array_map(fn($t) => [
                'slug' => $t['slug'],
                'name' => $t['name'],
                'premium' => (bool)$t['is_premium'],
                'locked' => (bool)$t['is_premium'] && !$hasThemes,
                'tokens' => json_decode($t['tokens'], true),
                'wallpaper_url' => Present::mediaUrl($t['wallpaper_media_id'] ? (int)$t['wallpaper_media_id'] : null),
                'active' => $t['slug'] === $prefs['theme_slug'],
            ], Database::all('SELECT * FROM themes WHERE enabled = 1 ORDER BY sort_order, id')),
            'preferences' => $prefs,
        ];
    }

    public function preferences(Request $req, array $user): array
    {
        return $this->prefs((int)$user['id']);
    }

    public function update(Request $req, array $user): array
    {
        $d = Validator::check($req->json(), [
            'theme_slug' => 'string,max:40',
            'color_mode' => 'in:system|light|dark',
            'accent' => 'string,max:7',
            'bubble_mine' => 'string,max:7',
            'bubble_theirs' => 'string,max:7',
            'wallpaper' => 'string,max:100',
            'notify_messages' => 'bool',
            'notify_games' => 'bool',
            'notify_reminders' => 'bool',
            'show_preview' => 'bool',
            'quiet_start' => 'string,max:5',
            'quiet_end' => 'string,max:5',
        ]);
        $input = $req->json();
        if (isset($input['font_scale'])) {
            $d['font_scale'] = is_numeric($input['font_scale']) ? (string)$input['font_scale'] : 'x';
        }
        $errors = [];
        foreach (['accent', 'bubble_mine', 'bubble_theirs'] as $c) {
            if (isset($d[$c]) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $d[$c])) {
                $errors[$c] = 'Use a colour like #FF4F8B.';
            }
        }
        if (isset($d['font_scale']) && (!is_numeric($d['font_scale']) || $d['font_scale'] < 0.8 || $d['font_scale'] > 1.6)) {
            $errors['font_scale'] = 'Choose between 0.8 and 1.6.';
        }
        foreach (['quiet_start', 'quiet_end'] as $t) {
            if (isset($d[$t]) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d[$t])) {
                $errors[$t] = 'Use HH:MM (24-hour).';
            }
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }
        if (isset($d['theme_slug'])) {
            $t = Database::one('SELECT * FROM themes WHERE slug = ? AND enabled = 1', [$d['theme_slug']]);
            if (!$t) {
                throw ApiException::validation(['theme_slug' => 'Theme not found.']);
            }
            if ($t['is_premium'] && !Entitlements::has($user, 'themes')) {
                throw new ApiException(402, 'premium_required', 'Unlock Premium to use this theme.');
            }
        }
        // Custom bubble/accent colours are a premium personalisation
        if (array_intersect_key($d, array_flip(['accent', 'bubble_mine', 'bubble_theirs'])) && !Entitlements::has($user, 'themes')) {
            throw new ApiException(402, 'premium_required', 'Unlock Premium to customise colours.');
        }
        $this->prefs((int)$user['id']);
        if ($d) {
            $sets = [];
            $params = [];
            foreach ($d as $k => $v) {
                $sets[] = "$k = ?";
                $params[] = is_bool($v) ? (int)$v : $v;
            }
            $params[] = $user['id'];
            Database::run('UPDATE user_preferences SET ' . implode(', ', $sets) . ' WHERE user_id = ?', $params);
        }
        return $this->prefs((int)$user['id']);
    }

    private function prefs(int $userId): array
    {
        Database::run('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)', [$userId]);
        $p = Database::one('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
        return [
            'theme_slug' => $p['theme_slug'],
            'color_mode' => $p['color_mode'],
            'accent' => $p['accent'],
            'bubble_mine' => $p['bubble_mine'],
            'bubble_theirs' => $p['bubble_theirs'],
            'font_scale' => (float)$p['font_scale'],
            'wallpaper' => $p['wallpaper'],
            'notifications' => [
                'messages' => (bool)$p['notify_messages'],
                'games' => (bool)$p['notify_games'],
                'reminders' => (bool)$p['notify_reminders'],
                'show_preview' => (bool)$p['show_preview'],
                'quiet_start' => $p['quiet_start'] ? substr($p['quiet_start'], 0, 5) : null,
                'quiet_end' => $p['quiet_end'] ? substr($p['quiet_end'], 0, 5) : null,
            ],
        ];
    }
}
