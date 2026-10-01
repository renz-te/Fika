<?php
require_once __DIR__ . '/init.php';
require_login();
$user = current_user();

// Check if user is a Head Barista via employee position
$stmt = $pdo->prepare('SELECT position FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?)');
$stmt->execute([$user['id']]);
$empPos = $stmt->fetchColumn();
if ($empPos && stripos($empPos, 'Head Barista') !== false) {
    $user['role'] = 'Head Barista';
}

if (!in_array($user['role'], ['Admin', 'Super Admin', 'HR', 'Head Barista'])) {
    http_response_code(403);
    die('Forbidden');
}

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if (!$employee_id) die("Employee ID required.");

$stmt = $pdo->prepare('SELECT *, CONCAT(first_name, " ",   last_name) AS full_name FROM employees WHERE id = ?');
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
if (!$employee) die("Employee not found.");

$is_hr = (stripos($employee['position'], 'HR') !== false);
$is_leadership = !$is_hr && ((stripos($employee['position'], 'Head Barista') !== false) || (stripos($employee['position'], 'Assistant Head') !== false));
$evaluation_type = 'Official';

// Check HB Weekly Cooldown
$has_rated_this_week = false;
if ($user['role'] === 'Head Barista') {
    $checkStmt = $pdo->prepare("SELECT id FROM performance_reviews WHERE evaluator_id = ? AND employee_id = ? AND YEARWEEK(review_date, 1) = YEARWEEK(CURDATE(), 1)");
    $checkStmt->execute([$user['id'], $employee_id]);
    if ($checkStmt->fetch()) {
        $has_rated_this_week = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($has_rated_this_week) {
        echo "<script>alert('You have already rated this staff member this week.'); window.parent.closeEvaluateModal();</script>";
        exit;
    }
    
    if ($is_hr) {
        $score_setup = (int)$_POST['score_setup'];
        $score_scheduling = (int)$_POST['score_scheduling'];
        $score_recruitment = (int)$_POST['score_recruitment'];
        $score_compliance = (int)$_POST['score_compliance'];
        $comments = trim($_POST['comments'] ?? '');
        
        $total_score = ($score_setup + $score_scheduling + $score_recruitment + $score_compliance) / 4.0;
        
        $stmt = $pdo->prepare('INSERT INTO performance_reviews (employee_id, evaluator_id, review_date, is_leadership, evaluation_type, score_setup, score_scheduling, score_recruitment, score_compliance, total_score, comments) VALUES (?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $employee_id, 
            $user['id'],
            $evaluation_type,
            $score_setup,
            $score_scheduling,
            $score_recruitment,
            $score_compliance,
            $total_score,
            $comments
        ]);
    } elseif ($is_leadership) {
        $score_floor_mgmt = (int)$_POST['score_floor_mgmt'];
        $score_staff_training = (int)$_POST['score_staff_training'];
        $score_quality_control = (int)$_POST['score_quality_control'];
        $score_reliability = (int)$_POST['score_reliability'];
        $comments = trim($_POST['comments'] ?? '');
        
        $total_score = ($score_floor_mgmt + $score_staff_training + $score_quality_control + $score_reliability) / 4.0;
        
        $stmt = $pdo->prepare('INSERT INTO performance_reviews (employee_id, evaluator_id, review_date, is_leadership, evaluation_type, score_floor_mgmt, score_staff_training, score_quality_control, score_reliability, total_score, comments) VALUES (?, ?, CURDATE(), 1, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $employee_id, 
            $user['id'],
            $evaluation_type,
            $score_floor_mgmt,
            $score_staff_training,
            $score_quality_control,
            $score_reliability,
            $total_score,
            $comments
        ]);
    } else {
        $score_kitchen = (int)$_POST['score_kitchen'];
        $score_cashier = (int)$_POST['score_cashier'];
        $score_cleaning = (int)$_POST['score_cleaning'];
        $score_inventory = (int)$_POST['score_inventory'];
        $comments = trim($_POST['comments'] ?? '');
        
        $total_score = ($score_kitchen + $score_cashier + $score_cleaning + $score_inventory) / 4.0;
        
        $stmt = $pdo->prepare('INSERT INTO performance_reviews (employee_id, evaluator_id, review_date, is_leadership, evaluation_type, score_kitchen, score_cashier, score_cleaning, score_inventory, total_score, comments) VALUES (?, ?, CURDATE(), 0, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $employee_id, 
            $user['id'],
            $evaluation_type,
            $score_kitchen,
            $score_cashier,
            $score_cleaning,
            $score_inventory,
            $total_score,
            $comments
        ]);
    }
    
    echo "<script>window.parent.closeEvaluateModal();</script>";
    exit;
}

