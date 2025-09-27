<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        // First check if it's the admin login
        if ($email === 'admin@cookpad.com' && $password === 'admin123') {
            // Set admin session variables
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_username'] = 'Admin';
            $_SESSION['admin_email'] = 'admin@cookpad.com';
            header("Location: admin-dashboard.php"); // Changed to hyphen
            exit();
        }
        
        // If not admin, check regular user in database
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if password is correct
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = false;
                
                header("Location: user_dashboard.php"); // Keep underscore
                exit();
            } else {
                $_SESSION['message'] = "Invalid password!";
                $_SESSION['message_type'] = 'error';
                header("Location: index.php");
                exit();
            }
        } else {
            $_SESSION['message'] = "No account found with that email!";
            $_SESSION['message_type'] = 'error';
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Login failed: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}