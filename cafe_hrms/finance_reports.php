<?php
require_once __DIR__ . '/init.php';

// Access Control
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], [ROLE_SUPER_ADMIN, ROLE_EXECUTIVE, ROLE_GLOBAL_ACCOUNTANT, ROLE_BRANCH_ACCOUNTANT, ROLE_BRANCH_MANAGER])) {
    redirect('dashboard');
}

if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $output = fopen('php://output', 'w');
    header('Content-Type: text/csv; charset=utf-8');

    if (isset($_GET['month']) && !empty($_GET['month'])) {
        $targetMonth = $_GET['month'];
        $monthFilterT = " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = " . $pdo->quote($targetMonth);
        $monthFilterP = " AND DATE_FORMAT(p.period_end, '%Y-%m') = " . $pdo->quote($targetMonth);
    } else {
        $targetMonth = date('Y-m');
        $monthFilterT = "";
        $monthFilterP = "";
    }
    
    $branchFilterT = get_branch_filter('t');
    $branchFilterP = get_branch_filter('p');

    if ($exportType === 'csv') {
        $stmt = $pdo->query("
            SELECT t.transaction_date, b.name as branch_name, t.item_name, t.quantity, i.unit, t.cost as financial_impact
            FROM inventory_transactions t
            JOIN inventory i ON t.inventory_id = i.id
            LEFT JOIN branches b ON t.branch_id = b.id
            WHERE t.type = 'Write-off' $branchFilterT $monthFilterT
            ORDER BY t.transaction_date DESC
        ");
        header("Content-Disposition: attachment; filename=inventory_writeoffs_{$targetMonth}.csv");
        fputcsv($output, ['Date', 'Branch', 'Item', 'Quantity Deducted', 'Unit', 'Financial Impact']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['transaction_date'], $row['branch_name'] ?? 'Global', $row['item_name'], 
                $row['quantity'], $row['unit'], $row['financial_impact']
            ]);
        }
    } 
    elseif ($exportType === 'sss') {
        $stmt = $pdo->query("
            SELECT b.name as branch_name, DATE_FORMAT(p.period_end, '%Y-%m') as month, e.first_name, e.last_name, e.sss as sss_no, 
                   SUM(p.gross_pay) as gross_pay, SUM(p.sss) as ee_share, SUM(p.employer_sss) as er_share
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.status = 'Released' $branchFilterP $monthFilterP
            GROUP BY p.branch_id, month, e.id
            ORDER BY month DESC, b.name ASC, e.last_name ASC
        ");
        header("Content-Disposition: attachment; filename=sss_remittance_{$targetMonth}.csv");
        fputcsv($output, ['Branch', 'Month', 'Employee Name', 'SSS No', 'Gross Pay', 'EE Share', 'ER Share', 'Total SSS']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total = $row['ee_share'] + $row['er_share'];
            fputcsv($output, [$row['branch_name'], $row['month'], $row['last_name'] . ', ' . $row['first_name'], decryptData($row['sss_no']), $row['gross_pay'], $row['ee_share'], $row['er_share'], $total]);
        }
    }
    elseif ($exportType === 'philhealth') {
        $stmt = $pdo->query("
            SELECT b.name as branch_name, DATE_FORMAT(p.period_end, '%Y-%m') as month, e.first_name, e.last_name, e.philhealth as ph_no, 
                   SUM(p.gross_pay) as gross_pay, SUM(p.philhealth) as ee_share, SUM(p.employer_philhealth) as er_share
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.status = 'Released' $branchFilterP $monthFilterP
            GROUP BY p.branch_id, month, e.id
            ORDER BY month DESC, b.name ASC, e.last_name ASC
        ");
        header("Content-Disposition: attachment; filename=philhealth_remittance_{$targetMonth}.csv");
        fputcsv($output, ['Branch', 'Month', 'Employee Name', 'PhilHealth No', 'Gross Pay', 'EE Share', 'ER Share', 'Total PH']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total = $row['ee_share'] + $row['er_share'];
            fputcsv($output, [$row['branch_name'], $row['month'], $row['last_name'] . ', ' . $row['first_name'], decryptData($row['ph_no']), $row['gross_pay'], $row['ee_share'], $row['er_share'], $total]);
        }
    }
    elseif ($exportType === 'pagibig') {
        $stmt = $pdo->query("
            SELECT b.name as branch_name, DATE_FORMAT(p.period_end, '%Y-%m') as month, e.first_name, e.last_name, e.pagibig as hdmf_no, 
                   SUM(p.gross_pay) as gross_pay, SUM(p.pagibig) as ee_share, SUM(p.employer_pagibig) as er_share
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.status = 'Released' $branchFilterP $monthFilterP
            GROUP BY p.branch_id, month, e.id
            ORDER BY month DESC, b.name ASC, e.last_name ASC
        ");
        header("Content-Disposition: attachment; filename=pagibig_remittance_{$targetMonth}.csv");
        fputcsv($output, ['Branch', 'Month', 'Employee Name', 'Pag-IBIG No', 'Gross Pay', 'EE Share', 'ER Share', 'Total HDMF']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total = $row['ee_share'] + $row['er_share'];
            fputcsv($output, [$row['branch_name'], $row['month'], $row['last_name'] . ', ' . $row['first_name'], decryptData($row['hdmf_no']), $row['gross_pay'], $row['ee_share'], $row['er_share'], $total]);
        }
    }
    elseif ($exportType === 'bir') {
        $stmt = $pdo->query("
            SELECT b.name as branch_name, DATE_FORMAT(p.period_end, '%Y-%m') as month, e.first_name, e.last_name, e.tin, 
                   SUM(p.gross_pay) as gross_pay, SUM(p.sss + p.philhealth + p.pagibig) as non_taxable, SUM(p.tax) as tax_withheld
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.status = 'Released' $branchFilterP $monthFilterP
            GROUP BY p.branch_id, month, e.id
            ORDER BY month DESC, b.name ASC, e.last_name ASC
        ");
        header("Content-Disposition: attachment; filename=bir_withholding_{$targetMonth}.csv");
        fputcsv($output, ['Branch', 'Month', 'Employee Name', 'TIN', 'Gross Pay', 'Non-Taxable Contributions', 'Taxable Income', 'Tax Withheld']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $taxable = $row['gross_pay'] - $row['non_taxable'];
            fputcsv($output, [$row['branch_name'], $row['month'], $row['last_name'] . ', ' . $row['first_name'], decryptData($row['tin']), $row['gross_pay'], $row['non_taxable'], $taxable, $row['tax_withheld']]);
        }
    }
    elseif ($exportType === 'payroll_register') {
        $run_id = (int)($_GET['run_id'] ?? 0);
        if (!$run_id) {
            echo "run_id required"; exit;
        }
        $stmt = $pdo->prepare("
            SELECT b.name as branch_name, p.period_start, p.period_end, e.first_name, e.last_name,
                   p.regular_hours, p.overtime_hours, p.overtime_pay, p.night_differential, p.late_deduction,
                   p.gross_pay, p.sss, p.philhealth, p.pagibig, p.tax, p.bonus_amount, p.deductions, p.net_pay
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.run_id = ? AND p.status = 'Released' $branchFilterP
            ORDER BY e.last_name ASC
        ");
        $stmt->execute([$run_id]);
        header('Content-Disposition: attachment; filename=payroll_register_run_' . $run_id . '.csv');
        fputcsv($output, ['Run ID', 'Branch', 'Period Start', 'Period End', 'Employee Name', 'Reg Hours', 'OT Hours', 'OT Pay', 'ND Pay', 'Late Ded', 'Gross Pay', 'SSS', 'PhilHealth', 'Pag-IBIG', 'Tax', 'Bonus', 'Total Deductions', 'Net Pay']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                'RUN-' . $run_id, $row['branch_name'], $row['period_start'], $row['period_end'], $row['last_name'] . ', ' . $row['first_name'],
                $row['regular_hours'], $row['overtime_hours'], $row['overtime_pay'], $row['night_differential'], $row['late_deduction'],
                $row['gross_pay'], $row['sss'], $row['philhealth'], $row['pagibig'], $row['tax'], $row['bonus_amount'], $row['deductions'], $row['net_pay']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

$pageTitle = 'Financial Reports';

// Fetch table data
    $targetMonth = $_GET['month'] ?? '';
    $monthFilterT = !empty($targetMonth) ? " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = " . $pdo->quote($targetMonth) : "";
    $branchFilterT = get_branch_filter('t');
    
    $stmt = $pdo->query("
        SELECT t.*, i.unit, b.name as branch_name
        FROM inventory_transactions t
        JOIN inventory i ON t.inventory_id = i.id
        LEFT JOIN branches b ON t.branch_id = b.id
        WHERE t.type = 'Write-off' $branchFilterT $monthFilterT
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
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-col md:flex-row justify-between items-start md:items-center z-10 gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Financial Reports</h2>
                <p class="text-sm text-slate-500">Statutory Remittances, Payroll & Ledger</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <!-- Dropdown for Remittance -->
                <div class="relative group">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                        <i class="fa-solid fa-file-invoice-dollar mr-2"></i> Statutory Exports <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                        <a href="#" onclick="exportWithFilters('sss')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100">SSS Remittance</a>
                        <a href="#" onclick="exportWithFilters('philhealth')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100">PhilHealth Remittance</a>
                        <a href="#" onclick="exportWithFilters('pagibig')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100">Pag-IBIG Remittance</a>
                        <a href="#" onclick="exportWithFilters('bir')" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">BIR Withholding</a>
                    </div>
                </div>

                <!-- Run ID for Payroll -->
                <form action="" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="export" value="payroll_register">
                    <input type="number" name="run_id" placeholder="Run ID" class="w-24 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <i class="fa-solid fa-file-invoice mr-2"></i> Payroll Register
                    </button>
                </form>

                <a href="#" onclick="exportWithFilters('csv')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <i class="fa-solid fa-file-csv mr-2"></i> Write-Offs
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-slate-50 relative">
            
            <!-- Filters -->
            <form method="GET" class="mb-6 flex gap-4 items-end bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Filter Month</label>
                    <input type="month" id="filter_month" name="month" value="<?= h($targetMonth) ?>" class="rounded-lg border-slate-300 border p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                </div>
                <?php if (!empty($targetMonth)): ?>
                <div>
                    <a href="finance_reports" class="inline-flex items-center px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">
                        <i class="fa-solid fa-xmark mr-1"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
            
            <script>
            function exportWithFilters(type) {
                const month = document.getElementById('filter_month').value;
                let url = '?export=' + type;
                if (month) url += '&month=' + encodeURIComponent(month);
                window.location.href = url;
            }
            </script>

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
