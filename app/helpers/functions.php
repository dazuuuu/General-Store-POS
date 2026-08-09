<?php
// app/helpers/functions.php
require_once __DIR__ . '/PathConfig.php';

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2;
}

function isSuperAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function getRoleBasedProfileUrl() {
    if (isAdmin()) {
        return public_url('profile/admin/index.php');
    }
    return public_url('profile/client/index.php');
}
?>