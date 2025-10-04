<?php
// logout.php
session_start();

// Log the logout action
if (isset($_SESSION['user'])) {
    error_log("User logout: " . $_SESSION['user']['email'] . " (ID: " . $_SESSION['user']['id'] . ")");
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Redirect to login page with logout status
header("Location: login.php?status=logged_out");
exit();
?>