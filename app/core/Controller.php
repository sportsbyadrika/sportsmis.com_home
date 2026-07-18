<?php
namespace Core;

class Controller
{
    protected array $data = [];

    protected function render(string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        $this->prepareViewErrors();
        extract($this->data);
        $viewFile = APP_ROOT . "/views/{$view}.php";
        if (!file_exists($viewFile)) {
            http_response_code(500);
            exit("View not found: {$view}");
        }
        require $viewFile;
    }

    protected function renderWith(string $layout, string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        $this->prepareViewErrors();
        extract($this->data);
        $content    = APP_ROOT . "/views/{$view}.php";
        $layoutFile = APP_ROOT . "/views/layouts/{$layout}.php";
        require $layoutFile;
    }

    private function prepareViewErrors(): void
    {
        // Make validation errors available to fieldError()/hasError() helpers.
        // Prefer errors already in $this->data (passed explicitly by controller),
        // then fall back to session errors set by validate().
        $errors = $this->data['errors'] ?? $_SESSION['errors'] ?? [];
        $GLOBALS['_sms_errors'] = $errors;
        // Keep $_SESSION['old'] alive so old() helper works during this render,
        // then clear both so they don't bleed into subsequent requests.
        unset($_SESSION['errors']);
    }

    protected function redirect(string $path, string $message = '', string $type = 'success'): void
    {
        if ($message) {
            $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        }
        header("Location: {$path}");
        exit;
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function abort(int $code = 403): void
    {
        http_response_code($code);
        require APP_ROOT . "/views/errors/{$code}.php";
        exit;
    }

    protected function requireAuth(string ...$roles): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
        if ($roles && !in_array(Auth::user()['role'], $roles, true)) {
            $this->abort(403);
        }
    }

    protected function requireGuest(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::homeUrl());
        }
    }

    protected function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }

    protected function flash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    protected function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? null;
            foreach (explode('|', $rule) as $r) {
                [$r, $param] = array_pad(explode(':', $r, 2), 2, null);
                match ($r) {
                    'required' => !$value || trim($value) === '' ? $errors[$field][] = ucfirst($field) . ' is required.' : null,
                    'email'    => $value && !filter_var($value, FILTER_VALIDATE_EMAIL) ? $errors[$field][] = 'Enter a valid email.' : null,
                    'min'      => $value && strlen($value) < (int)$param ? $errors[$field][] = "Minimum {$param} characters required." : null,
                    'max'      => $value && strlen($value) > (int)$param ? $errors[$field][] = "Maximum {$param} characters allowed." : null,
                    'numeric'  => $value && !is_numeric($value) ? $errors[$field][] = ucfirst($field) . ' must be numeric.' : null,
                    'mobile'   => $value && !preg_match('/^[6-9]\d{9}$/', $value) ? $errors[$field][] = 'Enter a valid 10-digit mobile number.' : null,
                    default    => null,
                };
            }
        }
        if ($errors) {
            $_SESSION['old']    = $_POST;
            $_SESSION['errors'] = $errors;
        }
        return $errors;
    }

    protected function errors(): array
    {
        $e = $_SESSION['errors'] ?? [];
        // Don't clear here — prepareViewErrors() handles cleanup during render
        return $e;
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): void
    {
        // A POST that exceeds php.ini's post_max_size is delivered with an
        // EMPTY $_POST even though the browser did send a body. Without this
        // guard the missing _token would surface as a bare 403 — which reads
        // as "forbidden" and badly confuses users who simply picked too large
        // a file. Detect that shape and return a clear "upload too large"
        // page instead.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && empty($_POST)
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $max = (string)ini_get('post_max_size');
            http_response_code(413);
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<div style="font-family:system-ui,-apple-system,sans-serif;max-width:620px;margin:60px auto;padding:28px;border:1px solid #e5e7eb;border-radius:14px">'
               . '<h2 style="color:#b91c1c;margin-top:0">Upload too large</h2>'
               . '<p>The file you tried to upload is larger than the server accepts'
               . ($max !== '' ? ' (server limit &asymp; ' . htmlspecialchars($max, ENT_QUOTES) . ')' : '')
               . ', so your details were <strong>not saved</strong>.</p>'
               . '<p>Please choose a smaller file (max 7&nbsp;MB) and submit again.</p>'
               . '<p style="margin-top:20px"><a href="javascript:history.back()" '
               . 'style="display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none">&larr; Go back</a></p>'
               . '</div>';
            exit;
        }
        $token = $_POST['_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->abort(403);
        }
    }
}
