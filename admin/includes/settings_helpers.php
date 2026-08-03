<?php
// admin/includes/settings_helpers.php

function ensureSettingsSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    ensureSignatureSchema($pdo);
}

function ensureSignatureSchema($pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `authority_signatures` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(120) NOT NULL,
        `designation` varchar(120) NOT NULL,
        `signature` varchar(255) DEFAULT NULL,
        `is_default` tinyint(1) NOT NULL DEFAULT 0,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $done = true;
}

function getAuthoritySignatures($pdo, $activeOnly = false) {
    ensureSignatureSchema($pdo);
    $sql = "SELECT * FROM authority_signatures";
    if ($activeOnly) {
        $sql .= " WHERE status = 'Active'";
    }
    $sql .= " ORDER BY is_default DESC, sort_order ASC, id ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getAuthoritySignatureById($pdo, $id) {
    ensureSignatureSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM authority_signatures WHERE id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getDefaultAuthoritySignature($pdo) {
    ensureSignatureSchema($pdo);
    $row = $pdo->query("SELECT * FROM authority_signatures WHERE status = 'Active' AND is_default = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $row = $pdo->query("SELECT * FROM authority_signatures WHERE status = 'Active' ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
    return $row ?: null;
}

function saveAuthoritySignature($pdo, $id, array $data) {
    ensureSignatureSchema($pdo);
    $name = trim($data['name'] ?? '');
    $designation = trim($data['designation'] ?? '');
    $sortOrder = (int) ($data['sort_order'] ?? 0);
    $status = ($data['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    if ($id) {
        $pdo->prepare("UPDATE authority_signatures SET name = ?, designation = ?, sort_order = ?, status = ? WHERE id = ?")
            ->execute([$name, $designation, $sortOrder, $status, (int) $id]);
        if (array_key_exists('signature', $data) && $data['signature'] !== null) {
            $pdo->prepare("UPDATE authority_signatures SET signature = ? WHERE id = ?")->execute([$data['signature'], (int) $id]);
        }
        return (int) $id;
    }
    $pdo->prepare("INSERT INTO authority_signatures (name, designation, signature, sort_order, status) VALUES (?,?,?,?,?)")
        ->execute([$name, $designation, $data['signature'] ?? null, $sortOrder, $status]);
    $newId = (int) $pdo->lastInsertId();
    // First signature becomes default automatically
    $count = (int) $pdo->query("SELECT COUNT(*) FROM authority_signatures")->fetchColumn();
    if ($count === 1) {
        setDefaultAuthoritySignature($pdo, $newId);
    }
    return $newId;
}

function setDefaultAuthoritySignature($pdo, $id) {
    ensureSignatureSchema($pdo);
    $pdo->exec("UPDATE authority_signatures SET is_default = 0");
    $pdo->prepare("UPDATE authority_signatures SET is_default = 1, status = 'Active' WHERE id = ?")->execute([(int) $id]);
}

function deleteAuthoritySignature($pdo, $id) {
    $row = getAuthoritySignatureById($pdo, $id);
    if (!$row) {
        return;
    }
    if (!empty($row['signature'])) {
        deleteSchoolBrandingFile($row['signature']);
    }
    $pdo->prepare("DELETE FROM authority_signatures WHERE id = ?")->execute([(int) $id]);
    // If we removed the default, promote another active signature
    if ((int) $row['is_default'] === 1) {
        $next = $pdo->query("SELECT id FROM authority_signatures WHERE status = 'Active' ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
        if ($next) {
            setDefaultAuthoritySignature($pdo, (int) $next);
        }
    }
}

function uploadSignatureFile(array $file) {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$mime] ?? 'png';
    $filename = 'sign_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return 'uploads/signatures/' . $filename;
    }
    return false;
}

function getSetting($pdo, $key, $default = '') {
    ensureSettingsSchema($pdo);
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null && $val !== '') ? $val : $default;
}

function setSetting($pdo, $key, $value) {
    ensureSettingsSchema($pdo);
    $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    )->execute([$key, $value]);
}

