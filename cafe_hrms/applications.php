<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();
$role = $user['role'];
$user_role = $_SESSION['user']['role'] ?? 'Branch Manager';
$user_branch_id = $_SESSION['user']['branch_id'] ?? null;

// Determine accessible positions
$positions = [];
if ($user_role === 'Super Admin' || $user_role === 'System Admin' || $user_role === 'Central HR') {
    // CHR has global recruitment authority for branch management
    $positions = ['Barista', 'Head Barista', 'Branch Manager', 'Branch Accountant'];
} else {
    // Branch Manager is restricted to recruiting standard branch operations staff
    $positions = ['Barista', 'Head Barista'];
}

// Establish the Global Query Silo
$location_sql = "";
$global_params = [];

if ($user_role === 'Central HR' || $user_role === 'Super Admin' || $user_role === 'System Admin') {
    // Top-level roles can view all, or filter by a specific location
    if (!empty($_GET['location']) && $_GET['location'] !== 'All Locations') {
        $location_sql = " AND a.branch_id = ?";
        $global_params[] = (int)$_GET['location'];
    }
} else if ($user_branch_id) {
    // Branch Managers are strictly locked to their own branch
    $location_sql = " AND a.branch_id = ?";
    $global_params[] = (int)$user_branch_id;
}

// Handle Stage Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_stage') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    $new_stage = $_POST['new_stage'] ?? '';
    if ($applicant_id && in_array($new_stage, ['Initial Interview', 'Final Interview', 'Hireable', 'Rejected'])) {
        
        // Backend State Lock
        $is_forward = in_array($new_stage, ['Final Interview', 'Hireable']);
        if ($is_forward) {
            $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE applicant_id = ? AND (status = 'Scheduled' OR (status = 'Completed' AND id NOT IN (SELECT interview_id FROM interview_scorecards)))");
            $chkStmt->execute([$applicant_id]);
            if ($chkStmt->fetchColumn() > 0) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['status' => 'error', 'message' => 'Progression blocked. Candidate requires scorecard submission via the Interview Workspace.']);
                    exit;
                } else {
                    flash('error', 'Progression blocked. Candidate requires scorecard submission via the Interview Workspace.');
                    redirect('applications');
                }
            }
        }
        
        if ($new_stage === 'Rejected') {
            $rejection_reason = $_POST['rejection_reason'] ?? '';
            $stmt = $pdo->prepare('UPDATE applicants SET stage = ?, rejection_reason = ? WHERE id = ?');
            $stmt->execute([$new_stage, $rejection_reason, $applicant_id]);
            
            if ($rejection_reason === 'No-Show') {
                $stmtAWOL = $pdo->prepare("UPDATE interviews SET status = 'AWOL' WHERE applicant_id = ? AND status = 'Scheduled'");
                $stmtAWOL->execute([$applicant_id]);
            }
        } else {
            $stmt = $pdo->prepare('UPDATE applicants SET stage = ? WHERE id = ?');
            $stmt->execute([$new_stage, $applicant_id]);
        }
        
        // Log the stage change
        $user_name = $_SESSION['user']['name'] ?? 'System';
        $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
        $logStmt->execute([$applicant_id, $user_name, "Moved to $new_stage"]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'success']);
            exit;
        } else {
            flash('success', 'Applicant stage updated.');
            redirect('applications');
        }
    }
}

// Handle AJAX Conflict Check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_conflict') {
    $interviewer_id = (int)($_POST['interviewer_id'] ?? $_POST['new_interviewer_id'] ?? 0);
    $interview_date = $_POST['interview_date'] ?? '';
    $interview_time = $_POST['interview_time'] ?? '';
    $interview_end_time = $_POST['interview_end_time'] ?? '';
    $exclude_id = (int)($_POST['interview_id'] ?? 0);
    
    $query = 'SELECT COUNT(*) FROM interviews WHERE interviewer_id = ? AND interview_date = ? AND status != "Cancelled" AND (interview_time < ? AND end_time > ?)';
    $params = [$interviewer_id, $interview_date, $interview_end_time, $interview_time];
    
    if ($exclude_id > 0) {
        $query .= ' AND id != ?';
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    header('Content-Type: application/json');
    echo json_encode(['conflict' => $stmt->fetchColumn() > 0]);
    exit;
}

// Handle Schedule Interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_interview') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    $target_stage = $_POST['target_stage'] ?? '';
    $interview_date = $_POST['interview_date'] ?? '';
    $interview_time = $_POST['interview_time'] ?? '';
    $interview_end_time = $_POST['interview_end_time'] ?? '';
    $interviewer_id = (int)($_POST['interviewer_id'] ?? 0);
    $send_invite = isset($_POST['send_invite']) ? true : false;
    
    if ($applicant_id && $target_stage && $interview_date && $interview_time && $interview_end_time && $interviewer_id > 0) {
        $user_name = $_SESSION['user']['name'] ?? 'System';
        
        // Validation Gate: Business Hours
        if ($interview_time < '08:00' || $interview_end_time > '18:00') {
            echo json_encode(['status' => 'error', 'message' => 'Scheduling Blocked: Interviews must be scheduled within standard business hours (08:00 AM - 06:00 PM).']);
            exit;
        }

        // Validation Gate: Overlap Block (Prevent Double-Booking)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM interviews WHERE interviewer_id = ? AND interview_date = ? AND status != "Cancelled" AND (interview_time < ? AND end_time > ?)');
        $stmt->execute([$interviewer_id, $interview_date, $interview_end_time, $interview_time]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Schedule Blocked: The selected manager already has an interview during this time block.']);
            exit;
        }
        
        // 1. Update applicant stage
        $stmt = $pdo->prepare('UPDATE applicants SET stage = ? WHERE id = ?');
        $stmt->execute([$target_stage, $applicant_id]);
        
        // 2. Log the stage move
        $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
        $logStmt->execute([$applicant_id, $user_name, "Moved to $target_stage (Scheduled)"]);
        
        // 3. Insert into interviews table
        $intStmt = $pdo->prepare('INSERT INTO interviews (applicant_id, interview_date, interview_time, end_time, interviewer_id, stage, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $intStmt->execute([$applicant_id, $interview_date, $interview_time, $interview_end_time, $interviewer_id, $target_stage, 'Scheduled']);
        
        // 4. Send Email if checked
        if ($send_invite) {
            $appStmt = $pdo->prepare('SELECT CONCAT(first_name, " ", last_name) AS full_name, email FROM applicants WHERE id = ?');
            $appStmt->execute([$applicant_id]);
            $applicant = $appStmt->fetch();
            
            if ($applicant && !empty($applicant['email'])) {
                $compName = get_setting($pdo, 'company_name', 'Cafe HRMS');
                $formatDate = date('l, F j, Y', strtotime($interview_date));
                $formatTime = date('g:i A', strtotime($interview_time));
                
                $subject = "Interview Invitation: $target_stage - $compName";
                $body = "
                    <div style='font-family: sans-serif; color: #333;'>
                        <h2>Interview Invitation</h2>
                        <p>Dear <strong>{$applicant['full_name']}</strong>,</p>
                        <p>We are pleased to invite you to a <strong>$target_stage</strong> for the position you applied for at $compName.</p>
                        <div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid #4f46e5; margin: 20px 0;'>
                            <p style='margin: 0 0 10px 0;'><strong>Date:</strong> $formatDate</p>
                            <p style='margin: 0;'><strong>Time:</strong> $formatTime</p>
                        </div>
                        <p>Please reply to this email to confirm your attendance. We look forward to speaking with you!</p>
                        <br>
                        <p>Best regards,<br>The Hiring Team at $compName</p>
                    </div>
                ";
                send_email($applicant['email'], $subject, $body);
            }
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'success']);
            exit;
        } else {
            flash('success', 'Applicant scheduled and moved successfully!');
            redirect('applications');
        }
    }
}

// Handle Reschedule Interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_interview'])) {
    $interview_id = (int)($_POST['interview_id'] ?? 0);
    $new_interviewer_id = (int)($_POST['new_interviewer_id'] ?? 0);
    $interview_date = $_POST['interview_date'] ?? '';
    $interview_time = $_POST['interview_time'] ?? '';
    $interview_end_time = $_POST['interview_end_time'] ?? '';
    $reason = trim($_POST['reschedule_reason'] ?? '');
    
    if ($interview_id && $new_interviewer_id && $interview_date && $interview_time && $interview_end_time) {
        // Validation Gate: Business Hours
        if ($interview_time < '08:00' || $interview_end_time > '18:00') {
            echo json_encode(['status' => 'error', 'message' => 'Scheduling Blocked: Interviews must be scheduled within standard business hours (08:00 AM - 06:00 PM).']);
            exit;
        }

        // Enforce Overlap Validation Gate
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM interviews WHERE interviewer_id = ? AND interview_date = ? AND id != ? AND status != "Cancelled" AND (interview_time < ? AND end_time > ?)');
        $stmt->execute([$new_interviewer_id, $interview_date, $interview_id, $interview_end_time, $interview_time]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Schedule Blocked: The selected manager already has an interview during this time block.']);
            exit;
        }
        
        $stmt = $pdo->prepare('UPDATE interviews SET interviewer_id = ?, interview_date = ?, interview_time = ?, end_time = ? WHERE id = ?');
        $stmt->execute([$new_interviewer_id, $interview_date, $interview_time, $interview_end_time, $interview_id]);
        
        // Find applicant id for logging
        $appStmt = $pdo->prepare('SELECT applicant_id FROM interviews WHERE id = ?');
        $appStmt->execute([$interview_id]);
        $app_id = $appStmt->fetchColumn();
        
        if ($app_id) {
            $user_name = $_SESSION['user']['name'] ?? 'System';
            $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
            $actionText = "Interview Rescheduled. Reason: " . ($reason ?: "Not provided");
            $logStmt->execute([$app_id, $user_name, $actionText]);
        }
        
        flash('success', 'Interview rescheduled successfully.');
        redirect('applications.php?tab=calendar');
    }
}

