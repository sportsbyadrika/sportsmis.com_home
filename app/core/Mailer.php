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
        $fromAddr = (string)$this->cfg['from_address'];
        $fromName = (string)$this->cfg['from_name'];
        $html     = $this->wrapHtml($subject, $body);

        // If real SMTP credentials are configured, ship via authenticated
        // SMTP — this is dramatically more reliable than PHP's mail()
        // from a shared cPanel host (mail() emits unauthenticated mail
        // that Gmail / Outlook silently drop or quarantine).
        $useSmtp = !empty($this->cfg['username']) && !empty($this->cfg['password'])
                && !empty($this->cfg['host']);
        if ($useSmtp) {
            try {
                $ok = $this->sendSmtp($to, $subject, $html);
                error_log('[Mailer] SMTP send to=' . $to
                    . ' subject="' . $subject . '" result='
                    . ($ok ? 'ok' : 'fail'));
                if ($ok) return true;
                // Fall through to mail() if SMTP failed — better to try
                // than silently lose the message.
            } catch (\Throwable $e) {
                error_log('[Mailer] SMTP exception to=' . $to
                    . ' err=' . $e->getMessage()
                    . ' — falling back to mail()');
            }
        }

        $msgId   = bin2hex(random_bytes(8))
                 . '@' . parse_url($this->cfg['from_address']
                                  ? ('mailto:' . $this->cfg['from_address']) : 'mailto:noreply@local',
                                  PHP_URL_HOST);
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$fromAddr}>\r\n";
        $headers .= "Sender: {$fromAddr}\r\n";
        $headers .= "Reply-To: {$fromAddr}\r\n";
        $headers .= "Return-Path: {$fromAddr}\r\n";
        $headers .= "Message-ID: <{$msgId}>\r\n";
        $headers .= "X-Mailer: SportsMIS/1.0\r\n";

        // -f <envelope-sender> on mail() sets the envelope-From so
        // bounces come back to a real mailbox; cPanel hosts honour
        // this and many spam filters check it.
        $ok = mail($to, $subject, $html, $headers, '-f ' . $fromAddr);
        error_log('[Mailer] mail() send to=' . $to
            . ' subject="' . $subject . '" result=' . ($ok ? 'ok' : 'fail'));
        return $ok;
    }

    /**
     * Minimal raw-socket SMTP submission. Supports STARTTLS (port 587)
     * and implicit TLS (port 465). Auth via AUTH LOGIN. Throws on any
     * protocol error so the caller can log + fall back to mail().
     */
    private function sendSmtp(string $to, string $subject, string $html): bool
    {
        $host = (string)$this->cfg['host'];
        $port = (int)($this->cfg['port'] ?? 587);
        $enc  = strtolower((string)($this->cfg['encryption'] ?? 'tls'));
        $user = (string)$this->cfg['username'];
        $pass = (string)$this->cfg['password'];
        $from = (string)$this->cfg['from_address'];
        $fromN= (string)$this->cfg['from_name'];

        $remote = ($port === 465 ? 'tls://' : '') . $host . ':' . $port;
        $sock = @stream_socket_client($remote, $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT);
        if (!$sock) {
            throw new \RuntimeException("connect {$remote}: {$errstr} ({$errno})");
        }
        stream_set_timeout($sock, 15);

        $read = function () use ($sock) {
            $lines = '';
            while (!feof($sock)) {
                $line = fgets($sock, 1024);
                if ($line === false) break;
                $lines .= $line;
                // Multi-line replies have a '-' on every line except the last.
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $lines;
        };
        $send = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
        $expect = function (string $reply, string $code, string $what): void {
            if (substr($reply, 0, 3) !== $code) {
                throw new \RuntimeException("SMTP {$what} failed: " . trim($reply));
            }
        };

        $expect($read(), '220', 'banner');
        $ehloHost = parse_url((string)($this->cfg['from_address']
            ? 'mailto:' . $this->cfg['from_address'] : 'mailto:local'),
            PHP_URL_HOST) ?: 'localhost';
        $send('EHLO ' . $ehloHost);
        $expect($read(), '250', 'EHLO');

        if ($port !== 465 && $enc === 'tls') {
            $send('STARTTLS');
            $expect($read(), '220', 'STARTTLS');
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($sock, true, $crypto)) {
                throw new \RuntimeException('TLS negotiation failed');
            }
            $send('EHLO ' . $ehloHost);
            $expect($read(), '250', 'EHLO/TLS');
        }

        if ($user !== '' && $pass !== '') {
            $send('AUTH LOGIN');
            $expect($read(), '334', 'AUTH LOGIN');
            $send(base64_encode($user));
            $expect($read(), '334', 'AUTH username');
            $send(base64_encode($pass));
            $expect($read(), '235', 'AUTH password');
        }

        $send('MAIL FROM:<' . $from . '>');
        $expect($read(), '250', 'MAIL FROM');
        $send('RCPT TO:<' . $to . '>');
        $reply = $read();
        if (substr($reply, 0, 1) !== '2') {
            throw new \RuntimeException('RCPT TO rejected: ' . trim($reply));
        }

        $send('DATA');
        $expect($read(), '354', 'DATA');

        $msgId = bin2hex(random_bytes(8)) . '@' . $ehloHost;
        $date  = date('r');
        $headers =
            "Date: {$date}\r\n"
            . "From: {$fromN} <{$from}>\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "Message-ID: <{$msgId}>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Reply-To: {$from}\r\n"
            . "X-Mailer: SportsMIS/1.0\r\n";

        // RFC 5321 §4.5.2 dot-stuffing — any line starting with '.' must
        // be prefixed with another '.' or the server interprets it as
        // end-of-DATA mid-message.
        $payload = $headers . "\r\n" . $html;
        $payload = preg_replace("/\r?\n/", "\r\n", $payload);
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($sock, $payload . "\r\n.\r\n");
        $expect($read(), '250', 'DATA body');

        $send('QUIT');
        fclose($sock);
        return true;
    }

    private function wrapHtml(string $subject, string $body): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <meta name="x-apple-disable-message-reformatting">
          <style>
            body{font-family:Inter,Arial,sans-serif;background:#f8fafc;margin:0;padding:0;-webkit-text-size-adjust:100%}
            img{border:0;line-height:100%;outline:none;-ms-interpolation-mode:bicubic;max-width:100%;height:auto}
            table{border-collapse:collapse}
            .wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
            .header{background:#0b1f3a;padding:28px 32px;text-align:center}
            .header h1{color:#f59e0b;margin:0;font-size:22px;letter-spacing:1px}
            .content{padding:32px}
            .content h2{color:#0b1f3a;margin-top:0}
            .footer{background:#f1f5f9;padding:16px 32px;text-align:center;font-size:12px;color:#94a3b8}
            .btn{display:inline-block;background:#f59e0b;color:#0b1f3a;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;margin:16px 0}
            @media only screen and (max-width:600px){
              .wrapper{margin:0 !important;border-radius:0 !important;box-shadow:none !important;max-width:100% !important;width:100% !important}
              .header{padding:20px 16px !important}
              .header h1{font-size:18px !important}
              .content{padding:18px 14px !important}
              .footer{padding:14px 16px !important}
            }
          </style>
        </head>
        <body>
          <div class="wrapper">
            <div class="header"><h1>SportsMIS</h1></div>
            <div class="content">{$body}</div>
            <div class="footer">
              &copy; <a href="https://sportsbya.com" style="color:#94a3b8;text-decoration:none">SportsByA Tech (OPC) Private Limited</a>
              &nbsp;|&nbsp; Powered by <strong>SportsMIS&reg;</strong>
              &nbsp;|&nbsp; sportsmis.com
            </div>
          </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Email a generated certificate as a PDF attachment. Skips silently
     * and returns false when no SMTP credentials are configured (e.g.
     * local dev) so the caller's bulk loop can still report a useful
     * sent/skipped tally.
     */
    public function sendCertificate(string $to, string $name, array $event, string $pdfPath, string $certNo): bool
    {
        if (!is_file($pdfPath)) return false;
        $eventName = (string)($event['name'] ?? 'your event');
        $subject = 'Your Certificate — ' . $eventName;
        $body = "
            <h2>Hello {$name},</h2>
            <p>Your certificate from <strong>" . htmlspecialchars($eventName, ENT_QUOTES) . "</strong> is attached.</p>
            <p>Certificate number: <code>" . htmlspecialchars($certNo, ENT_QUOTES) . "</code></p>
            <p>Congratulations and thank you for taking part. You can also view your
            certificate any time from your athlete portal.</p>
            <p>Regards,<br>The SportsMIS Team</p>
        ";
        $html = $this->wrapHtml($subject, $body);
        $fileName = 'Certificate-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $certNo) . '.pdf';
        return $this->sendSmtpWithAttachments($to, $subject, $html, [
            ['path' => $pdfPath, 'name' => $fileName, 'mime' => 'application/pdf'],
        ]);
    }

    /**
     * SMTP delivery with one or more file attachments. Shares the
     * envelope handling with sendSmtp() but emits a multipart/mixed
     * body with the HTML alternative and base64-encoded attachments.
     */
    private function sendSmtpWithAttachments(string $to, string $subject, string $html, array $attachments): bool
    {
        $host = (string)$this->cfg['host'];
        $port = (int)($this->cfg['port'] ?? 587);
        $enc  = strtolower((string)($this->cfg['encryption'] ?? 'tls'));
        $user = (string)$this->cfg['username'];
        $pass = (string)$this->cfg['password'];
        $from = (string)$this->cfg['from_address'];
        $fromN= (string)$this->cfg['from_name'];
        if ($host === '' || $from === '') return false;

        $remote = ($port === 465 ? 'tls://' : '') . $host . ':' . $port;
        $sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$sock) {
            throw new \RuntimeException("connect {$remote}: {$errstr} ({$errno})");
        }
        stream_set_timeout($sock, 30);
        $read = function () use ($sock) {
            $lines = '';
            while (!feof($sock)) {
                $line = fgets($sock, 1024);
                if ($line === false) break;
                $lines .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $lines;
        };
        $send   = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
        $expect = function (string $reply, string $code, string $what): void {
            if (substr($reply, 0, 3) !== $code) {
                throw new \RuntimeException("SMTP {$what} failed: " . trim($reply));
            }
        };

        $expect($read(), '220', 'banner');
        $ehloHost = parse_url('mailto:' . $from, PHP_URL_HOST) ?: 'localhost';
        $send('EHLO ' . $ehloHost);
        $expect($read(), '250', 'EHLO');
        if ($port !== 465 && $enc === 'tls') {
            $send('STARTTLS');
            $expect($read(), '220', 'STARTTLS');
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($sock, true, $crypto)) {
                throw new \RuntimeException('TLS negotiation failed');
            }
            $send('EHLO ' . $ehloHost);
            $expect($read(), '250', 'EHLO/TLS');
        }
        if ($user !== '' && $pass !== '') {
            $send('AUTH LOGIN');                  $expect($read(), '334', 'AUTH LOGIN');
            $send(base64_encode($user));          $expect($read(), '334', 'AUTH user');
            $send(base64_encode($pass));          $expect($read(), '235', 'AUTH pass');
        }
        $send('MAIL FROM:<' . $from . '>');       $expect($read(), '250', 'MAIL FROM');
        $send('RCPT TO:<' . $to . '>');
        $reply = $read();
        if (substr($reply, 0, 1) !== '2') {
            throw new \RuntimeException('RCPT TO rejected: ' . trim($reply));
        }
        $send('DATA');                            $expect($read(), '354', 'DATA');

        $boundary = '----=_smsbnd_' . bin2hex(random_bytes(8));
        $msgId    = bin2hex(random_bytes(8)) . '@' . $ehloHost;
        $headers  =
            "Date: " . date('r') . "\r\n"
            . "From: {$fromN} <{$from}>\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "Message-ID: <{$msgId}>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n"
            . "Reply-To: {$from}\r\n"
            . "X-Mailer: SportsMIS/1.0\r\n";
        $body =
            "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n";
        foreach ($attachments as $att) {
            $path = (string)($att['path'] ?? '');
            $name = (string)($att['name'] ?? basename($path));
            $mime = (string)($att['mime'] ?? 'application/octet-stream');
            if (!is_file($path)) continue;
            $data = base64_encode((string)file_get_contents($path));
            $data = chunk_split($data, 76, "\r\n");
            $body .= "--{$boundary}\r\n"
                  . "Content-Type: {$mime}; name=\"{$name}\"\r\n"
                  . "Content-Transfer-Encoding: base64\r\n"
                  . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
                  . $data . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $payload = $headers . "\r\n" . $body;
        $payload = preg_replace("/\r?\n/", "\r\n", $payload);
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($sock, $payload . "\r\n.\r\n");
        $expect($read(), '250', 'DATA body');
        $send('QUIT');
        fclose($sock);
        return true;
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
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $loginUrl    = $base . '/login';
        $profileUrl  = $base . '/athlete/profile';
        $eventsUrl   = $base . '/athlete/dashboard';
        return $this->send($to, 'Welcome to SportsMIS – Your Login Credentials', "
            <h2>Welcome to SportsMIS, {$name}!</h2>
            <p>Your account has been created and is now active. Use the credentials below to sign in:</p>
            <table style='border-collapse:collapse;width:100%'>
              <tr><td style='padding:8px;font-weight:bold'>Username (Email)</td><td style='padding:8px'>{$to}</td></tr>
              <tr style='background:#f8fafc'><td style='padding:8px;font-weight:bold'>Password</td><td style='padding:8px'><code>{$password}</code></td></tr>
            </table>
            <p style='color:#ef4444;font-size:13px'>Please change your password immediately after first login.</p>
            <p><a class='btn' href='{$loginUrl}'>Login Now</a></p>

            <h3 style='margin-top:28px'>Next Steps</h3>
            <ol style='padding-left:18px;line-height:1.7'>
              <li>
                <strong>Sign in</strong> at <a href='{$loginUrl}'>{$loginUrl}</a> using the credentials above.
              </li>
              <li>
                <strong>Complete your profile</strong> at
                <a href='{$profileUrl}'>{$profileUrl}</a> — upload your passport photo, fill in personal &amp;
                location details, your Aadhaar / DOB proof, and pick your preferred sports. Click
                <em>Submit Profile</em> when done.
              </li>
              <li>
                <strong>Find Active Events</strong> on your dashboard at
                <a href='{$eventsUrl}'>{$eventsUrl}</a> after submitting your profile.
              </li>
              <li>
                <strong>Register for an event</strong> by selecting your Unit / Club, the sport events
                you want, and submitting your payment transaction details. The event administrator
                will review and issue your competitor card.
              </li>
            </ol>
        ");
    }

    /**
     * Credentials email for a newly created Institution / Club account.
     * Mirrors sendCredentials() but with institution-appropriate next steps
     * (institution portal links, not the athlete profile / event flow).
     */
    public function sendInstitutionCredentials(string $to, string $name, string $password): bool
    {
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $loginUrl   = $base . '/login';
        $profileUrl = $base . '/institution/profile';
        $dashUrl    = $base . '/institution/dashboard';
        return $this->send($to, 'Welcome to SportsMIS – Your Login Credentials', "
            <h2>Welcome to SportsMIS, {$name}!</h2>
            <p>Your <strong>Institution / Club</strong> account has been created and is now active.
               Use the credentials below to sign in:</p>
            <table style='border-collapse:collapse;width:100%'>
              <tr><td style='padding:8px;font-weight:bold'>Username (Email)</td><td style='padding:8px'>{$to}</td></tr>
              <tr style='background:#f8fafc'><td style='padding:8px;font-weight:bold'>Password</td><td style='padding:8px'><code>{$password}</code></td></tr>
            </table>
            <p style='color:#ef4444;font-size:13px'>Please change your password immediately after first login.</p>
            <p><a class='btn' href='{$loginUrl}'>Login Now</a></p>

            <h3 style='margin-top:28px'>Next Steps</h3>
            <ol style='padding-left:18px;line-height:1.7'>
              <li>
                <strong>Sign in</strong> at <a href='{$loginUrl}'>{$loginUrl}</a> using the
                <em>Schools / Institutions / Clubs</em> login with the credentials above.
              </li>
              <li>
                <strong>Complete your institution profile</strong> at
                <a href='{$profileUrl}'>{$profileUrl}</a> — logo, contact details and address.
              </li>
              <li>
                <strong>Manage everything from your dashboard</strong> at
                <a href='{$dashUrl}'>{$dashUrl}</a> — events, units, athlete registrations, staff and certificates.
              </li>
              <li>
                <strong>Creating events?</strong> If the Create Event facility isn't enabled for your
                account yet, please reach out to the SportsMIS team to activate it.
              </li>
            </ol>
        ");
    }

    /**
     * Credentials email for a newly created Staff account. Staff sign in
     * through the Institution / Club portal and see only the modules their
     * institution administrator assigned them.
     */
    public function sendStaffCredentials(string $to, string $name, string $password): bool
    {
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $loginUrl = $base . '/login';
        return $this->send($to, 'Welcome to SportsMIS – Your Login Credentials', "
            <h2>Welcome to SportsMIS, {$name}!</h2>
            <p>A <strong>Staff</strong> account has been created for you and is now active.
               Use the credentials below to sign in:</p>
            <table style='border-collapse:collapse;width:100%'>
              <tr><td style='padding:8px;font-weight:bold'>Username (Email)</td><td style='padding:8px'>{$to}</td></tr>
              <tr style='background:#f8fafc'><td style='padding:8px;font-weight:bold'>Password</td><td style='padding:8px'><code>{$password}</code></td></tr>
            </table>
            <p style='color:#ef4444;font-size:13px'>Please change your password immediately after first login.</p>
            <p><a class='btn' href='{$loginUrl}'>Login Now</a></p>

            <h3 style='margin-top:28px'>Next Steps</h3>
            <ol style='padding-left:18px;line-height:1.7'>
              <li>
                <strong>Sign in</strong> at <a href='{$loginUrl}'>{$loginUrl}</a> using the
                <em>Schools / Institutions / Clubs</em> login with the credentials above.
              </li>
              <li>
                You'll have access to the modules your institution administrator has assigned to you.
              </li>
              <li>
                For any change in access, please contact your institution administrator.
              </li>
            </ol>
        ");
    }

    /**
     * Sent when an athlete clicks Submit Profile so they know what to do next.
     */
    public function sendProfileCompleted(string $to, string $name): bool
    {
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $eventsUrl = $base . '/athlete/dashboard';
        return $this->send($to, 'Profile Complete – Find Events on SportsMIS', "
            <h2>Great work, {$name}!</h2>
            <p>Your athlete profile has been submitted successfully and is now ready to use.</p>
            <h3 style='margin-top:24px'>What's next?</h3>
            <ol style='padding-left:18px;line-height:1.7'>
              <li><strong>Browse Active Events</strong> on your dashboard at
                  <a href='{$eventsUrl}'>{$eventsUrl}</a>.</li>
              <li>Click <strong>Register</strong> on the event you want to enter.</li>
              <li>Pick your <strong>Unit / Club / Institution</strong> (or use the <em>Other</em> option),
                  upload an NOC letter if your event requires one, and add the sport events you'd like
                  to compete in.</li>
              <li>Save your selection, then submit one or more <strong>payment transactions</strong>
                  totalling the entry fee. The event administrator will review your registration and,
                  on approval, issue you a <strong>competitor number</strong> and emailable card.</li>
            </ol>
            <p><a class='btn' href='{$eventsUrl}'>Open My Dashboard</a></p>
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

    /**
     * Send freshly issued credentials to a Unit / Institution / Club user.
     */
    public function sendUnitUserCredentials(string $to, string $name, string $eventCode, string $eventName, string $password): bool
    {
        $cfg  = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $login = $base . '/unit/login';
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n  = $h($name); $ec = $h($eventCode); $en = $h($eventName); $em = $h($to); $pw = $h($password);
        return $this->send($to, 'Unit Login – ' . $eventName, "
            <h2>Hello {$n},</h2>
            <p>You've been added as a <strong>Unit / Club / Institution user</strong> for the event
               <strong>{$en}</strong>. Use the credentials below to sign in to the unit portal:</p>
            <p style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;line-height:1.9'>
              <strong>Login URL:</strong> <a href='{$login}'>{$login}</a><br>
              <strong>Event Code:</strong> <code>{$ec}</code><br>
              <strong>Email:</strong> {$em}<br>
              <strong>Temporary Password:</strong> <code>{$pw}</code>
            </p>
            <p>You'll be able to change your password after logging in.</p>
            <p><a class='btn' href='{$login}'>Sign in to the Unit Portal</a></p>
        ");
    }

    /**
     * Send freshly issued credentials to an Event Staff user.
     */
    public function sendEventStaffCredentials(string $to, string $name, string $eventCode, string $eventName, string $password): bool
    {
        $cfg  = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $login = $base . '/event-staff/login';
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n  = $h($name); $ec = $h($eventCode); $en = $h($eventName); $em = $h($to); $pw = $h($password);
        return $this->send($to, 'Event Staff Login – ' . $eventName, "
            <h2>Hello {$n},</h2>
            <p>You've been added as <strong>Event Staff</strong> for the event
               <strong>{$en}</strong>. Use the credentials below to sign in to the staff portal:</p>
            <p style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;line-height:1.9'>
              <strong>Login URL:</strong> <a href='{$login}'>{$login}</a><br>
              <strong>Event Code:</strong> <code>{$ec}</code><br>
              <strong>Email:</strong> {$em}<br>
              <strong>Temporary Password:</strong> <code>{$pw}</code>
            </p>
            <p>You'll be able to change your password after logging in. Your dashboard
               menu reflects the privileges assigned to you by the event administrator.</p>
            <p><a class='btn' href='{$login}'>Sign in to the Staff Portal</a></p>
        ");
    }

    /**
     * Notify the athlete that their event registration has been approved.
     * Sent on approve; the Competitor Card itself is now issued separately
     * via the event-admin Competitor Card report.
     */
    public function sendRegistrationApproved(string $to, array $athlete, array $event): bool
    {
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $dashUrl = $base . '/athlete/my-registrations';
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name      = $h($athlete['name'] ?? '');
        $eventName = $h($event['name']   ?? '');
        return $this->send($to, 'Registration Approved – ' . $eventName, "
            <h2>Good news, {$name}!</h2>
            <p>Your registration for <strong>{$eventName}</strong> has been
               <span style='color:#16a34a;font-weight:600'>approved</span> by the event administrator.</p>
            <p>The Competitor Card will be issued by the organiser separately. Once it's ready
               you'll receive another email with the card attached, and the card download will
               also appear on your dashboard.</p>
            <p><a class='btn' href='{$dashUrl}'>Open My Registrations</a></p>
            <p style='color:#64748b;font-size:13px;margin-top:24px'>
              If you have any questions, reach out to the event administrator through the
              <em>Grievances</em> section of the event.
            </p>
        ");
    }

    /**
     * Send the inline-styled Competitor Card to an athlete. Uses the SportsMIS
     * wrapper so the email looks consistent; the card itself is built with
     * inline CSS for maximum email-client compatibility.
     */
    public function sendCompetitorCard(
        string $to,
        array $athlete,
        array $event,
        ?array $institution,
        array $registration,
        array $items
    ): bool {
        $cfg = require CONFIG_ROOT . '/app.php';
        $base = rtrim($cfg['url'], '/');
        $cardUrl = $base . '/athlete/registrations/' . \hid_reg((int)$registration['id']) . '/card';
        $compNo    = str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT);
        $compLabel = \Models\Event::competitorLabel($event);   // e.g. "Chest Number"

        // QR content per the event's Card Settings — default encodes the
        // padded competitor number; the 'url' mode encodes whatever URL
        // the admin configured. Same resolver lives on the web card view
        // so both surfaces always agree.
        $qrMode      = (string)($event['competitor_card_qr_mode'] ?? 'competitor_no');
        $qrCustomUrl = trim((string)($event['competitor_card_qr_url'] ?? ''));
        $qrData      = ($qrMode === 'url' && $qrCustomUrl !== '') ? $qrCustomUrl : $compNo;
        $qrSrc       = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=' . rawurlencode($qrData);
        $qrCaption   = trim((string)($event['competitor_card_qr_label'] ?? '')) ?: 'Scan to verify';
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Pull the same rich context the printable card uses so the email
        // shows category-grouped events, team entries, age categories and
        // allotted relay/lane (with the range's address).
        $ctx          = \Models\EventRegistration::competitorCardContext((int)$registration['id']);
        $catRows      = $ctx['category_rows'] ?? [];
        $ageCatLabel  = (string)($ctx['age_category_label'] ?? '');

        // Header bits.
        $ageYrs = !empty($athlete['date_of_birth'])
            ? (int)(new \DateTime($athlete['date_of_birth']))->diff(new \DateTime())->y . ' yrs'
            : '';
        $genderAgeCat = [];
        if (!empty($athlete['gender'])) $genderAgeCat[] = $h(genderLabel((string)$athlete['gender'], $event));
        if ($ageYrs !== '')             $genderAgeCat[] = $h($ageYrs);
        if ($ageCatLabel !== '')        $genderAgeCat[] = $h($ageCatLabel);
        $genderAgeCatLine = implode(' / ', $genderAgeCat) ?: '—';

        // Category rows table — mirrors the printable card's grid:
        // # · Event Category · Events · Team Entries · Relay & Lane · Fee.
        $rowsHtml = '';
        $i = 0;
        foreach ($catRows as $catName => $row) {
            $i++;
            $codes      = '';
            foreach ($row['events'] as $c) {
                $codes .= "<code style='display:inline-block;margin:1px 2px;font-family:monospace'>{$h($c)}</code>";
            }
            if ($codes === '') $codes = "<span style='color:#94a3b8'>—</span>";

            $teamCodes  = '';
            foreach ($row['team_events'] as $c) {
                $teamCodes .= "<code style='display:inline-block;margin:1px 2px;padding:1px 4px;background:#fff3cd;font-family:monospace'>{$h($c)}</code>";
            }
            if ($teamCodes === '') $teamCodes = "<span style='color:#94a3b8'>—</span>";

            $relayHtml = '';
            foreach ($row['relays'] as $rl) {
                $line = '<strong>Relay ' . $h($rl['relay_number']) . '</strong>';
                if (!empty($rl['relay_date'])) {
                    $line .= ' · ' . $h(date('d M Y', strtotime((string)$rl['relay_date'])));
                }
                if (!empty($rl['match_time'])) {
                    $line .= ' · ' . $h(substr((string)$rl['match_time'], 0, 5));
                }
                $line .= ' · Lane ' . $h($rl['lane_number']);
                $venueExtra = '';
                if (!empty($rl['range_name']) || !empty($rl['range_address'])) {
                    $bits = [];
                    if (!empty($rl['range_name']))    $bits[] = $h($rl['range_name']);
                    if (!empty($rl['range_address'])) $bits[] = $h($rl['range_address']);
                    $venueExtra = "<div style='color:#475569;font-weight:400;font-size:11px'>"
                                . implode(' — ', $bits) . "</div>";
                }
                $relayHtml .= "<div style='line-height:1.3;margin-bottom:4px'>{$line}{$venueExtra}</div>";
            }
            if ($relayHtml === '') $relayHtml = "<span style='color:#94a3b8'>— not yet —</span>";

            $fee = '₹' . number_format((float)$row['fee'], 2);
            $L = "<span class='cc-mail-cell-label' style='display:none'>";
            $rowsHtml .= "<tr>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top'>{$L}#</span>{$i}</td>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top'>{$L}Event Category</span>{$h($catName)}</td>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top'>{$L}Events</span>{$codes}</td>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top'>{$L}Team Entries</span>{$teamCodes}</td>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top'>{$L}Relay &amp; Lane</span>{$relayHtml}</td>"
                . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;vertical-align:top'>{$L}Fee</span>{$fee}</td>"
                . "</tr>";
        }
        if ($rowsHtml === '') {
            $rowsHtml = "<tr><td colspan='6' style='padding:8px;color:#64748b'>No events registered.</td></tr>";
        }

        $photoHtml = !empty($athlete['passport_photo'])
            ? "<img src='" . $h($base . $athlete['passport_photo']) . "' width='140' height='140'"
              . " style='object-fit:cover;border-radius:12px;border:3px solid #0b1f3a'>"
            : "<div style='width:140px;height:140px;border-radius:12px;background:#e2e8f0;text-align:center;line-height:140px;font-size:42px;font-weight:700;color:#475569'>"
              . $h(strtoupper(substr($athlete['name'] ?? 'A', 0, 1))) . "</div>";

        // Header logo + heading come from the EVENT — institution rides
        // below as the sub-line. Falls back to a one-letter chip of the
        // event name when no event logo is configured.
        $headerLogo = !empty($event['logo'])
            ? "<img src='" . $h($base . $event['logo']) . "' width='48' height='48' style='object-fit:contain;background:#fff;border-radius:8px;padding:3px'>"
            : "<div style='width:48px;height:48px;border-radius:8px;background:rgba(255,255,255,.15);text-align:center;line-height:48px;font-weight:700;color:#fff'>"
              . $h(strtoupper(substr($event['name'] ?? 'E', 0, 1))) . "</div>";

        $name = $h($athlete['name'] ?? '');
        $eventName = $h($event['name'] ?? '');
        $eventDates = $h(date('d M Y', strtotime($event['event_date_from'])) . ' – ' . date('d M Y', strtotime($event['event_date_to'])));
        $venue = $h($event['location'] ?? '');
        $unitLabel = $h($registration['unit_name'] ?? ($registration['unit_name_other'] ?? ''));
        $unitAddr  = $h($registration['unit_address'] ?? '');
        $instName = $h($institution['name'] ?? '');
        $mobile = $h($athlete['mobile'] ?? '');
        $approvedOn = !empty($registration['admin_reviewed_at'])
            ? $h(date('d M Y', strtotime((string)$registration['admin_reviewed_at'])))
            : '—';

        // Optional per-event card message — preserve line breaks.
        $cardMsg = trim((string)($event['competitor_card_message'] ?? ''));
        $cardMessageHtml = '';
        if ($cardMsg !== '') {
            $msgHtml = nl2br($h($cardMsg), false);
            $cardMessageHtml =
                "<div style='margin:0 20px 18px;padding:8px 14px 12px;background:#fff7ed;"
                . "border:1px solid #fed7aa;border-radius:8px;color:#7c2d12;"
                . "font-size:12.5px;line-height:1.45;font-weight:700'>"
                . "<div style='font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;"
                . "color:#9a3412;margin-bottom:1px;font-weight:700;line-height:1.2'>Important Note</div>"
                . $msgHtml
                . "</div>";
        }

        // Scoped responsive overrides. Email clients that honour
        // @media queries (Apple Mail, iOS Mail, Gmail apps, Yahoo) will
        // collapse the two-column body and stack the events grid into
        // labelled cards. Outlook desktop ignores media queries and
        // keeps the desktop layout, which still fits at 600px width.
        $responsiveCss = "
        <style>
          @media only screen and (max-width:600px){
            .cc-mail-stack { display:block !important; width:100% !important; box-sizing:border-box !important; }
            .cc-mail-stack-photo { text-align:center !important; border-top:1px solid #e2e8f0 !important; padding:14px 16px !important; }
            .cc-mail-stack-text  { padding:14px 16px !important; }
            .cc-mail-events thead { display:none !important; }
            .cc-mail-events tr {
              display:block !important; width:100% !important;
              border:1px solid #e2e8f0 !important; border-radius:8px !important;
              margin-bottom:10px !important; padding:6px 10px !important;
              box-sizing:border-box !important;
            }
            .cc-mail-events td {
              display:block !important; width:100% !important;
              border:0 !important; padding:5px 0 !important;
              text-align:left !important;
            }
            .cc-mail-cell-label {
              display:block !important;
              font-size:10px !important; letter-spacing:.05em !important;
              text-transform:uppercase !important; color:#94a3b8 !important;
              margin-bottom:2px !important;
            }
            .cc-mail-header-text  { font-size:14px !important; }
            .cc-mail-header-meta  { font-size:12px !important; }
            .cc-mail-photo img,
            .cc-mail-photo div { width:120px !important; height:120px !important; line-height:120px !important; }
            .cc-mail-comp-no   { font-size:28px !important; }
          }
        </style>
        ";

        $body = $responsiveCss . "
        <h2 style='margin-top:0'>Hello {$name},</h2>
        <p>Your registration for <strong>{$eventName}</strong> has been <strong>approved</strong>.
           Your competitor card is below — keep it handy for the venue. You can also
           <a href='{$h($cardUrl)}'>view / print it online</a> at any time.</p>

        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin:16px 0'>
          <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#0b1f3a;color:#fff'>
            <tr>
              <td style='padding:16px 18px;width:64px;vertical-align:middle'>{$headerLogo}</td>
              <td style='padding:16px 18px;vertical-align:middle'>
                <div class='cc-mail-header-text' style='font-weight:700;font-size:15px'>{$eventName}</div>
                <div class='cc-mail-header-meta' style='opacity:.85;font-size:13px'>{$instName} · {$eventDates}</div>
              </td>
            </tr>
          </table>
          <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
            <tr>
              <td class='cc-mail-stack cc-mail-stack-text' style='padding:18px 20px;vertical-align:top'>
                <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px'>Competitor</div>
                <div style='font-size:13px;line-height:1.7'>
                  <strong>Name:</strong> {$name}<br>
                  <strong>Gender / Age / Category:</strong> {$genderAgeCatLine}<br>
                  <strong>Mobile:</strong> {$mobile}"
                  . ($unitLabel !== ''
                      ? "<br><strong>Unit:</strong> {$unitLabel}"
                        . ($unitAddr !== ''
                            ? "<div style='color:#475569;font-weight:400;font-size:12px;margin-top:2px'>{$unitAddr}</div>"
                            : '')
                      : '')
                  . "
                </div>

                <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:14px 0 6px'>Event</div>
                <div style='font-size:13px;line-height:1.7'>
                  <strong>Venue:</strong> {$venue}<br>
                  <strong>Approved On:</strong> {$approvedOn}
                </div>
              </td>
              <td class='cc-mail-stack cc-mail-stack-photo cc-mail-photo' style='padding:18px 20px;vertical-align:top;width:180px;text-align:center'>
                {$photoHtml}
                <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-top:10px'>{$compLabel}</div>
                <div class='cc-mail-comp-no' style='font-size:32px;font-weight:800;color:#0b1f3a;letter-spacing:1px'>{$compNo}</div>
                <img src='{$h($qrSrc)}' width='110' height='110' alt='QR' style='display:block;margin:8px auto 0;max-width:100%;height:auto'>
                <div style='font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;margin-top:4px'>{$h($qrCaption)}</div>
              </td>
            </tr>
          </table>
          <div style='padding:0 20px 18px'>
            <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px'>Registered Events</div>
            <table class='cc-mail-events' role='presentation' width='100%' cellpadding='0' cellspacing='0' style='font-size:13px;border-collapse:collapse;table-layout:fixed'>
              <thead><tr style='background:#f8fafc;color:#475569'>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase;width:32px'>#</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Event Category</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Events</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Team Entries</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Relay &amp; Lane</th>
                <th style='padding:6px 8px;text-align:right;font-size:11px;text-transform:uppercase;width:64px'>Fee</th>
              </tr></thead>
              <tbody>{$rowsHtml}</tbody>
            </table>
          </div>
          {$cardMessageHtml}
        </div>

        <p><a class='btn' href='{$h($cardUrl)}'>Open / Print Card</a></p>
        <p style='color:#64748b;font-size:12px'>Tip: open the link above and use your browser's Print → Save as PDF.</p>
        ";

        return $this->send($to, 'Competitor Card #' . $compNo . ' – ' . $event['name'], $body);
    }

    /**
     * Per-unit broadcast — used by the Unit-wise Competitor List
     * report to send an organiser-authored message to every athlete
     * in the unit. The greeting ("Dear {name},") and the sign-off
     * are common across the project; only $bodyHtml comes from the
     * form on the front end.
     */
    public function sendUnitBroadcast(
        string $to,
        string $athleteName,
        string $subject,
        string $bodyHtml,
        array  $event,
        array  $institution
    ): bool {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name      = $h($athleteName ?: 'Athlete');
        $eventName = $h($event['name'] ?? '');
        $instName  = $h($institution['name'] ?? '');
        // bodyHtml is the operator-supplied content. Trust it as HTML —
        // the textarea on the form lives behind the institution-admin
        // auth wall, and admins routinely paste basic formatting.
        $body = "
            <h2 style='margin-top:0'>Dear {$name},</h2>
            <div style='font-size:14px;line-height:1.55;color:#0f172a'>{$bodyHtml}</div>
            <p style='margin-top:24px'>Thanks,<br><strong>{$instName}</strong>"
            . ($eventName ? " &middot; <span style='color:#475569'>{$eventName}</span>" : '')
            . "</p>
        ";
        return $this->send($to, $subject, $body);
    }
}
