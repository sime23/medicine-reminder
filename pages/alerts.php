<?php
/**
 * Alerts/Notifications Page
 * 
 * Display upcoming and overdue medication doses
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'notification_id = :id AND user_id = :user_id', 
            ['id' => $_GET['mark_read'], 'user_id' => $userId]
        );
    } catch (Exception $e) {
        error_log("Mark read error: " . $e->getMessage());
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'user_id = :user_id AND is_read = 0', 
            ['user_id' => $userId]
        );
        header('Location: alerts.php');
        exit();
    } catch (Exception $e) {
        error_log("Mark all read error: " . $e->getMessage());
    }
}

try {
    // Get upcoming doses (next 24 hours)
    $upcomingDoses = fetchAll(
        "SELECT ml.log_id, ml.scheduled_time, 
                m.medication_id, m.medication_name, m.dosage_amount, m.dosage_form, m.color, m.instructions,
                TIMESTAMPDIFF(MINUTE, NOW(), ml.scheduled_time) as minutes_until
         FROM medication_logs ml
         JOIN medications m ON ml.medication_id = m.medication_id
         WHERE ml.user_id = :user_id 
            AND ml.status = 'pending'
            AND ml.scheduled_time > NOW()
            AND ml.scheduled_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
            AND m.is_active = 1
         ORDER BY ml.scheduled_time ASC",
        ['user_id' => $userId]
    );

    // Get overdue doses
    $overdueDoses = fetchAll(
        "SELECT ml.log_id, ml.scheduled_time, 
                m.medication_id, m.medication_name, m.dosage_amount, m.dosage_form, m.color, m.instructions,
                TIMESTAMPDIFF(MINUTE, ml.scheduled_time, NOW()) as minutes_overdue
         FROM medication_logs ml
         JOIN medications m ON ml.medication_id = m.medication_id
         WHERE ml.user_id = :user_id 
            AND ml.status = 'pending'
            AND ml.scheduled_time < NOW()
            AND ml.scheduled_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND m.is_active = 1
         ORDER BY ml.scheduled_time DESC",
        ['user_id' => $userId]
    );

    // Get notifications
    $notifications = fetchAll(
        "SELECT n.*, m.medication_name, m.color
         FROM notifications n
         LEFT JOIN medications m ON n.medication_id = m.medication_id
         WHERE n.user_id = :user_id
         ORDER BY n.created_at DESC
         LIMIT 50",
        ['user_id' => $userId]
    );

    // Count unread notifications
    $unreadCount = fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0",
        ['user_id' => $userId]
    )['count'] ?? 0;

} catch (Exception $e) {
    $upcomingDoses = [];
    $overdueDoses = [];
    $notifications = [];
    $unreadCount = 0;
    error_log("Alerts error: " . $e->getMessage());
}

$pageTitle = 'Alerts';
$currentPage = 'alerts';
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
                <h1 class="text-2xl font-bold text-slate-800">Alerts</h1>
                <p class="text-slate-500 mt-1">Stay on top of your medication schedule</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <a href="?mark_all_read=1" class="btn btn-secondary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Mark All Read
            </a>
            <?php endif; ?>
        </div>

        <!-- Alert Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-500 flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-orange-700"><?php echo count($overdueDoses); ?></p>
                            <p class="text-sm text-orange-600">Overdue Doses</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-primary-50 to-primary-100 border-primary-200">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-primary-500 flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-primary-700"><?php echo count($upcomingDoses); ?></p>
                            <p class="text-sm text-primary-600">Upcoming (24h)</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-gradient-to-br from-slate-50 to-slate-100 border-slate-200">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-500 flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-700"><?php echo $unreadCount; ?></p>
                            <p class="text-sm text-slate-600">Unread Notifications</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column: Doses -->
            <div class="space-y-6">
                <!-- Overdue Doses -->
                <div class="card border-red-200">
                    <div class="card-header bg-red-50">
                        <h2 class="card-title flex items-center gap-2 text-red-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Overdue Doses
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($overdueDoses)): ?>
                        <div class="text-center py-8">
                            <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-emerald-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-slate-500">No overdue doses! Great job!</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($overdueDoses as $dose): 
                                $minutes = $dose['minutes_overdue'];
                                $timeText = $minutes < 60 ? $minutes . ' min ago' : ($minutes < 1440 ? floor($minutes/60) . ' hours ago' : floor($minutes/1440) . ' days ago');
                            ?>
                            <div class="flex items-center gap-4 p-4 bg-red-50 rounded-xl border border-red-100">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                                     style="background-color: <?php echo htmlspecialchars($dose['color'] ?? '#0d9488'); ?>">
                                    <?php echo strtoupper(substr($dose['medication_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-slate-800"><?php echo htmlspecialchars($dose['medication_name']); ?></h3>
                                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($dose['dosage_amount']); ?></p>
                                    <p class="text-xs text-red-600 font-medium mt-1">Due <?php echo $timeText; ?></p>
                                </div>
                                <button onclick="markTaken(<?php echo $dose['log_id']; ?>)" 
                                    class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                                    Take Now
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Doses -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Upcoming Doses (Next 24 Hours)
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingDoses)): ?>
                        <div class="text-center py-8">
                            <p class="text-slate-500">No upcoming doses in the next 24 hours</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($upcomingDoses as $dose): 
                                $minutes = $dose['minutes_until'];
                                $timeText = $minutes < 60 ? 'in ' . $minutes . ' min' : 'in ' . floor($minutes/60) . ' hours';
                                $scheduledTime = new DateTime($dose['scheduled_time']);
                            ?>
                            <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                                     style="background-color: <?php echo htmlspecialchars($dose['color'] ?? '#0d9488'); ?>">
                                    <?php echo strtoupper(substr($dose['medication_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-slate-800"><?php echo htmlspecialchars($dose['medication_name']); ?></h3>
                                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($dose['dosage_amount']); ?></p>
                                    <p class="text-xs text-primary-600 font-medium mt-1"><?php echo $scheduledTime->format('g:i A'); ?> (<?php echo $timeText; ?>)</p>
                                </div>
                                <span class="badge badge-primary">Upcoming</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Notifications -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notifications
                    </h2>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                    <div class="text-center py-12">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center">
                            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <p class="text-slate-500">No notifications yet</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($notifications as $notif): 
                            $notifTime = new DateTime($notif['created_at']);
                            $timeDiff = time() - $notifTime->getTimestamp();
                            if ($timeDiff < 60) $timeAgo = 'Just now';
                            elseif ($timeDiff < 3600) $timeAgo = floor($timeDiff/60) . ' min ago';
                            elseif ($timeDiff < 86400) $timeAgo = floor($timeDiff/3600) . ' hours ago';
                            else $timeAgo = floor($timeDiff/86400) . ' days ago';
                            
                            $iconColor = match($notif['notification_type']) {
                                'reminder' => 'text-primary-600 bg-primary-50',
                                'overdue' => 'text-red-600 bg-red-50',
                                'refill' => 'text-orange-600 bg-orange-50',
                                default => 'text-slate-600 bg-slate-50'
                            };
                            
                            $icon = match($notif['notification_type']) {
                                'reminder' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                'overdue' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                                'refill' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
                                default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                            };
                        ?>
                        <div class="p-4 flex items-start gap-4 <?php echo $notif['is_read'] ? 'bg-white' : 'bg-primary-50/50'; ?>">
                            <div class="w-10 h-10 rounded-lg <?php echo $iconColor; ?> flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <?php echo $icon; ?>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-slate-800"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-sm text-slate-500 mt-0.5"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notif['notification_id']; ?>" 
                                       class="w-2 h-2 rounded-full bg-primary-500 flex-shrink-0" title="Mark as read"></a>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-400 mt-2"><?php echo $timeAgo; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
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
    </script>
</body>
</html>
