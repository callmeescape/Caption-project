<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: index.php#contact");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: index.php#contact");
        exit();
    }
    
    // Save to database
    $stmt = $conn->prepare("INSERT INTO support_mails (name, email, subject, message) VALUES (:name, :email, 'Contact Form', :message)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':message', $message);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Thank you for your message! We'll get back to you soon.";
    } else {
        $_SESSION['error'] = "Failed to send message. Please try again.";
    }
    
    header("Location: index.php#contact");
    exit();
}
?>