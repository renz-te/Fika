<?php
require_once __DIR__ . '/../init.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    
    if (!$applicant_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid applicant ID.']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ", last_name) AS full_name FROM applicants WHERE id = ? FOR UPDATE');
        $stmt->execute([$applicant_id]);
        $applicant = $stmt->fetch();
        
        if (!$applicant || $applicant['stage'] !== 'Hireable') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Applicant not found or not in Hireable stage.']);
            exit;
        }

        $emp_position = $applicant['position_applied'] ?: 'Barista';
        $emp_cat = $applicant['employment_category'] ?: 'Full-Time';
        $target_branch = $applicant['branch_id'];

        // Enforce branch assignment — Global Pool hires must have a branch
        if (empty($target_branch)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot convert: Applicant must be assigned to a specific branch before hiring.']);
            exit;
        }

        // Fetch the approved hourly wage from the most recent scorecard
        $wageStmt = $pdo->prepare('
            SELECT sc.hourly_wage 
            FROM interview_scorecards sc 
            INNER JOIN interviews i ON sc.interview_id = i.id 
            WHERE i.applicant_id = ? AND sc.hourly_wage IS NOT NULL AND sc.hourly_wage > 0
            ORDER BY sc.created_at DESC 
            LIMIT 1
        ');
        $wageStmt->execute([$applicant_id]);
        $approvedWage = (float)($wageStmt->fetchColumn() ?: 0);

        // Generate collision-resistant employee_id string
        $prefixMap = [
            'Branch Manager' => 'BM', 'Branch Accountant' => 'BA',
            'Head Barista' => 'HBAR', 'Barista' => 'BAR',
            'Central HR' => 'HR', 'Super Admin' => 'SA',
        ];
        $prefix = $prefixMap[$emp_position] ?? 'EMP';
        if ($emp_cat === 'Part-Time' && !in_array($prefix, ['BM','BA','HR','SA'])) {
            $prefix = 'PT';
        }
        $datePart = date('ym');
        $serial   = random_int(1000, 9999);
        $empCode  = $prefix . '-' . $datePart . '-' . $serial;

        $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
        $chk->execute([$empCode]);
        while ($chk->fetchColumn() > 0) {
            $serial  = random_int(1000, 9999);
            $empCode = $prefix . '-' . $datePart . '-' . $serial;
            $chk->execute([$empCode]);
        }

        // 1. INSERT into employees
        $insert = $pdo->prepare('INSERT INTO employees 
            (employee_id, first_name, last_name, email, phone, position, status,
             employment_category, date_hired, hourly_rate,
             valid_id_photo, valid_id_back_photo,
             emergency_contact_name, emergency_contact_phone,
             education_level, school_name, school_address,
             year_graduated, education_status, course_diploma,
             birthdate, address, sex, nationality,
             preferred_schedule, branch_id, employment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?,
                    ?, CURDATE(), ?,
                    ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?)');
        $insert->execute([
            $empCode,
            $applicant['first_name'],
            $applicant['last_name'],
            $applicant['email'],
            $applicant['phone'],
            $emp_position,
            'Active',
            $emp_cat,
            $approvedWage,
            $applicant['valid_id_photo'] ?? null,
            $applicant['valid_id_back_photo'] ?? null,
            $applicant['reference_name'] ?? null,
            $applicant['reference_phone'] ?? null,
            $applicant['education_level'] ?? null,
            $applicant['school_name'] ?? null,
            $applicant['school_address'] ?? null,
            $applicant['year_graduated'] ?? null,
            $applicant['education_status'] ?? null,
            $applicant['course_diploma'] ?? null,
            $applicant['birthdate'] ?? null,
            $applicant['address'] ?? null,
            $applicant['sex'] ?? null,
            $applicant['nationality'] ?? null,
            $applicant['preferred_schedule'] ?? 'Any',
            $target_branch,
            'Probationary'
        ]);

        $newEmployeePk = (int)$pdo->lastInsertId();

        // 2. UPDATE applicants stage to Hired
        $update = $pdo->prepare('UPDATE applicants SET stage = ? WHERE id = ?');
        $update->execute(['Hired', $applicant_id]);

        // Log the hiring action
        $user_name = $_SESSION['user']['name'] ?? 'System';
        $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
        $logStmt->execute([$applicant_id, $user_name, "Hired as $emp_position ($empCode)"]);

        // 3. Auto-create user account
        $username = strtolower(trim($applicant['first_name']) . '.' . trim($applicant['last_name']));
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute([$username]);
        if ($checkUser->fetchColumn()) {
            $username .= rand(100, 999);
        }

        // Resolve role_id from position
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $roleStmt->execute([$emp_position]);
        $empRoleId = $roleStmt->fetchColumn();
        if (!$empRoleId) {
            $roleStmt->execute(['Barista']);
            $empRoleId = $roleStmt->fetchColumn();
        }

        if ($empRoleId) {
            // Generate a secure 12-character random password
            $raw_password = substr(bin2hex(random_bytes(8)), 0, 12);
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            
            $userInsert = $pdo->prepare('INSERT INTO users 
                (name, username, email, password, role_id, employee_id, verified, created_at, branch_id) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)');
            $fullName = $applicant['full_name'];
            $userInsert->execute([
                $fullName,
                $username,
                $applicant['email'],
                $hashed_password,
                $empRoleId,
                $newEmployeePk,
                $target_branch
            ]);

            // Notify Super Admin
            $notifStmt = $pdo->prepare('INSERT INTO global_notifications (title, message, target_roles, type) VALUES (?, ?, ?, ?)');
            $notifStmt->execute([
                'New Hire Account Created',
                "Account created for {$fullName} ({$empCode}). Username: $username",
                'Super Admin',
                'info'
            ]);

            // Send Welcome Email to Recruit
            if (!empty($applicant['email'])) {
                $compName = get_setting($pdo, 'company_name', 'Cafe HRMS');
                $appUrl = get_setting($pdo, 'app_url', 'http://localhost/Fika/cafe_hrms');

                $subject = "You're Hired! Welcome to $compName";
                $body = "
                    <div style='font-family: sans-serif; color: #333;'>
                        <h2>Welcome to the team!</h2>
                        <p>Dear <strong>{$fullName}</strong>,</p>
                        <p>Congratulations on your new role as a <strong>$emp_position</strong> at $compName!</p>
                        <p>We have provisioned your employee portal account so you can view your schedule, payslips, and request leaves.</p>
                        <div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid #4f46e5; margin: 20px 0;'>
                            <p style='margin: 0 0 10px 0;'><strong>Employee ID:</strong> $empCode</p>
                            <p style='margin: 0 0 10px 0;'><strong>Login URL:</strong> <a href='$appUrl/login.php'>$appUrl/login.php</a></p>
                            <p style='margin: 0 0 10px 0;'><strong>Username:</strong> $username</p>
                            <p style='margin: 0;'><strong>Temporary Password:</strong> $raw_password</p>
                        </div>
                        <p>Please log in and change your password immediately.</p>
                        <br>
                        <p>Best regards,<br>The $compName Team</p>
                    </div>
                ";
                send_email($applicant['email'], $subject, $body);
            }
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Employee account generated.']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            echo json_encode(['status' => 'error', 'message' => 'Duplicate email or employee ID collision.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit;