// Handle No-Show
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_no_show'])) {
    $interview_id = (int)($_POST['interview_id'] ?? 0);
    
    if ($interview_id) {
        $appStmt = $pdo->prepare('SELECT applicant_id FROM interviews WHERE id = ?');
        $appStmt->execute([$interview_id]);
        $app_id = $appStmt->fetchColumn();
        
        if ($app_id) {
            $stmt1 = $pdo->prepare('UPDATE applicants SET stage = "Rejected" WHERE id = ?');
            $stmt1->execute([$app_id]);
            
            $stmt2 = $pdo->prepare('UPDATE interviews SET status = "AWOL" WHERE id = ?');
            $stmt2->execute([$interview_id]);
            
            $user_name = $_SESSION['user']['name'] ?? 'System';
            $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
            $logStmt->execute([$app_id, $user_name, "Marked as No-Show & Rejected"]);
            
            flash('success', 'Applicant marked as No-Show.');
        }
        redirect('applications.php?tab=calendar');
    }
}

// Handle Hire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hire') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    $emp_status = $_POST['status'] ?? 'Trainee';
    $emp_position = $_POST['position'] ?? 'Barista';
    
    if ($applicant_id) {
        $stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ", last_name) AS full_name FROM applicants WHERE id = ?');
        $stmt->execute([$applicant_id]);
        $applicant = $stmt->fetch();
        
        if ($applicant) {
            $emp_cat = $_POST['employment_category'] ?? $applicant['employment_category'] ?? 'Full-Time';

            // --- Generate collision-resistant employee_id string ---
            $prefixMap = [
                'Branch Manager' => 'BM', 'Branch Accountant' => 'BA',
                'Head Barista' => 'HBAR', 'Barista' => 'BAR',
                'Central HR' => 'HR', 'Super Admin' => 'SA',
            ];
            $prefix = $prefixMap[$emp_position] ?? 'EMP';
            if ($emp_cat === 'Part-Time' && !in_array($prefix, ['BM','BA','HR','SA'])) {
                $prefix = 'PT';
            }
            $datePart = date('ym'); // e.g. 2609
            $serial   = random_int(1000, 9999);
            $empCode  = $prefix . '-' . $datePart . '-' . $serial;

            // Ensure uniqueness
            $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
            $chk->execute([$empCode]);
            while ($chk->fetchColumn() > 0) {
                $serial  = random_int(1000, 9999);
                $empCode = $prefix . '-' . $datePart . '-' . $serial;
                $chk->execute([$empCode]);
            }

            try {
                // --- Determine branch_id ---
                $target_branch = null;
                if (!empty($_POST['branch_id'])) {
                    $target_branch = (int)$_POST['branch_id'];
                } elseif (!empty($user['branch_id'])) {
                    $target_branch = (int)$user['branch_id'];
                }

                // --- 1. INSERT into employees ---
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
                    isset($_POST['hourly_rate']) ? (float)str_replace(',', '', $_POST['hourly_rate']) : 0,
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
                    $emp_status
                ]);

                $newEmployeePk = (int)$pdo->lastInsertId();

                // --- 2. UPDATE applicants stage to Hired ---
                $update = $pdo->prepare('UPDATE applicants SET stage = ? WHERE id = ?');
                $update->execute(['Hired', $applicant_id]);

                // --- Log the hiring action ---
                $user_name = $_SESSION['user']['name'] ?? 'System';
                $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
                $logStmt->execute([$applicant_id, $user_name, "Hired as $emp_position ($empCode)"]);

                // --- 3. Auto-create user account with employee_id FK ---
                $firstname = $applicant['first_name'];
                $lastname = $applicant['last_name'];
                $username = strtolower(trim($firstname) . '.' . trim($lastname));

                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn()) {
                    $username .= rand(100, 999);
                }

                // Resolve role_id from position
                $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
                $roleStmt->execute([$emp_position]);
                $empRoleId = $roleStmt->fetchColumn();
                if (!$empRoleId) {
                    $roleStmt->execute(['Barista']); // Default fallback
                    $empRoleId = $roleStmt->fetchColumn();
                }

                if ($empRoleId) {
                    $default_password = 'password123';
                    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                    
                    $userInsert = $pdo->prepare('INSERT INTO users 
                        (name, username, email, password, role_id, employee_id, verified, created_at, branch_id) 
                        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)');
                    $fullName = $applicant['first_name'] . ' ' . $applicant['last_name'];
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
                                    <p style='margin: 0;'><strong>Temporary Password:</strong> Welcome123!</p>
                                </div>
                                <p>Please log in and change your password immediately.</p>
                                <br>
                                <p>Best regards,<br>The $compName Team</p>
                            </div>
                        ";
                        send_email($applicant['email'], $subject, $body);
                    }
                }

                flash('success', "{$applicant['full_name']} has been hired as {$emp_status} ({$emp_position})! Employee ID: {$empCode}. Account created and welcome email sent.");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('error', 'Error: This applicant has already been hired (duplicate email or employee ID collision).');
                } else {
                    flash('error', 'Database Error: ' . $e->getMessage());
                }
            }
            redirect('employees');
        }
    }
}

// Fetch applicants
$app_query = "
    SELECT a.id, a.first_name, a.last_name, CONCAT(a.first_name, ' ', a.last_name) AS full_name, a.email, a.phone, a.position_applied, a.stage, a.created_at, a.resume, a.employment_category, a.experience_level,
    (SELECT status FROM interviews WHERE applicant_id = a.id AND status != 'Cancelled' ORDER BY id DESC LIMIT 1) as latest_interview_status,
    (SELECT interview_date FROM interviews WHERE applicant_id = a.id AND status != 'Cancelled' ORDER BY id DESC LIMIT 1) as latest_interview_date,
    (SELECT interview_time FROM interviews WHERE applicant_id = a.id AND status != 'Cancelled' ORDER BY id DESC LIMIT 1) as latest_interview_time
    FROM applicants a 
    WHERE a.stage NOT IN ('Hireable', 'Hired', 'Rejected')" . $location_sql;

$app_params = $global_params;

if (!empty($_GET['position']) && $_GET['position'] !== 'All Positions') {
    $app_query .= " AND a.position_applied = ?";
    $app_params[] = $_GET['position'];
}
if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
    $app_query .= " AND a.employment_category = ?";
    $app_params[] = $_GET['category'];
}

$app_query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($app_query);
$stmt->execute($app_params);
$all_applicants = $stmt->fetchAll();

