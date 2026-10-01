<?php
require_once __DIR__ . '/init.php';

// Access Control
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Accountant'])) {
    redirect('dashboard');
}

$pageTitle = 'Procurement';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $inventory_id = $_POST['inventory_id'];
    $quantity = $_POST['quantity'];
    $estimated_cost = $_POST['estimated_cost'];
    $branch_id = $_SESSION['user']['branch_id'] ?? 1;
    $requested_by = $_SESSION['user']['id'];
    
    $stmt = $pdo->prepare("SELECT item_name FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $insert = $pdo->prepare("INSERT INTO purchase_requests (inventory_id, branch_id, requested_by, item_name, quantity, estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $insert->execute([$inventory_id, $branch_id, $requested_by, $item['item_name'], $quantity, $estimated_cost]);
    }
    
    redirect('procurement');
}

// Fetch requested item context if any
$request_item = null;
if (isset($_GET['request_item_id']) && !empty($_GET['request_item_id'])) {
    $stmt = $pdo->prepare("SELECT item_name, unit FROM inventory WHERE id = ?");
    $stmt->execute([$_GET['request_item_id']]);
    $request_item = $stmt->fetch();
}

// Fetch table data
$stmt = $pdo->query("
    SELECT p.*, i.item_name, i.unit, b.name as branch_name
    FROM purchase_requests p
    JOIN inventory i ON p.inventory_id = i.id
    LEFT JOIN branches b ON p.branch_id = b.id
    ORDER BY p.created_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement - Cafe HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 font-sans text-slate-800 h-screen flex overflow-hidden">

    <?php include 'includes/header.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center z-10">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Procurement</h2>
                <p class="text-sm text-slate-500">Restock Requests & Order Management</p>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 relative">
            <?php if (isset($_GET['request_item_id'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                    <h5 class="text-lg font-bold text-slate-800 mb-4">Create Restock Request: <span class="text-blue-600"><?php echo $request_item ? htmlspecialchars($request_item['item_name']) : 'Item Not Found'; ?></span></h5>
                    <form method="POST" action="procurement">
                        <input type="hidden" name="inventory_id" value="<?php echo htmlspecialchars($_GET['request_item_id']); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Quantity Requested (<?php echo $request_item ? htmlspecialchars($request_item['unit']) : '-'; ?>)</label>
                                <input type="number" step="0.01" name="quantity" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Estimated Total Cost (₱)</label>
                                <input type="number" step="0.01" name="estimated_cost" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 border" required>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" name="submit_request" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm w-full md:w-auto shadow-sm">Submit Request</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <h3 class="text-lg font-bold text-slate-700 mb-4">Restock Requests</h3>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                            <th class="p-4 font-medium">Date</th>
                            <th class="p-4 font-medium">Branch</th>
                            <th class="p-4 font-medium">Item</th>
                            <th class="p-4 font-medium">Requested Quantity</th>
                            <th class="p-4 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-500">No restock requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach($requests as $row): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                    <td class="p-4 text-slate-600 text-sm"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at']))) ?></td>
                                    <td class="p-4 font-medium text-slate-900"><?= htmlspecialchars($row['branch_name'] ?? 'Global') ?></td>
                                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td class="p-4 font-medium text-slate-700"><?= number_format($row['quantity'] ?? 0, 2) ?> <?= htmlspecialchars($row['unit']) ?></td>
                                    <td class="p-4 text-sm font-medium">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($row['status']) ?></span>
                                        <?php elseif ($row['status'] === 'Approved'): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($row['status']) ?></span>
                                        <?php elseif ($row['status'] === 'Ordered'): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($row['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
