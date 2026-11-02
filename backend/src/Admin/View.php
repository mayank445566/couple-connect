<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Config;

/** Renders PHP templates from src/Admin/views inside the panel layout. All output is escaped with $h(). */
final class View
{
    public const PER_PAGE = 50;

    public static function render(Ctx $ctx, string $title, string $template, array $vars = []): string
    {
        $body = self::partial($ctx, $template, $vars);
        return self::layout($ctx, $title, $body);
    }

    public static function partial(?Ctx $ctx, string $template, array $vars = []): string
    {
        $file = __DIR__ . '/views/' . $template . '.php';
        $helpers = self::helpers($ctx);
        $render = static function (string $__file, array $__vars) {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require $__file;
            return (string)ob_get_clean();
        };
        return $render($file, $vars + $helpers);
    }

    public static function layout(?Ctx $ctx, string $title, string $body): string
    {
        $flash = $ctx ? Session::takeFlash($ctx) : null;
        return self::partial($ctx, 'layout', ['title' => $title, 'body' => $body, 'flash' => $flash, 'ctx' => $ctx]);
    }

    public static function error(int $status, string $message, ?Ctx $ctx): string
    {
        $ctx = ($ctx && $ctx->admin) ? $ctx : null;
        $body = '<div class="card"><h1>' . $status . '</h1><p>' . self::h($message) . '</p><p><a href="'
            . self::h(Kernel::url($ctx ? '/admin' : '/admin/login')) . '">Back</a></p></div>';
        return self::layout($ctx, 'Error', $body);
    }

    public static function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function helpers(?Ctx $ctx): array
    {
        $tz = new \DateTimeZone(Config::get('APP_TIMEZONE', 'Asia/Kolkata'));
        return [
            'h' => fn($v) => self::h($v),
            'url' => fn(string $p, array $q = []) => self::h(Kernel::url($p, $q)),
            'csrf' => $ctx && $ctx->session ? '<input type="hidden" name="_csrf" value="' . self::h($ctx->session['csrf_token']) . '">' : '',
            'nonce' => Kernel::$nonce,
            'can' => fn(string $p) => $ctx !== null && $ctx->can($p),
            'date' => function (?string $dt) use ($tz) {
                if (!$dt) {
                    return '—';
                }
                return (new \DateTimeImmutable($dt, new \DateTimeZone('UTC')))->setTimezone($tz)->format('d M Y, H:i');
            },
            'money' => fn($paise, string $cur = 'INR') => ($cur === 'INR' ? '₹' : $cur . ' ') . number_format(((int)$paise) / 100, 2),
            'pager' => function (int $total, array $query, string $path) use ($ctx) {
                $page = $ctx ? $ctx->page() : 1;
                $pages = max(1, (int)ceil($total / self::PER_PAGE));
                if ($pages <= 1) {
                    return '';
                }
                $out = '<nav class="pager">';
                if ($page > 1) {
                    $out .= '<a href="' . self::h(Kernel::url($path, ['page' => $page - 1] + $query)) . '">← Newer</a>';
                }
                $out .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
                if ($page < $pages) {
                    $out .= '<a href="' . self::h(Kernel::url($path, ['page' => $page + 1] + $query)) . '">Older →</a>';
                }
                return $out . '</nav>';
            },
            'ctx' => $ctx,
        ];
    }
}