function getSettingsGroup($pdo, $prefix) {
    ensureSettingsSchema($pdo);
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE ?");
    $stmt->execute([$prefix . '%']);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function saveSettingsGroup($pdo, $data, $secretKeys = []) {
    foreach ($data as $key => $value) {
        if (in_array($key, $secretKeys, true) && trim((string) $value) === '') {
            continue;
        }
        setSetting($pdo, $key, trim((string) $value));
    }
}

function getSmtpSettings($pdo) {
    return [
        'enabled'     => getSetting($pdo, 'smtp_enabled', '0'),
        'host'        => getSetting($pdo, 'smtp_host'),
        'port'        => getSetting($pdo, 'smtp_port', '587'),
        'encryption'  => getSetting($pdo, 'smtp_encryption', 'tls'),
        'username'    => getSetting($pdo, 'smtp_username'),
        'password'    => getSetting($pdo, 'smtp_password'),
        'from_email'  => getSetting($pdo, 'smtp_from_email'),
        'from_name'   => getSetting($pdo, 'smtp_from_name', 'EduDash School'),
    ];
}

function getSmsSettings($pdo) {
    return [
        'enabled'    => getSetting($pdo, 'sms_enabled', '0'),
        'provider'   => getSetting($pdo, 'sms_provider', 'MSG91'),
        'api_key'    => getSetting($pdo, 'sms_api_key'),
        'sender_id'  => getSetting($pdo, 'sms_sender_id'),
        'route'      => getSetting($pdo, 'sms_route', '4'),
        'api_url'    => getSetting($pdo, 'sms_api_url'),
    ];
}

function getWhatsAppSettings($pdo) {
    return [
        'enabled'          => getSetting($pdo, 'whatsapp_enabled', '0'),
        'provider'         => getSetting($pdo, 'whatsapp_provider', 'Meta Cloud API'),
        'api_token'        => getSetting($pdo, 'whatsapp_api_token'),
        'phone_id'         => getSetting($pdo, 'whatsapp_phone_id'),
        'business_number'  => getSetting($pdo, 'whatsapp_business_number'),
        'api_url'          => getSetting($pdo, 'whatsapp_api_url'),
    ];
}

function getSchoolProfile($pdo) {
    return [
        'name'       => getSetting($pdo, 'school_name', 'EduDash School'),
        'tagline'    => getSetting($pdo, 'school_tagline', 'Excellence in Education'),
        'address'    => getSetting($pdo, 'school_address', ''),
        'phone'      => getSetting($pdo, 'school_phone', ''),
        'email'      => getSetting($pdo, 'school_email', ''),
        'website'    => getSetting($pdo, 'school_website', ''),
        'principal'  => getSetting($pdo, 'school_principal', ''),
        'affiliation'=> getSetting($pdo, 'school_affiliation', 'CBSE'),
        'logo'       => getSetting($pdo, 'school_logo', ''),
        'logo_light' => getSetting($pdo, 'school_logo_light', ''),
        'logo_icon'  => getSetting($pdo, 'school_logo_icon', ''),
        'favicon'    => getSetting($pdo, 'school_favicon', ''),
    ];
}

function schoolBrandingUrl($relativePath, $context = 'admin') {
    if ($relativePath === '' || $relativePath === null) {
        return '';
    }
    if (preg_match('#^https?://#i', $relativePath)) {
        return $relativePath;
    }
    $path = ltrim($relativePath, '/');
    if ($context === 'portal' || $context === 'teacher') {
        return '../admin/' . $path;
    }
    return $path;
}

function schoolSidebarLogoUrl(array $school, $context = 'admin') {
    $icon = trim($school['logo_icon'] ?? '');
    if ($icon !== '') {
        return schoolBrandingUrl($icon, $context);
    }
    return schoolBrandingUrl($school['logo'] ?? '', $context);
}

function uploadSchoolBrandingFile(array $file, $type = 'logo') {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    $smallTypes = ['favicon', 'logo_icon'];
    $maxSize = in_array($type, $smallTypes, true) ? 512 * 1024 : 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/branding/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    $ext = $extMap[$mime] ?? 'png';
    $prefixMap = [
        'favicon' => 'favicon',
        'logo_light' => 'logo_light',
        'logo_icon' => 'logo_icon',
        'logo' => 'logo',
    ];
    $prefix = $prefixMap[$type] ?? 'logo';
    $filename = $prefix . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return 'uploads/branding/' . $filename;
    }
    return false;
}

