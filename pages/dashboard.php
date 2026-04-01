<?php
/**
 * Dashboard Page
 * 
 * Main dashboard showing overview of medications, upcoming doses, and statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

// Require authentication
requireAuth();

$userId = getCurrentUserId();
$user = getCurrentUser();

// Get dashboard statistics
try {
    // Total medications count
    $medCount = fetchOne(
        "SELECT COUNT(*) as count FROM medications WHERE user_id = :user_id AND is_active = 1",
        ['user_id' => $userId]
    )['count'] ?? 0;

    // Today's doses
    $todayDoses = fetchOne(
        "SELECT COUNT(*) as count FROM medication_logs 
         WHERE user_id = :user_id AND DATE(scheduled_time) = CURDATE()",
        ['user_id' => $userId]
    )['count'] ?? 0;

    // Taken today
    $takenToday = fetchOne(
        "SELECT COUNT(*) as count FROM medication_logs 
         WHERE user_id = :user_id AND DATE(scheduled_time) = CURDATE() AND status = 'taken'",
        ['user_id' => $userId]
    )['count'] ?? 0;

    // Upcoming doses (next 4 hours)
    $upcomingDoses = fetchAll(
        "SELECT m.medication_id, m.medication_name, m.dosage_amount, m.color,
                ml.scheduled_time, ml.log_id
         FROM medication_logs ml
         JOIN medications m ON ml.medication_id = m.medication_id
         WHERE ml.user_id = :user_id 
            AND ml.status = 'pending'
            AND ml.scheduled_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 HOUR)
         ORDER BY ml.scheduled_time ASC
         LIMIT 5",
        ['user_id' => $userId]
    );

    // Recent medications
    $recentMeds = fetchAll(
        "SELECT medication_id, medication_name, dosage_amount, dosage_form, color, created_at
         FROM medications 
         WHERE user_id = :user_id AND is_active = 1
         ORDER BY created_at DESC
         LIMIT 4",
        ['user_id' => $userId]
    );

    // Adherence rate (last 7 days)
    $adherence = fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
         FROM medication_logs 
         WHERE user_id = :user_id 
            AND scheduled_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        ['user_id' => $userId]
    );
    
    $adherenceRate = ($adherence['total'] > 0) 
        ? round(($adherence['taken'] / $adherence['total']) * 100) 
        : 100;

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
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
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
            <p class="text-slate-500 mt-1">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! Here's your health overview.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Medications -->
            <div class="stat-card">
                <div class="stat-icon primary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo $medCount; ?></div>
                <div class="stat-label">Active Medications</div>
            </div>

            <!-- Today's Doses -->
            <div class="stat-card">
                <div class="stat-icon warning">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo $todayDoses; ?></div>
                <div class="stat-label">Today's Doses</div>
            </div>

            <!-- Taken Today -->
            <div class="stat-card">
                <div class="stat-icon success">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo $takenToday; ?>/<?php echo $todayDoses; ?></div>
                <div class="stat-label">Taken Today</div>
            </div>

            <!-- Adherence Rate -->
            <div class="stat-card">
                <div class="stat-icon <?php echo $adherenceRate >= 80 ? 'success' : ($adherenceRate >= 50 ? 'warning' : 'danger'); ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="stat-value"><?php echo $adherenceRate; ?>%</div>
                <div class="stat-label">7-Day Adherence</div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Upcoming Doses -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Upcoming Doses
                        </h2>
                        <a href="schedule.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingDoses)): ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-slate-500">No upcoming doses in the next 4 hours</p>
                            <a href="medicines.php" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 font-medium mt-2">
                                Add medication
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($upcomingDoses as $dose): 
                                $scheduledTime = new DateTime($dose['scheduled_time']);
                                $timeUntil = $scheduledTime->getTimestamp() - time();
                                $minutesUntil = floor($timeUntil / 60);
                            ?>
                            <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg"
                                     style="background-color: <?php echo htmlspecialchars($dose['color'] ?? '#0d9488'); ?>">
                                    <?php echo strtoupper(substr($dose['medication_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-slate-800"><?php echo htmlspecialchars($dose['medication_name']); ?></h3>
                                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($dose['dosage_amount']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-primary-600"><?php echo $scheduledTime->format('g:i A'); ?></p>
                                    <p class="text-xs text-slate-500">
                                        <?php echo $minutesUntil < 60 ? 'in ' . $minutesUntil . ' min' : 'in ' . floor($minutesUntil/60) . ' hr'; ?>
                                    </p>
                                </div>
                                <button onclick="markTaken(<?php echo $dose['log_id']; ?>)" 
                                    class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Take
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Medications -->
                <div class="card mt-6">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            Recent Medications
                        </h2>
                        <a href="medicines.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMeds)): ?>
                        <div class="text-center py-8">
                            <p class="text-slate-500 mb-4">No medications added yet</p>
                            <button onclick="openAddMedicineModal()" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors">
                                Add Your First Medication
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($recentMeds as $med): ?>
                            <div class="medicine-card">
                                <div class="medicine-icon" style="background-color: <?php echo htmlspecialchars($med['color'] ?? '#0d9488'); ?>20; color: <?php echo htmlspecialchars($med['color'] ?? '#0d9488'); ?>">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                    </svg>
                                </div>
                                <div class="medicine-info">
                                    <h3 class="medicine-name"><?php echo htmlspecialchars($med['medication_name']); ?></h3>
                                    <p class="medicine-dosage"><?php echo htmlspecialchars($med['dosage_amount']); ?> • <?php echo ucfirst($med['dosage_form']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Tips -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                    </div>
                    <div class="card-body space-y-3">
                        <button onclick="openAddMedicineModal()" class="w-full flex items-center gap-3 p-3 rounded-xl bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-primary-500 flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-medium">Add Medication</p>
                                <p class="text-xs text-primary-600">Track a new medicine</p>
                            </div>
                        </button>
                        
                        <a href="schedule.php" class="w-full flex items-center gap-3 p-3 rounded-xl bg-slate-50 text-slate-700 hover:bg-slate-100 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-slate-500 flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-medium">View Schedule</p>
                                <p class="text-xs text-slate-500">See your full calendar</p>
                            </div>
                        </a>
                        
                        <a href="history.php" class="w-full flex items-center gap-3 p-3 rounded-xl bg-slate-50 text-slate-700 hover:bg-slate-100 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-slate-500 flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-medium">View History</p>
                                <p class="text-xs text-slate-500">Check past doses</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Health Tip -->
                <div class="card bg-gradient-to-br from-primary-500 to-primary-600 text-white border-0">
                    <div class="card-body">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-1">Daily Health Tip</h3>
                                <p class="text-sm text-primary-100">Take your medications at the same time each day to establish a routine and improve adherence.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Adherence Chart -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Weekly Progress</h2>
                    </div>
                    <div class="card-body">
                        <div class="flex items-end justify-between h-32 gap-2">
                            <?php
                            // Get last 7 days adherence data
                            for ($i = 6; $i >= 0; $i--) {
                                $date = date('Y-m-d', strtotime("-$i days"));
                                $dayData = fetchOne(
                                    "SELECT 
                                        COUNT(*) as total,
                                        SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
                                     FROM medication_logs 
                                     WHERE user_id = :user_id AND DATE(scheduled_time) = :date",
                                    ['user_id' => $userId, 'date' => $date]
                                );
                                $dayRate = ($dayData['total'] > 0) ? ($dayData['taken'] / $dayData['total']) * 100 : 0;
                                $dayName = date('D', strtotime($date));
                            ?>
                            <div class="flex-1 flex flex-col items-center gap-2">
                                <div class="w-full bg-slate-100 rounded-t-lg relative overflow-hidden" style="height: 100px;">
                                    <div class="absolute bottom-0 w-full bg-primary-500 rounded-t-lg transition-all duration-500" 
                                         style="height: <?php echo $dayRate; ?>%;"></div>
                                </div>
                                <span class="text-xs text-slate-500"><?php echo $dayName; ?></span>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Medicine Modal -->
    <div id="addMedicineModal" class="modal-overlay">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Add New Medication</h3>
                <button onclick="closeAddMedicineModal()" class="modal-close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="api/medicine_add.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="form-label">Medication Name *</label>
                            <input type="text" name="medication_name" required class="form-input" placeholder="e.g., Amoxicillin">
                        </div>
                        <div>
                            <label class="form-label">Dosage Amount *</label>
                            <input type="text" name="dosage_amount" required class="form-input" placeholder="e.g., 500mg">
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
                        <div class="col-span-2">
                            <label class="form-label">Instructions</label>
                            <textarea name="instructions" class="form-textarea" rows="2" placeholder="e.g., Take with food"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeAddMedicineModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Medication</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddMedicineModal() {
            document.getElementById('addMedicineModal').classList.add('active');
        }

        function closeAddMedicineModal() {
            document.getElementById('addMedicineModal').classList.remove('active');
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

        // Close modal on outside click
        document.getElementById('addMedicineModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddMedicineModal();
            }
        });
    </script>
</body>
</html>