// Fetch pending interviews for Kanban cards
$pendingIntStmt = $pdo->query("
    SELECT applicant_id, MAX(id) as interview_id 
    FROM interviews 
    WHERE status = 'Scheduled' OR (status = 'Completed' AND id NOT IN (SELECT interview_id FROM interview_scorecards)) 
    GROUP BY applicant_id
");
$pendingInterviews = [];
while ($row = $pendingIntStmt->fetch()) {
    $pendingInterviews[$row['applicant_id']] = $row['interview_id'];
}

$tab = $_GET['tab'] ?? 'kanban';


$stages = [
    'New' => [],
    'Initial Interview' => [],
    'Final Interview' => []
];

foreach ($all_applicants as $app) {
    if (isset($stages[$app['stage']])) {
        $stages[$app['stage']][] = $app;
    }
}

$pageTitle = 'Applications ATS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Applicant Tracking System</h1>
        <p class="text-slate-500">Manage recruitment pipeline from initial interview to hiring.</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200">
            <a href="?tab=kanban" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'kanban' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-solid fa-table-columns mr-1"></i> Kanban
            </a>
            <a href="?tab=calendar" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'calendar' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-regular fa-calendar-days mr-1"></i> Calendar
            </a>
            <a href="?tab=evaluations" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'evaluations' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-solid fa-clipboard-list mr-1"></i> Evaluations
            </a>
            <a href="?tab=directory" class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors <?= $tab === 'directory' ? 'bg-white shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="fa-solid fa-address-book mr-1"></i> Master List
            </a>
        </div>
        <?php if($user_role !== 'Central HR'): ?>
        <button onclick="openEditModal('applicant_form.php')" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition flex items-center shadow-sm">
            <i class="fa-solid fa-plus mr-2"></i> Add Applicant
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
// Fetch all branches for the location filter
$all_branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- GLOBAL FILTER BAR -->
<form id="ats-filter-form" method="GET" action="applications" class="mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap gap-4 items-center">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <!-- Global Calendar State -->
    <input type="hidden" name="week_offset" id="global_week_offset" value="<?php echo isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0; ?>">
    <div class="relative flex-grow md:max-w-xs">
        <i class="fa-solid fa-search absolute left-3 top-3 text-slate-400"></i>
        <input type="text" name="search" id="filterSearch" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Search applicant name..." autocomplete="off" class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:ring-primary focus:border-primary transition-colors">
    </div>
    
    <?php if ($user_role === 'Central HR' || $user_role === 'Super Admin' || $user_role === 'System Admin'): ?>
    <select name="location" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary transition-colors bg-indigo-50" onchange="<?php echo ($tab == 'kanban') ? 'window.applyFilters()' : 'this.form.submit()'; ?>">
        <option value="All Locations">All Locations</option>
        <?php foreach ($all_branches as $branch): ?>
            <option value="<?= $branch['id'] ?>" <?= (isset($_GET['location']) && $_GET['location'] == $branch['id']) ? 'selected' : '' ?>>
                <?= h($branch['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    
    <select name="position" id="filterPosition" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary transition-colors" onchange="<?php echo ($tab == 'kanban') ? 'window.applyFilters()' : 'this.form.submit()'; ?>">
        <option value="">All Positions</option>
        <?php foreach ($positions as $pos): ?>
            <option value="<?php echo h($pos); ?>" <?php echo (isset($_GET['position']) && $_GET['position'] == $pos) ? 'selected' : ''; ?>>
                <?php echo h($pos); ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <select name="category" id="filterCategory" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary transition-colors" onchange="<?php echo ($tab == 'kanban') ? 'window.applyFilters()' : 'this.form.submit()'; ?>">
        <option value="">All Categories</option>
        <option value="Full-Time" <?= (isset($_GET['category']) && $_GET['category'] == 'Full-Time') ? 'selected' : '' ?>>Full-Time</option>
        <option value="Part-Time" <?= (isset($_GET['category']) && $_GET['category'] == 'Part-Time') ? 'selected' : '' ?>>Part-Time</option>
    </select>
    
    <?php if ($tab === 'directory'): ?>
    <select name="stage_filter" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary transition-colors" onchange="this.form.submit()">
        <option value="">All Stages</option>
        <option value="New" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'New') ? 'selected' : ''; ?>>New</option>
        <option value="Initial Interview" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'Initial Interview') ? 'selected' : ''; ?>>Initial Interview</option>
        <option value="Final Interview" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'Final Interview') ? 'selected' : ''; ?>>Final Interview</option>
        <option value="Hireable" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'Hireable') ? 'selected' : ''; ?>>Hireable</option>
        <option value="Rejected" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
        <option value="Hired" <?php echo (isset($_GET['stage_filter']) && $_GET['stage_filter'] == 'Hired') ? 'selected' : ''; ?>>Hired</option>
    </select>
    <?php endif; ?>
    
    <?php if ($tab === 'kanban'): ?>
    <select id="filterExperience" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary transition-colors">
        <option value="">All Experience</option>
        <option value="No Experience">No Experience</option>
        <option value="<= 1 Year">&lt;= 1 Year</option>
        <option value="<= 3 Years">&lt;= 3 Years</option>
        <option value="<= 5 Years">&lt;= 5 Years</option>
        <option value="<= 10 Years">&lt;= 10 Years</option>
    </select>
    <?php endif; ?>
    
    <button type="submit" class="hidden">Submit</button>
</form>

<div id="ats-tab-content">
<?php if($tab === 'kanban'): ?>

<div class="flex flex-col lg:flex-row gap-6 overflow-x-auto pb-4 h-[calc(100vh-200px)]">
    
    <?php foreach($stages as $stage_name => $applicants): 
        $borderColor = 'border-slate-200';
        $headerColor = 'bg-slate-100 text-slate-700';
        if ($stage_name === 'New') { $borderColor = 'border-amber-200'; $headerColor = 'bg-amber-50 text-amber-700 border-amber-100'; }
        if ($stage_name === 'Initial Interview') { $borderColor = 'border-blue-200'; $headerColor = 'bg-blue-50 text-blue-700 border-blue-100'; }
        if ($stage_name === 'Final Interview') { $borderColor = 'border-purple-200'; $headerColor = 'bg-purple-50 text-purple-700 border-purple-100'; }
    ?>
    
    <div class="flex-1 min-w-[300px] bg-slate-50 rounded-xl border <?= $borderColor ?> flex flex-col max-h-full kanban-column" data-stage-name="<?= h($stage_name) ?>">
        <div class="px-4 py-3 border-b <?= $headerColor ?> rounded-t-xl flex justify-between items-center">
            <h3 class="font-bold text-sm uppercase tracking-wider"><?= h($stage_name) ?></h3>
            <span class="bg-white px-2 py-0.5 rounded-full text-xs font-bold shadow-sm"><?= count($applicants) ?></span>
        </div>
        
        <div class="p-4 flex-1 overflow-y-auto space-y-4">
            <?php if(empty($applicants)): ?>
                <div class="text-center p-6 border-2 border-dashed border-slate-200 rounded-lg text-slate-400 text-sm">
                    No applicants in this stage
                </div>
            <?php else: ?>
                <?php foreach($applicants as $app): ?>
                    <div class="applicant-card bg-white p-4 rounded-lg shadow-sm border border-slate-200 hover:shadow-md hover:border-primary transition cursor-pointer relative group flex flex-col gap-2"
                         draggable="true"
                         data-applicant-id="<?= $app['id'] ?>"
                         onclick="openApplicantDrawer(<?= $app['id'] ?>)"
                         data-name="<?= h(strtolower($app['full_name'])) ?>"
                         data-position="<?= h($app['position_applied']) ?>"
                         data-category="<?= h($app['employment_category'] ?? '') ?>"
                         data-experience="<?= h($app['experience_level'] ?? '') ?>">
                        
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-slate-800"><?= h($app['full_name']) ?></h4>
                            <?php 
                                $is_overdue = false;
                                if ($app['latest_interview_status'] === 'Scheduled') {
                                    $interview_dt = $app['latest_interview_date'] . ' ' . $app['latest_interview_time'];
                                    if (strtotime($interview_dt) < time()) {
                                        $is_overdue = true;
                                    }
                                }
                                if ($is_overdue): 
                            ?>
                                <span class="text-red-500" title="Overdue Interview"><i class="fa-solid fa-triangle-exclamation"></i></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center justify-between mt-1">
                            <p class="text-xs text-slate-500 font-medium"><i class="fa-solid fa-briefcase mr-1"></i> <?= h($app['position_applied']) ?></p>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                <?= h($app['employment_category'] ?? 'FT') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php elseif($tab === 'calendar'): 
    $weekOffset = (int)($_GET['week_offset'] ?? 0);
    $monday = new DateTime();
    if ($monday->format('N') != 1) {
        $monday->modify('last monday');
    }
    if ($weekOffset !== 0) {
        $monday->modify($weekOffset > 0 ? "+$weekOffset week" : "$weekOffset week");
    }
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    
    $scheduledOnly = isset($_GET['scheduled_only']) && $_GET['scheduled_only'] == 1;

    // Fetch interviews for the week
    $int_query = '
        SELECT i.*, 
               CONCAT(e.first_name, " ", e.last_name) AS interviewer_name,
               CONCAT(a.first_name, " ", a.last_name) AS applicant_name,
               a.position_applied 
        FROM interviews i 
        LEFT JOIN employees e ON i.interviewer_id = e.id 
        INNER JOIN applicants a ON i.applicant_id = a.id
        WHERE i.interview_date BETWEEN ? AND ? AND i.status != "Cancelled"' . $location_sql;
    
    $int_params = array_merge([$monday->format('Y-m-d'), $sunday->format('Y-m-d')], $global_params);

    if (!empty($_GET['position']) && $_GET['position'] !== 'All Positions') {
        $int_query .= " AND a.position_applied = ?";
        $int_params[] = $_GET['position'];
    }
    if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
        $int_query .= " AND a.employment_category = ?";
        $int_params[] = $_GET['category'];
    }

    $intStmt = $pdo->prepare($int_query);
    $intStmt->execute($int_params);
    $rawInterviews = $intStmt->fetchAll();
    
    if (!$scheduledOnly) {
        $interviewer_query = "
            SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, r.name AS role 
            FROM employees e 
            INNER JOIN users u ON u.employee_id = e.id 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE e.status = 'Active' 
            AND r.name IN ('" . ROLE_BRANCH_MANAGER . "', '" . ROLE_CENTRAL_HR . "', 'Head Barista', '" . ROLE_SUPER_ADMIN . "') 
        ";
        $manager_params = [];
        
        if (!empty($_GET['location']) && $_GET['location'] !== 'All Locations') {
            $interviewer_query .= " AND (e.branch_id = ? OR r.name = 'Central HR' OR r.name = 'Super Admin' OR r.name = 'System Admin')";
            $manager_params[] = (int)$_GET['location'];
        } else if ($user_branch_id) {
            $interviewer_query .= " AND (e.branch_id = ? OR r.name = 'Central HR' OR r.name = 'Super Admin' OR r.name = 'System Admin')";
            $manager_params[] = (int)$user_branch_id;
        }

        $interviewer_query .= " ORDER BY e.first_name";
        
        $managersStmt = $pdo->prepare($interviewer_query);
        $managersStmt->execute($manager_params);
        $yAxisItems = $managersStmt->fetchAll();
        $yAxisType = 'manager';
        
        $weekInterviews = [];
        foreach($rawInterviews as $int) {
            $weekInterviews[$int['interviewer_id']][$int['interview_date']][] = $int;
        }
    } else {
        $app_query = '
            SELECT a.id, CONCAT(a.first_name, " ", a.last_name) AS full_name, a.position_applied, a.stage 
            FROM applicants a 
            INNER JOIN interviews i ON a.id = i.applicant_id 
            WHERE i.interview_date BETWEEN ? AND ? AND i.status != "Cancelled"' . $location_sql;
            
        $app_params = array_merge([$monday->format('Y-m-d'), $sunday->format('Y-m-d')], $global_params);

        if (!empty($_GET['position']) && $_GET['position'] !== 'All Positions') {
            $app_query .= " AND a.position_applied = ?";
            $app_params[] = $_GET['position'];
        }
        if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
            $app_query .= " AND a.employment_category = ?";
            $app_params[] = $_GET['category'];
        }

        $app_query .= " GROUP BY a.id ORDER BY a.first_name";

        $appStmt = $pdo->prepare($app_query);
        $appStmt->execute($app_params);
        $yAxisItems = $appStmt->fetchAll();
        $yAxisType = 'applicant';
        
        $weekInterviews = [];
        foreach($rawInterviews as $int) {
            $weekInterviews[$int['applicant_id']][$int['interview_date']][] = $int;
        }
    }
