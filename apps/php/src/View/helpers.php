<?php

declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_json(mixed $value): string
{
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $encoded === false ? '{}' : $encoded;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function ui_change_status_label(string $status): string
{
    return match ($status) {
        'saved' => '保存済み',
        'applied' => '保存済み',
        'committed' => '保存済み',
        'discarded' => '破棄済み',
        'aborted' => '破棄済み',
        'failed' => '失敗',
        'validated' => '検証済み',
        default => 'ドラフト',
    };
}

function ui_change_status_class(string $status): string
{
    return match ($status) {
        'saved' => 'ok',
        'applied' => 'ok',
        'committed' => 'ok',
        'discarded' => 'error',
        'aborted' => 'error',
        'failed' => 'error',
        'validated' => 'info',
        default => 'warn',
    };
}

function ui_record_status_label(string $status): string
{
    return match ($status) {
        'Error' => 'エラー',
        'Warning' => '警告',
        default => '正常',
    };
}

function ui_record_status_class(string $status): string
{
    return match ($status) {
        'Error' => 'error',
        'Warning' => 'warn',
        default => 'ok',
    };
}
