<?php
require_once __DIR__ . '/init.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    $country_code = $_POST['country_code'] ?? '+63';
    $phone_raw = trim($_POST['phone_raw'] ?? '');
    $phone = $country_code . ' ' . $phone_raw;
    
    $birthdate = $_POST['birthdate'] ?? null;
    $sex = $_POST['sex'] ?? null;
    $nationality = trim($_POST['nationality'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $employment_category = $_POST['employment_category'] ?? 'Full-Time';
    $position_applied = trim($_POST['position_applied'] ?? '');
    $experience_level = trim($_POST['experience_level'] ?? '');
    
    $allowed_public_roles = ['Barista', 'Head Barista', 'Branch Manager', 'Branch Accountant'];
    $pos = $position_applied;
    $cat = $employment_category;
    $exp = $experience_level;
    
    if (!in_array($pos, $allowed_public_roles)) {
        die("Invalid position selected.");
    }
    
    $birthdate_val = $_POST['birthdate'] ?? null;
    if ($birthdate_val) {
        try {
            $bdate = new DateTime($birthdate_val);
        } catch (Exception $e) {
            die("Error: Invalid birthdate format.");
        }
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
    $preferred_schedule = trim($_POST['preferred_schedule'] ?? 'Any');
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    
    $education_level = trim($_POST['education_level'] ?? '');
    $course_diploma = trim($_POST['course_diploma'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $year_graduated = trim($_POST['year_graduated'] ?? '');
    $education_status = trim($_POST['education_status'] ?? '');
    
    $reference_phone = trim($_POST['reference_phone'] ?? '');
    $reference_name = trim($_POST['reference_name'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($position_applied) || empty($phone_raw) || empty($birthdate) || empty($reference_name) || empty($reference_phone) || empty($branch_id)) {
        $error = 'Please fill out all required fields (*).';
    } else {
        $uploadDir = __DIR__ . '/uploads/applicants/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $valid_id_photo = null;
        $valid_id_back_photo = null;
        $resume = null;
        $allowed_image_mimes = ['image/jpeg', 'image/png'];
        $allowed_resume_mimes = ['application/pdf'];

        if (isset($_FILES['valid_id_photo']) && $_FILES['valid_id_photo']['error'] === UPLOAD_ERR_OK) {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['valid_id_photo']['tmp_name']);
            if (!in_array($mime, $allowed_image_mimes)) {
                $error = 'Valid ID photo must be a JPEG or PNG image.';
            } else {
                $ext = pathinfo($_FILES['valid_id_photo']['name'], PATHINFO_EXTENSION);
                $valid_id_photo = 'id_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['valid_id_photo']['tmp_name'], $uploadDir . $valid_id_photo);
            }
        }

        if (empty($error) && isset($_FILES['valid_id_back_photo']) && $_FILES['valid_id_back_photo']['error'] === UPLOAD_ERR_OK) {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['valid_id_back_photo']['tmp_name']);
            if (!in_array($mime, $allowed_image_mimes)) {
                $error = 'Valid ID back photo must be a JPEG or PNG image.';
            } else {
                $ext = pathinfo($_FILES['valid_id_back_photo']['name'], PATHINFO_EXTENSION);
                $valid_id_back_photo = 'id_back_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['valid_id_back_photo']['tmp_name'], $uploadDir . $valid_id_back_photo);
            }
        }

        if (empty($error) && isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['resume']['tmp_name']);
            if (!in_array($mime, $allowed_resume_mimes)) {
                $error = 'Resume must be a valid PDF document.';
            } else {
                $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
                $resume = 'resume_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $resume);
            }
        }
        
        if (empty($error)) {
            $stmt = $pdo->prepare('INSERT INTO applicants (
                first_name, last_name, email, phone, position_applied, employment_category, 
                education_level, course_diploma, school_name, year_graduated, education_status,
                experience_level, stage, valid_id_photo, valid_id_back_photo, resume,
                birthdate, sex, nationality, address, preferred_schedule, reference_name, reference_phone, branch_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            $inserted = $stmt->execute([
                $first_name, $last_name, $email, $phone, $position_applied, $employment_category, 
                $education_level, $course_diploma, $school_name, $year_graduated, $education_status,
                $experience_level, 'New', 
                $valid_id_photo, $valid_id_back_photo, $resume,
                $birthdate, $sex, $nationality, $address, $preferred_schedule, $reference_name, $reference_phone, $branch_id
            ]);

            if ($inserted) {
                $new_id = $pdo->lastInsertId();
                $logStmt = $pdo->prepare('INSERT INTO applicant_logs (applicant_id, user_name, action) VALUES (?, ?, ?)');
                $logStmt->execute([$new_id, 'System (Online)', 'Applicant submitted online application']);
                
                $success = true;
            } else {
                $error = 'Something went wrong while submitting your application. Please try again.';
            }
        }
    }
}
$companyName = get_setting($pdo, 'company_name', 'Slow Hours Cafe');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers | <?= h($companyName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#d4a373', // Cafe aesthetic
                        secondary: '#1e293b',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .hero-pattern {
            background-color: #1e293b;
            background-image: url('https://images.unsplash.com/photo-1509042239860-f550ce710b93?q=80&w=2000&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
        }
        /* Green highlight for completed fields */
        input:valid:not([type="file"]), select:valid, textarea:valid {
            border-color: #10b981; 
            background-color: #f0fdf4;
        }
        input:invalid:not([type="file"]), select:invalid, textarea:invalid {
            border-color: #e2e8f0;
            background-color: #fff;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fa-solid fa-mug-hot text-primary text-2xl mr-3"></i>
                    <span class="font-bold text-xl tracking-tight text-slate-800"><?= h($companyName) ?></span>
                </div>
                <div class="flex items-center">
                    <a href="login" class="text-slate-500 hover:text-primary transition-colors text-sm font-medium">Employee Portal</a>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($success): ?>
    <div class="flex-1 flex items-center justify-center pt-16 px-4">
        <div class="glass-panel p-10 rounded-2xl max-w-md w-full text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-500 text-4xl shadow-sm">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Application Received!</h2>
            <p class="text-slate-600 mb-8 leading-relaxed">Thank you for applying to <?= h($companyName) ?>. Our HR team will review your application and get back to you soon.</p>
            <a href="careers" class="inline-block bg-primary hover:bg-[#c29161] text-white px-6 py-3 rounded-lg font-medium transition-colors">Submit Another</a>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Hero Section -->
    <div class="hero-pattern pt-32 pb-24 px-4 text-center text-white relative">
        <div class="absolute inset-0 bg-slate-900/60"></div>
        <div class="relative z-10 max-w-3xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4 tracking-tight">Join Our Team</h1>
            <p class="text-lg md:text-xl text-slate-200 font-light">We are looking for passionate individuals who love coffee, pastries, and delivering great customer experiences.</p>
        </div>
    </div>

    <!-- Application Form -->
    <div class="max-w-4xl mx-auto w-full px-4 -mt-12 mb-20 relative z-20">
        <div class="glass-panel rounded-2xl overflow-hidden shadow-lg">
            <div class="p-8 md:p-10 border-b border-slate-200 bg-white">
                <h2 class="text-2xl font-bold text-slate-800">Application Form</h2>
                <p class="text-slate-500 mt-1">Please fill out all the details accurately.</p>
                
                <?php if ($error): ?>
                    <div class="mt-4 bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 flex items-center">
                        <i class="fa-solid fa-circle-exclamation mr-3 text-lg"></i>
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <form action="careers" method="POST" enctype="multipart/form-data" class="p-8 md:p-10 space-y-8 bg-white" autocomplete="off">
                
                <!-- 1. Basic Info Section -->
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                            <input type="text" name="first_name" required minlength="2" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" required minlength="2" class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                            <input type="email" name="email" required class="w-full rounded-lg border-slate-300 border p-2.5 focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                            <div class="flex">
                                <select name="country_code" required class="rounded-l-lg border-slate-300 border-r-0 bg-slate-50 border focus:ring-primary focus:border-primary transition-colors py-2.5 pl-2 pr-6">
                                    <option value="+63" selected>+63 (PH)</option>
                                    <option value="+1">+1 (US)</option>
                                    <option value="+44">+44 (UK)</option>
                                </select>
                                <input type="tel" name="phone_raw" maxlength="10" required oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                       class="w-full rounded-r-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors" placeholder="XXXXXXXXXX">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Birthdate *</label>
                            <?php $maxDate = date('Y-m-d', strtotime('-16 years')); ?>
                            <input type="date" name="birthdate" id="apply_birthdate" required max="<?= $maxDate ?>"
                                   class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                            <span id="birthdateError" class="text-xs text-red-500 mt-1 hidden block"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sex</label>
                            <select name="sex" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="">Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nationality</label>
                            <input type="text" name="nationality" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                            <input type="text" name="address" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                        </div>
                    </div>
                </div>

                <!-- 2. Application Details -->
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Application Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Employment Category *</label>
                            <select name="employment_category" id="apply_category" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="" disabled selected>Select Category...</option>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Target Location *</label>
                            <select name="branch_id" id="branchSelect" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="">Select Location...</option>
                                <?php
                                    $branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name");
                                    while ($branch = $branchStmt->fetch()) {
                                        echo '<option value="' . $branch['id'] . '">' . h($branch['name']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Position Applied *</label>
                            <select name="position_applied" required id="apply_position" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="" disabled selected>Select Position...</option>
                                <option value="Barista">Barista</option>
                                <option value="Head Barista">Head Barista</option>
                                <option value="Branch Manager">Branch Manager</option>
                                <option value="Branch Accountant">Branch Accountant</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Experience Level *</label>
                            <select name="experience_level" required id="apply_experience" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="" disabled selected>Select Experience...</option>
                                <option value="No Experience">No Experience</option>
                                <option value="<= 1 Year"><= 1 Year</option>
                                <option value="<= 3 Years"><= 3 Years</option>
                                <option value="<= 5 Years"><= 5 Years</option>
                                <option value="<= 10 Years"><= 10 Years</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Preferred Schedule *</label>
                            <select name="preferred_schedule" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="Any" selected>Any Schedule</option>
                                <option value="Morning">Morning Shift</option>
                                <option value="Mid">Mid Shift</option>
                                <option value="Night">Night / Closing</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- 3. Educational Background -->
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider border-b border-slate-100 pb-2">Educational Background</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Education Level *</label>
                            <select name="education_level" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                <option value="">Select Level...</option>
                                <option value="Highschool">Highschool</option>
                                <option value="Senior High">Senior High</option>
                                <option value="College">College</option>
                                <option value="Post-Graduate">Post-Graduate</option>
                                <option value="None">None</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Course / Diploma</label>
                            <input type="text" name="course_diploma" placeholder="e.g. BS Computer Science" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">School Name *</label>
                            <input type="text" name="school_name" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                        </div>
                        <div class="grid grid-cols-2 gap-3 md:col-span-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Year (Grad/Last Attended)</label>
                                <select name="year_graduated" class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                    <option value="">Select Year...</option>
                                    <?php 
                                        $currYear = date('Y');
                                        for($y = $currYear; $y >= 1980; $y--):
                                    ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Status *</label>
                                <select name="education_status" required class="w-full rounded-lg border-slate-300 p-2.5 bg-white border focus:ring-primary focus:border-primary transition-colors">
                                    <option value="">Select...</option>
                                    <option value="Graduated">Graduated</option>
                                    <option value="Undergraduate">Undergraduate</option>
                                    <option value="Enrolled">Enrolled</option>
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
                            <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Valid ID (Front) *</label>
                            <input type="file" name="valid_id_photo" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Valid ID (Back) *</label>
                            <input type="file" name="valid_id_back_photo" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1 text-xs">Resume / CV *</label>
                            <input type="file" name="resume" accept=".pdf" required class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
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
                            <input type="text" name="reference_name" class="form-input w-full border-gray-300 rounded-md bg-white p-2.5 border" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Contact Phone *</label>
                            <input type="text" name="reference_phone" class="form-input w-full border-gray-300 rounded-md bg-white p-2.5 border" required>
                        </div>
                    </div>
                </div>

                <div class="pt-6 text-right border-t border-slate-200 mt-8">
                    <button type="submit" class="bg-primary hover:bg-[#c29161] text-white px-8 py-3 rounded-lg font-bold text-lg shadow-md transition-all transform hover:-translate-y-0.5">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const positionSelect = document.getElementById('apply_position');
    const categorySelect = document.getElementById('apply_category');
    const experienceSelect = document.getElementById('apply_experience');
    const birthdateInput = document.getElementById('apply_birthdate');

    function calculateAge(dobString) {
        if (!dobString) return 0;
        const today = new Date();
        const birthDate = new Date(dobString);
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    function enforceLockdowns() {
        const pos = positionSelect.value;
        const age = calculateAge(birthdateInput.value);
        const errorSpan = document.getElementById('birthdateError');

        if (errorSpan) {
            errorSpan.classList.add('hidden');
            errorSpan.innerText = '';
        }

        // Reset disabled states to start fresh on each change
        Array.from(categorySelect.options).forEach(opt => opt.disabled = false);
        Array.from(experienceSelect.options).forEach(opt => opt.disabled = false);
        Array.from(positionSelect.options).forEach(opt => opt.disabled = false);

        if (pos === 'Head Barista' || pos === 'Branch Manager' || pos === 'Branch Accountant') {
            // Force Full-Time
            categorySelect.value = 'Full-Time';
            Array.from(categorySelect.options).forEach(opt => {
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

        // Age Verification
        if (birthdateInput.value) {
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
                
                // Minors can only be Part-Time Baristas
                if (pos !== 'Barista' && pos !== '') {
                    if (errorSpan) {
                        errorSpan.innerText = "Management and financial roles require applicants to be 18 or older.";
                        errorSpan.classList.remove('hidden');
                    }
                    positionSelect.value = 'Barista';
                    enforceLockdowns(); // Re-trigger
                } else {
                    categorySelect.value = 'Part-Time';
                    Array.from(categorySelect.options).forEach(opt => {
                        if (opt.value === 'Full-Time') opt.disabled = true;
                    });
                }
            }
        }
    }

    positionSelect.addEventListener('change', enforceLockdowns);
    birthdateInput.addEventListener('change', enforceLockdowns);
    
    // Validate one last time before submission
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            enforceLockdowns(); // Run rules
            const errorSpan = document.getElementById('birthdateError');
            if (errorSpan && !errorSpan.classList.contains('hidden')) {
                e.preventDefault();
                errorSpan.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        });
    }
});
    </script>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-slate-900 py-8 text-center text-slate-400 mt-auto">
        <p>&copy; <?= date('Y') ?> <?= h($companyName) ?>. All rights reserved.</p>
    </footer>
</body>
</html>
