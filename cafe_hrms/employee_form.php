<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'HR Admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modal = isset($_GET['modal']) ? (int)$_GET['modal'] : 0;
$showSidebar = !$modal;

$employee = [
    'id' => 0,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'position' => '',
    'status' => 'Active',
    'employment_category' => 'Full-Time',
    'hourly_rate' => 0,
    'birthdate' => '',
    'address' => '',
    'sex' => '',
    'nationality' => '',
    'emergency_contact_name' => '',
    'emergency_contact_phone' => '',
    'valid_id_photo' => '',
    'valid_id_back_photo' => ''
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) $employee = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $employment_category = $_POST['employment_category'] ?? 'Full-Time';
    $hourly_rate = (float)str_replace(',', '', $_POST['hourly_rate'] ?? 0);
    $birthdate = trim($_POST['birthdate'] ?? '');
    if ($birthdate === '') $birthdate = null;
    $address = trim($_POST['address'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    
    $valid_id_photo = $employee['valid_id_photo'] ?? '';
    $valid_id_back_photo = $employee['valid_id_back_photo'] ?? '';
    
    $preferred_schedule = trim($_POST['preferred_schedule'] ?? 'Any');
    
    // Handle file uploads
    $uploadDir = __DIR__ . '/uploads/applicants/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['valid_id_photo']) && $_FILES['valid_id_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['valid_id_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'emp_id_' . time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['valid_id_photo']['tmp_name'], $uploadDir . $filename);
        $valid_id_photo = $filename;
    }

    if (isset($_FILES['valid_id_back_photo']) && $_FILES['valid_id_back_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['valid_id_back_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'emp_id_back_' . time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['valid_id_back_photo']['tmp_name'], $uploadDir . $filename);
        $valid_id_back_photo = $filename;
    }
    
    if ($id) {
        $stmt = $pdo->prepare('UPDATE employees SET first_name=?, last_name=?, email=?, phone=?, position=?, status=?, employment_category=?, hourly_rate=?, birthdate=?, address=?, sex=?, nationality=?, emergency_contact_name=?, emergency_contact_phone=?, valid_id_photo=?, valid_id_back_photo=?, preferred_schedule=? WHERE id=?');
        $stmt->execute([$first_name, $last_name, $email, $phone, $position, $status, $employment_category, $hourly_rate, $birthdate, $address, $sex, $nationality, $emergency_contact_name, $emergency_contact_phone, $valid_id_photo, $valid_id_back_photo, $preferred_schedule, $id]);
    }
    
    if ($modal) {
        echo "<script>window.parent.closeEditModal();</script>";
        exit;
    } else {
        redirect('employees');
    }
}

$pageTitle = $id ? 'Edit Employee' : 'Add Employee';
require_once __DIR__ . '/includes/header.php';
?>
<?php if ($modal): ?>
<style>
    /* Hide the top navbar and sidebar when inside modal */
    header, aside { display: none !important; }
    main { padding: 1rem !important; background: white !important; overflow: visible !important; }
    body, html { background: white !important; height: 100% !important; overflow: auto !important; }
</style>
<?php endif; ?>

<div class="max-w-3xl mx-auto">
    <form autocomplete="off" method="POST" enctype="multipart/form-data" autocomplete="off" class="bg-white <?= $modal ? '' : 'rounded-xl shadow-sm border border-slate-200' ?> p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                <input type="text" name="first_name" required minlength="2" value="<?= h($employee['first_name']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                <input type="text" name="last_name" required minlength="2" value="<?= h($employee['last_name']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                <input type="email" name="email" required value="<?= h($employee['email']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                <input type="tel" name="phone" required value="<?= h($employee['phone']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Birthdate</label>
                <input type="date" name="birthdate" value="<?= h($employee['birthdate'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Sex</label>
                <?php $currSex = $employee['sex'] ?? ''; ?>
                <select name="sex" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
                    <option value="">Select...</option>
                    <option value="Male" <?= $currSex === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $currSex === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nationality</label>
                <input type="text" name="nationality" value="<?= h($employee['nationality'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                <input type="text" name="address" value="<?= h($employee['address'] ?? '') ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div class="md:col-span-2 border-t border-slate-100 my-4"></div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Position / Role *</label>
                <input type="text" name="position" required value="<?= h($employee['position']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
                    <?php foreach(['Active', 'Probationary', 'Regular', 'Trainee', 'Archived'] as $st): ?>
                        <option value="<?= $st ?>" <?= $employee['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Employment Category</label>
                <select name="employment_category" id="empCategorySelect" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
                    <option value="Full-Time" <?= ($employee['employment_category'] ?? '') === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                    <option value="Part-Time" <?= ($employee['employment_category'] ?? '') === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                </select>
            </div>
            
            <div>
                <label id="salaryLabel" class="block text-sm font-medium text-slate-700 mb-1">Hourly Rate (₱)</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-100 text-slate-500 font-medium">₱</span>
                    <input type="text" name="hourly_rate" id="employeeHourlyRate" value="<?= ($employee['hourly_rate'] ?? 0) > 0 ? h(number_format($employee['hourly_rate'], 2)) : '' ?>" placeholder="0.00" class="w-full rounded-r-lg border-slate-300 p-2 border focus:ring-primary"
                           onblur="formatSalary(this)" onfocus="unformatSalary(this)" onclick="unformatSalary(this)"
                           oninput="limitSalaryInput(this); updateEmployeeProjection();">
                </div>
                <p id="employeeProjectionText" class="text-xs text-slate-500 mt-2 ml-1 italic"></p>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Preferred Schedule</label>
                <?php $currSched = $employee['preferred_schedule'] ?? 'Any'; ?>
                <select name="preferred_schedule" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
                    <option value="Any" <?= $currSched === 'Any' ? 'selected' : '' ?>>Any Schedule</option>
                    <option value="Morning" <?= $currSched === 'Morning' ? 'selected' : '' ?>>Morning Shift</option>
                    <option value="Mid" <?= $currSched === 'Mid' ? 'selected' : '' ?>>Mid Shift</option>
                    <option value="Night" <?= $currSched === 'Night' ? 'selected' : '' ?>>Night / Closing</option>
                </select>
            </div>
            
            <div class="md:col-span-2 pt-4 border-t border-slate-100 mt-2">
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider">Emergency Contact</h3>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="<?= h($employee['emergency_contact_name']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" value="<?= h($employee['emergency_contact_phone']) ?>" class="w-full rounded-lg border-slate-300 p-2 border focus:ring-primary">
            </div>
            
            <div class="md:col-span-2 pt-4 border-t border-slate-100 mt-2">
                <h3 class="text-sm font-bold text-slate-800 mb-4 uppercase tracking-wider">Identification Documents</h3>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Valid ID (Front Photo)</label>
                <input type="file" name="valid_id_photo" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                <?php if(!empty($employee['valid_id_photo'])): ?>
                    <p class="text-xs text-green-600 mt-1"><i class="fa-solid fa-check-circle"></i> File on record</p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Valid ID (Back Photo)</label>
                <input type="file" name="valid_id_back_photo" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
                <?php if(!empty($employee['valid_id_back_photo'])): ?>
                    <p class="text-xs text-green-600 mt-1"><i class="fa-solid fa-check-circle"></i> File on record</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex justify-end pt-4 border-t border-slate-100">
            <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-indigo-700">Save</button>
        </div>
    </form>
</div>

<script>
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
    
    if (window.updateEmployeeProjection) {
        window.updateEmployeeProjection();
    }
}

function unformatSalary(input) {
    input.value = input.value.replace(/,/g, '');
}

document.addEventListener('DOMContentLoaded', function() {
    function updateEmployeeProjection() {
        let input = document.getElementById('employeeHourlyRate');
        if (!input) return;
        let val = parseFloat(input.value.replace(/,/g, ''));
        let textObj = document.getElementById('employeeProjectionText');
        if (!isNaN(val) && val > 0) {
            let monthly = val * 8 * 22;
            textObj.innerHTML = 'Estimated Monthly Income: <strong class="text-emerald-600">₱' + monthly.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>';
        } else {
            textObj.innerText = '';
        }
    }
    
    // Call initially
    updateEmployeeProjection();
    
    // Make function globally accessible
    window.updateEmployeeProjection = updateEmployeeProjection;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

