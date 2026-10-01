<?php
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($key, $message = null) {
    if ($message === null) {
        if (!empty($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        return null;
    }
    $_SESSION['flash'][$key] = $message;
}

function require_login() {
    if (empty($_SESSION['user'])) {
        redirect('login');
    }
}

function require_role($roles = []) {
    if (empty($_SESSION['user'])) {
        redirect('login');
    }
    if (!in_array($_SESSION['user']['role'], (array)$roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function get_branch_filter($tableAlias = '') {
    $user = current_user();
    if (!$user || empty($user['branch_id'])) {
        return ''; // Central roles (NULL branch_id) get no filter
    }
    
    $prefix = $tableAlias ? $tableAlias . '.' : '';
    return " AND {$prefix}branch_id = " . (int)$user['branch_id'];
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function log_activity($pdo, $userId, $action, $details = null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        // Silently ignore log insertion errors (e.g. invalid user_id during reset)
    }
}

function send_email($to, $subject, $body) {
    global $pdo; // Require global PDO to fetch settings
    
    // Include PHPMailer
    require_once __DIR__ . '/includes/PHPMailer/Exception.php';
    require_once __DIR__ . '/includes/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/includes/PHPMailer/SMTP.php';
    
    $smtpHost = get_setting($pdo, 'smtp_host', '');
    
    if (empty($smtpHost)) {
        // Fallback to basic mail() if SMTP is not configured
        $headers = "From: noreply@cafehrms.local\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $body, $headers);
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = get_setting($pdo, 'smtp_host', '');
        $mail->SMTPAuth   = true;
        $mail->Username   = get_setting($pdo, 'smtp_user', '');
        $mail->Password   = get_setting($pdo, 'smtp_pass', '');
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = get_setting($pdo, 'smtp_port', 587);
        
        $fromEmail = get_setting($pdo, 'smtp_from_email', 'noreply@cafehrms.local');
        $fromName  = get_setting($pdo, 'smtp_from_name', 'Cafe HRMS');
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error or silently fail
        return false;
    }
}

function get_setting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function get_settings($pdo) {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $res = [];
    while ($row = $stmt->fetch()) {
        $res[$row['setting_key']] = $row['setting_value'];
    }
    return $res;
}

function notify_roles($pdo, $roles, $message, $link = null, $icon = 'fa-bell', $color = 'primary') {
    if (empty($roles)) return;
    $targetRoles = implode(',', $roles);
    $insert = $pdo->prepare("INSERT INTO global_notifications (message, link, icon, color, target_roles) VALUES (?, ?, ?, ?, ?)");
    $insert->execute([$message, $link, $icon, $color, $targetRoles]);
}

function notify_user($pdo, $userId, $message, $link = null, $icon = 'fa-bell', $color = 'primary') {
    $insert = $pdo->prepare("INSERT INTO global_notifications (message, link, icon, color, target_user_id) VALUES (?, ?, ?, ?, ?)");
    $insert->execute([$message, $link, $icon, $color, $userId]);
}

function set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$key, $value]);
}
