WHITE PAGE = wrong URL or PHP not running in this folder.

Upload ALL files in backend/php/ including .htaccess and ping.txt

Test IN ORDER (copy exact URLs, change domain if needed):

1) Static file (no PHP):
   https://crm.ttshop.pro/code_source/backend/php/ping.txt
   MUST show: STATIC OK

2) Minimal PHP:
   https://crm.ttshop.pro/code_source/backend/php/test_php_ping.php
   MUST show: PHP OK 8.x

3) API health (already on server):
   https://crm.ttshop.pro/code_source/backend/php/health.php
   MUST show: JSON {"ok":...}

4) Mail test:
   https://crm.ttshop.pro/code_source/backend/php/test_native_mail.php?token=crm-mail-test-2026

If (1) is white or 404: files not uploaded to /code_source/backend/php/
If (1) works but (2) is white: PHP broken or .htaccess missing on server
If (2) works but (4) no email: mail() issue only — use SMTP in mailer.php

View page source (Ctrl+U): if you see "<?php echo" as text, PHP is NOT executed.
