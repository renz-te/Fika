<?php
require_once __DIR__ . '/init.php';
require_login();
require_role(['Admin', 'Super Admin', 'HR', 'HR Admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    flash('error', 'Invalid employee ID.');
    redirect('employees');
}

$stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ", last_name) AS full_name FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    flash('error', 'Employee not found.');
    redirect('employees');
}

$pageTitle = h($employee['full_name']) . ' - Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center space-x-3">
            <a href="employees" class="text-slate-400 hover:text-primary transition">
                <i class="fa-solid fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Employee Profile</h1>
        </div>
        <?php if(isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['Admin', 'Super Admin', 'HR', 'HR Admin'])): ?>
        <div class="flex space-x-2">
            <?php if ($employee['status'] !== 'Archived'): ?>
                <button onclick="openEditModal('terminate_form.php?id=<?= $employee['id'] ?>')" class="bg-white border border-red-200 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition shadow-sm font-medium">
                    <i class="fa-solid fa-user-slash mr-2"></i> Terminate
                </button>
            <?php else: ?>
                <button onclick="openEditModal('rehire_form.php?id=<?= $employee['id'] ?>')" class="bg-white border border-emerald-200 text-emerald-600 px-4 py-2 rounded-lg hover:bg-emerald-50 transition shadow-sm font-medium">
                    <i class="fa-solid fa-user-plus mr-2"></i> Reactivate
                </button>
            <?php endif; ?>
            <?php if(isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['Admin', 'Super Admin'])): ?>
            <button onclick="openEditModal('employee_form.php?id=<?= $employee['id'] ?>')" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg hover:bg-slate-50 transition shadow-sm font-medium">
                <i class="fa-solid fa-pen mr-2"></i> Edit Profile
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Archived Banner -->
    <?php if ($employee['status'] === 'Archived'): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 flex items-start space-x-3 mb-6 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-xl text-red-500 mt-0.5"></i>
            <div>
                <h3 class="font-bold">Archived Record</h3>
                <p class="text-sm mt-1">This employee was terminated on <strong><?= $employee['termination_date'] ? date('F j, Y', strtotime($employee['termination_date'])) : 'Unknown' ?></strong> due to: <strong><?= h($employee['termination_reason'] ?? 'Unspecified') ?></strong></p>
                <?php if (!empty($employee['termination_details'])): ?>
                    <p class="text-sm text-red-600 mt-2 p-3 bg-white/50 rounded-lg border border-red-100">"<?= nl2br(h($employee['termination_details'])) ?>"</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 flex flex-col md:flex-row items-center md:items-start gap-6 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-primary/10 to-blue-500/10"></div>
        
        <div class="h-24 w-24 rounded-full bg-primary text-white flex items-center justify-center text-4xl font-bold shadow-lg z-10 border-4 border-white">
            <?= h(strtoupper(substr($employee['full_name'], 0, 1))) ?>
        </div>
        
        <div class="flex-1 text-center md:text-left z-10 pt-2">
            <h2 class="text-3xl font-bold text-slate-800 mb-1"><?= h($employee['full_name']) ?></h2>
            <p class="text-lg text-slate-500 font-medium mb-3"><?= h($employee['position']) ?></p>
            
            <div class="flex flex-wrap justify-center md:justify-start gap-3">
                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-sm font-medium border border-slate-200">
                    <i class="fa-solid fa-id-badge mr-1"></i> <?= h($employee['employee_id']) ?>
                </span>
                <?php 
                    $statusClass = 'bg-slate-100 text-slate-600';
                    if ($employee['status'] === 'Active') $statusClass = 'bg-green-100 text-green-700';
                    if ($employee['status'] === 'On Leave') $statusClass = 'bg-amber-100 text-amber-700';
                ?>
                <span class="px-3 py-1 <?= $statusClass ?> rounded-full text-sm font-medium">
                    <i class="fa-solid fa-circle-dot text-[10px] mr-1"></i> <?= h($employee['status']) ?>
                </span>
                
                <?php $cat = $employee['employment_category'] ?? 'Full-Time'; ?>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-sm font-medium border border-indigo-100">
                    <i class="fa-solid fa-clock text-[10px] mr-1"></i> <?= h($cat) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Details Column -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Basic Information</h3>
                <div class="space-y-4">
                    <?php
                        $ageStr = 'Unknown';
                        if (!empty($employee['birthdate'])) {
                            $bd = new DateTime($employee['birthdate']);
                            $today = new DateTime('today');
                            $age = $bd->diff($today)->y;
                            $ageStr = $age . ' yrs';
                        }
                    ?>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-cake-candles"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Birthdate (Age)</div>
                            <div class="text-slate-800">
                                <?= !empty($employee['birthdate']) ? date('F j, Y', strtotime($employee['birthdate'])) : 'Not provided' ?> 
                                <span class="text-slate-500 text-sm ml-1">(<?= $ageStr ?>)</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-venus-mars"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Sex</div>
                            <div class="text-slate-800"><?= h($employee['sex'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-flag"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Nationality</div>
                            <div class="text-slate-800"><?= h($employee['nationality'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Address</div>
                            <div class="text-slate-800"><?= h($employee['address'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Email Address</div>
                            <div class="text-slate-800"><?= h($employee['email'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Phone Number</div>
                            <div class="text-slate-800"><?= h($employee['phone'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-calendar-day"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Date Hired</div>
                            <div class="text-slate-800"><?= !empty($employee['date_hired']) ? date('F j, Y', strtotime($employee['date_hired'])) : 'Unknown' ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-slate-400 mt-0.5"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Hourly Rate</div>
                            <div class="text-slate-800 font-medium">₱<?= number_format((float)$employee['hourly_rate'], 2) ?>/hr <span class="text-xs text-slate-400 ml-1 font-normal">(Est. ₱<?= number_format((float)$employee['hourly_rate'] * 8 * 22, 2) ?>/mo)</span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Emergency Contact</h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-8 text-red-400 mt-0.5"><i class="fa-solid fa-heart-pulse"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Contact Name</div>
                            <div class="text-slate-800 font-medium"><?= h($employee['emergency_contact_name'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-red-400 mt-0.5"><i class="fa-solid fa-phone-volume"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Contact Phone</div>
                            <div class="text-slate-800"><?= h($employee['emergency_contact_phone'] ?: 'Not provided') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Education -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Educational Background</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-xs text-slate-500 font-semibold block mb-1">Education Level</span>
                        <span class="text-slate-800 font-medium"><?= h($employee['education_level'] ?: 'Not specified') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 font-semibold block mb-1">School Name</span>
                        <span class="text-slate-800 font-medium"><?= h($employee['school_name'] ?: 'Not specified') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 font-semibold block mb-1">Course / Diploma</span>
                        <span class="text-slate-800 font-medium"><?= h($employee['course_diploma'] ?: 'Not specified') ?></span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 font-semibold block mb-1">Year & Status</span>
                        <span class="text-slate-800 font-medium"><?= h($employee['year_graduated']) ?><?= !empty($employee['education_status']) ? ' - ' . h($employee['education_status']) : '' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- IDs Column -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Statutory & Banking</h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-8 text-indigo-400 mt-0.5"><i class="fa-solid fa-building-columns"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Bank Account</div>
                            <div class="text-slate-800 font-medium"><?= h(decryptData($employee['bank_account']) ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-indigo-400 mt-0.5"><i class="fa-solid fa-file-invoice"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">TIN</div>
                            <div class="text-slate-800 font-medium"><?= h(decryptData($employee['tin']) ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-indigo-400 mt-0.5"><i class="fa-solid fa-shield-halved"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">SSS Number</div>
                            <div class="text-slate-800 font-medium"><?= h(decryptData($employee['sss']) ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-indigo-400 mt-0.5"><i class="fa-solid fa-heart-circle-check"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">PhilHealth Number</div>
                            <div class="text-slate-800 font-medium"><?= h(decryptData($employee['philhealth']) ?: 'Not provided') ?></div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 text-indigo-400 mt-0.5"><i class="fa-solid fa-house-chimney"></i></div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Pag-IBIG Number</div>
                            <div class="text-slate-800 font-medium"><?= h(decryptData($employee['pagibig']) ?: 'Not provided') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">Identification Documents</h3>
                
                <div class="space-y-6">
                    <div>
                        <div class="text-sm font-medium text-slate-700 mb-2">Valid ID (Front)</div>
                        <?php if(!empty($employee['valid_id_photo'])): ?>
                            <a href="file_proxy.php?file=applicants/<?= h($employee['valid_id_photo']) ?>" target="_blank" class="block rounded-lg border-2 border-slate-200 overflow-hidden hover:border-primary transition group relative">
                                <img src="file_proxy.php?file=applicants/<?= h($employee['valid_id_photo']) ?>" alt="ID Front" class="w-full h-48 object-cover">
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <span class="text-white bg-black/50 px-4 py-2 rounded-full text-sm font-medium"><i class="fa-solid fa-magnifying-glass mr-1"></i> View Full Image</span>
                                </div>
                            </a>
                        <?php else: ?>
                            <div class="h-32 rounded-lg border-2 border-dashed border-slate-200 flex items-center justify-center text-slate-400 text-sm bg-slate-50">
                                <i class="fa-solid fa-image text-xl mr-2 opacity-50"></i> No Front ID uploaded
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <div class="text-sm font-medium text-slate-700 mb-2">Valid ID (Back)</div>
                        <?php if(!empty($employee['valid_id_back_photo'])): ?>
                            <a href="file_proxy.php?file=applicants/<?= h($employee['valid_id_back_photo']) ?>" target="_blank" class="block rounded-lg border-2 border-slate-200 overflow-hidden hover:primary transition group relative">
                                <img src="file_proxy.php?file=applicants/<?= h($employee['valid_id_back_photo']) ?>" alt="ID Back" class="w-full h-48 object-cover">
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <span class="text-white bg-black/50 px-4 py-2 rounded-full text-sm font-medium"><i class="fa-solid fa-magnifying-glass mr-1"></i> View Full Image</span>
                                </div>
                            </a>
                        <?php else: ?>
                            <div class="h-32 rounded-lg border-2 border-dashed border-slate-200 flex items-center justify-center text-slate-400 text-sm bg-slate-50">
                                <i class="fa-solid fa-image text-xl mr-2 opacity-50"></i> No Back ID uploaded
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal Script (since we have an Edit button here) -->
<div id="editModal" class="fixed inset-0 z-50 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl h-[85vh] flex flex-col relative overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Edit Employee</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <iframe id="editIframe" class="w-full flex-1" src=""></iframe>
    </div>
</div>

<script>
function openEditModal(url) {
    document.getElementById('editIframe').src = url + '&modal=1';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editIframe').src = '';
    window.location.reload(); // Reload to see changes
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