function deleteSchoolBrandingFile($relativePath) {
    if ($relativePath === '' || preg_match('#^https?://#i', $relativePath)) {
        return;
    }
    $full = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

function saveSchoolProfile($pdo, array $data) {
    saveSettingsGroup($pdo, [
        'school_name'        => $data['name'] ?? '',
        'school_tagline'     => $data['tagline'] ?? '',
        'school_address'     => $data['address'] ?? '',
        'school_phone'       => $data['phone'] ?? '',
        'school_email'       => $data['email'] ?? '',
        'school_website'     => $data['website'] ?? '',
        'school_principal'   => $data['principal'] ?? '',
        'school_affiliation' => $data['affiliation'] ?? '',
    ]);
    if (array_key_exists('logo', $data)) {
        setSetting($pdo, 'school_logo', $data['logo'] ?? '');
    }
    if (array_key_exists('logo_light', $data)) {
        setSetting($pdo, 'school_logo_light', $data['logo_light'] ?? '');
    }
    if (array_key_exists('logo_icon', $data)) {
        setSetting($pdo, 'school_logo_icon', $data['logo_icon'] ?? '');
    }
    if (array_key_exists('favicon', $data)) {
        setSetting($pdo, 'school_favicon', $data['favicon'] ?? '');
    }
}

function changeAdminPassword($pdo, $adminId, $currentPassword, $newPassword, $confirmPassword) {
    $errors = [];
    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }
    if (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if (!empty($errors)) {
        return $errors;
    }

    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
    $stmt->execute([(int) $adminId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($currentPassword, $hash)) {
        return ['Current password is incorrect.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?")->execute([$newHash, (int) $adminId]);
    return [];
}

function sendSmtpEmail(array $cfg, $to, $subject, $body, &$error = null) {
    if (empty($cfg['host']) || empty($cfg['from_email'])) {
        $error = 'SMTP host and from email are required.';
        return false;
    }

    $host = $cfg['host'];
    $port = (int) ($cfg['port'] ?: 587);
    $encryption = strtolower($cfg['encryption'] ?? 'tls');
    $username = $cfg['username'] ?? '';
    $password = $cfg['password'] ?? '';
    $fromEmail = $cfg['from_email'];
    $fromName = $cfg['from_name'] ?? 'EduDash';

    $remote = ($encryption === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20);
    if (!$socket) {
        $error = "Connection failed: $errstr ($errno)";
        return false;
    }

    stream_set_timeout($socket, 20);
    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function ($command) use ($socket, $read) {
        fwrite($socket, $command . "\r\n");
        return $read();
    };

    $read();
    $ehloHost = 'localhost';
    $resp = $cmd('EHLO ' . $ehloHost);
    if (strpos($resp, '250') !== 0) {
        $error = 'EHLO failed: ' . trim($resp);
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        $resp = $cmd('STARTTLS');
        if (strpos($resp, '220') !== 0) {
            $error = 'STARTTLS failed: ' . trim($resp);
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'TLS negotiation failed.';
            fclose($socket);
            return false;
        }
        $resp = $cmd('EHLO ' . $ehloHost);
        if (strpos($resp, '250') !== 0) {
            $error = 'EHLO after TLS failed.';
            fclose($socket);
            return false;
        }
    }

    if ($username !== '') {
        $resp = $cmd('AUTH LOGIN');
        if (strpos($resp, '334') !== 0) {
            $error = 'AUTH LOGIN failed.';
            fclose($socket);
            return false;
        }
        $cmd(base64_encode($username));
        $resp = $cmd(base64_encode($password));
        if (strpos($resp, '235') !== 0) {
            $error = 'SMTP authentication failed.';
            fclose($socket);
            return false;
        }
    }

    $resp = $cmd('MAIL FROM:<' . $fromEmail . '>');
    if (strpos($resp, '250') !== 0) {
        $error = 'MAIL FROM failed.';
        fclose($socket);
        return false;
    }
    $resp = $cmd('RCPT TO:<' . $to . '>');
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        $error = 'RCPT TO failed.';
        fclose($socket);
        return false;
    }
    $resp = $cmd('DATA');
    if (strpos($resp, '354') !== 0) {
        $error = 'DATA command failed.';
        fclose($socket);
        return false;
    }

    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r'),
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    fwrite($socket, $message . "\r\n");
    $resp = $read();
    $cmd('QUIT');
    fclose($socket);

    if (strpos($resp, '250') !== 0) {
        $error = 'Message not accepted: ' . trim($resp);
        return false;
    }
    return true;
}

function dispatchSms($pdo, $mobile, $message) {
    $cfg = getSmsSettings($pdo);
    if ($cfg['enabled'] !== '1' || $cfg['api_key'] === '') {
        return ['ok' => false, 'error' => 'SMS is not configured or disabled.'];
    }

    $mobile = preg_replace('/\D/', '', $mobile);
    if (strlen($mobile) < 10) {
        return ['ok' => false, 'error' => 'Invalid mobile number.'];
    }

    $provider = $cfg['provider'];
    if ($provider === 'MSG91') {
        $url = 'https://api.msg91.com/api/sendhttp.php?' . http_build_query([
            'authkey' => $cfg['api_key'],
            'mobiles' => $mobile,
            'message' => $message,
            'sender'  => $cfg['sender_id'],
            'route'   => $cfg['route'] ?: '4',
            'country' => '91',
        ]);
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['ok' => false, 'error' => 'MSG91 request failed.'];
        }
        return ['ok' => true, 'response' => $response];
    }

    if ($provider === 'Custom' && $cfg['api_url'] !== '') {
        $url = str_replace(
            ['{mobile}', '{message}', '{sender}', '{api_key}'],
            [urlencode($mobile), urlencode($message), urlencode($cfg['sender_id']), urlencode($cfg['api_key'])],
            $cfg['api_url']
        );
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['ok' => false, 'error' => 'Custom SMS API request failed.'];
        }
        return ['ok' => true, 'response' => $response];
    }

    return ['ok' => false, 'error' => 'Unsupported SMS provider.'];
}

function dispatchWhatsApp($pdo, $mobile, $message) {
    $cfg = getWhatsAppSettings($pdo);
    if ($cfg['enabled'] !== '1' || $cfg['api_token'] === '') {
        return ['ok' => false, 'error' => 'WhatsApp is not configured or disabled.'];
    }

    $mobile = preg_replace('/\D/', '', $mobile);
    if (strlen($mobile) === 10) {
        $mobile = '91' . $mobile;
    }

    if ($cfg['provider'] === 'Meta Cloud API') {
        if ($cfg['phone_id'] === '') {
            return ['ok' => false, 'error' => 'WhatsApp Phone Number ID is required.'];
        }
        $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($cfg['phone_id']) . '/messages';
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $mobile,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $message],
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . $cfg['api_token'] . "\r\n",
                'content' => $payload,
                'timeout' => 20,
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return ['ok' => false, 'error' => 'WhatsApp API request failed.'];
        }
        $json = json_decode($response, true);
        if (isset($json['error'])) {
            return ['ok' => false, 'error' => $json['error']['message'] ?? 'WhatsApp API error.'];
        }
        return ['ok' => true, 'response' => $response];
    }

    if ($cfg['provider'] === 'Custom' && $cfg['api_url'] !== '') {
        $url = str_replace(
            ['{mobile}', '{message}', '{token}'],
            [urlencode($mobile), urlencode($message), urlencode($cfg['api_token'])],
            $cfg['api_url']
        );
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['ok' => false, 'error' => 'Custom WhatsApp API request failed.'];
        }
        return ['ok' => true, 'response' => $response];
    }

    return ['ok' => false, 'error' => 'Unsupported WhatsApp provider.'];
}

function sendEmailViaSettings($pdo, $to, $subject, $body, &$error = null) {
    $cfg = getSmtpSettings($pdo);
    if ($cfg['enabled'] !== '1') {
        $error = 'SMTP is disabled in settings.';
        return false;
    }
    return sendSmtpEmail($cfg, $to, $subject, $body, $error);
}
