<?php
require_once 'config.php';

// Destroy session
session_start();
session_unset();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to public landing page
header('Location: landingpage.php');
exit();
?>