?>

<div class="mb-4 flex justify-between items-center">
    <h2 class="text-xl font-bold text-slate-800">Interview Calendar</h2>
        <div class="flex items-center gap-4 border-r pr-4 border-slate-200">
            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700">
                <input type="checkbox" id="calendar_view_toggle" name="scheduled_only" value="1" form="ats-filter-form" onchange="this.form.submit();" <?php echo (isset($_GET['scheduled_only']) && $_GET['scheduled_only'] == '1') ? 'checked' : ''; ?> class="w-4 h-4 text-primary border-slate-300 rounded focus:ring-primary">
                Show Scheduled Only (Applicant View)
            </label>
        </div>
        <div class="flex items-center gap-2">
            <a href="?tab=calendar&week_offset=<?= $weekOffset - 1 ?><?= $scheduledOnly ? '&scheduled_only=1' : '' ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded shadow-sm hover:bg-slate-50 text-slate-600">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <span class="px-4 py-1.5 font-medium text-slate-700 bg-white border border-slate-200 rounded shadow-sm">
                <?= $monday->format('M d') ?> - <?= $sunday->format('M d, Y') ?>
            </span>
            <a href="?tab=calendar&week_offset=<?= $weekOffset + 1 ?><?= $scheduledOnly ? '&scheduled_only=1' : '' ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded shadow-sm hover:bg-slate-50 text-slate-600">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php if ($weekOffset !== 0): ?>
                <a href="?tab=calendar<?= $scheduledOnly ? '&scheduled_only=1' : '' ?>" class="text-primary text-sm hover:underline ml-2">This Week</a>
            <?php endif; ?>
        </div>
</div>

