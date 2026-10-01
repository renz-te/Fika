<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

$totalEmployees = $pdo->query('SELECT COUNT(*) FROM employees WHERE status != "Archived"')->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE DATE(time_in) = CURDATE()');
$stmt->execute();
$presentToday = $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE DATE(time_in) = CURDATE() AND is_late = 1');
$stmt->execute();
$lateToday = $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM leaves WHERE status = "Pending"');
$stmt->execute();
$pendingLeaves = $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE DATE_FORMAT(birthday, "%m-%d") = DATE_FORMAT(CURDATE() + INTERVAL 7 DAY, "%m-%d")');
$stmt->execute();
$upcomingBirthdays = $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM applicants');
$stmt->execute();
$newApplicants = $stmt->fetchColumn();

// Daily Agenda Dashboard Integration
$todayInterviews = [];
if (in_array($user['role'], ['HR', 'Central HR', 'Branch Admin', 'Super Admin', 'Head Barista'])) {
    $stmt = $pdo->prepare('
        SELECT i.id as interview_id, i.interview_time as start_time, a.first_name, a.last_name, a.position_applied as position 
        FROM interviews i 
        INNER JOIN applicants a ON i.applicant_id = a.id 
        INNER JOIN users u ON i.interviewer_id = u.employee_id
        WHERE u.id = ? AND i.interview_date = CURDATE() AND i.status != "Cancelled" 
        ORDER BY i.interview_time ASC
    ');
    $stmt->execute([$user['id']]);
    $todayInterviews = $stmt->fetchAll();
}

$attendanceByMonth = $pdo->query('SELECT MONTH(time_in) AS month, COUNT(*) AS total FROM attendance WHERE YEAR(time_in) = YEAR(CURDATE()) GROUP BY MONTH(time_in)')->fetchAll();
$payrollTotal = $pdo->query('SELECT SUM(net_pay) FROM payroll')->fetchColumn();
$recentActivities = $pdo->query('SELECT a.*, u.name AS user_name FROM activity_logs a JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 6')->fetchAll();
$notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5')->fetchAll();

$attendanceLabels = [];
$attendanceValues = [];
if ($attendanceByMonth) {
    foreach ($attendanceByMonth as $row) {
        $attendanceLabels[] = date('M', mktime(0,0,0,$row['month'],1));
        $attendanceValues[] = (int)$row['total'];
    }
}

$summaryPayroll = $pdo->query('SELECT COUNT(*) AS count, SUM(net_pay) AS total FROM payroll')->fetch();

// Recruitment Data Mining Insights (Current Month)
$currentMonth = date('Y-m');
$insights = [];

// 1. Most Applied Position
$stmt = $pdo->query("SELECT position_applied, COUNT(*) as cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth' AND position_applied != '' GROUP BY position_applied ORDER BY cnt DESC LIMIT 1");
$topPosition = $stmt->fetch();
$insights['top_position'] = $topPosition ? $topPosition['position_applied'] : 'N/A';

// 2. Most Common Category
$stmt = $pdo->query("SELECT employment_category, COUNT(*) as cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth' AND employment_category != '' GROUP BY employment_category ORDER BY cnt DESC LIMIT 1");
$topCategory = $stmt->fetch();
$insights['top_category'] = $topCategory ? $topCategory['employment_category'] : 'N/A';

// 3. Most Common Gender
$stmt = $pdo->query("SELECT sex, COUNT(*) as cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth' AND sex != '' GROUP BY sex ORDER BY cnt DESC LIMIT 1");
$topSex = $stmt->fetch();
$insights['top_sex'] = $topSex ? $topSex['sex'] : 'N/A';

// 4. Most Common Experience
$stmt = $pdo->query("SELECT experience_level, COUNT(*) as cnt FROM applicants WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth' AND experience_level != '' GROUP BY experience_level ORDER BY cnt DESC LIMIT 1");
$topExp = $stmt->fetch();
$insights['top_experience'] = $topExp ? $topExp['experience_level'] : 'N/A';

// Top Performers Logic
if (!function_exists('renderStars')) {
    function renderStars($score) {
        $html = '<div class="flex text-xs">';
        $score = round((float)$score, 1);
        for ($i = 1; $i <= 5; $i++) {
            if ($score >= $i) {
                $html .= '<i class="fa-solid fa-star text-amber-400"></i>';
            } elseif ($score >= $i - 0.5) {
                $html .= '<i class="fa-solid fa-star-half-stroke text-amber-400"></i>';
            } else {
                $html .= '<i class="fa-regular fa-star text-slate-300"></i>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}

function getTopPerformers($pdo, $isHB, $period) {
    $dateCondition = ($period === 'week') 
        ? 'YEARWEEK(p.review_date, 1) = YEARWEEK(CURDATE(), 1)'
        : 'MONTH(p.review_date) = MONTH(CURDATE()) AND YEAR(p.review_date) = YEAR(CURDATE())';

    $positionCondition = $isHB ? "emp.position LIKE '%Head Barista%'" : "emp.position NOT LIKE '%Head Barista%' AND emp.position LIKE '%Barista%'";

    $sql = "
        SELECT emp.id, CONCAT(emp.first_name, ' ', emp.last_name) AS full_name, emp.position, emp.photo, 
               AVG(p.total_score) as avg_score, COUNT(p.id) as review_count
        FROM performance_reviews p
        JOIN employees emp ON p.employee_id = emp.id
        WHERE $dateCondition
          AND emp.status = 'Active'
          AND $positionCondition
          AND p.evaluation_type = 'Official'
        GROUP BY p.employee_id
        ORDER BY avg_score DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    if (empty($results)) return [];
    
    $topScore = (float)$results[0]['avg_score'];
    $topPerformers = [];
    foreach ($results as $row) {
        if (abs((float)$row['avg_score'] - $topScore) < 0.001) {
            $topPerformers[] = $row;
        } else {
            break;
        }
    }
    return $topPerformers;
}

$topHBWeek = getTopPerformers($pdo, true, 'week');
$topBaristaWeek = getTopPerformers($pdo, false, 'week');
$topHBMonth = getTopPerformers($pdo, true, 'month');
$topBaristaMonth = getTopPerformers($pdo, false, 'month');

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Stat Card 1 -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center transition hover:shadow-md">
        <div class="h-12 w-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Total Employees</p>
            <p class="text-2xl font-bold text-slate-800"><?= number_format($totalEmployees) ?></p>
        </div>
    </div>
    
    <!-- Stat Card 2 -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center transition hover:shadow-md">
        <div class="h-12 w-12 rounded-lg bg-green-100 text-green-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Present Today</p>
            <p class="text-2xl font-bold text-slate-800"><?= number_format($presentToday) ?></p>
        </div>
    </div>
    
    <!-- Stat Card 3 -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center transition hover:shadow-md">
        <div class="h-12 w-12 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Late Today</p>
            <p class="text-2xl font-bold text-slate-800"><?= number_format($lateToday) ?></p>
        </div>
    </div>
    
    <!-- Stat Card 4 -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center transition hover:shadow-md">
        <div class="h-12 w-12 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-xl mr-4">
            <i class="fa-solid fa-calendar-alt"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Pending Leaves</p>
            <p class="text-2xl font-bold text-slate-800"><?= number_format($pendingLeaves) ?></p>
        </div>
    </div>
</div>

<!-- Daily Agenda Widget -->
<?php if (in_array($user['role'], ['HR', 'Central HR', 'Branch Admin', 'Super Admin', 'Head Barista'])): ?>
<div class="mb-8">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800"><i class="fa-solid fa-calendar-day text-indigo-500 mr-2"></i> Today's Interviews</h3>
        <a href="applications?tab=calendar" class="text-sm text-indigo-600 font-medium hover:underline">View Calendar</a>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($todayInterviews)): ?>
            <div class="p-8 text-center text-slate-500 flex flex-col items-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3">
                    <i class="fa-solid fa-mug-hot text-2xl text-slate-400"></i>
                </div>
                <p class="font-medium text-slate-600">No interviews scheduled for today.</p>
                <p class="text-xs text-slate-400 mt-1">Enjoy your free time!</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach($todayInterviews as $interview): ?>
                    <a href="interview_workspace?interview_id=<?= $interview['interview_id'] ?>" class="block p-4 hover:bg-indigo-50/50 transition-colors group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold border border-indigo-200">
                                    <?= date('g:i', strtotime($interview['start_time'])) ?>
                                    <span class="text-[10px] ml-0.5"><?= date('A', strtotime($interview['start_time'])) ?></span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 group-hover:text-indigo-700 transition-colors"><?= h($interview['first_name'] . ' ' . $interview['last_name']) ?></h4>
                                    <p class="text-xs text-slate-500 mt-0.5"><span class="inline-block px-2 py-0.5 bg-slate-100 rounded text-slate-600 font-medium uppercase tracking-wider"><?= h($interview['position']) ?></span></p>
                                </div>
                            </div>
                            <div class="text-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity transform group-hover:translate-x-1">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Data Mining Insights -->
<div class="mb-8">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Recruitment Insights (<?= date('F Y') ?>)</h3>
    </div>
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl shadow-md text-white p-6 relative overflow-hidden">
        <div class="absolute right-0 top-0 opacity-10">
            <i class="fa-solid fa-chart-pie text-9xl -mr-6 -mt-6"></i>
        </div>
        <div class="relative z-10 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Top Position</p>
                <p class="text-2xl font-bold"><?= h($insights['top_position']) ?></p>
            </div>
            <div>
                <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Top Category</p>
                <p class="text-2xl font-bold"><?= h($insights['top_category']) ?></p>
            </div>
            <div>
                <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Prevalent Gender</p>
                <p class="text-2xl font-bold"><?= h($insights['top_sex']) ?></p>
            </div>
            <div>
                <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Prevalent Experience</p>
                <p class="text-2xl font-bold"><?= h($insights['top_experience']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="mb-8">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Top Performers</h3>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- This Week -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 p-4 font-bold text-slate-700 flex justify-between items-center">
                <span><i class="fa-solid fa-calendar-week mr-2 text-indigo-500"></i> This Week</span>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Best HB Week -->
                <div class="bg-amber-50 rounded-lg p-4 border border-amber-100 relative">
                    <div class="absolute -top-3 -right-3 bg-amber-400 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                        <i class="fa-solid fa-crown text-sm"></i>
                    </div>
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-3">Best Head Barista</p>
                    
                    <?php if (empty($topHBWeek)): ?>
                        <p class="text-sm text-slate-500 italic text-center py-4">No reviews yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($topHBWeek as $emp): ?>
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 rounded-full bg-amber-200 overflow-hidden border-2 border-white shadow-sm flex-shrink-0">
                                        <?php if (!empty($emp['photo'])): ?>
                                            <img src="<?= h($emp['photo']) ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center text-amber-700 font-bold text-sm">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm truncate"><?= h($emp['full_name']) ?></p>
                                        <div class="flex items-center mt-0.5 space-x-1">
                                            <span class="text-xs font-black text-slate-700"><?= number_format($emp['avg_score'], 1) ?></span>
                                            <?= renderStars($emp['avg_score']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Best Barista Week -->
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200 relative">
                    <div class="absolute -top-3 -right-3 bg-slate-400 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                        <i class="fa-solid fa-medal text-sm"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Best Barista</p>
                    
                    <?php if (empty($topBaristaWeek)): ?>
                        <p class="text-sm text-slate-500 italic text-center py-4">No reviews yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($topBaristaWeek as $emp): ?>
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden border-2 border-white shadow-sm flex-shrink-0">
                                        <?php if (!empty($emp['photo'])): ?>
                                            <img src="<?= h($emp['photo']) ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center text-slate-600 font-bold text-sm">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm truncate"><?= h($emp['full_name']) ?></p>
                                        <div class="flex items-center mt-0.5 space-x-1">
                                            <span class="text-xs font-black text-slate-700"><?= number_format($emp['avg_score'], 1) ?></span>
                                            <?= renderStars($emp['avg_score']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- This Month -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 p-4 font-bold text-slate-700 flex justify-between items-center">
                <span><i class="fa-solid fa-calendar-alt mr-2 text-indigo-500"></i> This Month</span>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Best HB Month -->
                <div class="bg-amber-50 rounded-lg p-4 border border-amber-100 relative">
                    <div class="absolute -top-3 -right-3 bg-amber-400 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                        <i class="fa-solid fa-crown text-sm"></i>
                    </div>
                    <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-3">Best Head Barista</p>
                    
                    <?php if (empty($topHBMonth)): ?>
                        <p class="text-sm text-slate-500 italic text-center py-4">No reviews yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($topHBMonth as $emp): ?>
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 rounded-full bg-amber-200 overflow-hidden border-2 border-white shadow-sm flex-shrink-0">
                                        <?php if (!empty($emp['photo'])): ?>
                                            <img src="<?= h($emp['photo']) ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center text-amber-700 font-bold text-sm">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm truncate"><?= h($emp['full_name']) ?></p>
                                        <div class="flex items-center mt-0.5 space-x-1">
                                            <span class="text-xs font-black text-slate-700"><?= number_format($emp['avg_score'], 1) ?></span>
                                            <?= renderStars($emp['avg_score']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Best Barista Month -->
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200 relative">
                    <div class="absolute -top-3 -right-3 bg-slate-400 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                        <i class="fa-solid fa-medal text-sm"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Best Barista</p>
                    
                    <?php if (empty($topBaristaMonth)): ?>
                        <p class="text-sm text-slate-500 italic text-center py-4">No reviews yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($topBaristaMonth as $emp): ?>
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden border-2 border-white shadow-sm flex-shrink-0">
                                        <?php if (!empty($emp['photo'])): ?>
                                            <img src="<?= h($emp['photo']) ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center text-slate-600 font-bold text-sm">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm truncate"><?= h($emp['full_name']) ?></p>
                                        <div class="flex items-center mt-0.5 space-x-1">
                                            <span class="text-xs font-black text-slate-700"><?= number_format($emp['avg_score'], 1) ?></span>
                                            <?= renderStars($emp['avg_score']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Main Chart Area (Mockup for now) -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 lg:col-span-2">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-slate-800">Attendance Overview</h3>
            <button class="text-sm text-primary font-medium hover:underline">View Full Report</button>
        </div>
        <div class="h-64 flex items-end space-x-2 w-full justify-between pb-6 border-b border-slate-100 relative">
            <?php 
            $maxVal = max(1, count($attendanceValues) > 0 ? max($attendanceValues) : 10); 
            foreach($attendanceLabels as $i => $label): 
                $height = ($attendanceValues[$i] / $maxVal) * 100;
            ?>
                <div class="flex flex-col items-center flex-1 group">
                    <div class="w-full max-w-[40px] bg-primary/20 group-hover:bg-primary transition-colors rounded-t-sm" style="height: <?= $height ?>%;"></div>
                    <span class="text-xs text-slate-500 mt-2"><?= h($label) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($attendanceLabels)): ?>
                <div class="absolute inset-0 flex items-center justify-center text-slate-400">
                    No attendance data yet
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-6">Recent Activity</h3>
        <div class="space-y-4">
            <?php if (!empty($recentActivities)): ?>
                <?php foreach($recentActivities as $activity): ?>
                    <div class="flex items-start">
                        <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mt-0.5 mr-3 shrink-0">
                            <i class="fa-solid fa-bolt text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-800">
                                <?= h($activity['user_name']) ?> <span class="font-normal text-slate-500"><?= h($activity['action']) ?></span>
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5"><?= h(date('M d, Y h:i A', strtotime($activity['created_at']))) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-slate-500 italic">No recent activities.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
