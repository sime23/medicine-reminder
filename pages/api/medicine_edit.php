<?php
/**
 * API: Edit Medication
 * 
 * Handles updating an existing medication
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
$medicationId = (int)($_POST['medication_id'] ?? 0);

if (!$medicationId) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid medication'];
    header('Location: ../medicines.php');
    exit();
}

// Verify ownership
$medication = fetchOne(
    "SELECT medication_id FROM medications WHERE medication_id = :id AND user_id = :user_id",
    ['id' => $medicationId, 'user_id' => $userId]
);

if (!$medication) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Medication not found'];
    header('Location: ../medicines.php');
    exit();
}

// Validate required fields
$medicationName = trim($_POST['medication_name'] ?? '');
$dosageAmount = trim($_POST['dosage_amount'] ?? '');

if (empty($medicationName) || empty($dosageAmount)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Medication name and dosage are required'];
    header('Location: ../medicines.php');
    exit();
}

try {
    // Prepare update data  
    $updateData = [
        'medication_name' => sanitizeInput($medicationName),
        'dosage_amount'   => sanitizeInput($dosageAmount),
        'dosage_form'     => $_POST['dosage_form'] ?? 'tablet',
        'color'           => !empty($_POST['color']) ? $_POST['color'] : '#0d9488',
        'shape'           => !empty($_POST['shape']) ? $_POST['shape'] : null,
        'instructions'    => !empty($_POST['instructions']) ? sanitizeInput($_POST['instructions']) : null,
        'prescribed_by'   => !empty($_POST['prescribed_by']) ? sanitizeInput($_POST['prescribed_by']) : null,
        'notes'           => !empty($_POST['notes']) ? sanitizeInput($_POST['notes']) : null,
    ];

    // Update quantity only if provided
    if (isset($_POST['total_quantity']) && is_numeric($_POST['total_quantity'])) {
        $updateData['total_quantity']     = (int)$_POST['total_quantity'];
        $updateData['remaining_quantity'] = (int)$_POST['remaining_quantity'] ?? (int)$_POST['total_quantity'];
    }

    // Update medication
    update('medications', $updateData, 'medication_id = :id', ['id' => $medicationId]);

    $_SESSION['message'] = ['type' => 'success', 'text' => 'Medication updated successfully'];

} catch (Exception $e) {
    error_log("Edit medication error: " . $e->getMessage());
    $_SESSION['message'] = ['type' => 'error', 'text' => 'An error occurred. Please try again.'];
}

header('Location: ../medicines.php');
exit();
