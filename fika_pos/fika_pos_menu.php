<?php
require_once __DIR__ . '/../app/bootstrap.php';

Auth::requireLogin();
Rbac::require_permission('pos.menu.view');

$canManage = Rbac::can('pos.menu.manage');
$user = Auth::user();

global $pdo;

// Branch Scope
$userBranchId = $user['branch_id'] ?? null;
$selectedBranchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : $userBranchId;

if ($selectedBranchId !== null) {
    Rbac::assert_branch_access($selectedBranchId);
}

// Fetch branches for branch switcher (if admin or accessible)
$branches = [];
if ($userBranchId === null) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    if ($selectedBranchId === null && !empty($branches)) {
        $selectedBranchId = (int)$branches[0]['id'];
    }
} else {
    $bStmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $bStmt->execute([$userBranchId]);
    $branches = $bStmt->fetchAll();
}

// Ensure initial default categories exist
$pdo->exec("INSERT IGNORE INTO categories (name) VALUES ('Hot'), ('Cold'), ('Special'), ('Pastries')");

// Fetch categories with linked product count
$categories = $pdo->query("
    SELECT c.id, c.name, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category = c.name AND p.deleted_at IS NULL
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Search and Category Filter
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$sql = "
    SELECT p.*, bp.is_active AS branch_active
    FROM products p
    LEFT JOIN branch_products bp ON bp.product_id = p.id AND bp.branch_id = ?
    WHERE p.deleted_at IS NULL
";
$params = [$selectedBranchId];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.category LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($categoryFilter !== '') {
    $sql .= " AND p.category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY p.category ASC, p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu & Product Management - Fika POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <!-- CSRF Token Meta -->
    <input type="hidden" id="csrf-token" value="<?= e($csrfToken) ?>">
    <input type="hidden" id="current-branch-id" value="<?= e($selectedBranchId ?? '') ?>">

    <!-- Top Navigation -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl font-black tracking-tight text-amber-800">FIKA</span>
                    <span class="text-xs px-2 py-0.5 rounded font-semibold bg-amber-100 text-amber-800 uppercase tracking-wider">POS Menu</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-semibold text-slate-900"><?= e($user['username'] ?? 'User') ?></div>
                        <div class="text-xs text-slate-500">
                            <?= $userBranchId ? 'Branch #' . e($userBranchId) : 'HQ Admin' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Menu & Products</h1>
                <p class="text-sm text-slate-500 mt-1">Manage categories, products, prices, and per-branch availability.</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($canManage): ?>
                <button type="button" onclick="openCategoryModal()" class="inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-lg shadow-sm text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Manage Categories
                </button>
                <button onclick="openProductModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-amber-700 hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Product
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="flex-1 flex flex-col sm:flex-row gap-3 w-full">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search product name..." class="pl-10 block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border">
                    </div>

                    <div class="w-full sm:w-56">
                        <select name="category" onchange="this.form.submit()" class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['name']) ?>" <?= $categoryFilter === $cat['name'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (count($branches) > 1): ?>
                    <div class="w-full sm:w-56">
                        <select name="branch_id" onchange="this.form.submit()" class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border bg-amber-50/50 text-amber-950 font-medium">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= e($b['id']) ?>" <?= (int)$selectedBranchId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition-colors">
                        Filter
                    </button>
                    <?php if ($search !== '' || $categoryFilter !== ''): ?>
                        <a href="fika_pos_menu.php<?= $selectedBranchId ? '?branch_id=' . e($selectedBranchId) : '' ?>" class="text-sm text-slate-500 hover:text-slate-800">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <h3 class="mt-4 text-base font-medium text-slate-900">No products found</h3>
                <p class="mt-1 text-sm text-slate-500">Get started by creating a new menu product or adjust your filters.</p>
                <?php if ($canManage): ?>
                <div class="mt-6">
                    <button onclick="openProductModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-amber-700 hover:bg-amber-800">
                        Add First Product
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($products as $p): ?>
                    <?php 
                        $priceCents = (int)($p['price_cents'] ?? round((float)$p['price'] * 100));
                        $isBranchActive = ($p['branch_active'] === null || (int)$p['branch_active'] === 1) && (int)$p['is_active'] === 1;
                        $imageSrc = !empty($p['image_path']) ? '../' . ltrim($p['image_path'], '/') : null;
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
                        <div>
                            <!-- Product Image / Placeholder -->
                            <div class="w-full h-44 bg-slate-100 relative overflow-hidden flex items-center justify-center">
                                <?php if ($imageSrc): ?>
                                    <img src="<?= e($imageSrc) ?>" alt="<?= e($p['image_alt'] ?? $p['name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="text-slate-300 flex flex-col items-center">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/></svg>
                                        <span class="text-xs text-slate-400 mt-1">No Image</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Availability Badge -->
                                <span class="absolute top-2.5 right-2.5 text-xs font-semibold px-2 py-0.5 rounded-full shadow-sm <?= $isBranchActive ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                    <?= $isBranchActive ? 'Available' : 'Unavailable' ?>
                                </span>
                            </div>

                            <!-- Product Info -->
                            <div class="p-4">
                                <div class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-1">
                                    <?= e($p['category']) ?>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 truncate" title="<?= e($p['name']) ?>">
                                    <?= e($p['name']) ?>
                                </h3>
                                <div class="mt-2 text-lg font-bold text-slate-800">
                                    <?= e(Money::format($priceCents)) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Controls -->
                        <div class="p-4 pt-0 border-t border-slate-100 mt-2 flex items-center justify-between">
                            <?php if ($canManage): ?>
                            <!-- Branch Availability Toggle -->
                            <button onclick="toggleAvailability(<?= (int)$p['id'] ?>, <?= $isBranchActive ? 0 : 1 ?>)" class="text-xs font-medium px-2.5 py-1.5 rounded-lg border transition-colors <?= $isBranchActive ? 'border-amber-200 text-amber-800 hover:bg-amber-50' : 'border-emerald-200 text-emerald-800 hover:bg-emerald-50' ?>">
                                <?= $isBranchActive ? 'Mark Out of Stock' : 'Mark Available' ?>
                            </button>

                            <div class="flex items-center space-x-1">
                                <button onclick='editProduct(<?= json_encode($p) ?>)' class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button onclick="confirmDelete(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-slate-100" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-slate-400 italic">Read-only view</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Product Create/Edit Modal -->
    <div id="product-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 id="modal-title" class="text-lg font-bold text-slate-900">Add Product</h3>
                <button onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="product-form" onsubmit="saveProduct(event)" class="p-6 space-y-4">
                <input type="hidden" id="prod-id" name="id" value="">
                <input type="hidden" id="prod-branch-id" name="branch_id" value="<?= e($selectedBranchId ?? '') ?>">
                <input type="hidden" id="prod-delete-image" name="delete_image" value="0">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Name *</label>
                    <input type="text" id="prod-name" name="name" required class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border" placeholder="e.g. Spanish Latte">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category *</label>
                        <select id="prod-category" name="category" required class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Price (₱) *</label>
                        <input type="number" step="0.01" min="0" id="prod-price" name="price" required class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border" placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Image</label>
                    <input type="file" id="prod-image" name="image" accept="image/jpeg,image/png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    <p class="text-xs text-slate-400 mt-1">Accepts JPG or PNG (max 5MB). Verified securely via finfo.</p>
                </div>

                <div id="image-preview-container" class="hidden flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="flex items-center space-x-3">
                        <img id="image-preview" src="" class="w-12 h-12 object-cover rounded-md border border-slate-200">
                        <span class="text-xs font-medium text-slate-700">Current Image</span>
                    </div>
                    <button type="button" onclick="removeCurrentImage()" class="text-xs text-rose-600 hover:text-rose-800 font-semibold">Remove Image</button>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Image Description (Alt Text)</label>
                    <input type="text" id="prod-image-alt" name="image_alt" maxlength="160" class="block w-full rounded-lg border-slate-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm p-2.5 border" placeholder="Short description for accessibility">
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 shadow-sm">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category Management Modal -->
    <div id="category-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Manage Categories</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Add new menu categories or remove empty ones.</p>
                </div>
                <button type="button" onclick="closeCategoryModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 space-y-5">
                <!-- Add Category Form -->
                <form id="add-category-form" onsubmit="addCategory(event)" class="flex gap-2">
                    <input type="text" id="new-cat-name" placeholder="New category name (e.g. Milktea)" required maxlength="100" class="flex-1 rounded-lg border-slate-200 text-sm p-2.5 border focus:border-amber-500 focus:ring-amber-500 shadow-xs">
                    <button type="submit" id="add-cat-btn" class="px-4 py-2.5 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 transition-colors shadow-sm flex items-center gap-1.5 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Category</span>
                    </button>
                </form>

                <div id="cat-msg" class="hidden text-xs py-2 px-3 rounded-lg font-medium"></div>

                <!-- Categories List -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Existing Categories</span>
                        <span id="cat-count-badge" class="text-xs text-slate-500"><?= count($categories) ?> categories</span>
                    </div>
                    <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 border border-slate-200 rounded-xl bg-slate-50/50">
                        <ul id="category-list" class="divide-y divide-slate-100">
                            <?php foreach ($categories as $cat): 
                                $pCount = (int)($cat['product_count'] ?? 0);
                            ?>
                                <li id="cat-row-<?= (int)$cat['id'] ?>" class="flex items-center justify-between px-4 py-3 bg-white hover:bg-slate-50/80 transition-colors">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-semibold text-slate-800 cat-row-name"><?= e($cat['name']) ?></span>
                                        <span class="text-[11px] px-2 py-0.5 rounded-full <?= $pCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500' ?> font-medium">
                                            <?= $pCount ?> <?= $pCount === 1 ? 'product' : 'products' ?>
                                        </span>
                                    </div>
                                    <div>
                                        <?php if ($pCount === 0): ?>
                                            <button type="button" onclick="deleteCategory(<?= (int)$cat['id'] ?>, '<?= e(addslashes($cat['name'])) ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Delete Empty Category">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 italic px-2 py-1">In use</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex justify-end">
                    <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50">Done</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.getElementById('csrf-token').value;
        const currentBranchId = document.getElementById('current-branch-id').value;

        function openProductModal() {
            document.getElementById('modal-title').textContent = 'Add Product';
            document.getElementById('prod-id').value = '';
            document.getElementById('prod-name').value = '';
            document.getElementById('prod-category').value = '';
            document.getElementById('prod-price').value = '';
            document.getElementById('prod-image').value = '';
            document.getElementById('prod-image-alt').value = '';
            document.getElementById('prod-delete-image').value = '0';
            document.getElementById('image-preview-container').classList.add('hidden');
            document.getElementById('product-modal').classList.remove('hidden');
        }

        function closeProductModal() {
            document.getElementById('product-modal').classList.add('hidden');
        }

        function editProduct(p) {
            document.getElementById('modal-title').textContent = 'Edit Product';
            document.getElementById('prod-id').value = p.id;
            document.getElementById('prod-name').value = p.name;
            const catSel = document.getElementById('prod-category');
            if (p.category) {
                let found = false;
                for (let i = 0; i < catSel.options.length; i++) {
                    if (catSel.options[i].value.toLowerCase() === p.category.toLowerCase()) {
                        catSel.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    const opt = document.createElement('option');
                    opt.value = p.category;
                    opt.textContent = p.category;
                    catSel.appendChild(opt);
                    catSel.value = p.category;
                }
            } else {
                catSel.value = '';
            }
            
            const priceCents = p.price_cents ? parseInt(p.price_cents) : Math.round(parseFloat(p.price) * 100);
            document.getElementById('prod-price').value = (priceCents / 100).toFixed(2);
            
            document.getElementById('prod-image').value = '';
            document.getElementById('prod-image-alt').value = p.image_alt || '';
            document.getElementById('prod-delete-image').value = '0';

            const previewContainer = document.getElementById('image-preview-container');
            const previewImg = document.getElementById('image-preview');
            if (p.image_path) {
                previewImg.src = '../' + p.image_path.replace(/^\/+/, '');
                previewContainer.classList.remove('hidden');
            } else {
                previewContainer.classList.add('hidden');
            }

            document.getElementById('product-modal').classList.remove('hidden');
        }

        function removeCurrentImage() {
            document.getElementById('prod-delete-image').value = '1';
            document.getElementById('image-preview-container').classList.add('hidden');
        }

        async function saveProduct(e) {
            e.preventDefault();
            const btn = document.getElementById('save-btn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const form = document.getElementById('product-form');
            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('api/fika_pos_menu_save.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: formData
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to save product.'));
                }
            } catch (err) {
                alert('Network error while saving product.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Product';
            }
        }

        async function toggleAvailability(productId, newStatus) {
            try {
                const res = await fetch('api/fika_pos_menu_toggle_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        product_id: productId,
                        branch_id: currentBranchId,
                        is_active: newStatus
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to update availability.'));
                }
            } catch (err) {
                alert('Network error updating availability.');
            }
        }

        async function confirmDelete(id, name) {
            if (!confirm(`Are you sure you want to delete "${name}"? Past order history will be preserved.`)) {
                return;
            }

            try {
                const res = await fetch('api/fika_pos_menu_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        id: id,
                        branch_id: currentBranchId
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete product.'));
                }
            } catch (err) {
                alert('Network error deleting product.');
            }
        }

        function openCategoryModal() {
            document.getElementById('cat-msg').classList.add('hidden');
            document.getElementById('new-cat-name').value = '';
            document.getElementById('category-modal').classList.remove('hidden');
            document.getElementById('new-cat-name').focus();
        }

        function closeCategoryModal() {
            document.getElementById('category-modal').classList.add('hidden');
        }

        async function addCategory(e) {
            e.preventDefault();
            const nameInput = document.getElementById('new-cat-name');
            const name = nameInput.value.trim();
            if (!name) return;

            const btn = document.getElementById('add-cat-btn');
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Adding...';

            const msgBox = document.getElementById('cat-msg');
            msgBox.classList.add('hidden');

            try {
                const res = await fetch('api/fika_pos_category_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        name: name
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    nameInput.value = '';
                    msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-emerald-50 text-emerald-800 border border-emerald-200';
                    msgBox.textContent = `Category "${data.category.name}" added successfully!`;
                    msgBox.classList.remove('hidden');

                    // 1. Instantly append to Product Modal Category <select> dropdown (Constraint 3)
                    const prodSelect = document.getElementById('prod-category');
                    if (prodSelect) {
                        let exists = false;
                        for (let i = 0; i < prodSelect.options.length; i++) {
                            if (prodSelect.options[i].value.toLowerCase() === data.category.name.toLowerCase()) {
                                exists = true;
                                break;
                            }
                        }
                        if (!exists) {
                            const opt = document.createElement('option');
                            opt.value = data.category.name;
                            opt.textContent = data.category.name;
                            prodSelect.appendChild(opt);
                        }
                    }

                    // 2. Also append to Filter category select
                    const filterSelect = document.querySelector('select[name="category"]');
                    if (filterSelect) {
                        let fExists = false;
                        for (let i = 0; i < filterSelect.options.length; i++) {
                            if (filterSelect.options[i].value.toLowerCase() === data.category.name.toLowerCase()) {
                                fExists = true;
                                break;
                            }
                        }
                        if (!fExists) {
                            const fOpt = document.createElement('option');
                            fOpt.value = data.category.name;
                            fOpt.textContent = data.category.name;
                            filterSelect.appendChild(fOpt);
                        }
                    }

                    // 3. Append to Modal category list
                    const list = document.getElementById('category-list');
                    const li = document.createElement('li');
                    li.id = 'cat-row-' + data.category.id;
                    li.className = 'flex items-center justify-between px-4 py-3 bg-white hover:bg-slate-50/80 transition-colors animate-in fade-in duration-150';
                    li.innerHTML = `
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-semibold text-slate-800 cat-row-name">${escapeHtml(data.category.name)}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-medium">0 products</span>
                        </div>
                        <div>
                            <button type="button" onclick="deleteCategory(${data.category.id}, '${escapeJs(data.category.name)}')" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Delete Empty Category">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    `;
                    list.appendChild(li);

                    // Update badge count
                    const countBadge = document.getElementById('cat-count-badge');
                    if (countBadge) {
                        const total = list.children.length;
                        countBadge.textContent = `${total} categories`;
                    }
                } else {
                    msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-rose-50 text-rose-800 border border-rose-200';
                    msgBox.textContent = data.error || 'Failed to add category.';
                    msgBox.classList.remove('hidden');
                }
            } catch (err) {
                msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-rose-50 text-rose-800 border border-rose-200';
                msgBox.textContent = 'Network error while adding category.';
                msgBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        async function deleteCategory(id, name) {
            if (!confirm(`Are you sure you want to delete category "${name}"?`)) {
                return;
            }

            const msgBox = document.getElementById('cat-msg');
            msgBox.classList.add('hidden');

            try {
                const res = await fetch('api/fika_pos_category_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        id: id
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    // Remove from list
                    const row = document.getElementById('cat-row-' + id);
                    if (row) row.remove();

                    // Remove from select dropdowns
                    const prodSelect = document.getElementById('prod-category');
                    if (prodSelect) {
                        for (let i = 0; i < prodSelect.options.length; i++) {
                            if (prodSelect.options[i].value.toLowerCase() === name.toLowerCase()) {
                                prodSelect.remove(i);
                                break;
                            }
                        }
                    }
                    const filterSelect = document.querySelector('select[name="category"]');
                    if (filterSelect) {
                        for (let i = 0; i < filterSelect.options.length; i++) {
                            if (filterSelect.options[i].value.toLowerCase() === name.toLowerCase()) {
                                filterSelect.remove(i);
                                break;
                            }
                        }
                    }

                    msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-emerald-50 text-emerald-800 border border-emerald-200';
                    msgBox.textContent = `Category "${name}" deleted.`;
                    msgBox.classList.remove('hidden');

                    const countBadge = document.getElementById('cat-count-badge');
                    if (countBadge) {
                        const list = document.getElementById('category-list');
                        countBadge.textContent = `${list.children.length} categories`;
                    }
                } else {
                    msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-rose-50 text-rose-800 border border-rose-200';
                    msgBox.textContent = data.error || 'Failed to delete category.';
                    msgBox.classList.remove('hidden');
                }
            } catch (err) {
                msgBox.className = 'text-xs py-2 px-3 rounded-lg font-medium bg-rose-50 text-rose-800 border border-rose-200';
                msgBox.textContent = 'Network error while deleting category.';
                msgBox.classList.remove('hidden');
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function escapeJs(str) {
            return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }
    </script>
</body>
</html>
