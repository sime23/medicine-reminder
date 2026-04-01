<?php
/**
 * Authentication Functions
 * 
 * Handles user authentication, registration, session management
 * and security features for the Medicine Reminder App
 * 
 * @author Medicine Reminder App
 * @version 1.0.0
 */

require_once __DIR__ . '/db_config.php';

// Session configuration
session_name('medrem_session');
session_set_cookie_params([
    'lifetime' => 86400 * 30, // 30 days
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Register a new user
 * 
 * @param array $userData User registration data
 * @return array Result with success status and message
 */
function registerUser(array $userData): array {
    try {
        // Validate required fields
        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'message' => ucfirst($field) . ' is required'];
            }
        }
        
        // Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Validate password strength
        if (strlen($userData['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        // Check if email already exists
        $existing = fetchOne(
            "SELECT user_id FROM users WHERE email = :email",
            ['email' => strtolower($userData['email'])]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password using bcrypt
        $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Prepare user data for insertion
        $insertData = [
            'email' => strtolower($userData['email']),
            'password_hash' => $passwordHash,
            'first_name' => sanitizeInput($userData['first_name']),
            'last_name' => sanitizeInput($userData['last_name']),
            'phone' => !empty($userData['phone']) ? sanitizeInput($userData['phone']) : null,
            'date_of_birth' => !empty($userData['date_of_birth']) ? $userData['date_of_birth'] : null,
            'email_notifications' => $userData['email_notifications'] ?? 1,
            'sms_notifications' => $userData['sms_notifications'] ?? 0,
            'push_notifications' => $userData['push_notifications'] ?? 1
        ];
        
        // Insert user into database
        $userId = insert('users', $insertData);
        
        if ($userId) {
            // Log successful registration
            logDBError("User registered successfully", ['user_id' => $userId, 'email' => $userData['email']]);
            
            return [
                'success' => true, 
                'message' => 'Registration successful! Please log in.',
                'user_id' => $userId
            ];
        }
        
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        
    } catch (Exception $e) {
        logDBError("Registration error", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

/**
 * Authenticate user login
 * 
 * @param string $email User email
 * @param string $password User password
 * @param bool $remember Whether to remember login
 * @return array Result with success status and message
 */
function loginUser(string $email, string $password, bool $remember = false): array {
    try {
        // Validate inputs
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        // Fetch user by email
        $user = fetchOne(
            "SELECT user_id, email, password_hash, first_name, last_name, is_active, 
                    email_notifications, sms_notifications, push_notifications, theme
             FROM users 
             WHERE email = :email",
            ['email' => strtolower($email)]
        );
        
        // Check if user exists
        if (!$user) {
            // Use constant time to prevent timing attacks
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuuxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is deactivated. Please contact support.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check if password needs rehashing
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            update('users', ['password_hash' => $newHash], 'user_id = :id', ['id' => $user['user_id']]);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['theme'] = $user['theme'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Store notification preferences in session
        $_SESSION['preferences'] = [
            'email_notifications' => $user['email_notifications'],
            'sms_notifications' => $user['sms_notifications'],
            'push_notifications' => $user['push_notifications']
        ];
        
        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (86400 * 30); // 30 days
            
            // Store token hash in database
            update('users', [
                'reset_token' => hash('sha256', $token),
                'reset_token_expires' => date('Y-m-d H:i:s', $expires)
            ], 'user_id = :id', ['id' => $user['user_id']]);
            
            setcookie('remember_token', $token, [
                'expires' => $expires,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        // Update last login time
        update('users', ['last_login' => date('Y-m-d H:i:s')], 'user_id = :id', ['id' => $user['user_id']]);
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        logDBError("User logged in successfully", ['user_id' => $user['user_id']]);
        
        return [
            'success' => true, 
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['user_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'theme' => $user['theme']
            ]
        ];
        
    } catch (Exception $e) {
        logDBError("Login error", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is authenticated
 */
function isLoggedIn(): bool {
    // Check session
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Check session timeout (30 minutes of inactivity)
        $timeout = 1800; // 30 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            logoutUser();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Check remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        return validateRememberToken($_COOKIE['remember_token']);
    }
    
    return false;
}

/**
 * Validate remember me token
 * 
 * @param string $token Remember token
 * @return bool True if valid
 */
function validateRememberToken(string $token): bool {
    try {
        $tokenHash = hash('sha256', $token);
        
        $user = fetchOne(
            "SELECT user_id, email, first_name, last_name, theme,
                    email_notifications, sms_notifications, push_notifications
             FROM users 
             WHERE reset_token = :token AND reset_token_expires > NOW()",
            ['token' => $tokenHash]
        );
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['theme'] = $user['theme'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['preferences'] = [
                'email_notifications' => $user['email_notifications'],
                'sms_notifications' => $user['sms_notifications'],
                'push_notifications' => $user['push_notifications']
            ];
            
            session_regenerate_id(true);
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Logout user and clear session
 */
function logoutUser(): void {
    // Clear remember token from database if exists
    if (isset($_SESSION['user_id'])) {
        try {
            update('users', [
                'reset_token' => null,
                'reset_token_expires' => null
            ], 'user_id = :id', ['id' => $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        unset($_COOKIE['remember_token']);
    }
    
    // Clear session
    $_SESSION = [];
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/'
        ]);
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Get current logged in user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        return fetchOne(
            "SELECT user_id, email, first_name, last_name, phone, date_of_birth,
                    email_notifications, sms_notifications, push_notifications,
                    reminder_time_before, theme, profile_image, created_at
             FROM users 
             WHERE user_id = :id",
            ['id' => $_SESSION['user_id']]
        );
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Require authentication - redirect to login if not authenticated
 * 
 * @param string $redirect URL to redirect after login
 */
function requireAuth(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $loginUrl = '/pages/login.php';
        if (!empty($redirect)) {
            $loginUrl .= '?redirect=' . urlencode($redirect);
        }
        header("Location: $loginUrl");
        exit();
    }
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Change user password
 * 
 * @param int $userId User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return array Result with success status and message
 */
function changePassword(int $userId, string $currentPassword, string $newPassword): array {
    try {
        // Get current password hash
        $user = fetchOne(
            "SELECT password_hash FROM users WHERE user_id = :id",
            ['id' => $userId]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Validate new password
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters'];
        }
        
        // Hash and update new password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        update('users', ['password_hash' => $newHash], 'user_id = :id', ['id' => $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

/**
 * Request password reset
 * 
 * @param string $email User email
 * @return array Result with success status
 */
function requestPasswordReset(string $email): array {
    try {
        $user = fetchOne(
            "SELECT user_id, first_name FROM users WHERE email = :email AND is_active = 1",
            ['email' => strtolower($email)]
        );
        
        if (!$user) {
            // Don't reveal if email exists
            return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        update('users', [
            'reset_token' => hash('sha256', $token),
            'reset_token_expires' => $expires
        ], 'user_id = :id', ['id' => $user['user_id']]);
        
        // Here you would send the email with reset link
        // sendPasswordResetEmail($email, $user['first_name'], $token);
        
        return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}