<div class="bg-slate-50 rounded-xl shadow-sm border border-slate-300 overflow-x-auto">
    <table class="w-full text-left text-sm whitespace-nowrap">
        <thead>
            <tr class="bg-slate-200 border-b border-slate-300 text-slate-700">
                <th class="p-4 font-semibold sticky left-0 bg-slate-200 z-10 shadow-[1px_0_0_0_#cbd5e1]"><?= $yAxisType === 'manager' ? 'Interviewer (Manager)' : 'Scheduled Applicant' ?></th>
                <?php 
                $curr = clone $monday;
                for($i=0; $i<7; $i++): 
                    $isToday = $curr->format('Y-m-d') == date('Y-m-d');
                ?>
                    <th class="p-3 font-semibold text-center <?= $isToday ? 'bg-indigo-100 text-indigo-800' : '' ?>">
                        <div class="text-xs font-normal"><?= $curr->format('l') ?></div>
                        <?= $curr->format('M d') ?>
                    </th>
                <?php 
                    $curr->modify('+1 day');
                endfor; 
                ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php 
            if(empty($yAxisItems)): ?>
                <tr>
                    <td colspan="8" class="p-8 text-center text-slate-500">No data found for this view.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($yAxisItems as $item): ?>
                    <tr class="hover:bg-slate-200/50">
                        <td class="p-4 sticky left-0 bg-slate-50 shadow-[1px_0_0_0_#cbd5e1]">
                            <div class="font-bold text-slate-800 text-sm"><?= h($item['full_name']) ?></div>
                            <div class="text-[10px] mt-0.5 space-x-1">
                                <span class="inline-block px-1.5 py-0.5 rounded font-bold uppercase tracking-wider bg-slate-100 text-slate-600"><?= h($item['role'] ?? $item['position_applied']) ?></span>
                            </div>
                            <?php if(isset($item['stage'])): ?>
                                <div class="text-[10px] text-slate-400 mt-1">Stage: <?= h($item['stage']) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php 
                        $curr = clone $monday;
                        for($i=0; $i<7; $i++): 
                            $dateStr = $curr->format('Y-m-d');
                            $dayInterviews = $weekInterviews[$item['id']][$dateStr] ?? [];
                            $cellClasses = "p-2 min-w-[120px] text-center border-l border-slate-100 relative align-top";
                        ?>
                            <td class="<?= $cellClasses ?>">
                                <?php if (!empty($dayInterviews)): ?>
                                    <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1 custom-scrollbar">
                                    <?php foreach($dayInterviews as $interview): ?>
                                        <div onclick="openInterviewActionModal(<?= $interview['id'] ?>, <?= json_encode(htmlspecialchars($interview['applicant_name'], ENT_QUOTES, 'UTF-8')) ?>, '<?= $interview['interview_date'] ?>', '<?= $interview['interview_time'] ?>', '<?= $interview['end_time'] ?>', <?= $interview['interviewer_id'] ?? 0 ?>, <?= json_encode(htmlspecialchars($interview['interviewer_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8')) ?>)" 
                                             class="cursor-pointer group flex items-start justify-between border rounded px-1.5 py-1 text-xs text-left <?= $yAxisType === 'manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200' ?> hover:shadow-sm transition">
                                            
                                            <div class="leading-tight">
                                                <span class="font-bold">
                                                    <?php 
                                                        $startTime = !empty($interview['interview_time']) ? date('g:ia', strtotime($interview['interview_time'])) : 'TBD';
                                                    ?>
                                                    <?= $startTime ?> - 
                                                </span>
                                                <span class="truncate max-w-[80px] inline-block align-bottom" title="<?= $yAxisType === 'manager' ? h($interview['applicant_name']) : h($interview['interviewer_name'] ?? 'Unassigned') ?>">
                                                    <?= $yAxisType === 'manager' ? h($interview['applicant_name']) : h($interview['interviewer_name'] ?? 'Unassigned') ?>
                                                </span>
                                            </div>
                                            
                                            <button type="button" 
                                                    onclick="openApplicantDrawer(<?= $interview['applicant_id'] ?>, true); event.stopPropagation();" 
                                                    class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-primary transition-opacity ml-1" 
                                                    title="View Applicant Details">
                                                <i class="fa-solid fa-circle-info"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php 
                            $curr->modify('+1 day');
                        endfor; 
                        ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif($tab === 'evaluations'): ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-800"><i class="fa-solid fa-clipboard-list text-primary mr-2"></i> All Evaluations</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider">
                <tr>
                    <th class="p-4 font-bold border-b">Applicant Name</th>
                    <th class="p-4 font-bold border-b">Category</th>
                    <th class="p-4 font-bold border-b">Target Role</th>
                    <th class="p-4 font-bold border-b">Interviewer</th>
                    <th class="p-4 font-bold border-b">Date</th>
                    <th class="p-4 font-bold border-b">Verdict</th>
                    <th class="p-4 font-bold border-b text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $eval_query = '
                    SELECT s.*, 
                           CONCAT(a.first_name, " ", a.last_name) AS applicant_name,
                           a.position_applied,
                           a.employment_category AS category,
                           i.interview_date,
                           CONCAT(e.first_name, " ", e.last_name) AS interviewer_name
                    FROM interview_scorecards s
                    INNER JOIN interviews i ON s.interview_id = i.id
                    INNER JOIN applicants a ON i.applicant_id = a.id
                    LEFT JOIN employees e ON i.interviewer_id = e.id
                    WHERE 1=1' . $location_sql;
                    
                $eval_params = $global_params;

                if (!empty($_GET['position']) && $_GET['position'] !== 'All Positions') {
                    $eval_query .= " AND a.position_applied = ?";
                    $eval_params[] = $_GET['position'];
                }
                if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
                    $eval_query .= " AND a.employment_category = ?";
                    $eval_params[] = $_GET['category'];
                }

                $eval_query .= " ORDER BY s.created_at DESC";

                $evalStmt = $pdo->prepare($eval_query);
                $evalStmt->execute($eval_params);
                $evaluations = $evalStmt->fetchAll();
                
                if (empty($evaluations)):
                ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-500">No evaluations recorded yet.</td>
                    </tr>
                <?php else: foreach($evaluations as $eval): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4 font-medium text-slate-800"><?= h($eval['applicant_name']) ?></td>
                        <td class="p-4"><?= h($eval['category'] ?? 'N/A') ?></td>
                        <td class="p-4"><?= h($eval['position_applied']) ?></td>
                        <td class="p-4"><?= h($eval['interviewer_name'] ?? 'Unknown') ?></td>
                        <td class="p-4"><?= date('M j, Y', strtotime($eval['interview_date'])) ?></td>
                        <td class="p-4 font-bold <?= $eval['recommendation'] === 'Hire' ? 'text-green-600' : ($eval['recommendation'] === 'Reject' ? 'text-red-600' : 'text-indigo-600') ?>">
                            <?= h($eval['recommendation']) ?>
                        </td>
                        <td class="p-4 text-center">
                            <button onclick="openNotesModal(<?= htmlspecialchars(json_encode([
                                'name' => $eval['applicant_name'],
                                'strengths' => $eval['strengths'],
                                'concerns' => $eval['concerns'],
                                'tech' => $eval['technical_score'],
                                'comm' => $eval['communication_score'],
                                'rel' => $eval['reliability_score'],
                                'cult' => $eval['culture_score'],
                                'ps' => $eval['problem_solving_score']
                            ])) ?>)" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition">
                                View Notes
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif($tab === 'directory'): ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-800"><i class="fa-solid fa-address-book text-primary mr-2"></i> Master Applicant Directory</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider">
                <tr>
                    <th class="p-4 font-bold border-b">Date Applied</th>
                    <th class="p-4 font-bold border-b">Applicant Name</th>
                    <th class="p-4 font-bold border-b">Contact Info</th>
                    <th class="p-4 font-bold border-b">Target Role</th>
                    <th class="p-4 font-bold border-b">Category</th>
                    <th class="p-4 font-bold border-b">Current Stage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $dir_query = "SELECT id, first_name, last_name, email, phone, position_applied, employment_category, stage, created_at FROM applicants a WHERE 1=1" . $location_sql;
                $params = $global_params;
                
                // Search Filter
                if (!empty($_GET['search'])) {
                    $dir_query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ?)";
                    $search_term = '%' . $_GET['search'] . '%';
                    $params[] = $search_term;
                    $params[] = $search_term;
                }
                
                // Position Filter
                if (!empty($_GET['position']) && $_GET['position'] !== 'All Positions') {
                    $dir_query .= " AND a.position_applied = ?";
                    $params[] = $_GET['position'];
                }
                
                // Category Filter
                if (!empty($_GET['category']) && $_GET['category'] !== 'All Categories') {
                    $dir_query .= " AND a.employment_category = ?";
                    $params[] = $_GET['category'];
                }
                
                // Stage Filter
                if (!empty($_GET['stage_filter'])) {
                    $dir_query .= " AND a.stage = ?";
                    $params[] = $_GET['stage_filter'];
                }
                
                $dir_query .= " ORDER BY a.created_at DESC";
                
                // Execute via PDO
                $stmt = $pdo->prepare($dir_query);
                $stmt->execute($params);
                $directory_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($directory_results as $app):
                ?>
                <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="openApplicantDrawer(<?= $app['id'] ?>, true)" data-applicant-id="<?= $app['id'] ?>">
                    <td class="p-4"><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
                    <td class="p-4 font-medium text-slate-800"><?= h($app['first_name'] . ' ' . $app['last_name']) ?></td>
                    <td class="p-4">
                        <div class="text-xs"><?= h($app['email']) ?></div>
                        <div class="text-xs text-slate-400"><?= h($app['phone']) ?></div>
                    </td>
                    <td class="p-4"><?= h($app['position_applied']) ?></td>
                    <td class="p-4"><?= h($app['employment_category'] ?? 'N/A') ?></td>
                    <td class="p-4">
                        <?php
                        $badgeClass = 'bg-blue-100 text-blue-800';
                        if ($app['stage'] === 'Rejected') {
                            $badgeClass = 'bg-red-100 text-red-800';
                        } elseif (in_array($app['stage'], ['Hireable', 'Hired'])) {
                            $badgeClass = 'bg-green-100 text-green-800';
                        }
                        ?>
                        <span class="stage-badge inline-flex px-2 py-1 rounded text-xs font-bold <?= $badgeClass ?>">
                            <?= h($app['stage']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
</div>

<!-- Interview Action Modal -->
<div id="interviewActionModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl flex flex-col relative overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-calendar-day text-primary mr-2"></i> Manage Interview</h3>
            <button onclick="closeInterviewActionModal()" type="button" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column: Context & Primary Actions -->
            <div class="space-y-6">
                <div class="space-y-1">
                    <p class="text-sm text-slate-600">Applicant: <strong id="actionApplicantName" class="text-slate-800"></strong></p>
                    <p class="text-sm text-slate-600">Assigned Interviewer: <strong id="actionInterviewerName" class="text-slate-800"></strong></p>
                </div>
                
                <!-- Action 1: Launch Workspace -->
                <div>
                    <a href="#" id="launchWorkspaceBtn" class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-lg font-bold shadow-sm transition">
                        <i class="fa-solid fa-laptop-file mr-2"></i> Launch Interview Workspace
                    </a>
                </div>
                
                <hr class="border-slate-200">
                
                <!-- Action 3: No-Show -->
                <form method="POST" action="applications" onsubmit="return confirm('Are you sure you want to mark this applicant as AWOL and reject their application?');">
                    <input type="hidden" name="mark_no_show" value="1">
                    <input type="hidden" name="interview_id" id="noshow_interview_id">
                    <button type="submit" class="w-full border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2 rounded font-medium transition text-sm flex items-center justify-center">
                        <i class="fa-solid fa-user-xmark mr-2"></i> Mark as No-Show & Reject
                    </button>
                </form>
            </div>
            
            <!-- Right Column: Reschedule -->
            <div>
                <form method="POST" action="applications" id="rescheduleInterviewForm">
                    <input type="hidden" name="reschedule_interview" value="1">
                    <input type="hidden" name="interview_id" id="reschedule_interview_id">
                    <h4 class="font-semibold text-sm text-slate-700 mb-3"><i class="fa-solid fa-clock mr-1 text-slate-400"></i> Reschedule</h4>
                    <div class="space-y-3 bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Interviewer</label>
                            <select name="new_interviewer_id" id="reschedule_interviewer_id" class="w-full text-sm rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                                <?php
                                    $empStmt = $pdo->query("
                                        SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name 
                                        FROM employees e 
                                        INNER JOIN users u ON u.employee_id = e.id 
                                        INNER JOIN roles r ON u.role_id = r.id 
                                        WHERE e.status = 'Active' 
                                        AND r.name IN ('" . ROLE_BRANCH_MANAGER . "', '" . ROLE_CENTRAL_HR . "', 'Head Barista', '" . ROLE_SUPER_ADMIN . "') 
                                        " . get_branch_filter('e') . " 
                                        ORDER BY e.first_name
                                    ");
                                    while ($emp = $empStmt->fetch()) {
                                        echo '<option value="' . $emp['id'] . '">' . h($emp['full_name']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">New Date</label>
                            <input type="date" name="interview_date" id="reschedule_date" required class="w-full text-sm rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Start Time</label>
                                <input type="time" name="interview_time" id="reschedule_start_time" min="08:00" max="17:00" required class="w-full text-sm rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">End Time</label>
                                <input type="time" name="interview_end_time" id="reschedule_end_time" min="08:00" max="18:00" required class="w-full text-sm rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Reason (Optional)</label>
                            <textarea name="reschedule_reason" class="w-full text-sm rounded border-slate-300 p-2 focus:ring-primary focus:border-primary" rows="2" placeholder="e.g., Manager unavailable"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded font-medium transition text-sm">
                            Save New Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Drawer -->
<div id="editModal" class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white shadow-[0_0_40px_rgba(0,0,0,0.3)] z-[60] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
        <h3 class="font-bold text-lg text-slate-800">Edit Applicant</h3>
        <div class="flex gap-2">
            <button onclick="closeEditModal()" class="text-sm px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded transition font-medium"><i class="fa-solid fa-arrow-right mr-1"></i> Back</button>
        </div>
    </div>
    <iframe id="editIframe" class="w-full flex-1" src=""></iframe>
</div>

<!-- Custom Hire Modal -->
<div id="hireModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
        <h3 class="font-bold text-slate-800 text-lg mb-2">Hire Applicant</h3>
        <p class="text-sm text-slate-500 mb-4">You are hiring <span id="hireName" class="font-bold text-slate-700"></span>.</p>
        <form autocomplete="off" method="POST" action="applications">
            <input type="hidden" name="action" value="hire">
            <input type="hidden" name="applicant_id" id="hireApplicantId">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Employment Category *</label>
                <select name="employment_category" id="hireEmpCategory" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    <option value="Full-Time">Full-Time</option>
                    <option value="Part-Time">Part-Time</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Employment Status *</label>
                <select name="status" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    <option value="Trainee">Trainee</option>
                    <option value="Probationary">Probationary</option>
                    <option value="Regular">Regular</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-1">Position *</label>
                <select name="position" id="hirePosition" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    <option value="" disabled selected>Select Position...</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?php echo h($pos); ?>"><?php echo h($pos); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (empty($user['branch_id'])): ?>
            <div class="mb-6" id="hireBranchContainer">
                <label class="block text-sm font-medium text-slate-700 mb-1">Target Location *</label>
                <select name="branch_id" id="hireBranch" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    <option value="">Select Location...</option>
                    <?php
                        $branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name");
                        while ($branch = $branchStmt->fetch()) {
                            echo '<option value="' . $branch['id'] . '">' . h($branch['name']) . '</option>';
                        }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="mb-6 pt-4 border-t border-slate-100">
                <label id="hireAgreedLabel" class="block text-sm font-medium text-slate-700 mb-1">Hourly Rate (₱) *</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-amber-100 text-amber-700 font-medium">₱</span>
                    <input type="text" name="hourly_rate" id="hireAgreedSalary" required placeholder="0.00"
                           onblur="formatSalary(this)" onfocus="unformatSalary(this)" onclick="unformatSalary(this)"
                           oninput="limitSalaryInput(this); updateHireProjection();"
                           class="w-full rounded-r-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                </div>
                <p id="hireProjectionText" class="text-xs text-slate-500 mt-2 ml-1 italic"></p>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeHireModal()" class="px-4 py-2 text-slate-600 hover:text-slate-800 transition">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition">
                    Confirm Hire
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Interview Modal -->
<div id="scheduleModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md flex flex-col relative overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-calendar-check text-primary mr-2"></i> Schedule Interview</h3>
            <button onclick="closeScheduleModal()" type="button" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form autocomplete="off" method="POST" action="applications" class="p-5 space-y-4" id="scheduleInterviewForm">
            <input type="hidden" name="action" value="schedule_interview">
            <input type="hidden" name="applicant_id" id="sched_applicant_id">
            <input type="hidden" name="target_stage" id="sched_target_stage">
            
            <p class="text-sm text-slate-600 mb-2">You are moving <strong id="sched_applicant_name" class="text-slate-800"></strong> to <strong id="sched_stage_name" class="text-slate-800"></strong>.</p>
            
            <div class="space-y-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Interview Date *</label>
                    <input type="date" name="interview_date" min="<?= date('Y-m-d') ?>" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Time *</label>
                        <input type="time" name="interview_time" min="08:00" max="17:00" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">End Time *</label>
                        <input type="time" name="interview_end_time" min="08:00" max="18:00" required class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Interviewer</label>
                    <select name="interviewer_id" class="w-full rounded border-slate-300 p-2 focus:ring-primary focus:border-primary">
                        <option value="">-- Select Interviewer --</option>
                        <?php
                            $empStmt = $pdo->query("
                                SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name 
                                FROM employees e 
                                INNER JOIN users u ON u.employee_id = e.id 
                                INNER JOIN roles r ON u.role_id = r.id 
                                WHERE e.status = 'Active' 
                                AND r.name IN ('" . ROLE_BRANCH_MANAGER . "', '" . ROLE_CENTRAL_HR . "', 'Head Barista', '" . ROLE_SUPER_ADMIN . "') 
                                " . get_branch_filter('e') . " 
                                ORDER BY e.first_name
                            ");
                            while ($emp = $empStmt->fetch()) {
                                echo '<option value="' . $emp['id'] . '">' . h($emp['full_name']) . '</option>';
                            }
                        ?>
                    </select>
                </div>
                <div class="flex items-start mt-3">
                    <div class="flex items-center h-5">
                        <input id="send_invite" name="send_invite" type="checkbox" value="1" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" checked>
                    </div>
                    <div class="ml-2 text-sm">
                        <label for="send_invite" class="font-medium text-slate-700">Send Email Invite</label>
                        <p id="sched_applicant_email" class="text-xs text-slate-500 font-normal"></p>
                    </div>
                </div>
            </div>
            <div id="scheduleError" class="text-red-500 text-sm mb-3"></div>
            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="submitWithoutScheduling()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-medium transition">Skip & Move</button>
                <button type="submit" class="px-4 py-2 bg-primary hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition shadow-sm">Schedule & Move</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Reject Modal -->
<div id="customRejectModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 relative">
        <h3 class="font-bold text-red-600 text-lg mb-2"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Confirm Rejection</h3>
        <p class="text-sm text-slate-500 mb-6">Are you sure you want to reject this applicant? This action will move them to the Rejected stage.</p>
        <form method="POST" action="applications">
            <input type="hidden" name="action" value="change_stage">
            <input type="hidden" name="applicant_id" id="rejectApplicantId">
            <input type="hidden" name="new_stage" value="Rejected">
            
            <select name="rejection_reason" class="w-full text-sm rounded border-slate-300 p-2 mb-4 focus:ring-primary focus:border-primary" required>
                <option value="" disabled selected>Select reason for rejection...</option>
                <option value="Did not meet criteria">Did not meet criteria</option>
                <option value="Candidate withdrew">Candidate withdrew</option>
                <option value="No-Show">No-Show / Missed Interview</option>
                <option value="Position filled">Position filled</option>
                <option value="Other">Other</option>
            </select>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-slate-600 hover:text-slate-800 transition">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition">
                    Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notes Modal -->
<div id="notesModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl flex flex-col relative overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-file-lines text-primary mr-2"></i> Evaluation Notes: <span id="notesAppName"></span></h3>
            <button onclick="closeNotesModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4 pb-4 border-b">
                <div><span class="text-xs text-gray-500 block">Technical</span><span class="font-bold" id="modal_tech"></span></div>
                <div><span class="text-xs text-gray-500 block">Communication</span><span class="font-bold" id="modal_comm"></span></div>
                <div><span class="text-xs text-gray-500 block">Reliability</span><span class="font-bold" id="modal_rel"></span></div>
                <div><span class="text-xs text-gray-500 block">Culture Fit</span><span class="font-bold" id="modal_cult"></span></div>
                <div><span class="text-xs text-gray-500 block">Problem Solving</span><span class="font-bold" id="modal_ps"></span></div>
            </div>
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <p class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-2">Strengths</p>
                <p id="notesStrengths" class="text-slate-700 whitespace-pre-wrap text-sm"></p>
            </div>
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <p class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-2">Concerns / Areas for Growth</p>
                <p id="notesConcerns" class="text-slate-700 whitespace-pre-wrap text-sm"></p>
            </div>
        </div>
    </div>
</div>

<script>
    let currentScheduleFormId = null;

    function submitStageChangeForm(formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        fetch('applications.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.status === 'error') {
                    alert(data.message);
                    window.location.reload();
                } else if (data.status === 'success') {
                    window.location.reload();
                }
            } catch (e) {
                // Not JSON (e.g., standard redirect page), just reload
                window.location.reload();
            }
        });
    }

    function handleStageChange(selectElement, applicantId, applicantName, applicantEmail) {
        const newStage = selectElement.value;
        const formId = 'form-stage-' + applicantId;
        
        if (newStage === 'Initial Interview' || newStage === 'Final Interview') {
            currentScheduleFormId = formId;
            // Open the Schedule Modal
            document.getElementById('sched_applicant_id').value = applicantId;
            document.getElementById('sched_target_stage').value = newStage;
            document.getElementById('sched_applicant_name').innerText = applicantName;
            document.getElementById('sched_applicant_email').innerText = applicantEmail || 'No email provided';
            document.getElementById('sched_stage_name').innerText = newStage;
            
            // Auto-Fill Scheduling Defaults
            const dateInput = document.querySelector('#scheduleInterviewForm input[name="interview_date"]');
            const startTimeInput = document.querySelector('#scheduleInterviewForm input[name="interview_time"]');
            const endTimeInput = document.querySelector('#scheduleInterviewForm input[name="interview_end_time"]');
            
            let now = new Date();
            if (now.getHours() >= 17) {
                now.setDate(now.getDate() + 1);
                now.setHours(8, 0, 0, 0);
            } else if (now.getHours() < 8) {
                now.setHours(8, 0, 0, 0);
            } else {
                const roundedMinutes = now.getMinutes() >= 30 ? 60 : 30;
                now.setMinutes(roundedMinutes);
                now.setSeconds(0);
            }
            
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            dateInput.value = `${y}-${m}-${d}`;
            
            const startHours = String(now.getHours()).padStart(2, '0');
            const startMins = String(now.getMinutes()).padStart(2, '0');
            startTimeInput.value = `${startHours}:${startMins}`;
            
            const end = new Date(now.getTime() + 30 * 60000);
            const endHours = String(end.getHours()).padStart(2, '0');
            const endMins = String(end.getMinutes()).padStart(2, '0');
            endTimeInput.value = `${endHours}:${endMins}`;

            document.getElementById('scheduleModal').classList.remove('hidden');
        } else {
            // If it's Hireable, or anything else, submit via AJAX to catch backend state locks
            submitStageChangeForm(formId);
        }
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
        // Reset the select dropdown back to original so they can try again
        if(currentScheduleFormId) {
            const select = document.getElementById(currentScheduleFormId).querySelector('select');
            select.value = '';
        }
    }

    function submitWithoutScheduling() {
        if(currentScheduleFormId) {
            submitStageChangeForm(currentScheduleFormId);
        }
    }

    function attachConflictCheck(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'check_conflict');
            
            fetch('applications.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.conflict) {
                    const errDiv = document.getElementById('scheduleError');
                    if(errDiv) errDiv.innerText = 'Schedule Blocked: Manager is unavailable during this time.';
                    return; 
                } else {
                    if (form.dataset.dragDrop === 'true') {
                        // Submit scheduling payload via AJAX for Drag-and-Drop flow
                        const scheduleData = new FormData(form);
                        scheduleData.append('action', 'schedule_interview'); // Make sure it hits the right backend block
                        fetch('applications.php', {
                            method: 'POST',
                            body: scheduleData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                document.getElementById('scheduleModal').classList.add('hidden');
                                // Move the card in DOM
                                const targetStage = form.dataset.targetCol;
                                const col = document.querySelector(`.kanban-column[data-stage-name="${targetStage}"]`);
                                const applicantId = scheduleData.get('applicant_id');
                                const card = document.querySelector(`.applicant-card[data-applicant-id="${applicantId}"]`);
                                
                                if (col && card) {
                                    const listContainer = col.querySelector('.overflow-y-auto');
                                    const placeholder = listContainer.querySelector('.border-dashed');
                                    if (placeholder) placeholder.remove();
                                    listContainer.appendChild(card);
                                    if(window.updateStageCounts) window.updateStageCounts();
                                }
                                form.dataset.dragDrop = 'false';
                            } else {
                                console.error('Error updating stage: ' + res.message);
                            }
                        })
                        .catch(err => console.error('Network error scheduling applicant.'));
                    } else {
                        form.submit();
                    }
                }
            })
            .catch(err => {
                form.submit();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        attachConflictCheck('scheduleInterviewForm');
        attachConflictCheck('rescheduleInterviewForm');
    });

