<?php
declare(strict_types=1);

namespace App\Admin;

final class Redirect extends \RuntimeException
{
    public function __construct(public readonly string $path, public readonly ?string $flash = null, public readonly string $kind = 'ok')
    {
        parent::__construct('redirect');
    }
}
