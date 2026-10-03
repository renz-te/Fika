<?php
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function encryptData($data) {
    if (empty($data)) return $data;
    $method = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($data, $method, APP_KEY, 0, $iv);
    // Prepend the raw IV to the encrypted data, then Base64 encode the whole string
    return base64_encode($iv . $encrypted);
}

function decryptData($data) {
    if (empty($data)) return $data;
    $method = 'aes-256-cbc';
    $decoded = base64_decode($data);
    
    // If it happens to be the old format with ::, let's just fall back to splitting it so existing valid data doesn't break?
    // Actually the prompt says "Refactor the functions to use fixed-length IV byte slicing", let's strictly follow it.
    
    $ivLength = openssl_cipher_iv_length($method);
    
    // Extract the exact IV length from the start of the string
    $iv = substr($decoded, 0, $ivLength);
    // Extract the ciphertext starting immediately after the IV
    $ciphertext = substr($decoded, $ivLength);
    
    return openssl_decrypt($ciphertext, $method, APP_KEY, 0, $iv);
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

// ============================================================
// Philippine Statutory Contribution Helpers
// ============================================================

/**
 * Calculate SSS contribution based on Monthly Salary Credit (MSC) brackets.
 * Uses the contribution_rates table for dynamic bracket lookup.
 * Returns ['employee' => float, 'employer' => float]
 */
function calculate_sss($pdo, $monthlyGross) {
    if ($monthlyGross <= 0) return ['employee' => 0.0, 'employer' => 0.0];
    
    $stmt = $pdo->prepare("
        SELECT base_amount, employee_rate, employer_rate
        FROM contribution_rates
        WHERE type = 'SSS'
          AND ? BETWEEN min_salary AND max_salary
          AND effective_date <= CURDATE()
        ORDER BY effective_date DESC
        LIMIT 1
    ");
    $stmt->execute([$monthlyGross]);
    $bracket = $stmt->fetch();

    if (!$bracket) {
        $minCheck = $pdo->query("SELECT min_salary FROM contribution_rates WHERE type = 'SSS' ORDER BY min_salary ASC LIMIT 1")->fetchColumn();
        if ($minCheck !== false && $monthlyGross < $minCheck) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }

        // Fallback to max bracket if salary exceeds all ranges
        $fallback = $pdo->query("
            SELECT base_amount, employee_rate, employer_rate
            FROM contribution_rates
            WHERE type = 'SSS'
            ORDER BY max_salary DESC
            LIMIT 1
        ")->fetch();
        $bracket = $fallback ?: ['base_amount' => 30000, 'employee_rate' => 0.045, 'employer_rate' => 0.095];
    }

    $msc = (float)$bracket['base_amount'];
    return [
        'employee' => round($msc * (float)$bracket['employee_rate'], 2),
        'employer' => round($msc * (float)$bracket['employer_rate'], 2),
    ];
}

/**
 * Calculate PhilHealth contribution.
 * 2025 rules: 5% total (2.5% each), floor ₱10,000, ceiling ₱100,000.
 * Returns ['employee' => float, 'employer' => float]
 */
function calculate_philhealth($monthlyGross) {
    if ($monthlyGross <= 0) return ['employee' => 0.0, 'employer' => 0.0];
    
    $floor = 10000.00;
    $ceiling = 100000.00;
    $rate = 0.05; // 5% total

    $base = max($floor, min($ceiling, $monthlyGross));
    $total = $base * $rate;

    return [
        'employee' => round($total / 2, 2),
        'employer' => round($total / 2, 2),
    ];
}

/**
 * Calculate Pag-IBIG contribution.
 * Employee 2%, Employer 2%, max base ₱10,000 => max ₱200/month each.
 * Returns ['employee' => float, 'employer' => float]
 */
function calculate_pagibig($monthlyGross) {
    if ($monthlyGross <= 0) return ['employee' => 0.0, 'employer' => 0.0];
    
    $maxBase = 10000.00;
    $rate = 0.02;

    $base = min($maxBase, $monthlyGross);

    return [
        'employee' => round($base * $rate, 2),
        'employer' => round($base * $rate, 2),
    ];
}

/**
 * Calculate BIR Withholding Tax (semi-monthly basis).
 * Standard 2023+ TRAIN Law brackets applied to semi-monthly taxable income.
 * $taxableIncome = grossPay - sss_ee - philhealth_ee - pagibig_ee - absences/tardiness
 */
function calculate_withholding_tax_semimonthly($taxableIncome) {
    // BIR semi-monthly brackets (TRAIN Law, effective 2023+)
    // Bracket: [threshold, base_tax, excess_rate]
    $brackets = [
        [0,        0,        0.00],   // ₱0 - ₱10,417: exempt
        [10417,    0,        0.15],   // Over ₱10,417 - ₱16,667
        [16667,    937.50,   0.20],   // Over ₱16,667 - ₱33,333
        [33333,    4270.83,  0.25],   // Over ₱33,333 - ₱83,333
        [83333,    16770.83, 0.30],   // Over ₱83,333 - ₱333,333
        [333333,   91770.83, 0.35],   // Over ₱333,333
    ];

    if ($taxableIncome <= $brackets[0][0]) return 0.00;

    $tax = 0.00;
    for ($i = count($brackets) - 1; $i >= 0; $i--) {
        if ($taxableIncome > $brackets[$i][0]) {
            $tax = $brackets[$i][1] + (($taxableIncome - $brackets[$i][0]) * $brackets[$i][2]);
            break;
        }
    }

    return round(max(0, $tax), 2);
}
