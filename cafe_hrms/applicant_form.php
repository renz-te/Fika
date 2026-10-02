<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modal = isset($_GET['modal']) ? (int)$_GET['modal'] : 0;

$user_role = $_SESSION['user']['role'] ?? 'Branch Manager';
$positions = [];
if ($user_role === 'Super Admin' || $user_role === 'System Admin' || $user_role === 'Central HR') {
    $positions = ['Barista', 'Head Barista', 'Branch Manager', 'Branch Accountant'];
} else {
    $positions = ['Barista', 'Head Barista'];
}

$applicant = [
    'id' => 0,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'position_applied' => '',
    'notes' => '',
    'resume' => '',
    'experience_level' => '',
    'valid_id_photo' => '',
    'valid_id_back_photo' => '',
    'reference_name' => '',
    'reference_phone' => '',
    'employment_category' => 'Full-Time',
    'education_level' => '',
    'school_name' => '',
    'school_address' => '',
    'year_graduated' => '',
    'education_status' => '',
    'course_diploma' => '',
    'birthdate' => '',
    'address' => '',
    'sex' => '',
    'nationality' => '',
    'preferred_schedule' => 'Any'
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM applicants WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $applicant = $existing;
    } else {
        flash('error', 'Applicant not found.');
        redirect('applications');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_note') {
        header('Content-Type: application/json');
        try {
            $note = trim($_POST['notes'] ?? '');
            if ($note !== '') {
                $stmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())');
                $action = "Evaluation Note: " . $note;
                $stmt->execute([$id, $_SESSION['user']['name'] ?? 'System', $action]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Note cannot be empty.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_raw = trim($_POST['phone_raw'] ?? '');
    $country_code = $_POST['country_code'] ?? '+63';
    $phone = $phone_raw !== '' ? $country_code . ' ' . $phone_raw : '';
    $position_applied = trim($_POST['position_applied'] ?? '');
    
    if (!in_array($position_applied, $positions)) {
        die("Error: Unauthorized position selection for current session role.");
    }
    
    $pos = $position_applied;
    $cat = trim($_POST['employment_category'] ?? 'Full-Time');
    $exp = trim($_POST['experience_level'] ?? '');
    
    $birthdate_val = trim($_POST['birthdate'] ?? '');
    if ($birthdate_val !== '') {
        $bdate = new DateTime($birthdate_val);
        $today = new DateTime('today');
        $age = $bdate->diff($today)->y;
        
        if ($age < 16) {
            die("Error: Minimum age requirement is 16.");
        }
        if ($age < 18 && ($pos !== 'Barista' || $cat === 'Full-Time')) {
            die("Error: Applicants under 18 are restricted to Part-Time Barista positions.");
        }
    }
    
    if (($pos === 'Head Barista' || $pos === 'Branch Accountant') && $exp === 'No Experience') {
        die("Error: This position requires prior experience.");
    }
    if ($pos === 'Branch Manager' && ($exp === 'No Experience' || $exp === '<= 1 Year')) {
        die("Error: Branch Manager requires a minimum of 3 years experience.");
    }
    
    $notes = trim($_POST['notes'] ?? '');
    $stage = $_POST['stage'] ?? 'New';
    $reference_name = trim($_POST['reference_name'] ?? '');
    $reference_phone = trim($_POST['reference_phone'] ?? '');
    $employment_category = trim($_POST['employment_category'] ?? 'Full-Time');
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;

    $education_level = trim($_POST['education_level'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $year_graduated = trim($_POST['year_graduated'] ?? '');
    $education_status = trim($_POST['education_status'] ?? '');
    $course_diploma = trim($_POST['course_diploma'] ?? '');
    $experience_level = trim($_POST['experience_level'] ?? '');
    
    $birthdate = trim($_POST['birthdate'] ?? '');
    if ($birthdate === '') $birthdate = null;
    $address = trim($_POST['address'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');
    $preferred_schedule = trim($_POST['preferred_schedule'] ?? 'Any');
    
    $errors = [];
    if ($first_name === '' || $last_name === '') $errors[] = 'First Name and Last Name are required.';
    if ($email === '') $errors[] = 'Email is required.';
    if ($position_applied === '') $errors[] = 'Position Applied is required.';
    
    $valid_id_photo = $applicant['valid_id_photo'] ?? '';
    $valid_id_back_photo = $applicant['valid_id_back_photo'] ?? '';
    $resume = $applicant['resume'] ?? '';

    if (empty($errors)) {
        // Handle file uploads
        $uploadDir = __DIR__ . '/uploads/applicants/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['valid_id_photo']) && $_FILES['valid_id_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['valid_id_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'id_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['valid_id_photo']['tmp_name'], $uploadDir . $filename);
            $valid_id_photo = $filename;
        }

        if (isset($_FILES['valid_id_back_photo']) && $_FILES['valid_id_back_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['valid_id_back_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'id_back_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['valid_id_back_photo']['tmp_name'], $uploadDir . $filename);
            $valid_id_back_photo = $filename;
        }

        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = 'Resume must be a PDF document.';
            } else {
                $filename = 'resume_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $filename);
                $resume = $filename;
            }
        }

        $user_name = $_SESSION['user']['name'] ?? 'System';

        if ($id) {
            // Check if stage changed
            $stageChanged = ($applicant['stage'] ?? '') !== $stage;
            $actionText = $stageChanged ? "Moved to $stage" : "Updated profile";
            
            $stmt = $pdo->prepare('UPDATE applicants SET first_name=?, last_name=?, email=?, phone=?, position_applied=?, notes=?, stage=?, valid_id_photo=?, valid_id_back_photo=?, resume=?, reference_name=?, reference_phone=?, employment_category=?, education_level=?, school_name=?, school_address=?, year_graduated=?, education_status=?, course_diploma=?, experience_level=?, birthdate=?, address=?, sex=?, nationality=?, preferred_schedule=?, branch_id=? WHERE id=?');
            $stmt->execute([$first_name, $last_name, $email, $phone, $position_applied, $notes, $stage, $valid_id_photo, $valid_id_back_photo, $resume, $reference_name, $reference_phone, $employment_category, $education_level, $school_name, $school_address, $year_graduated, $education_status, $course_diploma, $experience_level, $birthdate, $address, $sex, $nationality, $preferred_schedule, $branch_id, $id]);
            
            // Insert log
            $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
            $logStmt->execute([$id, $user_name, $actionText]);
            
            flash('success', 'Applicant updated successfully.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO applicants (first_name, last_name, email, phone, position_applied, notes, stage, valid_id_photo, valid_id_back_photo, resume, reference_name, reference_phone, employment_category, education_level, school_name, school_address, year_graduated, education_status, course_diploma, experience_level, birthdate, address, sex, nationality, preferred_schedule, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$first_name, $last_name, $email, $phone, $position_applied, $notes, $stage, $valid_id_photo, $valid_id_back_photo, $resume, $reference_name, $reference_phone, $employment_category, $education_level, $school_name, $school_address, $year_graduated, $education_status, $course_diploma, $experience_level, $birthdate, $address, $sex, $nationality, $preferred_schedule, $branch_id]);
            
            $new_id = $pdo->lastInsertId();
            // Insert log
            $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
            $logStmt->execute([$new_id, $user_name, 'Added Applicant']);
            
            flash('success', 'Applicant added successfully.');
        }
        
        if ($modal) {
            echo "<script>window.parent.closeEditModal();</script>";
            exit;
        } else {
            redirect('applications');
        }
    }
}

$pageTitle = $id ? 'Edit Applicant' : 'New Applicant';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($modal): ?>
<style>
    /* Hide the top navbar and sidebar when inside modal */
    header, aside { display: none !important; }
    main { padding: 1rem !important; background: white !important; }
    body, html { background: white !important; }
    
    /* Green highlight for completed fields */
    input:valid, select:valid, textarea:valid {
        border-color: #10b981; /* Tailwind emerald-500 */
        background-color: #f0fdf4; /* Tailwind emerald-50 */
    }
    input:invalid, select:invalid, textarea:invalid {
        border-color: #e2e8f0;
        background-color: #f8fafc;
    }
    /* Don't highlight empty file inputs */
    input[type="file"]:valid {
        border-color: transparent;
        background-color: transparent;
    }
</style>
<?php endif; ?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800"><?= h($pageTitle) ?></h1>
        <?php if (!$modal): ?>
        <a href="applications" class="text-slate-500 hover:text-slate-700 transition">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Applications
        </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 shadow-sm border border-red-100">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php 
        $stored_phone = $_POST['phone_raw'] ?? $applicant['phone'];
        $c_code = '+63';
        $p_num = $stored_phone;
        if (preg_match('/^(\+\d+)\s+(.*)$/', $stored_phone, $matches)) {
            $c_code = $matches[1];
            $p_num = $matches[2];
        }
    ?>
    <form autocomplete="off" method="POST" enctype="multipart/form-data" autocomplete="off" class="bg-white <?= $modal ? '' : 'rounded-xl shadow-sm border border-slate-200' ?> p-6 space-y-8">
            
            <!-- 1. Basic Info Section -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                        <input type="text" name="first_name" value="<?= h($_POST['first_name'] ?? $applicant['first_name']) ?>" required minlength="2"
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                        <input type="text" name="last_name" value="<?= h($_POST['last_name'] ?? $applicant['last_name']) ?>" required minlength="2"
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input type="email" name="email" value="<?= h($_POST['email'] ?? $applicant['email']) ?>" required
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                        <div class="flex">
                            <select name="country_code" required class="rounded-l-lg border-slate-300 border-r-0 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors py-2 pl-2 pr-6">
                                <option value="+63" <?= $c_code === '+63' ? 'selected' : '' ?>>+63 (PH)</option>
                                <option value="+1" <?= $c_code === '+1' ? 'selected' : '' ?>>+1 (US)</option>
                                <option value="+44" <?= $c_code === '+44' ? 'selected' : '' ?>>+44 (UK)</option>
                            </select>
                            <input type="tel" name="phone_raw" value="<?= h($p_num) ?>" maxlength="10" required oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                   class="w-full rounded-r-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors" placeholder="XXXXXXXXXX">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Birthdate *</label>
                        <div class="flex items-center gap-3">
                            <?php
                                // Max date is 16 years ago from today
                                $maxDate = date('Y-m-d', strtotime('-16 years'));
                                $isEdit = $id != 0;
                            ?>
                            <input type="date" name="birthdate" id="birthdateInput" value="<?= h($_POST['birthdate'] ?? $applicant['birthdate'] ?? '') ?>" required max="<?= $maxDate ?>"
                                   <?= $isEdit ? 'readonly class="w-full rounded-lg border-slate-300 p-2 bg-slate-100 text-slate-500 border focus:ring-primary focus:border-primary transition-colors pointer-events-none"' : 'class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors"' ?>>
                            <span id="ageDisplay" class="text-sm text-slate-500 font-medium whitespace-nowrap hidden">0 yrs</span>
                        </div>
                        <span id="birthdateError" class="text-xs text-red-500 mt-1 hidden block"></span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sex</label>
                        <?php $currSex = $_POST['sex'] ?? $applicant['sex'] ?? ''; ?>
                        <select name="sex" class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="">Select...</option>
                            <option value="Male" <?= $currSex === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $currSex === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nationality</label>
                        <input type="text" name="nationality" value="<?= h($_POST['nationality'] ?? $applicant['nationality'] ?? '') ?>"
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" name="address" value="<?= h($_POST['address'] ?? $applicant['address'] ?? '') ?>"
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                </div>
            </div>

            <!-- 2. Application Details -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Application Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employment Category *</label>
                        <?php $currCat = $_POST['employment_category'] ?? $applicant['employment_category'] ?? 'Full-Time'; ?>
                        <select name="employment_category" id="empCategorySelect" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="Full-Time" <?= $currCat === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                            <option value="Part-Time" <?= $currCat === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Target Location *</label>
                        <?php $currBranch = $_POST['branch_id'] ?? $applicant['branch_id'] ?? ''; ?>
                        <select name="branch_id" id="branchSelect" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="">Select Location...</option>
                            <?php
                                $branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name");
                                while ($branch = $branchStmt->fetch()) {
                                    $selected = ($currBranch == $branch['id']) ? 'selected' : '';
                                    echo '<option value="' . $branch['id'] . '" ' . $selected . '>' . h($branch['name']) . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Position Applied *</label>
                        <?php $currPos = $_POST['position_applied'] ?? $applicant['position_applied']; ?>
                        <select name="position_applied" id="positionSelect" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="" disabled <?= empty($currPos) ? 'selected' : '' ?>>Select Position...</option>
                            <?php foreach($positions as $pos): ?>
                                <option value="<?= h($pos) ?>" <?= $currPos === $pos ? 'selected' : '' ?>><?= h($pos) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Experience Level *</label>
                        <?php $currExp = $_POST['experience_level'] ?? $applicant['experience_level'] ?? ''; ?>
                        <select name="experience_level" id="experienceSelect" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="">Select Experience...</option>
                            <?php foreach(['No Experience', '<= 1 Year', '<= 3 Years', '<= 5 Years', '<= 10 Years'] as $exp): ?>
                                <option value="<?= h($exp) ?>" <?= $currExp === $exp ? 'selected' : '' ?>><?= h($exp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Preferred Schedule *</label>
                        <?php $currSched = $_POST['preferred_schedule'] ?? $applicant['preferred_schedule'] ?? 'Any'; ?>
                        <select name="preferred_schedule" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="Any" <?= $currSched === 'Any' ? 'selected' : '' ?>>Any Schedule</option>
                            <option value="Morning" <?= $currSched === 'Morning' ? 'selected' : '' ?>>Morning Shift</option>
                            <option value="Mid" <?= $currSched === 'Mid' ? 'selected' : '' ?>>Mid Shift</option>
                            <option value="Night" <?= $currSched === 'Night' ? 'selected' : '' ?>>Night / Closing</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current Stage</label>
                        <?php $currStage = $_POST['stage'] ?? ($applicant['stage'] ?? 'New'); ?>
                        <div class="w-full rounded-lg border-slate-300 p-2 bg-slate-100 border text-slate-500 cursor-not-allowed">
                            <?= h($currStage) ?>
                        </div>
                        <input type="hidden" name="stage" value="<?= h($currStage) ?>">
                    </div>
                    <div class="md:col-span-2 hidden" id="expectedSalaryContainer">
                        <!-- Removed salary inputs to ensure they only appear in the Onboard modal -->
                    </div>
                </div>
            </div>

            <!-- 3. Educational Background -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Educational Background</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Education Level *</label>
                        <?php $currEdu = $_POST['education_level'] ?? $applicant['education_level']; ?>
                        <select name="education_level" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                            <option value="">Select Level...</option>
                            <?php foreach(['Highschool', 'Senior High', 'College', 'Post-Graduate', 'None'] as $lvl): ?>
                                <option value="<?= h($lvl) ?>" <?= $currEdu === $lvl ? 'selected' : '' ?>><?= h($lvl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Course / Diploma</label>
                        <input type="text" name="course_diploma" value="<?= h($_POST['course_diploma'] ?? $applicant['course_diploma']) ?>" placeholder="e.g. BS Computer Science"
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">School Name *</label>
                        <input type="text" name="school_name" value="<?= h($_POST['school_name'] ?? $applicant['school_name']) ?>" required
                               class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                    </div>
                    <div class="grid grid-cols-2 gap-3 md:col-span-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Year (Grad/Last Attended)</label>
                            <?php $currYearGrad = $_POST['year_graduated'] ?? $applicant['year_graduated'] ?? ''; ?>
                            <select name="year_graduated" class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                                <option value="">Select Year...</option>
                                <?php 
                                    $currYear = date('Y');
                                    for($y = $currYear; $y >= 1980; $y--):
                                ?>
                                    <option value="<?= $y ?>" <?= (string)$currYearGrad === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                            <?php $currSts = $_POST['education_status'] ?? $applicant['education_status']; ?>
                            <select name="education_status" required class="w-full rounded-lg border-slate-300 p-2 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors">
                                <option value="">Select...</option>
                                <?php foreach(['Graduated', 'Undergraduate', 'Enrolled'] as $sts): ?>
                                    <option value="<?= h($sts) ?>" <?= $currSts === $sts ? 'selected' : '' ?>><?= h($sts) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Requirements & Documents -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Documents</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Valid ID (Front) <?= empty($applicant['valid_id_photo']) ? '*' : '' ?></label>
                        <input type="file" name="valid_id_photo" accept="image/*" <?= empty($applicant['valid_id_photo']) ? 'required' : '' ?> class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                        <?php if(!empty($applicant['valid_id_photo'])): ?>
                            <div class="mt-2 flex items-center justify-between bg-slate-50 p-2 rounded border border-slate-200">
                                <span class="text-xs text-green-600"><i class="fa-solid fa-check-circle"></i> Uploaded</span>
                                <a href="file_proxy.php?file=applicants/<?= h($applicant['valid_id_photo']) ?>" target="_blank" class="text-xs text-indigo-600 hover:underline"><i class="fa-solid fa-eye"></i> View File</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Valid ID (Back) <?= empty($applicant['valid_id_back_photo']) ? '*' : '' ?></label>
                        <input type="file" name="valid_id_back_photo" accept="image/*" <?= empty($applicant['valid_id_back_photo']) ? 'required' : '' ?> class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                        <?php if(!empty($applicant['valid_id_back_photo'])): ?>
                            <div class="mt-2 flex items-center justify-between bg-slate-50 p-2 rounded border border-slate-200">
                                <span class="text-xs text-green-600"><i class="fa-solid fa-check-circle"></i> Uploaded</span>
                                <a href="file_proxy.php?file=applicants/<?= h($applicant['valid_id_back_photo']) ?>" target="_blank" class="text-xs text-indigo-600 hover:underline"><i class="fa-solid fa-eye"></i> View File</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Resume / CV <?= empty($applicant['resume']) ? '*' : '' ?></label>
                        <input type="file" name="resume" accept=".pdf" <?= empty($applicant['resume']) ? 'required' : '' ?> class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                        <?php if(!empty($applicant['resume'])): ?>
                            <div class="mt-2 flex items-center justify-between bg-slate-50 p-2 rounded border border-slate-200">
                                <span class="text-xs text-green-600"><i class="fa-solid fa-check-circle"></i> Uploaded</span>
                                <a href="file_proxy.php?file=applicants/<?= h($applicant['resume']) ?>" target="_blank" class="text-xs text-indigo-600 hover:underline"><i class="fa-solid fa-eye"></i> View File</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 5. Professional Reference -->
            <div class="p-4 border border-gray-200 rounded-md bg-gray-50 mb-4 mt-4 relative z-10">
                <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase">
                    Professional Reference (Required)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Reference Name *</label>
                        <input type="text" name="reference_name" value="<?= h($_POST['reference_name'] ?? $applicant['reference_name'] ?? '') ?>" class="form-input w-full border-gray-300 rounded-md p-2 bg-white border" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Contact Phone *</label>
                        <input type="text" name="reference_phone" value="<?= h($_POST['reference_phone'] ?? $applicant['reference_phone'] ?? '') ?>" class="form-input w-full border-gray-300 rounded-md p-2 bg-white border" required>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end pt-4 border-t border-slate-100">
                <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg font-medium hover:bg-indigo-700 transition shadow-sm">
                    <?= $id ? 'Update Applicant' : 'Save Applicant' ?>
                </button>
            </div>
        </form>
        
        <?php if ($id): ?>
        

        
        <?php
            $logStmt = $pdo->prepare('SELECT * FROM applicant_logs WHERE applicant_id = ? ORDER BY created_at DESC');
            $logStmt->execute([$id]);
            $logs = $logStmt->fetchAll();
        ?>
        <div class="mt-8 bg-slate-50 rounded-xl border border-slate-200 p-6">
            <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Application History & Audit Log
            </h3>
            
            <?php if(empty($logs)): ?>
                <p class="text-sm text-slate-500 italic">No history found for this applicant.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach($logs as $log): ?>
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-primary mt-1.5"></div>
                            <div class="w-0.5 h-full bg-slate-200 mt-1"></div>
                        </div>
                        <div class="pb-2">
                            <p class="text-sm font-medium text-slate-800"><?= h($log['action']) ?></p>
                            <p class="text-xs text-slate-500">
                                By <span class="font-semibold"><?= h($log['user_name']) ?></span> on <?= date('M j, Y h:i A', strtotime($log['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function submitEvaluation(e) {
    e.preventDefault();
    const btn = document.getElementById('evalSubmitBtn');
    const noteInput = document.getElementById('evalNotes');
    const feedback = document.getElementById('evalFeedback');
    
    if (!noteInput.value.trim()) return;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'add_note');
        formData.append('notes', noteInput.value.trim());
        
        const response = await fetch('applicant_form.php?id=<?= $id ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            noteInput.value = '';
            feedback.className = 'mt-2 text-sm text-green-600 font-medium';
            feedback.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> Evaluation added to history.';
            feedback.classList.remove('hidden');
            setTimeout(() => { window.location.reload(); }, 1500); // Reload to show updated history
        } else {
            throw new Error(result.error || 'Unknown error occurred.');
        }
    } catch (err) {
        feedback.className = 'mt-2 text-sm text-red-600 font-medium';
        feedback.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + err.message;
        feedback.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> Submit Evaluation';
    }
}

function formatSalary(input) {
    if (input.value === '') return;
    let val = input.value.replace(/,/g, '');
    if (isNaN(val) || val === '' || parseFloat(val) === 0) {
        input.value = '';
        return;
    }
    let numVal = parseFloat(val);
    if (numVal > 999) {
        alert('Maximum allowed hourly rate is ₱999');
        input.value = '999.00';
        numVal = 999;
    }
    input.value = Number(numVal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function unformatSalary(input) {
    input.value = input.value.replace(/,/g, '');
}

document.addEventListener('DOMContentLoaded', function() {
    const empCategorySelect = document.getElementById('empCategorySelect');
    const birthdateInput = document.getElementById('birthdateInput');
    const ageDisplay = document.getElementById('ageDisplay');

    function toggleSalaryLabels() {
        // Labels are fixed to Hourly Rate now
    }
    
    const positionSelect = document.getElementById('positionSelect');
    
    function updateCategoryOptions() {
        const pos = positionSelect.value;
        const experienceSelect = document.getElementById('experienceSelect');
        const errorSpan = document.getElementById('birthdateError');

        if (errorSpan) {
            errorSpan.classList.add('hidden');
            errorSpan.innerText = '';
        }
        
        // Reset disabled states
        Array.from(empCategorySelect.options).forEach(opt => opt.disabled = false);
        Array.from(experienceSelect.options).forEach(opt => opt.disabled = false);
        Array.from(positionSelect.options).forEach(opt => opt.disabled = false);

        if (pos === 'Head Barista' || pos === 'Branch Manager' || pos === 'Branch Accountant') {
            // Force Full-Time
            empCategorySelect.value = 'Full-Time';
            Array.from(empCategorySelect.options).forEach(opt => {
                if (opt.value === 'Part-Time') opt.disabled = true;
            });

            // Restrict "No Experience"
            Array.from(experienceSelect.options).forEach(opt => {
                if (opt.value === 'No Experience') opt.disabled = true;
            });

            // Extra lockdown for Branch Manager (Require > 1 Year)
            if (pos === 'Branch Manager') {
                Array.from(experienceSelect.options).forEach(opt => {
                    if (opt.value === '<= 1 Year') opt.disabled = true;
                });
                if (experienceSelect.value === 'No Experience' || experienceSelect.value === '<= 1 Year') {
                    experienceSelect.value = ''; // Reset invalid selection
                }
            } else if (experienceSelect.value === 'No Experience') {
                experienceSelect.value = ''; 
            }
        }
        
        let age = 99;
        if (birthdateInput.value) {
            const birthDate = new Date(birthdateInput.value);
            const today = new Date();
            age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 16) {
                if (errorSpan) {
                    errorSpan.innerText = "Applicants must be at least 16 years old.";
                    errorSpan.classList.remove('hidden');
                }
                birthdateInput.value = '';
            } else if (age < 18) {
                // Disable management roles
                Array.from(positionSelect.options).forEach(opt => {
                    if (['Head Barista', 'Branch Manager', 'Branch Accountant'].includes(opt.value)) {
                        opt.disabled = true;
                    }
                });
                
                if (pos !== 'Barista' && pos !== '') {
                    if (errorSpan) {
                        errorSpan.innerText = "Management and financial roles require applicants to be 18 or older.";
                        errorSpan.classList.remove('hidden');
                    }
                    positionSelect.value = 'Barista';
                    return updateCategoryOptions(); // retrigger
                } else {
                    empCategorySelect.value = 'Part-Time';
                    Array.from(empCategorySelect.options).forEach(opt => {
                        if (opt.value === 'Full-Time') opt.disabled = true;
                    });
                }
            }
        }
        
        toggleSalaryLabels();
    }
    
    function calculateAge() {
        if (!birthdateInput.value) {
            ageDisplay.classList.add('hidden');
            updateCategoryOptions();
            return;
        }
        const birthDate = new Date(birthdateInput.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        ageDisplay.innerText = age + ' yrs';
        ageDisplay.classList.remove('hidden');

        updateCategoryOptions();
    }
    


    positionSelect.addEventListener('change', updateCategoryOptions);
    
    // Branch logic
    positionSelect.addEventListener('change', function() {
        const pos = this.value;
        const branchSelect = document.getElementById('branchSelect');
        const centralRoles = ['Central HR', 'Global Accountant', 'Executive'];
        if (centralRoles.includes(pos)) {
            branchSelect.value = '';
            branchSelect.disabled = true;
            branchSelect.removeAttribute('required');
        } else {
            branchSelect.disabled = false;
            branchSelect.setAttribute('required', 'required');
        }
    });

    empCategorySelect.addEventListener('change', toggleSalaryLabels);
    birthdateInput.addEventListener('change', calculateAge);
    
    // Validate one last time before submission
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            updateCategoryOptions(); // Run rules
            const errorSpan = document.getElementById('birthdateError');
            if (errorSpan && !errorSpan.classList.contains('hidden')) {
                e.preventDefault();
                errorSpan.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        });
    }
    
    toggleSalaryLabels(); // Run on load
    calculateAge(); // Run on load
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