function openRejectModal(id) {
    document.getElementById('rejectApplicantId').value = id;
    document.getElementById('customRejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('customRejectModal').classList.add('hidden');
}

function openNotesModal(data) {
    document.getElementById('notesAppName').innerText = data.name;
    document.getElementById('modal_tech').innerText = data.tech ? data.tech + '/5' : '-';
    document.getElementById('modal_comm').innerText = data.comm ? data.comm + '/5' : '-';
    document.getElementById('modal_rel').innerText = data.rel ? data.rel + '/5' : '-';
    document.getElementById('modal_cult').innerText = data.cult ? data.cult + '/5' : '-';
    document.getElementById('modal_ps').innerText = data.ps ? data.ps + '/5' : '-';
    document.getElementById('notesStrengths').innerText = data.strengths;
    document.getElementById('notesConcerns').innerText = data.concerns;
    document.getElementById('notesModal').classList.remove('hidden');
}

function closeNotesModal() {
    document.getElementById('notesModal').classList.add('hidden');
}

function openHireModal(id, name, pos, category) {
    document.getElementById('hireApplicantId').value = id;
    document.getElementById('hireName').innerText = name;
    document.getElementById('hireAgreedSalary').value = '';
    
    let posSelect = document.getElementById('hirePosition');
    for (let i = 0; i < posSelect.options.length; i++) {
        if (posSelect.options[i].value === pos) {
            posSelect.selectedIndex = i;
            break;
        }
    }
    
    let catSelect = document.getElementById('hireEmpCategory');
    for (let i = 0; i < catSelect.options.length; i++) {
        if (catSelect.options[i].value === category) {
            catSelect.selectedIndex = i;
            break;
        }
    }
    
    updateHireSalaryLabels();
    updateHirePositionConstraints();
    document.getElementById('hireModal').classList.remove('hidden');
}

function updateHirePositionConstraints() {
    const pos = document.getElementById('hirePosition').value;
    const catSelect = document.getElementById('hireEmpCategory');
    const statSelect = document.querySelector('#hireModal select[name="status"]');
    const branchContainer = document.getElementById('hireBranchContainer');
    const branchSelect = document.getElementById('hireBranch');
    
    if (pos === 'Central HR' || pos === 'Global Accountant' || pos === 'Executive') {
        for (let i = 0; i < catSelect.options.length; i++) {
            if (catSelect.options[i].value === 'Part-Time') catSelect.options[i].disabled = true;
        }
        catSelect.value = 'Full-Time';
        
        for (let i = 0; i < statSelect.options.length; i++) {
            if (statSelect.options[i].value === 'Trainee' || statSelect.options[i].value === 'Probationary') statSelect.options[i].disabled = true;
        }
        statSelect.value = 'Regular';
        
        if (branchContainer && branchSelect) {
            branchContainer.classList.add('hidden');
            branchSelect.removeAttribute('required');
            branchSelect.value = '';
        }
        
    } else {
        for (let i = 0; i < catSelect.options.length; i++) catSelect.options[i].disabled = false;
        for (let i = 0; i < statSelect.options.length; i++) statSelect.options[i].disabled = false;
        
        if (branchContainer && branchSelect) {
            branchContainer.classList.remove('hidden');
            branchSelect.setAttribute('required', 'required');
        }
    }
    updateHireSalaryLabels();
}

document.getElementById('hirePosition').addEventListener('change', updateHirePositionConstraints);

function updateHireSalaryLabels() {
    const cat = document.getElementById('hireEmpCategory').value;
    const agrLabel = document.getElementById('hireAgreedLabel');
    // We strictly use Hourly Rate now
    agrLabel.innerText = 'Hourly Rate (₱) *';
    updateHireProjection();
}

function updateHireProjection() {
    let input = document.getElementById('hireAgreedSalary');
    let val = parseFloat(input.value.replace(/,/g, ''));
    let textObj = document.getElementById('hireProjectionText');
    if (!isNaN(val) && val > 0) {
        let monthly = val * 8 * 22;
        textObj.innerHTML = 'Estimated Monthly Income: <strong class="text-emerald-600">₱' + monthly.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>';
    } else {
        textObj.innerText = '';
    }
}

document.getElementById('hireEmpCategory').addEventListener('change', updateHireSalaryLabels);

function limitSalaryInput(input) {
    let val = input.value.replace(/[^0-9.]/g, '');
    let parts = val.split('.');
    if (parts.length > 2) {
        parts = [parts[0], parts.slice(1).join('')];
    }
    if (parts[0].length > 3) {
        parts[0] = parts[0].substring(0, 3);
    }
    if (parts.length > 1 && parts[1].length > 2) {
        parts[1] = parts[1].substring(0, 2);
    }
    input.value = parts.join('.');
}

function formatSalary(input) {
    if (input.value === '') return;
    let val = input.value.replace(/,/g, '');
    if (isNaN(val) || val === '' || parseFloat(val) === 0) {
        input.value = '';
        return;
    }
    input.value = Number(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function unformatSalary(input) {
    input.value = input.value.replace(/,/g, '');
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filterSearch');
    const positionSelect = document.getElementById('filterPosition');
    const categorySelect = document.getElementById('filterCategory');
    const experienceSelect = document.getElementById('filterExperience');
    const cards = document.querySelectorAll('.applicant-card');

    window.applyFilters = function() {
        const cards = document.querySelectorAll('.applicant-card');
        const search = searchInput ? searchInput.value.toLowerCase() : '';
        const pos = positionSelect ? positionSelect.value : '';
        const cat = categorySelect ? categorySelect.value : '';
        const exp = experienceSelect ? experienceSelect.value : '';
        
        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const cPos = card.getAttribute('data-position');
            const cCat = card.getAttribute('data-category');
            const cExp = card.getAttribute('data-experience');
            
            let match = true;
            if (search && !name.includes(search)) match = false;
            if (pos && cPos !== pos) match = false;
            if (cat && cCat !== cat) match = false;
            if (exp && cExp !== exp) match = false;
            
            card.style.display = match ? 'block' : 'none';
        });
        
        // Update counts in column headers
        document.querySelectorAll('.flex-1.min-w-\\[300px\\]').forEach(col => {
            const visibleCards = col.querySelectorAll('.applicant-card[style="display: block;"], .applicant-card:not([style*="display: none"])');
            const countBadge = col.querySelector('.bg-white.px-2.rounded-full');
            if (countBadge) countBadge.innerText = visibleCards.length;
        });
    }

    if(searchInput) {
        searchInput.addEventListener('input', applyFilters);
        if (positionSelect) positionSelect.addEventListener('change', applyFilters);
        if (categorySelect) categorySelect.addEventListener('change', applyFilters);
        if (experienceSelect) experienceSelect.addEventListener('change', applyFilters);
        
        // Initial application of filters in case values are pre-filled via $_GET
        applyFilters();
    }
});

