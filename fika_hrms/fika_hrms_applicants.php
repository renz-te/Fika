<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Authentication and Authorization
Auth::requireLogin();
Rbac::require_permission('hr.employee.view');

global $pdo;

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR position LIKE ?)";
    $wild = '%' . $search . '%';
    $params = array_merge($params, [$wild, $wild, $wild, $wild]);
}

if ($statusFilter !== '') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants {$whereClause}");
$countStmt->execute($params);
$totalApplicants = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalApplicants / $limit);

// Fetch page
$sql = "SELECT id, job_opening_id, first_name, last_name, email, phone, position, resume, resume_path, status, created_at 
        FROM applicants 
        {$whereClause} 
        ORDER BY created_at DESC 
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATS Job Applicants &middot; Fika HRMS</title>
    <link rel="icon" type="image/svg+xml" href="../public/assets/logo.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800 font-sans antialiased">
    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="../public/assets/logo.svg" alt="Fika Logo" class="w-9 h-9 object-contain">
                <div>
                    <span class="font-black text-gray-900 tracking-wider text-base">FIKA HRMS</span>
                    <span class="text-[10px] text-amber-600 block font-bold uppercase tracking-widest">Recruitment &bull; ATS Intake</span>
                </div>
            </div>

            <div class="flex items-center gap-4 text-sm font-medium">
                <a href="fika_hrms_dashboard.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                    Dashboard
                </a>
                <a href="fika_hrms_employees.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                    Employees
                </a>
                <span class="text-amber-600 font-bold border-b-2 border-amber-500 pb-1">
                    Job Applicants
                </span>
                <a href="../login.php" class="text-xs text-red-600 hover:underline ml-2">
                    Exit
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Page Title & Stats Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Job Applicants (ATS)</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-1">Review candidate resumes and intake submissions across active café positions</p>
            </div>
            <div>
                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold">
                    <i class="fa-solid fa-users text-amber-600"></i>
                    <span>Total Applicants: <?= $totalApplicants ?></span>
                </span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="fika_hrms_applicants.php" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Search Candidates</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </div>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, or position..."
                            class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                    </div>
                </div>

                <div class="w-full sm:w-48">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full py-2 px-3 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                        <option value="">All Statuses</option>
                        <option value="NEW" <?= $statusFilter === 'NEW' ? 'selected' : '' ?>>NEW</option>
                        <option value="REVIEWING" <?= $statusFilter === 'REVIEWING' ? 'selected' : '' ?>>REVIEWING</option>
                        <option value="SHORTLISTED" <?= $statusFilter === 'SHORTLISTED' ? 'selected' : '' ?>>SHORTLISTED</option>
                        <option value="REJECTED" <?= $statusFilter === 'REJECTED' ? 'selected' : '' ?>>REJECTED</option>
                        <option value="HIRED" <?= $statusFilter === 'HIRED' ? 'selected' : '' ?>>HIRED</option>
                    </select>
                </div>

                <div class="flex gap-2 w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs uppercase tracking-wider rounded-lg shadow-sm transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-xs"></i>
                        <span>Filter</span>
                    </button>
                    <?php if ($search !== '' || $statusFilter !== ''): ?>
                        <a href="fika_hrms_applicants.php" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-colors flex items-center justify-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Candidate</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Position</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Contact Details</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Date Applied</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Resume</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php if (empty($applicants)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                    <i class="fa-regular fa-folder-open text-3xl mb-2 text-gray-300 block"></i>
                                    <span>No applicants match your criteria.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applicants as $app): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <!-- Candidate Name -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-gray-900">
                                            <?= e($app['first_name'] . ' ' . $app['last_name']) ?>
                                        </div>
                                        <div class="text-[11px] text-gray-400">ID: #<?= (int)$app['id'] ?></div>
                                    </td>

                                    <!-- Position -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                            <?= e($app['position']) ?>
                                        </span>
                                    </td>

                                    <!-- Contact Details -->
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                        <div><i class="fa-regular fa-envelope mr-1.5 text-gray-400"></i><?= e($app['email']) ?></div>
                                        <div class="mt-0.5"><i class="fa-solid fa-phone mr-1.5 text-gray-400"></i><?= e($app['phone']) ?></div>
                                    </td>

                                    <!-- Date Applied -->
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                        <?= date('M d, Y &bull; h:i A', strtotime($app['created_at'])) ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $badgeStyles = [
                                            'NEW' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'REVIEWING' => 'bg-purple-100 text-purple-800 border-purple-200',
                                            'SHORTLISTED' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'REJECTED' => 'bg-red-100 text-red-800 border-red-200',
                                            'HIRED' => 'bg-amber-100 text-amber-800 border-amber-200'
                                        ];
                                        $style = $badgeStyles[$app['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full border <?= $style ?>">
                                            <?= e($app['status']) ?>
                                        </span>
                                    </td>

                                    <!-- Resume Download / View -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <?php if (!empty($app['resume_path']) || !empty($app['resume'])): ?>
                                            <a href="api/fika_hrms_resume_serve.php?id=<?= (int)$app['id'] ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-amber-50 text-gray-700 hover:text-amber-700 border border-gray-300 hover:border-amber-300 rounded-lg text-xs font-semibold transition-all">
                                                <i class="fa-solid fa-file-pdf text-red-600"></i>
                                                <span>View PDF</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">No File</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
                    <div>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalApplicants) ?> of <?= $totalApplicants ?> entries
                    </div>
                    <div class="flex gap-1">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"
                               class="px-2.5 py-1 rounded border <?= $i === $page ? 'bg-amber-600 text-white border-amber-600 font-bold' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
