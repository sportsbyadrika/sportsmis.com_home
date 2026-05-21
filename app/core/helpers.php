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

function _getViewErrors(): array
{
    return $GLOBALS['_sms_errors'] ?? $_SESSION['errors'] ?? [];
}

function fieldError(string $field): string
{
    $errors = _getViewErrors();
    if (!isset($errors[$field])) return '';
    $msg = implode(' ', (array)$errors[$field]);
    return '<div class="invalid-feedback d-block">' . e($msg) . '</div>';
}

function hasError(string $field): string
{
    $errors = _getViewErrors();
    return isset($errors[$field]) ? 'is-invalid' : '';
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

/* ── URL-ID hashing (currently scoped to event IDs only) ─────────────── */

function hid_event(int $id): string
{
    return \Core\Hash::encode($id, 'event');
}

function hid_event_decode($value): int
{
    return \Core\Hash::decodeOrInt($value, 'event');
}

function hid_reg(int $id): string
{
    return \Core\Hash::encode($id, 'reg');
}

function hid_reg_decode($value): int
{
    return \Core\Hash::decodeOrInt($value, 'reg');
}

/**
 * Ensure an event has a short, unique Event Code that admins + unit users
 * share for login + identification. Generates one in the form `EVxxxxx`
 * (uppercase alnum) on first call. Idempotent.
 */
function ensureEventCode(int $eventId): string
{
    $row = \Models\Event::rowsRaw("SELECT event_code FROM events WHERE id = ?", [$eventId]);
    $current = $row[0]['event_code'] ?? null;
    if (!empty($current)) return (string)$current;

    for ($i = 0; $i < 8; $i++) {
        $code = 'EV' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            \Models\Event::updatePartial($eventId, ['event_code' => $code]);
            return $code;
        } catch (\Throwable $e) {
            // Collision on uq_event_code — try again with a new code.
            continue;
        }
    }
    return '';
}

/**
 * "Name (ABBR)" label for an event category. Falls back to just the name
 * when no abbreviation is configured.
 */
function categoryLabel(?string $name, ?string $abbr): string
{
    $name = (string)$name;
    $abbr = trim((string)$abbr);
    return $abbr !== '' ? "{$name} ({$abbr})" : $name;
}

/**
 * Resolve the team-entry submission methods enabled on an event. Returns a
 * subset of ['athlete','unit_user','event_staff']. Legacy events with no
 * configuration default to ['athlete'] so the original athlete-only flow
 * keeps working.
 */
function eventTeamEntryMethods(?array $event): array
{
    if (!$event || empty($event['team_entry_enabled'])) return [];
    $raw = trim((string)($event['team_entry_methods'] ?? ''));
    if ($raw === '') return ['athlete'];
    $methods = array_values(array_intersect(
        array_map('trim', explode(',', $raw)),
        ['athlete', 'unit_user', 'event_staff']
    ));
    return $methods ?: ['athlete'];
}

/**
 * Team-entry submission window. Default OPEN (1) when the column is
 * absent (legacy events) or NULL. Event Staff bypass the window — only
 * unit users and athletes are gated by it.
 */
function eventTeamEntryWindowOpen(?array $event): bool
{
    if (!$event) return false;
    if (!array_key_exists('team_entry_window_open', $event)) return true;
    return (int)$event['team_entry_window_open'] !== 0;
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

/**
 * Render an explicit "application status" badge for athlete event
 * registrations. Combines admin_review_status + submitted_at so the
 * default 'pending' isn't ambiguous between "draft" and "awaiting
 * review".
 */
function appStatusBadge(?string $reviewStatus, ?string $submittedAt = null): string
{
    if (empty($reviewStatus)) {
        return $submittedAt
            ? '<span class="badge bg-info text-dark"><i class="bi bi-send me-1"></i>Submitted</span>'
            : '<span class="badge bg-secondary"><i class="bi bi-pencil me-1"></i>Draft</span>';
    }
    $map = [
        'pending'  => ['Submitted — Pending Review', 'bg-warning text-dark', 'bi-hourglass-split'],
        'approved' => ['Approved',                   'bg-success',           'bi-check-circle'],
        'rejected' => ['Rejected',                   'bg-danger',            'bi-x-circle'],
        'returned' => ['Returned for Changes',       'bg-info text-dark',    'bi-arrow-counterclockwise'],
    ];
    [$lbl, $cls, $icon] = $map[$reviewStatus] ?? ['Pending', 'bg-warning text-dark', 'bi-hourglass-split'];
    return "<span class='badge {$cls}'><i class='bi {$icon} me-1'></i>{$lbl}</span>";
}

function statusBadge(string $status): string
{
    // Normalise legacy event statuses onto the new vocabulary so the UI is
    // consistent even on rows that haven't been backfilled yet.
    $aliases = [
        'pending_approval' => 'active',
        'approved'         => 'active',
        'rejected'         => 'suspended',
        'cancelled'        => 'suspended',
    ];
    $key = $aliases[$status] ?? $status;
    $map = [
        'active'    => 'success',
        'verified'  => 'success',
        'confirmed' => 'success',
        'paid'      => 'success',
        'pending'   => 'warning',
        'draft'     => 'secondary',
        'inactive'  => 'secondary',
        'suspended' => 'danger',
        'failed'    => 'danger',
        'completed' => 'info',
    ];
    $color = $map[$key] ?? 'secondary';
    $label = ucfirst(str_replace('_', ' ', $key));
    return "<span class='badge bg-{$color}'>{$label}</span>";
}
