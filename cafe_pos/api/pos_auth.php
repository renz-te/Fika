<?php
// POS Authentication API connecting to HRMS
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$hrmsHost = '127.0.0.1';
$hrmsDb = 'hrms';
$hrmsUser = 'root';
$hrmsPass = '';

$posDb = 'cafe_pos';

try {
    $pdo = new PDO("mysql:host=$hrmsHost;dbname=$hrmsDb;charset=utf8mb4", $hrmsUser, $hrmsPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
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
            echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
