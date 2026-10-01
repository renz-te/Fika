<?php
require_once __DIR__ . '/init.php';

// Access Control
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['Super Admin', 'Admin', 'Accountant'])) {
    redirect('dashboard');
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT t.transaction_date, b.name as branch_name, t.item_name, t.quantity, i.unit, t.cost as financial_impact
        FROM inventory_transactions t
        JOIN inventory i ON t.inventory_id = i.id
        LEFT JOIN branches b ON t.branch_id = b.id
        WHERE t.type = 'Write-off'
        ORDER BY t.transaction_date DESC
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventory_writeoffs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Branch', 'Item', 'Quantity Deducted', 'Unit', 'Financial Impact']);
    foreach ($data as $row) {
        fputcsv($output, [
            $row['transaction_date'],
            $row['branch_name'] ?? 'Global',
            $row['item_name'],
            $row['quantity'],
            $row['unit'],
            $row['financial_impact']
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = 'Financial Reports';

// Fetch table data
$stmt = $pdo->query("
    SELECT t.*, i.unit, b.name as branch_name
    FROM inventory_transactions t
    JOIN inventory i ON t.inventory_id = i.id
    LEFT JOIN branches b ON t.branch_id = b.id
    WHERE t.type = 'Write-off'
    ORDER BY t.transaction_date DESC
");
$writeoffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Cafe HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 font-sans text-slate-800 h-screen flex overflow-hidden">

    <?php include 'includes/header.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center z-10">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Financial Reports</h2>
                <p class="text-sm text-slate-500">Inventory Write-Offs & Ledger Valuation</p>
            </div>
            <div class="flex gap-3">
                <a href="?export=csv" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fa-solid fa-file-csv mr-2"></i> Export to CSV
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 relative">
            <h3 class="text-lg font-bold text-slate-700 mb-4">Inventory Write-Offs</h3>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                            <th class="p-4 font-medium">Date</th>
                            <th class="p-4 font-medium">Branch</th>
                            <th class="p-4 font-medium">Item</th>
                            <th class="p-4 font-medium">Quantity</th>
                            <th class="p-4 font-medium">Financial Impact (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($writeoffs)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-500">No write-offs found.</td></tr>
                        <?php else: ?>
                            <?php foreach($writeoffs as $row): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                    <td class="p-4 text-slate-600 text-sm"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['transaction_date']))) ?></td>
                                    <td class="p-4 font-medium text-slate-900"><?= htmlspecialchars($row['branch_name'] ?? 'Global') ?></td>
                                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td class="p-4 font-medium text-red-600">-<?= number_format($row['quantity'], 2) ?> <?= htmlspecialchars($row['unit']) ?></td>
                                    <td class="p-4 font-bold text-slate-900">₱<?= number_format($row['cost'], 2) ?></td>
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
