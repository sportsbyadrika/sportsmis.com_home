<?php
namespace Core;

class Controller
{
    protected array $data = [];

    protected function render(string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
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
        extract($this->data);
        $content = APP_ROOT . "/views/{$view}.php";
        $layoutFile = APP_ROOT . "/views/layouts/{$layout}.php";
        require $layoutFile;
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
        unset($_SESSION['errors'], $_SESSION['old']);
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
        $token = $_POST['_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->abort(403);
        }
    }
}
