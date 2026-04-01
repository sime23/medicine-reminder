<?php
/**
 * API: Add Schedule
 * 
 * Handles creating a new medication schedule
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_config.php';

// Require authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../schedule.php');
    exit();
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid request'];
    header('Location: ../schedule.php');
    exit();
}

$userId = getCurrentUserId();

// Get form data
$medicationId = (int)($_POST['medication_id'] ?? 0);
$frequencyType = $_POST['frequency_type'] ?? 'daily';
$time1 = $_POST['time_1'] ?? '08:00';
$startDate = $_POST['start_date'] ?? date('Y-m-d');
$endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

if (!$medicationId) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a medication'];
    header('Location: ../schedule.php');
    exit();
}

// Verify medication ownership
$medication = fetchOne(
    "SELECT medication_id FROM medications WHERE medication_id = :id AND user_id = :user_id AND is_active = 1",
    ['id' => $medicationId, 'user_id' => $userId]
);

if (!$medication) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Medication not found'];
    header('Location: ../schedule.php');
    exit();
}

try {
    beginTransaction();
    
    // Prepare schedule data
    $scheduleData = [
        'medication_id' => $medicationId,
        'user_id' => $userId,
        'frequency_type' => $frequencyType,
        'time_1' => $time1,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'is_active' => 1
    ];
    
    // Add frequency-specific fields
    switch ($frequencyType) {
        case 'once':
            $scheduleData['specific_date'] = $_POST['specific_date'] ?? $startDate;
            break;
            
        case 'weekly':
            $days = $_POST['days'] ?? [];
            $scheduleData['monday'] = in_array('monday', $days) ? 1 : 0;
            $scheduleData['tuesday'] = in_array('tuesday', $days) ? 1 : 0;
            $scheduleData['wednesday'] = in_array('wednesday', $days) ? 1 : 0;
            $scheduleData['thursday'] = in_array('thursday', $days) ? 1 : 0;
            $scheduleData['friday'] = in_array('friday', $days) ? 1 : 0;
            $scheduleData['saturday'] = in_array('saturday', $days) ? 1 : 0;
            $scheduleData['sunday'] = in_array('sunday', $days) ? 1 : 0;
            break;
            
        case 'custom':
            $scheduleData['interval_hours'] = (int)($_POST['interval_hours'] ?? 8);
            break;
    }
    
    // Insert schedule
    $scheduleId = insert('schedules', $scheduleData);
    
    // Generate medication logs for the next 30 days
    generateMedicationLogs($scheduleId, $medicationId, $userId, $scheduleData);
    
    commitTransaction();
    
    $_SESSION['message'] = ['type' => 'success', 'text' => 'Schedule created successfully'];
    
} catch (Exception $e) {
    rollbackTransaction();
    error_log("Add schedule error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to create schedule. Please try again.'];
}

header('Location: ../schedule.php');
exit();

/**
 * Generate medication logs based on schedule
 */
function generateMedicationLogs($scheduleId, $medicationId, $userId, $scheduleData) {
    $logs = [];
    $startDate = new DateTime($scheduleData['start_date']);
    $endDate = $scheduleData['end_date'] ? new DateTime($scheduleData['end_date']) : (clone $startDate)->modify('+30 days');
    $time1 = $scheduleData['time_1'];
    
    switch ($scheduleData['frequency_type']) {
        case 'once':
            $specificDate = new DateTime($scheduleData['specific_date']);
            $specificDate->setTime(
                (int)substr($time1, 0, 2),
                (int)substr($time1, 3, 2)
            );
            $logs[] = [
                'user_id' => $userId,
                'medication_id' => $medicationId,
                'schedule_id' => $scheduleId,
                'scheduled_time' => $specificDate->format('Y-m-d H:i:s'),
                'status' => 'pending'
            ];
            break;
            
        case 'daily':
            $current = clone $startDate;
            while ($current <= $endDate) {
                $logTime = clone $current;
                $logTime->setTime(
                    (int)substr($time1, 0, 2),
                    (int)substr($time1, 3, 2)
                );
                $logs[] = [
                    'user_id' => $userId,
                    'medication_id' => $medicationId,
                    'schedule_id' => $scheduleId,
                    'scheduled_time' => $logTime->format('Y-m-d H:i:s'),
                    'status' => 'pending'
                ];
                $current->modify('+1 day');
            }
            break;
            
        case 'weekly':
            $days = [];
            if ($scheduleData['monday']) $days[] = 'monday';
            if ($scheduleData['tuesday']) $days[] = 'tuesday';
            if ($scheduleData['wednesday']) $days[] = 'wednesday';
            if ($scheduleData['thursday']) $days[] = 'thursday';
            if ($scheduleData['friday']) $days[] = 'friday';
            if ($scheduleData['saturday']) $days[] = 'saturday';
            if ($scheduleData['sunday']) $days[] = 'sunday';
            
            $current = clone $startDate;
            while ($current <= $endDate) {
                $dayName = strtolower($current->format('l'));
                if (in_array($dayName, $days)) {
                    $logTime = clone $current;
                    $logTime->setTime(
                        (int)substr($time1, 0, 2),
                        (int)substr($time1, 3, 2)
                    );
                    $logs[] = [
                        'user_id' => $userId,
                        'medication_id' => $medicationId,
                        'schedule_id' => $scheduleId,
                        'scheduled_time' => $logTime->format('Y-m-d H:i:s'),
                        'status' => 'pending'
                    ];
                }
                $current->modify('+1 day');
            }
            break;
            
        case 'custom':
            $interval = $scheduleData['interval_hours'] ?? 8;
            $current = clone $startDate;
            $current->setTime(
                (int)substr($time1, 0, 2),
                (int)substr($time1, 3, 2)
            );
            while ($current <= $endDate) {
                $logs[] = [
                    'user_id' => $userId,
                    'medication_id' => $medicationId,
                    'schedule_id' => $scheduleId,
                    'scheduled_time' => $current->format('Y-m-d H:i:s'),
                    'status' => 'pending'
                ];
                $current->modify("+$interval hours");
            }
            break;
    }
    
    // Insert logs in batches
    if (!empty($logs)) {
        $pdo = getDBConnection();
        $columns = ['user_id', 'medication_id', 'schedule_id', 'scheduled_time', 'status'];
        $placeholders = '(' . implode(', ', array_map(fn($col) => ":$col", $columns)) . ')';
        
        $sql = "INSERT INTO medication_logs (" . implode(', ', $columns) . ") VALUES ";
        $values = [];
        $params = [];
        
        foreach ($logs as $i => $log) {
            $values[] = $placeholders;
            foreach ($columns as $col) {
                $params[":{$col}_{$i}"] = $log[$col];
            }
        }
        
        // Replace placeholders with indexed params
        $finalSql = $sql . implode(', ', $values);
        $finalParams = [];
        $index = 0;
        foreach ($logs as $log) {
            foreach ($columns as $col) {
                $finalParams[$index++] = $log[$col];
            }
        }
        
        // Use simple approach - insert one by one
        foreach ($logs as $log) {
            insert('medication_logs', $log);
        }
    }
}
