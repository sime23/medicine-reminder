<?php
/**
 * Medicine Reminder App - Entry Point
 * 
 * Redirects to appropriate page based on authentication status
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit();
