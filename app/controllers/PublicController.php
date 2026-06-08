<?php
namespace Controllers;

use Core\{Controller, Mailer};

/**
 * Static-ish public pages — Privacy, Terms, Contact. Linked from
 * the footer on every layout. Contact also accepts a POST and emails
 * the company address.
 */
class PublicController extends Controller
{
    public function privacy(): void
    {
        $this->renderWith('public', 'public/privacy', [
            'pageTitle' => 'Privacy Policy — SportsMIS®',
            'flash'     => $this->flash(),
        ]);
    }

    public function terms(): void
    {
        $this->renderWith('public', 'public/terms', [
            'pageTitle' => 'Terms of Use — SportsMIS®',
            'flash'     => $this->flash(),
        ]);
    }

    public function contact(): void
    {
        $this->renderWith('public', 'public/contact', [
            'pageTitle' => 'Contact — SportsMIS®',
            'flash'     => $this->flash(),
            'errors'    => $this->errors(),
        ]);
    }

    /** POST /contact — accepts the form, emails the company. */
    public function contactSubmit(): void
    {
        $this->verifyCsrf();
        $name    = trim((string)($_POST['name']    ?? ''));
        $mobile  = trim((string)($_POST['mobile']  ?? ''));
        $email   = trim((string)($_POST['email']   ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $message === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/contact',
                'Please fill name, a valid email, and a message before sending.',
                'warning');
        }

        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = "<h2>New enquiry from sportsmis.com</h2>"
              . "<p><strong>Name:</strong> {$h($name)}<br>"
              . "<strong>Email:</strong> {$h($email)}<br>"
              . "<strong>Mobile:</strong> {$h($mobile)}</p>"
              . "<h3>Message</h3><p style='white-space:pre-line'>{$h($message)}</p>";

        try {
            (new Mailer())->send('info@sportsbya.com',
                'SportsMIS Contact — ' . $name, $body);
        } catch (\Throwable $e) {
            error_log('[contact/submit] ' . $e->getMessage());
        }

        $this->redirect('/contact',
            'Thanks ' . $name . ' — a specialist will respond within 24 hours.');
    }
}
