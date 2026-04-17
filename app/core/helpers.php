<?php
// Global helper functions

function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function fieldError(string $field): string
{
    $errors = $_SESSION['errors'] ?? [];
    if (!isset($errors[$field])) return '';
    $msg = implode(' ', (array)$errors[$field]);
    return '<div class="invalid-feedback d-block">' . e($msg) . '</div>';
}

function hasError(string $field): string
{
    return isset($_SESSION['errors'][$field]) ? 'is-invalid' : '';
}

function csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    $cfg = require CONFIG_ROOT . '/app.php';
    return rtrim($cfg['url'], '/') . '/' . ltrim($path, '/');
}

function activeNav(string $prefix): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return str_starts_with($uri, $prefix) ? 'active' : '';
}

function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function ageFromDob(?string $dob): ?int
{
    if (!$dob) return null;
    return (int) (new DateTime($dob))->diff(new DateTime())->y;
}

function isMinor(?string $dob): bool
{
    if (!$dob) return false;
    return ageFromDob($dob) < 18;
}

function avatarInitials(string $name): string
{
    $words = explode(' ', trim($name));
    $initials = strtoupper($words[0][0] ?? '');
    if (count($words) > 1) $initials .= strtoupper(end($words)[0]);
    return $initials;
}

function flashBag(): string
{
    $f = flash();
    if (!$f) return '';
    $type = match ($f['type'] ?? 'info') {
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        default   => 'info',
    };
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show mb-3" role="alert">'
        . e($f['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
        . '</div>';
}

function statusBadge(string $status): string
{
    $map = [
        'active'          => 'success',
        'approved'        => 'success',
        'verified'        => 'success',
        'confirmed'       => 'success',
        'paid'            => 'success',
        'pending'         => 'warning',
        'pending_approval'=> 'warning',
        'draft'           => 'secondary',
        'inactive'        => 'secondary',
        'suspended'       => 'danger',
        'rejected'        => 'danger',
        'cancelled'       => 'danger',
        'failed'          => 'danger',
        'completed'       => 'info',
    ];
    $color = $map[$status] ?? 'secondary';
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span class='badge bg-{$color}'>{$label}</span>";
}
