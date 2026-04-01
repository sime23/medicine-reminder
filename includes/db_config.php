<?php
/**
 * Database Configuration File
 * 
 * Centralized database connection management using PDO
 * Provides secure connection with prepared statements support
 * 
 * @author Medicine Reminder App
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Database credentials - Update these with your actual database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'medicine');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO Options for secure and optimized connections
$DB_OPTIONS = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES => false,                // Use real prepared statements
    PDO::ATTR_STRINGIFY_FETCHES => false,               // Keep native types
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
    PDO::ATTR_PERSISTENT => true                         // Use persistent connections
];

/**
 * Get database connection instance
 * 
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $GLOBALS['DB_OPTIONS']);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Unable to connect to database. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Close database connection
 * Useful for cleanup, though PDO persistent connections manage this automatically
 */
function closeDBConnection(): void {
    // PDO connections are closed when the variable is set to null
    // This is mainly for explicit cleanup if needed
}

/**
 * Execute a prepared query with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement Executed statement
 * @throws PDOException if query fails
 */
function executeQuery(string $sql, array $params = []): PDOStatement {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row from database
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array|false Single row or false if not found
 */
function fetchOne(string $sql, array $params = []): array|false {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows from database
 * 
 * @param string $sql SQL query
 * @param array $params Query parameters
 * @return array Array of rows
 */
function fetchAll(string $sql, array $params = []): array {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert data and return last insert ID
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int Last insert ID
 */
function insert(string $table, array $data): int {
    $pdo = getDBConnection();
    
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Update data in database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value to update
 * @param string $where WHERE clause
 * @param array $whereParams Parameters for WHERE clause
 * @return int Number of affected rows
 */
function update(string $table, array $data, string $where, array $whereParams = []): int {
    $pdo = getDBConnection();
    
    $setParts = [];
    foreach ($data as $column => $value) {
        $setParts[] = "{$column} = :{$column}";
    }
    $setClause = implode(', ', $setParts);
    
    $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, $whereParams));
    
    return $stmt->rowCount();
}

/**
 * Delete data from database
 * 
 * @param string $table Table name
 * @param string $where WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function delete(string $table, string $where, array $params = []): int {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Begin a database transaction
 */
function beginTransaction(): void {
    getDBConnection()->beginTransaction();
}

/**
 * Commit a database transaction
 */
function commitTransaction(): void {
    getDBConnection()->commit();
}

/**
 * Rollback a database transaction
 */
function rollbackTransaction(): void {
    getDBConnection()->rollBack();
}

/**
 * Sanitize user input (additional layer of security)
 * 
 * @param mixed $input Input to sanitize
 * @return mixed Sanitized input
 */
function sanitizeInput(mixed $input): mixed {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    if (is_string($input)) {
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        // Trim whitespace
        $input = trim($input);
        // Remove slashes if magic quotes is on (legacy)
        $input = stripslashes($input);
    }
    
    return $input;
}

/**
 * Validate and format date/time inputs
 * 
 * @param string $date Date string
 * @param string $format Expected format
 * @return string|false Formatted date or false if invalid
 */
function validateDate(string $date, string $format = 'Y-m-d H:i:s'): string|false {
    $d = DateTime::createFromFormat($format, $date);
    return ($d && $d->format($format) === $date) ? $date : false;
}

/**
 * Log database errors
 * 
 * @param string $message Error message
 * @param array $context Additional context
 */
function logDBError(string $message, array $context = []): void {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    error_log(json_encode($logEntry));
}
