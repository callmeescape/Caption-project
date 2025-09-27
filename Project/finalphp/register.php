<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['message'] = "All fields are required";
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Invalid email format";
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['message'] = "Password must be at least 6 characters long";
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
    
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "User with this email or username already exists";
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
    
    // Hash password and create user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Registration successful! You can now login.";
        $_SESSION['message_type'] = 'success';
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['message'] = "Registration failed. Please try again.";
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>