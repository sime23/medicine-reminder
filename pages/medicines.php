<?php
/**
 * Medicines List Page
 * 
 * Display all medications with dosage info and add/edit functionality
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $medId = (int)$_GET['delete'];
        
        // Verify ownership before deleting
        $med = fetchOne(
            "SELECT medication_id FROM medications WHERE medication_id = :id AND user_id = :user_id",
            ['id' => $medId, 'user_id' => $userId]
        );
        
        if ($med) {
            // Soft delete - just mark as inactive
            update('medications', ['is_active' => 0], 'medication_id = :id', ['id' => $medId]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Medication deleted successfully'];
        }
        
        header('Location: medicines.php');
        exit();
    } catch (Exception $e) {
        error_log("Delete medication error: " . $e->getMessage());
    }
}

// Get all medications for user
try {
    $medicines = fetchAll(
        "SELECT m.*, 
            (SELECT COUNT(*) FROM schedules WHERE medication_id = m.medication_id AND is_active = 1) as schedule_count,
            (SELECT COUNT(*) FROM medication_logs WHERE medication_id = m.medication_id AND status = 'taken' AND scheduled_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_taken
         FROM medications m
         WHERE m.user_id = :user_id AND m.is_active = 1
         ORDER BY m.created_at DESC",
        ['user_id' => $userId]
    );
} catch (Exception $e) {
    $medicines = [];
    error_log("Fetch medicines error: " . $e->getMessage());
}

// Get medication colors for selection
$medColors = [
    '#0d9488' => 'Teal',
    '#3b82f6' => 'Blue',
    '#8b5cf6' => 'Purple',
    '#ec4899' => 'Pink',
    '#ef4444' => 'Red',
    '#f97316' => 'Orange',
    '#eab308' => 'Yellow',
    '#22c55e' => 'Green',
    '#06b6d4' => 'Cyan',
    '#6366f1' => 'Indigo',
];

$pageTitle = 'My Medicines';
$currentPage = 'medicines';
?>
<!DOCTYPE html>
<html lang="en" <?php echo ($_SESSION['theme'] ?? 'light') === 'dark' ? 'data-theme="dark"' : ''; ?>>
<head>
    <?php include __DIR__ . '/../includes/theme_init.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Medicine Reminder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">My Medicines</h1>
                <p class="text-slate-500 mt-1">Manage your medications and schedules</p>
            </div>
            <button onclick="openAddModal()" class="btn btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Medication
            </button>
        </div>

        <!-- Success Message -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-100 flex items-start gap-3 animate-fadeIn">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-emerald-700"><?php echo htmlspecialchars($message['text']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Medicines Grid -->
        <?php if (empty($medicines)): ?>
        <div class="card">
            <div class="card-body text-center py-16">
                <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-slate-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-2">No medications yet</h3>
                <p class="text-slate-500 mb-6 max-w-md mx-auto">Start tracking your medications by adding your first one. We'll help you stay on schedule.</p>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Your First Medication
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($medicines as $med): ?>
            <div class="card group">
                <div class="p-5">
                    <div class="flex items-start gap-4">
                        <!-- Medication Icon -->
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                             style="background-color: <?php echo htmlspecialchars($med['color'] ?? '#0d9488'); ?>">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        
                        <!-- Medication Info -->
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($med['medication_name']); ?></h3>
                            <p class="text-sm text-slate-500"><?php echo htmlspecialchars($med['dosage_amount']); ?></p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="badge badge-primary"><?php echo ucfirst($med['dosage_form']); ?></span>
                                <?php if ($med['schedule_count'] > 0): ?>
                                <span class="badge badge-success">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Scheduled
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Actions Menu -->
                        <div class="relative">
                            <button onclick="toggleMenu(<?php echo $med['medication_id']; ?>)" class="p-2 rounded-lg hover:bg-slate-100 transition-colors">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                </svg>
                            </button>
                            <div id="menu-<?php echo $med['medication_id']; ?>" class="hidden absolute right-0 top-full mt-1 w-48 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-10">
                                <a href="schedule.php?med_id=<?php echo $med['medication_id']; ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    View Schedule
                                </a>
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($med)); ?>)" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit
                                </button>
                                <hr class="my-1 border-slate-100">
                                <a href="medicines.php?delete=<?php echo $med['medication_id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this medication?')"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Info -->
                    <?php if ($med['instructions']): ?>
                    <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                        <p class="text-sm text-slate-600">
                            <span class="font-medium">Instructions:</span> <?php echo htmlspecialchars($med['instructions']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Footer Stats -->
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-sm">
                        <div class="flex items-center gap-4">
                            <span class="text-slate-500">
                                <span class="font-medium text-slate-700"><?php echo $med['recent_taken']; ?></span> taken (30d)
                            </span>
                            <?php if ($med['remaining_quantity']): ?>
                            <span class="text-slate-500">
                                <span class="font-medium text-slate-700"><?php echo $med['remaining_quantity']; ?></span> left
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($med['schedule_count'] == 0): ?>
                        <a href="schedule_add.php?med_id=<?php echo $med['medication_id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium text-xs">
                            + Add Schedule
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Add Medication Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Add New Medication</h3>
                <button onclick="closeAddModal()" class="modal-close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="api/medicine_add.php" method="POST" class="space-y-0">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="form-label">Medication Name <span class="text-red-500">*</span></label>
                            <input type="text" name="medication_name" required class="form-input" placeholder="e.g., Amoxicillin">
                        </div>
                        
                        <div>
                            <label class="form-label">Generic Name</label>
                            <input type="text" name="generic_name" class="form-input" placeholder="e.g., Amoxicillin Trihydrate">
                        </div>
                        
                        <div>
                            <label class="form-label">Brand Name</label>
                            <input type="text" name="brand_name" class="form-input" placeholder="e.g., Amoxil">
                        </div>
                        
                        <div>
                            <label class="form-label">Dosage Amount <span class="text-red-500">*</span></label>
                            <input type="text" name="dosage_amount" required class="form-input" placeholder="e.g., 500mg, 2 tablets">
                        </div>
                        
                        <div>
                            <label class="form-label">Form</label>
                            <select name="dosage_form" class="form-select">
                                <option value="tablet">Tablet</option>
                                <option value="capsule">Capsule</option>
                                <option value="liquid">Liquid</option>
                                <option value="injection">Injection</option>
                                <option value="inhaler">Inhaler</option>
                                <option value="drops">Drops</option>
                                <option value="patch">Patch</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Color</label>
                            <div class="grid grid-cols-5 gap-2">
                                <?php foreach ($medColors as $color => $name): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="color" value="<?php echo $color; ?>" 
                                           class="sr-only peer" <?php echo $color === '#0d9488' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-lg peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-primary-500 transition-all"
                                         style="background-color: <?php echo $color; ?>"
                                         title="<?php echo $name; ?>"></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">Shape</label>
                            <select name="shape" class="form-select">
                                <option value="">Select shape</option>
                                <option value="round">Round</option>
                                <option value="oval">Oval</option>
                                <option value="capsule">Capsule</option>
                                <option value="square">Square</option>
                                <option value="triangle">Triangle</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label">Instructions</label>
                            <textarea name="instructions" class="form-textarea" rows="2" placeholder="e.g., Take with food, Avoid alcohol"></textarea>
                        </div>
                        
                        <div>
                            <label class="form-label">Prescribed By</label>
                            <input type="text" name="prescribed_by" class="form-input" placeholder="Doctor's name">
                        </div>
                        
                        <div>
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div>
                            <label class="form-label">End Date (optional)</label>
                            <input type="date" name="end_date" class="form-input">
                        </div>
                        
                        <div>
                            <label class="form-label">Total Quantity</label>
                            <input type="number" name="total_quantity" class="form-input" placeholder="e.g., 30" min="0">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-textarea" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Medication</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Medication Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Edit Medication</h3>
                <button onclick="closeEditModal()" class="modal-close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="api/medicine_edit.php" method="POST" class="space-y-0">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="medication_id" id="edit_med_id">
                <div class="modal-body space-y-4">
                    <!-- Same fields as add modal, populated via JS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="form-label">Medication Name <span class="text-red-500">*</span></label>
                            <input type="text" name="medication_name" id="edit_name" required class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Dosage Amount <span class="text-red-500">*</span></label>
                            <input type="text" name="dosage_amount" id="edit_dosage" required class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Form</label>
                            <select name="dosage_form" id="edit_form" class="form-select">
                                <option value="tablet">Tablet</option>
                                <option value="capsule">Capsule</option>
                                <option value="liquid">Liquid</option>
                                <option value="injection">Injection</option>
                                <option value="inhaler">Inhaler</option>
                                <option value="drops">Drops</option>
                                <option value="patch">Patch</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Instructions</label>
                            <textarea name="instructions" id="edit_instructions" class="form-textarea" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="form-label">Color</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <?php foreach ($medColors as $color => $name): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="color" value="<?php echo $color; ?>"
                                           id="edit_color_<?php echo ltrim($color,'#'); ?>"
                                           class="sr-only peer">
                                    <div class="w-8 h-8 rounded-lg peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-primary-500 transition-all"
                                         style="background-color: <?php echo $color; ?>"
                                         title="<?php echo $name; ?>"></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Prescribed By</label>
                            <input type="text" name="prescribed_by" id="edit_prescribed_by" class="form-input" placeholder="Doctor's name">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(med) {
            document.getElementById('edit_med_id').value        = med.medication_id;
            document.getElementById('edit_name').value          = med.medication_name;
            document.getElementById('edit_dosage').value        = med.dosage_amount;
            document.getElementById('edit_form').value          = med.dosage_form;
            document.getElementById('edit_instructions').value  = med.instructions || '';
            document.getElementById('edit_prescribed_by').value = med.prescribed_by || '';

            // Pre-select color radio
            var colorId = 'edit_color_' + (med.color || '#0d9488').replace('#','');
            var colorRadio = document.getElementById(colorId);
            if (colorRadio) colorRadio.checked = true;

            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function toggleMenu(medId) {
            const menu = document.getElementById('menu-' + medId);
            const allMenus = document.querySelectorAll('[id^="menu-"]');
            
            allMenus.forEach(m => {
                if (m !== menu) m.classList.add('hidden');
            });
            
            menu.classList.toggle('hidden');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                document.querySelectorAll('[id^="menu-"]').forEach(m => m.classList.add('hidden'));
            }
        });

        // Close modals on outside click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
