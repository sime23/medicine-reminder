<?php
/**
 * API: Delete Account
 * 
 * Permanently deletes the user's account and all associated data
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_config.php';

requireAuth();

$userId = getCurrentUserId();

try {
    beginTransaction();

    // Delete in dependency order
    // medication_logs -> notifications -> schedules -> medications -> users
    // (Foreign keys with CASCADE should handle this, but being explicit)

    delete('medication_logs', 'user_id = :user_id', ['user_id' => $userId]);
    delete('notifications', 'user_id = :user_id', ['user_id' => $userId]);
    
    // Remove caregiver access (both as patient and caregiver)
    delete('caregiver_access', 'patient_id = :user_id OR caregiver_id = :user_id2', [
        'user_id' => $userId,
        'user_id2' => $userId
    ]);
    
    // Schedules and medications will cascade
    delete('schedules', 'user_id = :user_id', ['user_id' => $userId]);
    delete('medications', 'user_id = :user_id', ['user_id' => $userId]);
    
    // Finally delete the user
    delete('users', 'user_id = :user_id', ['user_id' => $userId]);

    commitTransaction();

    // Logout and redirect
    logoutUser();
    
    header('Location: /pages/login.php?deleted=1');
    exit();

} catch (Exception $e) {
    rollbackTransaction();
    error_log("Delete account error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete account. Please try again.'];
    header('Location: ../settings.php');
    exit();
}
