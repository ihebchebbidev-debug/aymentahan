<?php
/**
 * CRM mailer — native PHP mail() (hosting) or SMTP (optional).
 *
 * Env:
 *   MAIL_TRANSPORT     = native | smtp   (default: native)
 *   MAIL_FROM          = direction@animacom.com.tn
 *   MAIL_FROM_NAME     = CRM AnimaCom
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE, SMTP_FROM, SMTP_FROM_NAME
 */

defined('MAIL_TRANSPORT') or define(
    'MAIL_TRANSPORT',
    strtolower(trim((string) (getenv('MAIL_TRANSPORT') ?: 'native')))
);

defined('MAIL_FROM') or define(
    'MAIL_FROM',
    trim((string) (getenv('MAIL_FROM') ?: getenv('SMTP_FROM') ?: 'direction@animacom.com.tn'))
);

defined('MAIL_FROM_NAME') or define(
    'MAIL_FROM_NAME',
    trim((string) (getenv('MAIL_FROM_NAME') ?: getenv('SMTP_FROM_NAME') ?: 'CRM AnimaCom'))
);

defined('SMTP_HOST') or define('SMTP_HOST', getenv('SMTP_HOST') ?: 'ssl0.ovh.net');
defined('SMTP_PORT') or define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 465));
defined('SMTP_SECURE') or define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
defined('SMTP_USER') or define('SMTP_USER', getenv('SMTP_USER') ?: '');
defined('SMTP_PASS') or define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
defined('SMTP_FROM') or define('SMTP_FROM', getenv('SMTP_FROM') ?: (SMTP_USER !== '' ? SMTP_USER : MAIL_FROM));
defined('SMTP_FROM_NAME') or define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: MAIL_FROM_NAME);

class SmtpException extends RuntimeException {}

class MailException extends RuntimeException {}

/** Send HTML + plain email (OTP and notifications). Uses native mail() by default. */
function crm_mail_send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
{
    if (MAIL_TRANSPORT === 'smtp') {
        smtp_send_raw($toEmail, $toName, $subject, $htmlBody, $textBody);
        return;
    }
    native_mail_send($toEmail, $toName, $subject, $htmlBody, $textBody);
}

/** @deprecated Use crm_mail_send(); kept so existing call sites work. */
function smtp_send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
{
    crm_mail_send($toEmail, $toName, $subject, $htmlBody, $textBody);
}

function native_mail_send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
{
    if (!function_exists('mail')) {
        throw new MailException('PHP mail() is disabled on this server');
    }

    $from = MAIL_FROM;
    $fname = MAIL_FROM_NAME;
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new MailException('MAIL_FROM is not configured');
    }
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new MailException('Invalid recipient email');
    }

    $text = $textBody !== '' ? $textBody : strip_tags($htmlBody);
    $boundary = 'b_' . bin2hex(random_bytes(8));
    $encodedSubject = mail_encode_header($subject);

    $headers = 'From: ' . mail_encode_header($fname) . " <{$from}>\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

    $body = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    $ok = @mail($toEmail, $encodedSubject, $body, $headers);
    if (!$ok) {
        throw new MailException('mail() failed to send message');
    }
}

