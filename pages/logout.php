<?php
/**
 * Logout Page
 * 
 * Handles user logout and session cleanup
 */

require_once __DIR__ . '/../includes/auth.php';

// Logout the user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit();
