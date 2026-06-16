<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require_once __DIR__ . '/../../templates/helpers.php';
        require __DIR__ . "/../../templates/{$template}.php";
    }
}
