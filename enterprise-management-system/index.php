<?php
// Redirect to login page if not logged in
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Redirect based on user role
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: modules/admin/dashboard.php');
            exit();
        case 'manager':
            header('Location: modules/manager/dashboard.php');
            exit();
        case 'employee':
            header('Location: modules/employee/dashboard.php');
            exit();
        default:
            header('Location: login.php');
            exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>