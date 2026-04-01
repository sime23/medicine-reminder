<?php
/**
 * Settings Page
 * 
 * User profile management, notification preferences, and theme settings
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();
$user = getCurrentUser();

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $updateData = [
            'first_name'           => sanitizeInput($_POST['first_name']),
            'last_name'            => sanitizeInput($_POST['last_name']),
            'phone'                => sanitizeInput($_POST['phone']) ?: null,
            'date_of_birth'        => $_POST['date_of_birth'] ?: null,
            // Checkboxes send 'on' when checked; hidden fields send '0' or '1'
            // This handles both cases correctly
            'email_notifications'  => in_array($_POST['email_notifications']  ?? '0', ['on','1','true'], true) ? 1 : 0,
            'sms_notifications'    => in_array($_POST['sms_notifications']    ?? '0', ['on','1','true'], true) ? 1 : 0,
            'push_notifications'   => in_array($_POST['push_notifications']   ?? '0', ['on','1','true'], true) ? 1 : 0,
            'reminder_time_before' => (int)($_POST['reminder_time_before'] ?? 15),
            'theme'                => in_array($_POST['theme'] ?? 'light', ['light','dark']) ? $_POST['theme'] : 'light'
        ];
        
        try {
            update('users', $updateData, 'user_id = :id', ['id' => $userId]);
            
            // Update session
            $_SESSION['first_name'] = $updateData['first_name'];
            $_SESSION['last_name'] = $updateData['last_name'];
            $_SESSION['theme'] = $updateData['theme'];
            $_SESSION['preferences'] = [
                'email_notifications' => $updateData['email_notifications'],
                'sms_notifications' => $updateData['sms_notifications'],
                'push_notifications' => $updateData['push_notifications']
            ];
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Profile updated successfully'];
            header('Location: settings.php');
            exit();
        } catch (Exception $e) {
            $error = 'Failed to update profile';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $result = changePassword($userId, $_POST['current_password'], $_POST['new_password']);
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Password changed successfully'];
            header('Location: settings.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Settings';
$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="en" <?php echo ($user['theme'] ?? 'light') === 'dark' ? 'data-theme="dark"' : ''; ?>>
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
            <h1 class="text-2xl font-bold text-slate-800">Settings</h1>
            <p class="text-slate-500 mt-1">Manage your account and preferences</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-100 flex items-start gap-3 animate-fadeIn">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-emerald-700"><?php echo htmlspecialchars($message['text']); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Profile -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Profile Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Profile Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="flex items-center gap-6 mb-6">
                                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-2xl font-bold">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    <p class="text-sm text-slate-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <p class="text-xs text-slate-400 mt-1">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="form-input bg-slate-50">
                                    <p class="text-xs text-slate-400 mt-1">Email cannot be changed</p>
                                </div>
                                <div>
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-input" placeholder="+1 (555) 123-4567">
                                </div>
                                <div>
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" class="form-input">
                                </div>
                            </div>

                            <div class="pt-4">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Change Password
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" required class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" required minlength="8" class="form-input">
                                    <p class="text-xs text-slate-400 mt-1">Minimum 8 characters</p>
                                </div>
                                <div>
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required minlength="8" class="form-input">
                                </div>
                            </div>

                            <div class="pt-4">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Preferences -->
            <div class="space-y-6">
                <!-- Notification Preferences -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            Notifications
                        </h2>
                    </div>
                    <div class="card-body space-y-4">
                        <form id="notificationForm" method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            <input type="hidden" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                            <input type="hidden" name="theme" value="<?php echo htmlspecialchars($user['theme'] ?? 'light'); ?>">
                            
                            <div class="space-y-4">
                                <label class="toggle">
                                    <input type="checkbox" class="toggle-input" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?> 
                                           onchange="document.getElementById('notificationForm').submit()">
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">Email Notifications</span>
                                </label>
                                
                                <label class="toggle">
                                    <input type="checkbox" class="toggle-input" name="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?> 
                                           onchange="document.getElementById('notificationForm').submit()">
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">SMS Notifications</span>
                                </label>
                                
                                <label class="toggle">
                                    <input type="checkbox" class="toggle-input" name="push_notifications" <?php echo $user['push_notifications'] ? 'checked' : ''; ?> 
                                           onchange="document.getElementById('notificationForm').submit()">
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">Push Notifications</span>
                                </label>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100">
                                <label class="form-label">Reminder Time</label>
                                <p class="text-xs text-slate-400 mb-2">Minutes before dose</p>
                                <select name="reminder_time_before" onchange="document.getElementById('notificationForm').submit()" class="form-select">
                                    <option value="5" <?php echo $user['reminder_time_before'] == 5 ? 'selected' : ''; ?>>5 minutes</option>
                                    <option value="10" <?php echo $user['reminder_time_before'] == 10 ? 'selected' : ''; ?>>10 minutes</option>
                                    <option value="15" <?php echo $user['reminder_time_before'] == 15 ? 'selected' : ''; ?>>15 minutes</option>
                                    <option value="30" <?php echo $user['reminder_time_before'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                    <option value="60" <?php echo $user['reminder_time_before'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appearance -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                            </svg>
                            Appearance
                        </h2>
                    </div>
                    <div class="card-body">
                        <form id="themeForm" method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            <input type="hidden" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                            <input type="hidden" name="reminder_time_before" value="<?php echo (int)($user['reminder_time_before'] ?? 15); ?>">
                            <input type="hidden" name="email_notifications" value="<?php echo $user['email_notifications'] ? '1' : '0'; ?>">
                            <input type="hidden" name="sms_notifications" value="<?php echo $user['sms_notifications'] ? '1' : '0'; ?>">
                            <input type="hidden" name="push_notifications" value="<?php echo $user['push_notifications'] ? '1' : '0'; ?>">
                            
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors <?php echo $user['theme'] === 'light' ? 'border-primary-500 bg-primary-50' : 'border-slate-200 hover:border-primary-200'; ?>">
                                    <input type="radio" name="theme" value="light" <?php echo $user['theme'] === 'light' ? 'checked' : ''; ?> 
                                           onchange="document.getElementById('themeForm').submit()" class="sr-only">
                                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">Light</p>
                                        <p class="text-xs text-slate-500">Default light theme</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors <?php echo $user['theme'] === 'dark' ? 'border-primary-500 bg-primary-50' : 'border-slate-200 hover:border-primary-200'; ?>">
                                    <input type="radio" name="theme" value="dark" <?php echo $user['theme'] === 'dark' ? 'checked' : ''; ?> 
                                           onchange="document.getElementById('themeForm').submit()" class="sr-only">
                                    <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">Dark</p>
                                        <p class="text-xs text-slate-500">Easier on the eyes</p>
                                    </div>
                                </label>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card border-red-200">
                    <div class="card-header">
                        <h2 class="card-title text-red-600 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Danger Zone
                        </h2>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-slate-500 mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                        <form id="deleteAccountForm" method="POST" action="api/delete_account.php" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        </form>
                        <button onclick="confirmDeleteAccount()" class="btn btn-danger w-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // ── Theme: apply instantly + sync localStorage + save to server ──
        document.querySelectorAll('input[name="theme"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                var theme = this.value;
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
                try { localStorage.setItem('medremind_theme', theme); } catch(e) {}
                document.getElementById('themeForm').submit();
            });
        });

        // ── Delete account: double confirmation ──
        function confirmDeleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                const input = prompt('Type DELETE to confirm permanent account deletion:');
                if (input === 'DELETE') {
                    document.getElementById('deleteAccountForm').submit();
                } else if (input !== null) {
                    alert('Deletion cancelled — you did not type DELETE correctly.');
                }
            }
        }
    </script>
</body>
</html>
