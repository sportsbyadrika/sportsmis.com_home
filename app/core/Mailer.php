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
        $qrSrc   = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=' . rawurlencode($cardUrl);

        $compNo = str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT);
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $age = !empty($athlete['date_of_birth'])
            ? ' · ' . (int)(new \DateTime($athlete['date_of_birth']))->diff(new \DateTime())->y . ' yrs'
            : '';

        $rowsHtml = '';
        foreach ($items as $i => $it) {
            $n   = $i + 1;
            $sp  = $h($it['sport_name'] ?? '');
            $cd  = $h($it['event_code'] ?? '');
            $ev  = $h($it['sport_event_name'] ?? $it['category'] ?? '');
            $fee = '₹' . number_format((float)$it['fee'], 2);
            $rowsHtml .= "<tr>"
                       . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0'>{$n}</td>"
                       . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0'>{$sp}</td>"
                       . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;font-family:monospace'>{$cd}</td>"
                       . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0'>{$ev}</td>"
                       . "<td style='padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right'>{$fee}</td>"
                       . "</tr>";
        }
        if ($rowsHtml === '') {
            $rowsHtml = "<tr><td colspan='5' style='padding:8px;color:#64748b'>No events</td></tr>";
        }

        $photoHtml = !empty($athlete['passport_photo'])
            ? "<img src='" . $h($base . $athlete['passport_photo']) . "' width='140' height='140'"
              . " style='object-fit:cover;border-radius:12px;border:3px solid #0b1f3a'>"
            : "<div style='width:140px;height:140px;border-radius:12px;background:#e2e8f0;text-align:center;line-height:140px;font-size:42px;font-weight:700;color:#475569'>"
              . $h(strtoupper(substr($athlete['name'] ?? 'A', 0, 1))) . "</div>";

        $instLogo = !empty($institution['logo'])
            ? "<img src='" . $h($base . $institution['logo']) . "' width='48' height='48' style='object-fit:contain;background:#fff;border-radius:8px;padding:3px'>"
            : "<div style='width:48px;height:48px;border-radius:8px;background:rgba(255,255,255,.15);text-align:center;line-height:48px;font-weight:700;color:#fff'>"
              . $h(strtoupper(substr($institution['name'] ?? 'I', 0, 1))) . "</div>";

        $name = $h($athlete['name'] ?? '');
        $eventName = $h($event['name'] ?? '');
        $eventDates = $h(date('d M Y', strtotime($event['event_date_from'])) . ' – ' . date('d M Y', strtotime($event['event_date_to'])));
        $venue = $h($event['location'] ?? '');
        $instName = $h($institution['name'] ?? '');
        $gender = $h(ucfirst($athlete['gender'] ?? ''));
        $mobile = $h($athlete['mobile'] ?? '');

        $body = "
        <h2>Hello {$name},</h2>
        <p>Your registration for <strong>{$eventName}</strong> has been <strong>approved</strong>.
           Your competitor card is below — keep it handy for the venue. You can also
           <a href='{$h($cardUrl)}'>view / print it online</a> at any time.</p>

        <div style='background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin:16px 0'>
          <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#0b1f3a;color:#fff'>
            <tr>
              <td style='padding:18px 20px;width:60px;vertical-align:middle'>{$instLogo}</td>
              <td style='padding:18px 20px;vertical-align:middle'>
                <div style='font-weight:700;font-size:15px'>{$instName}</div>
                <div style='opacity:.85;font-size:13px'>{$eventName} · {$eventDates}</div>
              </td>
            </tr>
          </table>
          <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
            <tr>
              <td style='padding:18px 20px;vertical-align:top'>
                <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px'>Competitor</div>
                <div style='font-size:13px;line-height:1.7'>
                  <strong>Name:</strong> {$name}<br>
                  <strong>Gender / Age:</strong> {$gender}{$age}<br>
                  <strong>Mobile:</strong> {$mobile}<br>
                  <strong>Venue:</strong> {$venue}
                </div>
              </td>
              <td style='padding:18px 20px;vertical-align:top;width:180px;text-align:center'>
                {$photoHtml}
                <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-top:10px'>Competitor No.</div>
                <div style='font-size:32px;font-weight:800;color:#0b1f3a;letter-spacing:1px'>{$compNo}</div>
                <img src='{$h($qrSrc)}' width='110' height='110' alt='QR' style='display:block;margin:8px auto 0'>
                <div style='font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;margin-top:4px'>Scan to verify</div>
              </td>
            </tr>
          </table>
          <div style='padding:0 20px 18px'>
            <div style='font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px'>Registered Events</div>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='font-size:13px;border-collapse:collapse'>
              <thead><tr style='background:#f8fafc;color:#475569'>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>#</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Sport</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Code</th>
                <th style='padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase'>Event</th>
                <th style='padding:6px 8px;text-align:right;font-size:11px;text-transform:uppercase'>Fee</th>
              </tr></thead>
              <tbody>{$rowsHtml}</tbody>
            </table>
          </div>
        </div>

        <p><a class='btn' href='{$h($cardUrl)}'>Open / Print Card</a></p>
        <p style='color:#64748b;font-size:12px'>Tip: open the link above and use your browser's Print → Save as PDF.</p>
        ";

        return $this->send($to, 'Competitor Card #' . $compNo . ' – ' . $event['name'], $body);
    }
}
