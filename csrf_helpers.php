<?php
/**
 * CSRF Protection and Inactivity Session Management Helper
 * 
 * Provides defense-in-depth for state-changing HTTP requests:
 * 1. Cryptographically strong token generation using random_bytes()
 * 2. Timing-attack safe token verification using hash_equals()
 * 3. Enforced inactivity session timeout (10 minutes)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce 10-minute inactivity session expiry
$timeout_duration = 600; // 600 seconds = 10 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: login.php?msg=session_expired");
        exit();
    }
}
$_SESSION['last_activity'] = time();

/**
 * Generate a new CSRF token if one does not exist for the active session.
 * @return string Cryptographically secure 64-character hexadecimal token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF token against the session token.
 * Uses hash_equals to prevent timing-based side-channel attacks.
 * 
 * @param array $requestData Request parameters ($_POST or $_REQUEST)
 * @return bool True if valid, False otherwise
 */
function verifyCsrfToken($requestData) {
    if (!isset($requestData['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $requestData['csrf_token']);
}

/**
 * Convenience helper to render a hidden HTML input field containing the token.
 */
function csrfField() {
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
?>
