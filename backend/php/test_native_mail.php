<?php
echo "STEP 1 OK\n";

$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== 'crm-mail-test-2026') {
    echo "STEP 2: Missing or wrong token.\n";
    echo "Use: test_native_mail.php?token=crm-mail-test-2026\n";
    exit;
}

echo "STEP 2 token OK\n";

require_once __DIR__ . '/mailer.php';

$fromEmail = MAIL_FROM;
$fromName  = MAIL_FROM_NAME;
$toEmail   = getenv('MAIL_TEST_TO') ?: 'ihebchebbidev@gmail.com';
$subject   = 'Test mail TTshop CRM ' . date('Y-m-d H:i:s');
$html      = '<p>Test email from <strong>TTshop CRM</strong>.</p><p>PHP ' . htmlspecialchars(phpversion()) . '</p>';
$text      = "Test email from TTshop CRM.\nPHP " . phpversion() . "\n";

echo "STEP 3 sending to {$toEmail} via " . MAIL_TRANSPORT . "...\n";
try {
    crm_mail_send($toEmail, 'Test', $subject, $html, $text);
    echo "STEP 4 sent OK\n";
} catch (Throwable $e) {
    echo "STEP 4 ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Check inbox and spam. sendmail_path=" . ini_get('sendmail_path') . "\n";
