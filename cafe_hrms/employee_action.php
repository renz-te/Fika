<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Super Admin', 'HR Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        flash('error', 'Invalid request.');
        redirect('employees');
    }
    $id = $_POST['id'] ?? null;
    $employeeId = trim($_POST['employee_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $employmentType = trim($_POST['employment_type'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $dateHired = $_POST['date_hired'] ?? null;
    $birthday = $_POST['birthday'] ?? null;
    $salaryRate = $_POST['hourly_rate'] ?? 0;
    $address = trim($_POST['address'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $bankAccount = encryptData(trim($_POST['bank_account'] ?? ''));
    $tin = encryptData(trim($_POST['tin'] ?? ''));
    $sss = encryptData(trim($_POST['sss'] ?? ''));
    $philhealth = encryptData(trim($_POST['philhealth'] ?? ''));
    $pagibig = encryptData(trim($_POST['pagibig'] ?? ''));
    $governmentIds = encryptData(trim($_POST['government_ids'] ?? ''));
    $photoPath = null;

    if (!empty($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/uploads/photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('emp_', true) . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
            $photoPath = 'uploads/photos/' . $filename;
        }
    }

    if ($id) {
        $fields = 'employee_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, department = ?, employment_type = ?, status = ?, date_hired = ?, birthday = ?, hourly_rate = ?, address = ?, emergency_contact = ?, bank_account = ?, tin = ?, sss = ?, philhealth = ?, pagibig = ?, government_ids = ?';
        $params = [$employeeId, $first_name, $last_name, $email, $phone, $position, $department, $employmentType, $status, $dateHired, $birthday, $salaryRate, $address, $emergencyContact, $bankAccount, $tin, $sss, $philhealth, $pagibig, $governmentIds, $id];
        if ($photoPath) {
            $fields .= ', photo = ?';
            array_splice($params, -1, 0, $photoPath);
        }
        $stmt = $pdo->prepare('UPDATE employees SET ' . $fields . ' WHERE id = ?');
        $stmt->execute($params);
        log_activity($pdo, $_SESSION['user']['id'], 'update_employee', "Updated employee {$first_name} {$last_name}");
        flash('success', 'Employee details updated successfully.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO employees (employee_id, first_name, last_name, email, phone, position, department, employment_type, status, date_hired, birthday, hourly_rate, address, emergency_contact, bank_account, tin, sss, philhealth, pagibig, government_ids, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$employeeId, $first_name, $last_name, $email, $phone, $position, $department, $employmentType, $status, $dateHired, $birthday, $salaryRate, $address, $emergencyContact, $bankAccount, $tin, $sss, $philhealth, $pagibig, $governmentIds, $photoPath]);
        
        // Auto-create user account
        $username = strtolower(trim($first_name) . '.' . trim($last_name));
        // Ensure uniqueness
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetchColumn()) {
            $username .= rand(100, 999);
        }
        
        // Fetch Employee role id
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Employee' LIMIT 1");
        $roleStmt->execute();
        $empRoleId = $roleStmt->fetchColumn();
        
        if ($empRoleId) {
            $default_password = 'password123';
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            $userInsert = $pdo->prepare('INSERT INTO users (name, username, email, password, role_id, employee_id, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
            $userInsert->execute([$fullName, $username, $email, $hashed_password, $empRoleId, $pdo->lastInsertId()]);
        }

        log_activity($pdo, $_SESSION['user']['id'], 'create_employee', "Created employee {$fullName}");
        flash('success', 'Employee added successfully. Default login created: Username=' . $username . ' / Password=password123');
    }
    redirect('employees');
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
if ($action && $id) {
    if (!in_array($action, ['delete', 'archive', 'restore'], true)) {
        redirect('employees');
    }
    $statusMapping = [
        'archive' => 'Archived',
        'restore' => 'Active',
    ];
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        log_activity($pdo, $_SESSION['user']['id'], 'delete_employee', "Deleted employee ID {$id}");
        flash('success', 'Employee removed permanently.');
    } else {
        $stmt = $pdo->prepare('UPDATE employees SET status = ? WHERE id = ?');
        $stmt->execute([$statusMapping[$action], $id]);
        log_activity($pdo, $_SESSION['user']['id'], "{$action}_employee", "{$action} employee ID {$id}");
        flash('success', ucfirst($action) . 'd employee successfully.');
    }
}
redirect('employees.html');
