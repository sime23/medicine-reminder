<?php
/**
 * History Page
 * 
 * Timeline view of taken vs missed medications
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$period = $_GET['period'] ?? '7';

// Validate period
$validPeriods = ['7', '30', '90', '365'];
if (!in_array($period, $validPeriods)) {
    $period = '7';
}

try {
    // Build query based on filter
    $statusFilter = '';
    $params = ['user_id' => $userId, 'days' => (int)$period];
    
    if ($filter === 'taken') {
        $statusFilter = "AND ml.status = 'taken'";
    } elseif ($filter === 'missed') {
        $statusFilter = "AND ml.status = 'missed'";
    } elseif ($filter === 'skipped') {
        $statusFilter = "AND ml.status = 'skipped'";
    }

    // Get medication logs
    $logs = fetchAll(
        "SELECT ml.log_id, ml.scheduled_time, ml.taken_time, ml.status, ml.notes, ml.side_effects,
                m.medication_id, m.medication_name, m.dosage_amount, m.dosage_form, m.color
         FROM medication_logs ml
         JOIN medications m ON ml.medication_id = m.medication_id
         WHERE ml.user_id = :user_id 
            AND ml.scheduled_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
            $statusFilter
         ORDER BY ml.scheduled_time DESC",
        $params
    );

    // Get adherence statistics
    $stats = fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
         FROM medication_logs 
         WHERE user_id = :user_id 
            AND scheduled_time >= DATE_SUB(NOW(), INTERVAL :days DAY)",
        ['user_id' => $userId, 'days' => (int)$period]
    );

    $adherenceRate = ($stats['total'] > 0) 
        ? round(($stats['taken'] / $stats['total']) * 100, 1) 
        : 0;

    // Get daily breakdown for chart
    $dailyStats = fetchAll(
        "SELECT 
            DATE(scheduled_time) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
         FROM medication_logs 
         WHERE user_id = :user_id 
            AND scheduled_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
         GROUP BY DATE(scheduled_time)
         ORDER BY date ASC",
        ['user_id' => $userId, 'days' => (int)$period]
    );

} catch (Exception $e) {
    $logs = [];
    $stats = ['total' => 0, 'taken' => 0, 'missed' => 0, 'skipped' => 0, 'pending' => 0];
    $adherenceRate = 0;
    $dailyStats = [];
    error_log("History error: " . $e->getMessage());
}

// Group logs by date
$groupedLogs = [];
foreach ($logs as $log) {
    $logDate = date('Y-m-d', strtotime($log['scheduled_time']));
    if (!isset($groupedLogs[$logDate])) {
        $groupedLogs[$logDate] = [];
    }
    $groupedLogs[$logDate][] = $log;
}

$pageTitle = 'History';
$currentPage = 'history';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1 class="text-2xl font-bold text-slate-800">History</h1>
                <p class="text-slate-500 mt-1">Track your medication adherence over time</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Period Selector -->
                <select onchange="changePeriod(this.value)" class="form-select w-auto">
                    <option value="7" <?php echo $period === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30" <?php echo $period === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="90" <?php echo $period === '90' ? 'selected' : ''; ?>>Last 3 Months</option>
                    <option value="365" <?php echo $period === '365' ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-3xl font-bold text-slate-800"><?php echo $stats['total']; ?></p>
                    <p class="text-sm text-slate-500">Total Doses</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-3xl font-bold text-emerald-600"><?php echo $stats['taken']; ?></p>
                    <p class="text-sm text-slate-500">Taken</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-3xl font-bold text-red-500"><?php echo $stats['missed']; ?></p>
                    <p class="text-sm text-slate-500">Missed</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-3xl font-bold text-primary-600"><?php echo $adherenceRate; ?>%</p>
                    <p class="text-sm text-slate-500">Adherence</p>
                </div>
            </div>
        </div>

        <!-- Adherence Chart -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Adherence Trend</h2>
            </div>
            <div class="card-body">
                <canvas id="adherenceChart" height="100"></canvas>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
            <a href="?period=<?php echo $period; ?>&filter=all" 
               class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?php echo $filter === 'all' ? 'bg-primary-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                All (<?php echo $stats['total']; ?>)
            </a>
            <a href="?period=<?php echo $period; ?>&filter=taken" 
               class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?php echo $filter === 'taken' ? 'bg-emerald-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                Taken (<?php echo $stats['taken']; ?>)
            </a>
            <a href="?period=<?php echo $period; ?>&filter=missed" 
               class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?php echo $filter === 'missed' ? 'bg-red-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                Missed (<?php echo $stats['missed']; ?>)
            </a>
            <a href="?period=<?php echo $period; ?>&filter=skipped" 
               class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?php echo $filter === 'skipped' ? 'bg-orange-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                Skipped (<?php echo $stats['skipped']; ?>)
            </a>
        </div>

        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Activity Timeline</h2>
            </div>
            <div class="card-body">
                <?php if (empty($groupedLogs)): ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-slate-500">No activity found for this period</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($groupedLogs as $date => $dayLogs): 
                        $dateObj = new DateTime($date);
                        $isToday = $date === date('Y-m-d');
                        $dayTaken = count(array_filter($dayLogs, fn($l) => $l['status'] === 'taken'));
                        $dayTotal = count($dayLogs);
                        $dayRate = $dayTotal > 0 ? round(($dayTaken / $dayTotal) * 100) : 0;
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $dayRate === 100 ? 'taken' : ($dayRate >= 50 ? 'skipped' : 'missed'); ?>"></div>
                        <div class="timeline-content">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="timeline-time"><?php echo $dateObj->format('l, F j, Y'); ?> <?php echo $isToday ? '<span class="text-primary-600 font-medium">(Today)</span>' : ''; ?></p>
                                    <p class="text-sm text-slate-500"><?php echo $dayTaken; ?> of <?php echo $dayTotal; ?> doses taken</p>
                                </div>
                                <span class="badge <?php echo $dayRate === 100 ? 'badge-success' : ($dayRate >= 50 ? 'badge-warning' : 'badge-danger'); ?>">
                                    <?php echo $dayRate; ?>%
                                </span>
                            </div>
                            
                            <div class="space-y-2">
                                <?php foreach ($dayLogs as $log): 
                                    $scheduledTime = new DateTime($log['scheduled_time']);
                                    $statusColor = match($log['status']) {
                                        'taken' => 'bg-emerald-50 border-emerald-200',
                                        'missed' => 'bg-red-50 border-red-200',
                                        'skipped' => 'bg-orange-50 border-orange-200',
                                        default => 'bg-slate-50 border-slate-200'
                                    };
                                    $statusIcon = match($log['status']) {
                                        'taken' => '<svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
                                        'missed' => '<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
                                        'skipped' => '<svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>',
                                        default => '<svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                    };
                                ?>
                                <div class="flex items-center gap-3 p-3 rounded-lg border <?php echo $statusColor; ?>">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm"
                                         style="background-color: <?php echo htmlspecialchars($log['color'] ?? '#0d9488'); ?>">
                                        <?php echo strtoupper(substr($log['medication_name'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-slate-800"><?php echo htmlspecialchars($log['medication_name']); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars($log['dosage_amount']); ?> • <?php echo $scheduledTime->format('g:i A'); ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php echo $statusIcon; ?>
                                        <span class="text-xs font-medium capitalize <?php echo $log['status'] === 'taken' ? 'text-emerald-600' : ($log['status'] === 'missed' ? 'text-red-600' : 'text-orange-600'); ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Adherence Chart
        const ctx = document.getElementById('adherenceChart').getContext('2d');
        const dailyStats = <?php echo json_encode($dailyStats); ?>;
        
        const labels = dailyStats.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const data = dailyStats.map(d => d.total > 0 ? Math.round((d.taken / d.total) * 100) : 0);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Adherence Rate (%)',
                    data: data,
                    borderColor: '#0d9488',
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#0d9488',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + '% adherence';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: '#e2e8f0'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        function changePeriod(period) {
            window.location.href = '?period=' + period + '&filter=<?php echo $filter; ?>';
        }
    </script>
</body>
</html>
