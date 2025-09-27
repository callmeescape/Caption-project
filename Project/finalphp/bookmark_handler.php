<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

if (isset($_GET['recipe_id']) && isset($_GET['action'])) {
    $recipe_id = (int)$_GET['recipe_id'];
    $user_id = $_SESSION['user_id'];
    $action = $_GET['action'];
    
    try {
        if ($action == 'add') {
            // Add bookmark
            $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, recipe_id) VALUES (:user_id, :recipe_id)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':recipe_id', $recipe_id);
            $stmt->execute();
        } else if ($action == 'remove') {
            // Remove bookmark
            $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = :user_id AND recipe_id = :recipe_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':recipe_id', $recipe_id);
            $stmt->execute();
        }
        
        echo "success";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Invalid request";
}
?>