function closeHireModal() {
    document.getElementById('hireModal').classList.add('hidden');
}
function openEditModal(url) {
    document.getElementById('editIframe').src = url + (url.includes('?') ? '&' : '?') + 'modal=1';
    document.getElementById('editModal').classList.remove('translate-x-full');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('translate-x-full');
    setTimeout(() => {
        document.getElementById('editIframe').src = '';
    }, 300);
}

function openInterviewActionModal(interviewId, applicantName, date, startTime, endTime, interviewerId, interviewerName) {
    // Populate hidden IDs
    document.getElementById('reschedule_interview_id').value = interviewId;
    document.getElementById('noshow_interview_id').value = interviewId;
    
    // Set applicant name & interviewer
    document.getElementById('actionApplicantName').innerText = applicantName;
    document.getElementById('actionInterviewerName').innerText = interviewerName;
    
    // Set Action 1 Link
    document.getElementById('launchWorkspaceBtn').href = 'interview_workspace.php?interview_id=' + interviewId;
    
    // Set Reschedule Form values
    document.getElementById('reschedule_date').value = date;
    document.getElementById('reschedule_start_time').value = startTime;
    document.getElementById('reschedule_end_time').value = endTime;
    
    if (interviewerId) {
        document.getElementById('reschedule_interviewer_id').value = interviewerId;
    }
    
    // Toggle Modal
    document.getElementById('interviewActionModal').classList.remove('hidden');
}

