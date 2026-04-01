<?php
/**
 * API: Mark Medication as Taken
 * 
 * AJAX endpoint to mark a dose as taken
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_config.php';

// Set JSON response header
header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$userId = getCurrentUserId();
$logId = (int)($_POST['log_id'] ?? 0);

if (!$logId) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
    exit();
}

try {
    // Verify ownership and get medication info
    $log = fetchOne(
        "SELECT ml.log_id, ml.medication_id, m.remaining_quantity, m.total_quantity
         FROM medication_logs ml
         JOIN medications m ON ml.medication_id = m.medication_id
         WHERE ml.log_id = :log_id AND ml.user_id = :user_id",
        ['log_id' => $logId, 'user_id' => $userId]
    );
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found']);
        exit();
    }
    
    // Update log entry
    $updateData = [
        'status' => 'taken',
        'taken_time' => date('Y-m-d H:i:s')
    ];
    
    update('medication_logs', $updateData, 'log_id = :log_id', ['log_id' => $logId]);
    
    // Update remaining quantity if applicable
    if ($log['remaining_quantity'] !== null && $log['remaining_quantity'] > 0) {
        $newQuantity = $log['remaining_quantity'] - 1;
        update('medications', 
            ['remaining_quantity' => $newQuantity], 
            'medication_id = :med_id', 
            ['med_id' => $log['medication_id']]
        );
        
        // Create refill notification if running low
        if ($newQuantity <= 7) {
            // Check if refill notification already exists
            $existing = fetchOne(
                "SELECT notification_id FROM notifications 
                 WHERE user_id = :user_id AND medication_id = :med_id AND notification_type = 'refill' AND is_read = 0",
                ['user_id' => $userId, 'med_id' => $log['medication_id']]
            );
            
            if (!$existing) {
                $med = fetchOne(
                    "SELECT medication_name FROM medications WHERE medication_id = :med_id",
                    ['med_id' => $log['medication_id']]
                );
                
                insert('notifications', [
                    'user_id' => $userId,
                    'medication_id' => $log['medication_id'],
                    'notification_type' => 'refill',
                    'title' => 'Low Stock: ' . $med['medication_name'],
                    'message' => 'You have only ' . $newQuantity . ' doses remaining. Please refill soon.',
                    'scheduled_for' => date('Y-m-d H:i:s'),
                    'priority' => 'high'
                ]);
            }
        }
    }
    
    // Create notification
    insert('notifications', [
        'user_id' => $userId,
        'medication_id' => $log['medication_id'],
        'log_id' => $logId,
        'notification_type' => 'custom',
        'title' => 'Medication Taken',
        'message' => 'You marked a dose as taken.',
        'scheduled_for' => date('Y-m-d H:i:s'),
        'sent_at' => date('Y-m-d H:i:s'),
        'priority' => 'low'
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Marked as taken']);
    
} catch (Exception $e) {
    error_log("Mark taken error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