// Fetch recent reviews for this employee
$stmt = $pdo->prepare('SELECT pr.*, u.name as evaluator_name FROM performance_reviews pr JOIN users u ON pr.evaluator_id = u.id WHERE pr.employee_id = ? ORDER BY pr.id DESC LIMIT 3');
$stmt->execute([$employee_id]);
$floor_feedbacks = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluate <?= h($employee['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating input { display: none; }
        .star-rating label {
            color: #cbd5e1;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
            padding-right: 0.25rem;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #fbbf24;
        }
    </style>
</head>
<body class="p-6 text-slate-800 antialiased">

<div class="mb-6 flex items-center">
    <div class="h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xl mr-4 border border-indigo-200">
        <?= h(strtoupper(substr($employee['full_name'], 0, 1))) ?>
    </div>
    <div>
        <h2 class="text-xl font-bold text-slate-800"><?= h($employee['full_name']) ?></h2>
        <p class="text-sm text-slate-500 font-medium"><?= h($employee['employee_id']) ?> &bull; <?= h($employee['position']) ?></p>
    </div>
</div>

<?php if ($evaluation_type === 'Official' && count($floor_feedbacks) > 0): ?>
<div class="mb-6 bg-blue-50 rounded-xl p-4 border border-blue-100">
    <h4 class="font-bold text-blue-800 text-sm mb-2"><i class="fa-solid fa-clipboard-list mr-2"></i>Recent Floor Feedback from Head Baristas</h4>
    <div class="space-y-3">
        <?php foreach ($floor_feedbacks as $fb): ?>
            <div class="bg-white p-3 rounded border border-blue-50 text-sm">
                <div class="flex justify-between text-xs text-slate-500 mb-1">
                    <span><strong><?= h($fb['evaluator_name']) ?></strong></span>
                    <span><?= date('M d, Y', strtotime($fb['review_date'])) ?></span>
                </div>
                <div class="text-slate-700">Average Score: <strong><?= number_format($fb['total_score'], 1) ?> / 5.0</strong></div>
                <?php if ($fb['comments']): ?>
                    <p class="text-slate-600 mt-1 italic">"<?= h($fb['comments']) ?>"</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form autocomplete="off" method="POST" class="space-y-6">
    
    <div class="bg-slate-50 rounded-xl p-6 border border-slate-200 space-y-4">
        <h3 class="font-bold text-slate-700 border-b border-slate-200 pb-2 mb-4">
            <?php 
            if ($is_hr) echo 'HR Skills Rating (1-5)';
            elseif ($is_leadership) echo 'Leadership Skills Rating (1-5)';
            else echo 'All-Rounder Skills Rating (1-5)';
            ?>
        </h3>
        
        <?php
        if ($is_hr) {
            $skills = [
                'score_setup' => ['title' => 'System Setup', 'desc' => 'Managing system configurations and employee data'],
                'score_scheduling' => ['title' => 'Scheduling', 'desc' => 'Creating efficient schedules and managing leaves'],
                'score_recruitment' => ['title' => 'Recruitment', 'desc' => 'Managing job postings and applicant flow'],
                'score_compliance' => ['title' => 'Compliance', 'desc' => 'Ensuring accurate timekeeping and policy enforcement']
            ];
        } elseif ($is_leadership) {
            $skills = [
                'score_floor_mgmt' => ['title' => 'Floor Management', 'desc' => 'Handling rushes, deploying staff effectively'],
                'score_staff_training' => ['title' => 'Staff Training', 'desc' => 'Coaching trainees, enforcing standards'],
                'score_quality_control' => ['title' => 'Quality Control', 'desc' => 'Ensuring food/drink quality and cleanliness'],
                'score_reliability' => ['title' => 'Reliability & Admin', 'desc' => 'Punctuality, closing registers, reporting issues']
            ];
        } else {
            $skills = [
                'score_kitchen' => ['title' => 'Kitchen', 'desc' => 'Drink quality, food heating, plating, speed'],
                'score_cashier' => ['title' => 'Cashier', 'desc' => 'Order accuracy, POS speed, customer service'],
                'score_cleaning' => ['title' => 'Cleaning', 'desc' => 'Dining area, kitchen/bar cleanliness'],
                'score_inventory' => ['title' => 'Inventory Management', 'desc' => 'Stock counting, FIFO rotation, waste tracking']
            ];
        }
        foreach ($skills as $field => $data):
        ?>
        <div class="flex items-center justify-between">
            <div>
                <p class="font-bold text-slate-700 text-sm"><?= $data['title'] ?></p>
                <p class="text-xs text-slate-500"><?= $data['desc'] ?></p>
            </div>
            <div class="star-rating">
                <input type="radio" id="<?= $field ?>5" name="<?= $field ?>" value="5" required /><label for="<?= $field ?>5" title="5 stars"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="<?= $field ?>4" name="<?= $field ?>" value="4" /><label for="<?= $field ?>4" title="4 stars"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="<?= $field ?>3" name="<?= $field ?>" value="3" /><label for="<?= $field ?>3" title="3 stars"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="<?= $field ?>2" name="<?= $field ?>" value="2" /><label for="<?= $field ?>2" title="2 stars"><i class="fa-solid fa-star"></i></label>
                <input type="radio" id="<?= $field ?>1" name="<?= $field ?>" value="1" /><label for="<?= $field ?>1" title="1 star"><i class="fa-solid fa-star"></i></label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div>
        <h3 class="font-bold text-slate-700 border-b border-slate-200 pb-2 mb-4">Overall Comments</h3>
        <textarea name="comments" rows="3" class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Any specific feedback or areas for improvement?"></textarea>
    </div>
    
    <div class="flex justify-end pt-4 border-t border-slate-200">
        <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
            <?= $evaluation_type === 'Official' ? 'Submit Official Evaluation' : 'Submit Floor Feedback' ?>
        </button>
    </div>
    
</form>

</body>
</html>

