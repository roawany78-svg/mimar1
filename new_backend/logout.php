<?php
// logout.php - JWT VERSION
require_once __DIR__ . '/config.php';

clear_auth_cookie();

if (is_ajax()) {
    json_response(['ok' => true]);
}

// Redirect to login page
$login = (defined('BASE_URL') ? BASE_URL : '') . '/Login.html';
header('Location: ' . $login);
exit;