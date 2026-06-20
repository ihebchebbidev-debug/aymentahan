<?php
echo "STEP 1 OK\n";

$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== 'crm-mail-test-2026') {
    echo "STEP 2: Missing or wrong token.\n";
    echo "Use: test_native_mail.php?token=crm-mail-test-2026\n";
    exit;
}

echo "STEP 2 token OK\n";

$fromEmail = 'direction@animacom.com.tn';
$fromName  = 'CRM AnimaCom';
$toEmail   = 'ihebchebbidev@gmail.com';
$subject   = 'Test mail CRM ' . date('Y-m-d H:i:s');
$message   = "Test PHP mail() from server.\nPHP " . phpversion() . "\n";

$headers = "From: {$fromName} <{$fromEmail}>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (!function_exists('mail')) {
    echo "STEP 3 ERROR: mail() is disabled.\n";
    exit;
}

echo "STEP 3 sending to {$toEmail}...\n";
$ok = mail($toEmail, $subject, $message, $headers);
echo $ok ? "STEP 4 mail() returned TRUE\n" : "STEP 4 mail() returned FALSE\n";
echo "Check inbox and spam. sendmail_path=" . ini_get('sendmail_path') . "\n";
