<?php
namespace Core;

/**
 * Simple SMTP mailer using PHP's mail() or native socket SMTP.
 * For production, replace with PHPMailer or Symfony Mailer via Composer.
 */
class Mailer
{
    private array $cfg;

    public function __construct()
    {
        $app = require CONFIG_ROOT . '/app.php';
        $this->cfg = $app['mail'];
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->cfg['from_name']} <{$this->cfg['from_address']}>\r\n";
        $headers .= "Reply-To: {$this->cfg['from_address']}\r\n";
        $headers .= "X-Mailer: SportsMIS/1.0\r\n";

        return mail($to, $subject, $this->wrapHtml($subject, $body), $headers);
    }

    private function wrapHtml(string $subject, string $body): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            body{font-family:Inter,Arial,sans-serif;background:#f8fafc;margin:0;padding:0}
            .wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
            .header{background:#0b1f3a;padding:28px 32px;text-align:center}
            .header h1{color:#f59e0b;margin:0;font-size:22px;letter-spacing:1px}
            .content{padding:32px}
            .content h2{color:#0b1f3a;margin-top:0}
            .footer{background:#f1f5f9;padding:16px 32px;text-align:center;font-size:12px;color:#94a3b8}
            .btn{display:inline-block;background:#f59e0b;color:#0b1f3a;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;margin:16px 0}
          </style>
        </head>
        <body>
          <div class="wrapper">
            <div class="header"><h1>SportsMIS</h1></div>
            <div class="content">{$body}</div>
            <div class="footer">&copy; Sportsbya Tech Pvt. Ltd. &nbsp;|&nbsp; sportsmis.com</div>
          </div>
        </body>
        </html>
        HTML;
    }

    public function sendRegistrationPending(string $to, string $name): bool
    {
        return $this->send($to, 'Registration Received – SportsMIS', "
            <h2>Hello {$name},</h2>
            <p>Thank you for registering on <strong>SportsMIS</strong>.</p>
            <p>Your details are under review. You will receive your login credentials once verified.</p>
            <p>This usually takes <strong>1–2 business days</strong>.</p>
            <p>Regards,<br>The SportsMIS Team</p>
        ");
    }

    public function sendCredentials(string $to, string $name, string $password): bool
    {
        $loginUrl = (require CONFIG_ROOT . '/app.php')['url'] . '/login';
        return $this->send($to, 'Your SportsMIS Login Credentials', "
            <h2>Welcome to SportsMIS, {$name}!</h2>
            <p>Your account has been verified and is now active. Here are your login credentials:</p>
            <table style='border-collapse:collapse;width:100%'>
              <tr><td style='padding:8px;font-weight:bold'>Username (Email)</td><td style='padding:8px'>{$to}</td></tr>
              <tr style='background:#f8fafc'><td style='padding:8px;font-weight:bold'>Password</td><td style='padding:8px'><code>{$password}</code></td></tr>
            </table>
            <p><a class='btn' href='{$loginUrl}'>Login Now</a></p>
            <p style='color:#ef4444;font-size:13px'>Please change your password immediately after first login.</p>
        ");
    }

    public function sendPasswordReset(string $to, string $name, string $resetUrl): bool
    {
        return $this->send($to, 'Password Reset – SportsMIS', "
            <h2>Hello {$name},</h2>
            <p>We received a request to reset your password. Click the button below to proceed:</p>
            <p><a class='btn' href='{$resetUrl}'>Reset Password</a></p>
            <p style='color:#64748b;font-size:13px'>This link expires in 60 minutes. If you did not request this, ignore this email.</p>
        ");
    }
}
