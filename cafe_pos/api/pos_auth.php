<?php
// POS Authentication API connecting to HRMS
header('Content-Type: application/json');

if (!class_exists('Config')) {
    class Config {
        public static function get(string $key, mixed $default = null): mixed {
            $envFile = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v, " \t\n\r\0\x0B\"'");
                        putenv("{$k}={$v}");
                        $_ENV[$k] = $v;
                        $_SERVER[$k] = $v;
                    }
                }
            }

            $envKey = strtoupper(str_replace('.', '_', $key));
            $envVal = getenv($envKey);
            if ($envVal !== false && $envVal !== '') {
                return $envVal;
            }

            $parts = explode('.', $key);
            $file = $parts[0];
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            if (!file_exists($path)) {
                $configPath = dirname(__DIR__, 2) . '/config/config.php';
                if (file_exists($configPath)) {
                    $config = require $configPath;
                    $curr = $config;
                    foreach ($parts as $p) {
                        if (is_array($curr) && array_key_exists($p, $curr)) {
                            $curr = $curr[$p];
                        } else {
                            return $default;
                        }
                    }
                    return $curr;
                }
                return $default;
            }

            $config = require $path;
            array_shift($parts);
            $curr = $config;
            foreach ($parts as $p) {
                if (is_array($curr) && array_key_exists($p, $curr)) {
                    $curr = $curr[$p];
                } else {
                    return $default;
                }
            }
            return $curr;
        }
    }
}

$hrmsHost = Config::get('db.host', '127.0.0.1');
$hrmsDb   = Config::get('db.hrms_name', 'hrms');
$hrmsUser = Config::get('db.user', 'root');
$hrmsPass = Config::get('db.pass', '');

$posDb = 'cafe_pos';

try {
    $pdo = new PDO("mysql:host={$hrmsHost};dbname={$hrmsDb};charset=utf8mb4", $hrmsUser, $hrmsPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_active_baristas') {
        // Fetch users who are currently clocked in (time_in today, no time_out)
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.username, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.position 
            FROM attendance a 
            JOIN employees e ON a.employee_id = e.id 
            JOIN users u ON u.employee_id = e.id
            WHERE DATE(a.time_in) = CURDATE() 
            AND a.time_out IS NULL 
            AND (e.position LIKE '%Barista%' OR e.position LIKE '%Trainee%')
        ");
        $stmt->execute();
        $baristas = $stmt->fetchAll();
        echo json_encode(['success' => true, 'baristas' => $baristas]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($action === 'verify_pin') {
        // Shared terminal quick unlock via password
        $userId = $data['user_id'] ?? null;
        $password = $data['password'] ?? '';

        if (!$userId || !$password) {
            echo json_encode(['success' => false, 'error' => 'Missing credentials']);
            exit;
        }

        if (is_numeric($userId)) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.password, u.role_id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.position 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id 
                WHERE u.id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT u.id, u.password, u.role_id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.position 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id 
                WHERE u.username = ?
            ");
        }
        
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'] ?: 'HB User',
                    'role_id' => $user['role_id'],
                    'position' => $user['position']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        }
        exit;
    }

    if ($action === 'login_head_barista') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (!$username || !$password) {
            echo json_encode(['success' => false, 'error' => 'Missing credentials']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.password, u.role_id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.position 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id 
                WHERE u.username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if user has position Head Barista
                if (stripos($user['position'], 'Head Barista') === false && $user['role_id'] != 1) { // 1 = Admin as fallback
                    echo json_encode(['success' => false, 'error' => 'Only Head Baristas can manage shifts.']);
                    exit;
                }

                // Check if this HB already has an open shift on ANY terminal
                $stmt2 = $pdo->prepare("SELECT id FROM cash_sessions WHERE cashier_id = ? AND status = 'OPEN'");
                $stmt2->execute([$user['id']]);
                $openShift = $stmt2->fetch();

                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['full_name'] ?: $username,
                        'has_open_shift' => $openShift ? true : false
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            }
        } catch (PDOException $e) {
            error_log("DB Error in pos_auth: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error occurred']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
