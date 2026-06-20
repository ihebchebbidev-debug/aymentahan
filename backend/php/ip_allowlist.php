<?php
/**
 * OTP IP allowlist + optional admin BCC list (crminternet_settings, scope=global).
 *
 * Keys:
 *   otp_ip_allowlist      - JSON array: "1.2.3.4", "1.2.3.0/24", "1.2.3.10-1.2.3.20"
 *   otp_admin_copy_emails - JSON array of admin emails (also read by admin_copy_emails in config.php)
 */

function ip_allowlist_setting_json(PDO $db, string $key, $default)
{
    try {
        $s = $db->prepare(
            'SELECT value FROM crminternet_settings WHERE scope = "global" AND setting_key = :k LIMIT 1'
        );
        $s->execute(array(':k' => $key));
        $v = $s->fetchColumn();
        if ($v === false) {
            return $default;
        }
        $d = json_decode((string) $v, true);
        return $d === null ? $default : $d;
    } catch (Throwable $e) {
        return $default;
    }
}

function ip_allowlist_client_ip()
{
    if (function_exists('client_ip')) {
        $ip = client_ip();
        return ($ip !== '' && $ip !== '0.0.0.0') ? $ip : '';
    }
    $headers = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
    foreach ($headers as $k) {
        if (empty($_SERVER[$k])) {
            continue;
        }
        $raw = (string) $_SERVER[$k];
        $comma = strpos($raw, ',');
        $ip = trim($comma === false ? $raw : substr($raw, 0, $comma));
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '';
}

function ip_matches_rule($ip, $rule)
{
    $rule = trim((string) $rule);
    $ip = (string) $ip;
    if ($rule === '' || $ip === '') {
        return false;
    }

    // Range a.b.c.d-e.f.g.h
    if (strpos($rule, '-') !== false) {
        $parts = explode('-', $rule, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $a = trim($parts[0]);
        $b = trim($parts[1]);
        if (
            !filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || !filter_var($b, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            return false;
        }
        $ipL = ip2long($ip);
        $aL = ip2long($a);
        $bL = ip2long($b);
        if ($ipL === false || $aL === false || $bL === false) {
            return false;
        }
        return $ipL >= min($aL, $bL) && $ipL <= max($aL, $bL);
    }

    // CIDR a.b.c.d/n
    if (strpos($rule, '/') !== false) {
        $parts = explode('/', $rule, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $subnet = $parts[0];
        $bits = (int) $parts[1];

        if (strpos($ip, ':') !== false || strpos($subnet, ':') !== false) {
            $ipBin = @inet_pton($ip);
            $netBin = @inet_pton($subnet);
            if (!$ipBin || !$netBin || $bits < 0 || $bits > 128) {
                return false;
            }
            $bytes = (int) floor($bits / 8);
            $rem = $bits % 8;
            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
                return false;
            }
            if ($rem === 0) {
                return true;
            }
            $mask = chr((0xff << (8 - $rem)) & 0xff);
            return (ord($ipBin[$bytes]) & ord($mask)) === (ord($netBin[$bytes]) & ord($mask));
        }

        if ($bits < 0 || $bits > 32) {
            return false;
        }
        $ipL = ip2long($ip);
        $netL = ip2long($subnet);
        if ($ipL === false || $netL === false) {
            return false;
        }
        if ($bits === 0) {
            $mask = 0;
        } else {
            $mask = ~((1 << (32 - $bits)) - 1);
        }
        return ($ipL & $mask) === ($netL & $mask);
    }

    return $ip === $rule;
}

function ip_is_allowlisted(PDO $db, $ip)
{
    if ($ip === '' || $ip === null || $ip === '0.0.0.0') {
        return false;
    }
    $rules = ip_allowlist_setting_json($db, 'otp_ip_allowlist', array());
    if (!is_array($rules) || count($rules) === 0) {
        return false;
    }
    foreach ($rules as $rule) {
        if (is_string($rule) && ip_matches_rule($ip, $rule)) {
            return true;
        }
    }
    return false;
}

function otp_admin_copy_emails(PDO $db)
{
    $list = ip_allowlist_setting_json($db, 'otp_admin_copy_emails', array());
    if (!is_array($list)) {
        return array();
    }
    $out = array();
    foreach ($list as $e) {
        $email = trim((string) $e);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[] = $email;
        }
    }
    return $out;
}
