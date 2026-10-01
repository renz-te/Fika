# ATS Kanban Refactor: Clean Card + Sliding Drawer Pattern

This document outlines the structural plan to declutter the Kanban board in `applications.php`, moving heavy action blocks into a sliding right drawer.

## Proposed Structural Changes

### 1. Clean Kanban Card Template (`applications.php`)

The Kanban card will be stripped of forms, large alerts, and action buttons. The entire card will become a clickable target that triggers the drawer.

#### [MODIFY] `applications.php` (Card Template Diff)
```diff
- <div class="applicant-card bg-white p-4 rounded-lg shadow-sm border border-slate-200 hover:shadow-md transition-shadow relative group"
-      data-name="<?= h(strtolower($app['full_name'])) ?>" ...>
+ <div class="applicant-card bg-white p-4 rounded-lg shadow-sm border border-slate-200 hover:shadow-md hover:border-primary transition cursor-pointer relative group flex flex-col gap-2"
+      onclick="openApplicantDrawer(<?= $app['id'] ?>)"
+      data-name="<?= h(strtolower($app['full_name'])) ?>" ...>

-     <div class="absolute top-4 right-4 space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
-         <button onclick="openEditModal(...)">...</button>
-     </div>
      
-     <h4 class="font-bold text-slate-800 pr-8"><?= h($app['full_name']) ?></h4>
+     <div class="flex justify-between items-start">
+         <h4 class="font-bold text-slate-800"><?= h($app['full_name']) ?></h4>
+         <?php if ($is_overdue): ?>
+             <span class="text-red-500" title="Overdue Interview"><i class="fa-solid fa-triangle-exclamation"></i></span>
+         <?php endif; ?>
+     </div>

-     <p class="text-sm text-slate-500 font-medium mb-2"><i class="fa-solid fa-briefcase mr-1"></i> <?= h($app['position_applied']) ?></p>
+     <div class="flex items-center justify-between mt-1">
+         <p class="text-xs text-slate-500 font-medium"><i class="fa-solid fa-briefcase mr-1"></i> <?= h($app['position_applied']) ?></p>
+         <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
+             <?= h($app['employment_category'] ?? 'FT') ?>
+         </span>
+     </div>

-     <!-- GIANT ALERT BLOCKS REMOVED -->
-     <!-- ACTION BUTTONS REMOVED -->
-     <!-- FORMS REMOVED -->
  </div>
```

### 2. The Hidden Right Drawer (`applications.php`)

A fixed, full-height HTML drawer will be appended to the bottom of the `applications.php` view. It will remain off-screen (`translate-x-full`) until activated.

#### [NEW/MODIFY] Appending to `applications.php`
```html
<!-- Slide-over Drawer Overlay (Hidden by default) -->
<div id="drawerOverlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-40 hidden transition-opacity opacity-0" onclick="closeApplicantDrawer()"></div>

<!-- Slide-over Drawer -->
<div id="applicantDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    
    <!-- Drawer Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <h2 class="font-bold text-lg text-slate-800" id="drawerTitle">Applicant Details</h2>
        <button onclick="closeApplicantDrawer()" class="text-slate-400 hover:text-slate-600 transition"><i class="fa-solid fa-times text-xl"></i></button>
    </div>
    
    <!-- Drawer Content (Loaded via AJAX) -->
    <div id="drawerContent" class="p-6 flex-1 overflow-y-auto space-y-6">
        <!-- Loader Skeleton -->
        <div class="flex justify-center items-center h-full text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
        </div>
    </div>
</div>
```

### 3. JavaScript Interactivity & AJAX 

We will use JavaScript to toggle the drawer classes and fetch the heavy payload via AJAX so the main page stays lightweight.

#### [NEW] AJAX Script (Added to bottom of `applications.php`)
```javascript
const drawer = document.getElementById('applicantDrawer');
const overlay = document.getElementById('drawerOverlay');
const contentContainer = document.getElementById('drawerContent');

function openApplicantDrawer(applicantId) {
    // Show overlay and slide in drawer
    overlay.classList.remove('hidden');
    setTimeout(() => overlay.classList.remove('opacity-0'), 10);
    drawer.classList.remove('translate-x-full');
    
    // Reset content to loader
    contentContainer.innerHTML = '<div class="flex justify-center items-center h-32 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl"></i></div>';
    
    // Fetch payload
    fetch(`api/get_applicant_drawer.php?id=${applicantId}`)
        .then(res => res.text())
        .then(html => {
            contentContainer.innerHTML = html;
        })
        .catch(err => {
            contentContainer.innerHTML = '<div class="text-red-500 text-center"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Failed to load applicant data.</div>';
        });
}

function closeApplicantDrawer() {
    drawer.classList.add('translate-x-full');
    overlay.classList.add('opacity-0');
    setTimeout(() => overlay.classList.add('hidden'), 300); // Wait for transition
}
```

### 4. Payload Endpoint (`api/get_applicant_drawer.php`)
We will create a new endpoint that renders the stripped-out HTML segments (Large Alert blocks, Edit Buttons, Resume Links, "Move Forward" logic dropdowns, and "Hire/Reject" buttons) strictly for the requested ID. This prevents stale state (e.g., if an interview was completed while the ATS page was open).

## User Review Required
> [!IMPORTANT]
> The drawer requires creating a new AJAX endpoint (`api/get_applicant_drawer.php`) to fetch the heavy HTML payload dynamically. This ensures the main board loads instantly, regardless of volume, and ensures actions in the drawer are always looking at live database state. Is this approach acceptable, or would you prefer the payload to be rendered inside hidden `div`s on the main page?
