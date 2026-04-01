<?php
/**
 * API: Add Medication
 * 
 * Handles adding a new medication to the database
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_config.php';

// Require authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../medicines.php');
    exit();
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid request'];
    header('Location: ../medicines.php');
    exit();
}

$userId = getCurrentUserId();

// Validate required fields
$medicationName = trim($_POST['medication_name'] ?? '');
$dosageAmount = trim($_POST['dosage_amount'] ?? '');

if (empty($medicationName) || empty($dosageAmount)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Medication name and dosage are required'];
    header('Location: ../medicines.php');
    exit();
}

try {
    // Prepare medication data
    $medicationData = [
        'user_id' => $userId,
        'medication_name' => sanitizeInput($medicationName),
        'generic_name' => !empty($_POST['generic_name']) ? sanitizeInput($_POST['generic_name']) : null,
        'brand_name' => !empty($_POST['brand_name']) ? sanitizeInput($_POST['brand_name']) : null,
        'dosage_amount' => sanitizeInput($dosageAmount),
        'dosage_form' => $_POST['dosage_form'] ?? 'tablet',
        'color' => $_POST['color'] ?? '#0d9488',
        'shape' => !empty($_POST['shape']) ? $_POST['shape'] : null,
        'instructions' => !empty($_POST['instructions']) ? sanitizeInput($_POST['instructions']) : null,
        'prescribed_by' => !empty($_POST['prescribed_by']) ? sanitizeInput($_POST['prescribed_by']) : null,
        'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'total_quantity' => !empty($_POST['total_quantity']) ? (int)$_POST['total_quantity'] : null,
        'remaining_quantity' => !empty($_POST['total_quantity']) ? (int)$_POST['total_quantity'] : null,
        'notes' => !empty($_POST['notes']) ? sanitizeInput($_POST['notes']) : null,
        'is_active' => 1
    ];
    
    // Insert medication
    $medicationId = insert('medications', $medicationData);
    
    if ($medicationId) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Medication added successfully'];
        
        // If schedule data is provided, create schedule
        if (!empty($_POST['create_schedule'])) {
            header('Location: ../schedule_add.php?med_id=' . $medicationId);
            exit();
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to add medication'];
    }
    
} catch (Exception $e) {
    error_log("Add medication error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'An error occurred. Please try again.'];
}

header('Location: ../medicines.php');
exit();