function closeInterviewActionModal() {
    document.getElementById('interviewActionModal').classList.add('hidden');
}
</script>

<!-- Slide-over Drawer Overlay (Hidden by default) -->
<div id="drawerOverlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-40 hidden transition-opacity opacity-0" onclick="closeApplicantDrawer()"></div>

<!-- Slide-over Drawer -->
<div id="applicantDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <!-- Drawer Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <h2 class="font-bold text-lg text-slate-800" id="drawerTitle">Applicant Details</h2>
        <button onclick="closeApplicantDrawer()" class="text-slate-400 hover:text-slate-600 transition"><i class="fa-solid fa-times text-xl"></i></button>
    </div>
    
    <!-- Drawer Content (Loaded via AJAX) -->
    <div id="drawerContent" class="p-6 flex-1 overflow-y-auto space-y-6">
        <div class="flex justify-center items-center h-full text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
        </div>
    </div>
</div>

<script>
const drawer = document.getElementById('applicantDrawer');
const overlay = document.getElementById('drawerOverlay');
const contentContainer = document.getElementById('drawerContent');

function openApplicantDrawer(applicantId, readonly = false) {
    // Show overlay and slide in drawer
    overlay.classList.remove('hidden');
    setTimeout(() => overlay.classList.remove('opacity-0'), 10);
    drawer.classList.remove('translate-x-full');
    
    // Reset content to loader
    contentContainer.innerHTML = '<div class="flex justify-center items-center h-32 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl"></i></div>';
    
    // Fetch payload
    let url = 'api/get_applicant_drawer.php?id=' + applicantId;
    if (readonly) url += '&readonly=1';
    
    fetch(url)
        .then(res => res.text())
        .then(html => {
            contentContainer.innerHTML = html;
        })
        .catch(err => {
            contentContainer.innerHTML = '<div class="text-red-500 text-center"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Failed to load applicant data.</div>';
        });
}

function closeApplicantDrawer() {
    drawer.classList.add('translate-x-full');
    overlay.classList.add('opacity-0');
    setTimeout(() => overlay.classList.add('hidden'), 300);
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.applicant-card[draggable="true"]');
    const columns = document.querySelectorAll('.kanban-column');

    const pipeline = {
        'New': 1,
        'Initial Interview': 2,
        'Final Interview': 3,
        'Rejected': 99
    };

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', card.dataset.applicantId);
            setTimeout(() => card.classList.add('opacity-50'), 0);
        });
        card.addEventListener('dragend', (e) => {
            card.classList.remove('opacity-50');
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            col.classList.add('bg-slate-100');
        });
        
        col.addEventListener('dragleave', (e) => {
            col.classList.remove('bg-slate-100');
        });
        
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('bg-slate-100');
            
            const applicantId = e.dataTransfer.getData('text/plain');
            const targetStage = col.dataset.stageName;
            
            if (!applicantId || !targetStage) return;
            
            const card = document.querySelector(`.applicant-card[data-applicant-id="${applicantId}"]`);
            if (!card) return;

            const currentColumn = card.closest('.kanban-column');
            const currentStage = currentColumn ? currentColumn.dataset.stageName : null;
            
            // Directional Validation
            if (pipeline[targetStage] <= pipeline[currentStage] && targetStage !== 'Rejected') {
                return false; 
            }
            
            // Schedule Modal Interception
            if (targetStage === 'Initial Interview' || targetStage === 'Final Interview') {
                // Populate hidden inputs in your scheduling modal
                document.getElementById('sched_applicant_id').value = applicantId;
                document.getElementById('sched_target_stage').value = targetStage;
                
                document.getElementById('sched_applicant_name').innerText = card.dataset.name;
                document.getElementById('sched_applicant_email').innerText = ''; // Omitted for drag and drop
                document.getElementById('sched_stage_name').innerText = targetStage;
                
                // Auto-Fill Scheduling Defaults
                const dateInput = document.querySelector('#scheduleInterviewForm input[name="interview_date"]');
                const startTimeInput = document.querySelector('#scheduleInterviewForm input[name="interview_time"]');
                const endTimeInput = document.querySelector('#scheduleInterviewForm input[name="interview_end_time"]');
                
                let now = new Date();
                if (now.getHours() >= 17) {
                    now.setDate(now.getDate() + 1);
                    now.setHours(8, 0, 0, 0);
                } else if (now.getHours() < 8) {
                    now.setHours(8, 0, 0, 0);
                } else {
                    const roundedMinutes = now.getMinutes() >= 30 ? 60 : 30;
                    now.setMinutes(roundedMinutes);
                    now.setSeconds(0);
                }
                
                const y = now.getFullYear();
                const m = String(now.getMonth() + 1).padStart(2, '0');
                const d = String(now.getDate()).padStart(2, '0');
                dateInput.value = `${y}-${m}-${d}`;
                
                const startHours = String(now.getHours()).padStart(2, '0');
                const startMins = String(now.getMinutes()).padStart(2, '0');
                startTimeInput.value = `${startHours}:${startMins}`;
                
                const end = new Date(now.getTime() + 30 * 60000);
                const endHours = String(end.getHours()).padStart(2, '0');
                const endMins = String(end.getMinutes()).padStart(2, '0');
                endTimeInput.value = `${endHours}:${endMins}`;

                // Set a flag so the form knows it was triggered by drag drop
                document.getElementById('scheduleInterviewForm').dataset.dragDrop = 'true';
                document.getElementById('scheduleInterviewForm').dataset.targetCol = targetStage;

                document.getElementById('scheduleModal').classList.remove('hidden');
                return;
            }

            card.classList.add('opacity-50');

            let formData = new FormData();
            formData.append('applicant_id', applicantId);
            formData.append('new_stage', targetStage);
            
            fetch('api/update_applicant_stage.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                card.classList.remove('opacity-50');
                if (data.status === 'success') {
                    // Move card DOM
                    const listContainer = col.querySelector('.overflow-y-auto');
                    const placeholder = listContainer.querySelector('.border-dashed');
                    if (placeholder) placeholder.remove();
                    
                    listContainer.appendChild(card);
                    updateStageCounts();
                } else {
                    console.error('Error updating stage: ' + data.message);
                }
            })
            .catch(err => {
                card.classList.remove('opacity-50');
                console.error('Network error moving applicant.');
            });
        });
    });

    window.updateStageCounts = function() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const countBadge = col.querySelector('.bg-white.px-2.rounded-full');
            const colCards = col.querySelectorAll('.applicant-card');
            if (countBadge) countBadge.innerText = colCards.length;
            
            const listContainer = col.querySelector('.overflow-y-auto');
            if (colCards.length === 0 && !listContainer.querySelector('.border-dashed')) {
                listContainer.innerHTML = '<div class="text-center p-6 border-2 border-dashed border-slate-200 rounded-lg text-slate-400 text-sm">No applicants in this stage</div>';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

