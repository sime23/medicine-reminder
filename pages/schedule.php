<?php
/**
 * Schedule Page
 * 
 * Daily and weekly view of medication schedules
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();

// Get view type (day or week)
$view = $_GET['view'] ?? 'day';
$date = $_GET['date'] ?? date('Y-m-d');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$currentDate = new DateTime($date);
$today = new DateTime();

// Navigation dates
$prevDate = clone $currentDate;
$nextDate = clone $currentDate;

if ($view === 'week') {
    $prevDate->modify('-1 week');
    $nextDate->modify('+1 week');
} else {
    $prevDate->modify('-1 day');
    $nextDate->modify('+1 day');
}

// Get week's start and end
$weekStart = clone $currentDate;
$weekStart->modify('monday this week');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

try {
    if ($view === 'day') {
        // Get schedule for specific day
        $schedules = fetchAll(
            "SELECT ml.log_id, ml.scheduled_time, ml.status, ml.taken_time,
                    m.medication_id, m.medication_name, m.dosage_amount, m.dosage_form, m.color, m.instructions
             FROM medication_logs ml
             JOIN medications m ON ml.medication_id = m.medication_id
             WHERE ml.user_id = :user_id 
                AND DATE(ml.scheduled_time) = :date
                AND m.is_active = 1
             ORDER BY ml.scheduled_time ASC",
            ['user_id' => $userId, 'date' => $date]
        );
    } else {
        // Get schedule for entire week
        $schedules = fetchAll(
            "SELECT ml.log_id, ml.scheduled_time, ml.status, ml.taken_time,
                    m.medication_id, m.medication_name, m.dosage_amount, m.dosage_form, m.color, m.instructions
             FROM medication_logs ml
             JOIN medications m ON ml.medication_id = m.medication_id
             WHERE ml.user_id = :user_id 
                AND DATE(ml.scheduled_time) BETWEEN :start AND :end
                AND m.is_active = 1
             ORDER BY ml.scheduled_time ASC",
            ['user_id' => $userId, 'start' => $weekStart->format('Y-m-d'), 'end' => $weekEnd->format('Y-m-d')]
        );
    }

    // Get all medications for adding schedules
    $medicines = fetchAll(
        "SELECT medication_id, medication_name, dosage_amount, color 
         FROM medications 
         WHERE user_id = :user_id AND is_active = 1
         ORDER BY medication_name ASC",
        ['user_id' => $userId]
    );

} catch (Exception $e) {
    $schedules = [];
    $medicines = [];
    error_log("Schedule error: " . $e->getMessage());
}

// Group schedules by date for week view
$groupedSchedules = [];
foreach ($schedules as $schedule) {
    $scheduleDate = date('Y-m-d', strtotime($schedule['scheduled_time']));
    if (!isset($groupedSchedules[$scheduleDate])) {
        $groupedSchedules[$scheduleDate] = [];
    }
    $groupedSchedules[$scheduleDate][] = $schedule;
}

$pageTitle = 'Schedule';
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
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Schedule</h1>
                <p class="text-slate-500 mt-1">View and manage your medication schedule</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- View Toggle -->
                <div class="flex bg-white rounded-lg border border-slate-200 p-1">
                    <a href="?view=day&date=<?php echo $date; ?>" 
                       class="px-4 py-2 rounded-md text-sm font-medium transition-colors <?php echo $view === 'day' ? 'bg-primary-500 text-white' : 'text-slate-600 hover:bg-slate-50'; ?>">
                        Day
                    </a>
                    <a href="?view=week&date=<?php echo $date; ?>" 
                       class="px-4 py-2 rounded-md text-sm font-medium transition-colors <?php echo $view === 'week' ? 'bg-primary-500 text-white' : 'text-slate-600 hover:bg-slate-50'; ?>">
                        Week
                    </a>
                </div>
                <button onclick="openAddScheduleModal()" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Schedule
                </button>
            </div>
        </div>

        <!-- Calendar Navigation -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <a href="?view=<?php echo $view; ?>&date=<?php echo $prevDate->format('Y-m-d'); ?>" 
                       class="p-2 rounded-lg hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    
                    <div class="text-center">
                        <h2 class="text-xl font-semibold text-slate-800">
                            <?php 
                            if ($view === 'week') {
                                echo $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y');
                            } else {
                                echo $currentDate->format('l, F j, Y');
                            }
                            ?>
                        </h2>
                        <?php if ($currentDate->format('Y-m-d') === $today->format('Y-m-d')): ?>
                        <span class="inline-block mt-1 px-2 py-0.5 bg-primary-100 text-primary-700 text-xs font-medium rounded-full">Today</span>
                        <?php endif; ?>
                    </div>
                    
                    <a href="?view=<?php echo $view; ?>&date=<?php echo $nextDate->format('Y-m-d'); ?>" 
                       class="p-2 rounded-lg hover:bg-slate-100 transition-colors">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Schedule Content -->
        <?php if ($view === 'day'): ?>
        <!-- Day View -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo $currentDate->format('l, M j'); ?>
                </h3>
                <div class="flex items-center gap-2">
                    <?php 
                    $takenCount = count(array_filter($schedules, fn($s) => $s['status'] === 'taken'));
                    $totalCount = count($schedules);
                    ?>
                    <span class="text-sm text-slate-500">
                        <?php echo $takenCount; ?>/<?php echo $totalCount; ?> taken
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-slate-500 mb-4">No medications scheduled for this day</p>
                    <button onclick="openAddScheduleModal()" class="btn btn-primary">
                        Add Schedule
                    </button>
                </div>
                <?php else: ?>
                <div class="schedule-list">
                    <?php foreach ($schedules as $schedule): 
                        $scheduledTime = new DateTime($schedule['scheduled_time']);
                        $isOverdue = $schedule['status'] === 'pending' && $scheduledTime < $today;
                        $isUpcoming = $schedule['status'] === 'pending' && $scheduledTime >= $today;
                    ?>
                    <div class="schedule-item">
                        <div class="schedule-time">
                            <div class="schedule-time-hour"><?php echo $scheduledTime->format('g:i'); ?></div>
                            <div class="schedule-time-period"><?php echo $scheduledTime->format('A'); ?></div>
                        </div>
                        
                        <div class="schedule-divider <?php echo $schedule['status'] === 'taken' ? 'taken' : ($isOverdue ? 'missed' : 'upcoming'); ?>"></div>
                        
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                             style="background-color: <?php echo htmlspecialchars($schedule['color'] ?? '#0d9488'); ?>">
                            <?php echo strtoupper(substr($schedule['medication_name'], 0, 1)); ?>
                        </div>
                        
                        <div class="schedule-content">
                            <div class="schedule-medicine"><?php echo htmlspecialchars($schedule['medication_name']); ?></div>
                            <div class="schedule-dosage"><?php echo htmlspecialchars($schedule['dosage_amount']); ?> • <?php echo ucfirst($schedule['dosage_form']); ?></div>
                            <?php if ($schedule['instructions']): ?>
                            <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($schedule['instructions']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="schedule-status">
                            <?php if ($schedule['status'] === 'taken'): ?>
                            <span class="badge badge-success">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Taken
                            </span>
                            <?php elseif ($isOverdue): ?>
                            <span class="badge badge-danger">Overdue</span>
                            <?php else: ?>
                            <span class="badge badge-warning">Upcoming</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($schedule['status'] !== 'taken'): ?>
                        <button onclick="markTaken(<?php echo $schedule['log_id']; ?>)" 
                            class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                            Take
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Week View -->
        <div class="grid grid-cols-7 gap-2 mb-4">
            <?php 
            $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            for ($i = 0; $i < 7; $i++): 
                $dayDate = clone $weekStart;
                $dayDate->modify("+$i days");
                $isToday = $dayDate->format('Y-m-d') === $today->format('Y-m-d');
                $daySchedules = $groupedSchedules[$dayDate->format('Y-m-d')] ?? [];
                $dayTaken = count(array_filter($daySchedules, fn($s) => $s['status'] === 'taken'));
                $dayTotal = count($daySchedules);
            ?>
            <a href="?view=day&date=<?php echo $dayDate->format('Y-m-d'); ?>" 
               class="text-center p-3 rounded-xl border transition-all <?php echo $isToday ? 'bg-primary-50 border-primary-200' : 'bg-white border-slate-200 hover:border-primary-200'; ?>">
                <div class="text-xs text-slate-500 mb-1"><?php echo $dayNames[$i]; ?></div>
                <div class="text-lg font-semibold <?php echo $isToday ? 'text-primary-600' : 'text-slate-800'; ?>"><?php echo $dayDate->format('j'); ?></div>
                <?php if ($dayTotal > 0): ?>
                <div class="mt-2 text-xs <?php echo $dayTaken === $dayTotal ? 'text-emerald-600' : 'text-slate-500'; ?>">
                    <?php echo $dayTaken; ?>/<?php echo $dayTotal; ?>
                </div>
                <?php endif; ?>
            </a>
            <?php endfor; ?>
        </div>

        <!-- Week Schedule List -->
        <div class="space-y-4">
            <?php 
            for ($i = 0; $i < 7; $i++): 
                $dayDate = clone $weekStart;
                $dayDate->modify("+$i days");
                $daySchedules = $groupedSchedules[$dayDate->format('Y-m-d')] ?? [];
                if (empty($daySchedules)) continue;
            ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-sm"><?php echo $dayDate->format('l, M j'); ?></h3>
                    <span class="text-xs text-slate-500">
                        <?php echo count(array_filter($daySchedules, fn($s) => $s['status'] === 'taken')); ?>/<?php echo count($daySchedules); ?> taken
                    </span>
                </div>
                <div class="card-body py-3">
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($daySchedules as $schedule): 
                            $scheduledTime = new DateTime($schedule['scheduled_time']);
                        ?>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg border <?php echo $schedule['status'] === 'taken' ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-200'; ?>">
                            <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs"
                                 style="background-color: <?php echo htmlspecialchars($schedule['color'] ?? '#0d9488'); ?>">
                                <?php echo strtoupper(substr($schedule['medication_name'], 0, 1)); ?>
                            </div>
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($schedule['medication_name']); ?></span>
                            <span class="text-xs text-slate-500"><?php echo $scheduledTime->format('g:i A'); ?></span>
                            <?php if ($schedule['status'] === 'taken'): ?>
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
            
            <?php if (empty($groupedSchedules)): ?>
            <div class="card">
                <div class="card-body text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-slate-500 mb-4">No medications scheduled for this week</p>
                    <button onclick="openAddScheduleModal()" class="btn btn-primary">
                        Add Schedule
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Add Schedule Modal -->
    <div id="addScheduleModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add Schedule</h3>
                <button onclick="closeAddScheduleModal()" class="modal-close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="api/schedule_add.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body space-y-4">
                    <div>
                        <label class="form-label">Medication <span class="text-red-500">*</span></label>
                        <select name="medication_id" required class="form-select">
                            <option value="">Select medication</option>
                            <?php foreach ($medicines as $med): ?>
                            <option value="<?php echo $med['medication_id']; ?>">
                                <?php echo htmlspecialchars($med['medication_name']); ?> (<?php echo htmlspecialchars($med['dosage_amount']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Frequency <span class="text-red-500">*</span></label>
                        <select name="frequency_type" id="frequency_type" class="form-select" onchange="toggleFrequencyFields()">
                            <option value="once">Once</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="custom">Custom Interval</option>
                        </select>
                    </div>
                    
                    <div id="time_fields">
                        <label class="form-label">Time <span class="text-red-500">*</span></label>
                        <input type="time" name="time_1" required class="form-input">
                    </div>
                    
                    <div id="date_field">
                        <label class="form-label">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="specific_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div id="weekly_fields" class="hidden">
                        <label class="form-label">Days of Week</label>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $days = ['Mon' => 'monday', 'Tue' => 'tuesday', 'Wed' => 'wednesday', 'Thu' => 'thursday', 
                                     'Fri' => 'friday', 'Sat' => 'saturday', 'Sun' => 'sunday'];
                            foreach ($days as $label => $value): 
                            ?>
                            <label class="flex items-center gap-1 px-3 py-1.5 bg-slate-50 rounded-lg cursor-pointer hover:bg-slate-100">
                                <input type="checkbox" name="days[]" value="<?php echo $value; ?>" class="rounded border-slate-300 text-primary-600">
                                <span class="text-sm"><?php echo $label; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div id="interval_field" class="hidden">
                        <label class="form-label">Interval (hours)</label>
                        <input type="number" name="interval_hours" class="form-input" placeholder="e.g., 8" min="1" max="24">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label class="form-label">End Date (optional)</label>
                            <input type="date" name="end_date" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddScheduleModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddScheduleModal() {
            document.getElementById('addScheduleModal').classList.add('active');
        }

        function closeAddScheduleModal() {
            document.getElementById('addScheduleModal').classList.remove('active');
        }

        function toggleFrequencyFields() {
            const frequency = document.getElementById('frequency_type').value;
            const dateField = document.getElementById('date_field');
            const weeklyFields = document.getElementById('weekly_fields');
            const intervalField = document.getElementById('interval_field');
            
            dateField.classList.add('hidden');
            weeklyFields.classList.add('hidden');
            intervalField.classList.add('hidden');
            
            if (frequency === 'once') {
                dateField.classList.remove('hidden');
            } else if (frequency === 'weekly') {
                weeklyFields.classList.remove('hidden');
            } else if (frequency === 'custom') {
                intervalField.classList.remove('hidden');
            }
        }

        function markTaken(logId) {
            fetch('api/mark_taken.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'log_id=' + logId + '&csrf_token=<?php echo generateCSRFToken(); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to mark as taken');
                }
            });
        }

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
