<?php
/**
 * Schedule Add Page
 *
 * Dedicated page to create a schedule for a specific medication.
 * Reached from the medicines page via "Add Schedule" on a medication card.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();
$medId  = (int)($_GET['med_id'] ?? 0);

// Load the pre-selected medication (must belong to this user)
$preselected = null;
if ($medId) {
    $preselected = fetchOne(
        "SELECT medication_id, medication_name, dosage_amount FROM medications
         WHERE medication_id = :id AND user_id = :uid AND is_active = 1",
        ['id' => $medId, 'uid' => $userId]
    );
}

// All active medications for the dropdown
try {
    $medicines = fetchAll(
        "SELECT medication_id, medication_name, dosage_amount, color
         FROM medications
         WHERE user_id = :uid AND is_active = 1
         ORDER BY medication_name ASC",
        ['uid' => $userId]
    );
} catch (Exception $e) {
    $medicines = [];
}

$pageTitle   = 'Add Schedule';
$currentPage = 'schedule';
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
                            50:  '#f0fdfa',
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

    <main class="main-content">
        <!-- Page Header -->
        <div class="flex items-center gap-4 mb-8">
            <a href="schedule.php" class="p-2 rounded-lg hover:bg-slate-100 transition-colors text-slate-500 hover:text-slate-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Add Schedule</h1>
                <p class="text-slate-500 mt-1">
                    <?php if ($preselected): ?>
                        Creating a schedule for <strong><?php echo htmlspecialchars($preselected['medication_name']); ?></strong>
                    <?php else: ?>
                        Choose a medication and set its reminder schedule
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): $msg = $_SESSION['message']; unset($_SESSION['message']); ?>
        <div class="alert alert-<?php echo $msg['type'] === 'success' ? 'success' : 'danger'; ?> mb-6">
            <?php echo htmlspecialchars($msg['text']); ?>
        </div>
        <?php endif; ?>

        <div class="max-w-2xl">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Schedule Details</h2>
                </div>
                <div class="card-body">
                    <form action="api/schedule_add.php" method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <!-- Medication -->
                        <div>
                            <label class="form-label">Medication <span class="text-red-500">*</span></label>
                            <select name="medication_id" required class="form-select">
                                <option value="">Select a medication…</option>
                                <?php foreach ($medicines as $med): ?>
                                <option value="<?php echo $med['medication_id']; ?>"
                                    <?php echo ($preselected && $preselected['medication_id'] == $med['medication_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($med['medication_name']); ?>
                                    (<?php echo htmlspecialchars($med['dosage_amount']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($medicines)): ?>
                            <p class="form-hint">No medications found. <a href="medicines.php" class="text-primary-600 hover:underline">Add one first.</a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Frequency -->
                        <div>
                            <label class="form-label">Frequency <span class="text-red-500">*</span></label>
                            <select name="frequency_type" id="freq_type" class="form-select" onchange="toggleFreqFields()">
                                <option value="daily" selected>Daily</option>
                                <option value="twice_daily">Twice Daily</option>
                                <option value="weekly">Weekly (specific days)</option>
                                <option value="once">One-time only</option>
                                <option value="custom">Custom interval</option>
                            </select>
                        </div>

                        <!-- Time 1 (always shown) -->
                        <div>
                            <label class="form-label" id="time1_label">Time <span class="text-red-500">*</span></label>
                            <input type="time" name="time_1" required class="form-input" value="08:00">
                            <p class="form-hint" id="time1_hint">Primary reminder time</p>
                        </div>

                        <!-- Time 2 (twice daily only) -->
                        <div id="time2_field" class="hidden">
                            <label class="form-label">Second Time <span class="text-red-500">*</span></label>
                            <input type="time" name="time_2" class="form-input" value="20:00">
                        </div>

                        <!-- Specific date (once) -->
                        <div id="date_field" class="hidden">
                            <label class="form-label">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="specific_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Days of week (weekly) -->
                        <div id="weekly_fields" class="hidden">
                            <label class="form-label">Days of Week</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <?php $days = ['Mon'=>'monday','Tue'=>'tuesday','Wed'=>'wednesday','Thu'=>'thursday','Fri'=>'friday','Sat'=>'saturday','Sun'=>'sunday']; ?>
                                <?php foreach ($days as $label => $value): ?>
                                <label class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 rounded-lg cursor-pointer hover:bg-slate-100 border border-slate-200 text-sm font-medium text-slate-700 transition-colors">
                                    <input type="checkbox" name="days[]" value="<?php echo $value; ?>" class="rounded border-slate-300 text-primary-600 w-4 h-4">
                                    <?php echo $label; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Custom interval -->
                        <div id="interval_field" class="hidden">
                            <label class="form-label">Every (hours)</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="interval_hours" class="form-input" placeholder="e.g., 8" min="1" max="24" value="8">
                                <span class="text-sm text-slate-500 whitespace-nowrap">hours</span>
                            </div>
                            <p class="form-hint">Takes first dose at the time above, then repeats every N hours</p>
                        </div>

                        <!-- Date range -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div>
                                <label class="form-label">End Date <span class="text-slate-400 text-xs">(optional)</span></label>
                                <input type="date" name="end_date" class="form-input">
                                <p class="form-hint">Leave blank for 30-day default</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" class="btn btn-primary flex-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Create Schedule
                            </button>
                            <a href="schedule.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleFreqFields() {
            var freq = document.getElementById('freq_type').value;
            var time2   = document.getElementById('time2_field');
            var dateFld = document.getElementById('date_field');
            var weekly  = document.getElementById('weekly_fields');
            var interval= document.getElementById('interval_field');
            var hint    = document.getElementById('time1_hint');

            // Hide all optional fields first
            time2.classList.add('hidden');
            dateFld.classList.add('hidden');
            weekly.classList.add('hidden');
            interval.classList.add('hidden');
            hint.textContent = 'Primary reminder time';

            if (freq === 'twice_daily') {
                time2.classList.remove('hidden');
                hint.textContent = 'Morning dose time';
            } else if (freq === 'once') {
                dateFld.classList.remove('hidden');
                hint.textContent = 'Time for the one-time dose';
            } else if (freq === 'weekly') {
                weekly.classList.remove('hidden');
                hint.textContent = 'Time to take the dose on selected days';
            } else if (freq === 'custom') {
                interval.classList.remove('hidden');
                hint.textContent = 'Starting time for the first dose';
            }
        }
    </script>
</body>
</html>