function smtp_send_raw(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
{
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $secure = strtolower(SMTP_SECURE);
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = SMTP_FROM;
    $fname = SMTP_FROM_NAME;

    if ($user === '' || $pass === '') {
        throw new SmtpException('SMTP_USER and SMTP_PASS must be set when MAIL_TRANSPORT=smtp');
    }

    $hostPrefix = ($secure === 'ssl') ? 'ssl://' : '';
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($hostPrefix . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        throw new SmtpException("SMTP connect: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $send = function (string $cmd, int $expect) use ($fp, $read) {
        fwrite($fp, $cmd . "\r\n");
        $resp = $read();
        if ((int) substr($resp, 0, 3) !== $expect) {
            throw new SmtpException("SMTP error after '$cmd': " . trim($resp));
        }
        return $resp;
    };

    $read();
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    fwrite($fp, $ehlo . "\r\n");
    $read();

    if ($secure === 'tls') {
        $send('STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new SmtpException('STARTTLS failed');
        }
        fwrite($fp, $ehlo . "\r\n");
        $read();
    }

    $send('AUTH LOGIN', 334);
    $send(base64_encode($user), 334);
    $send(base64_encode($pass), 235);

    $send("MAIL FROM:<$from>", 250);
    $send("RCPT TO:<$toEmail>", 250);
    $send('DATA', 354);

    $boundary = 'b_' . bin2hex(random_bytes(8));
    $headers = 'From: ' . mail_encode_header($fname) . " <$from>\r\n";
    $headers .= 'To: ' . mail_encode_header($toName) . " <$toEmail>\r\n";
    $headers .= 'Subject: ' . mail_encode_header($subject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $text = $textBody !== '' ? $textBody : strip_tags($htmlBody);
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--$boundary--\r\n";

    $payload = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
    fwrite($fp, $payload . "\r\n.\r\n");
    $resp = $read();
    if ((int) substr($resp, 0, 3) !== 250) {
        throw new SmtpException('SMTP DATA reject: ' . trim($resp));
    }
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

function mail_encode_header(string $s): string
{
    if (preg_match('/[^\x20-\x7e]/', $s)) {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
    return $s;
}

/** @return array{0:string,1:string,2:string} */
function build_otp_email(string $code, string $fullName): array
{
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $subject = "Votre code de connexion CRM : $code";
    $digits = str_split($code);
    $cells = '';
    foreach ($digits as $d) {
        $cells .= '<td style="padding:0 6px;"><div style="width:54px;height:62px;border-radius:10px;background:#0f172a;color:#ffffff;font-family:\'Segoe UI\',Roboto,Arial,sans-serif;font-size:30px;font-weight:700;line-height:62px;text-align:center;letter-spacing:2px;">' . $d . '</div></td>';
    }
    $html = '<!doctype html><html lang="fr"><body style="margin:0;background:#f3f4f6;font-family:\'Segoe UI\',Roboto,Arial,sans-serif;color:#111827;">'
        . '<div style="max-width:520px;margin:32px auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(15,23,42,.08);">'
        . '<div style="background:linear-gradient(135deg,#2563eb,#7c3aed);padding:24px 28px;color:#fff;">'
        . '<div style="font-size:12px;letter-spacing:3px;opacity:.85;text-transform:uppercase;">CRM</div>'
        . '<div style="font-size:22px;font-weight:600;margin-top:4px;">Code de vérification</div>'
        . '</div>'
        . '<div style="padding:28px;">'
        . '<p style="margin:0 0 14px;font-size:15px;">Bonjour <strong>' . $safeName . '</strong>,</p>'
        . '<p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.55;">Vous tentez de vous connecter au CRM. Pour finaliser votre connexion, saisissez le code de vérification ci-dessous :</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px auto 18px;"><tr>' . $cells . '</tr></table>'
        . '<p style="margin:0 0 8px;font-size:13px;color:#6b7280;text-align:center;">Ce code est valable <strong>10 minutes</strong> et ne peut être utilisé qu\'une seule fois.</p>'
        . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:22px 0;">'
        . '<p style="margin:0;font-size:12px;color:#6b7280;line-height:1.55;">Si vous n\'êtes pas à l\'origine de cette tentative, ignorez ce message et changez immédiatement votre mot de passe.</p>'
        . '</div>'
        . '<div style="padding:16px 28px;background:#f9fafb;color:#9ca3af;font-size:11px;text-align:center;">&copy; ' . date('Y') . ' CRM &mdash; message automatique, ne pas répondre.</div>'
        . '</div></body></html>';
    $text = "Bonjour $fullName,\n\nVotre code de vérification CRM est : $code\nIl est valable 10 minutes.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.";
    return array($subject, $html, $text);
}
