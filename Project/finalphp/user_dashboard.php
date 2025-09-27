<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user recipes
$stmt = $conn->prepare("SELECT * FROM recipes WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bookmarked recipes
$stmt = $conn->prepare("
    SELECT r.* FROM recipes r 
    JOIN bookmarks b ON r.id = b.recipe_id 
    WHERE b.user_id = :user_id 
    ORDER BY b.created_at DESC
");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bookmarkedRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $bio = $_POST['bio'];
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'user_' . $user_id . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
            // Update profile image in database
            $stmt = $conn->prepare("UPDATE users SET profile_image = :image WHERE id = :id");
            $stmt->bindParam(':image', $uploadPath);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            $user['profile_image'] = $uploadPath;
        }
    }
    
    // Update other profile information
    $stmt = $conn->prepare("UPDATE users SET name = :name, email = :email, bio = :bio WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':bio', $bio);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    
    // Update password if provided
    if (!empty($_POST['password'])) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
    }
    
    // Refresh user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $successMessage = "Profile updated successfully!";
}

// Handle recipe upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_recipe'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $prep_time = $_POST['prep_time'];
    $cook_time = $_POST['cook_time'];
    $servings = $_POST['servings'];
    $difficulty = $_POST['difficulty'];
    $ingredients = $_POST['ingredients'];
    $steps = $_POST['steps'];
    
    // Handle recipe image upload
    $recipeImagePath = 'images/recipe-placeholder.jpg'; // Default placeholder
    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/recipes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['recipe_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'recipe_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['recipe_image']['tmp_name'], $uploadPath)) {
            $recipeImagePath = $uploadPath;
        }
    }
    
    // Insert recipe into database
    $stmt = $conn->prepare("
        INSERT INTO recipes (user_id, title, description, category, prep_time, cook_time, servings, difficulty, image_path, ingredients, steps)
        VALUES (:user_id, :title, :description, :category, :prep_time, :cook_time, :servings, :difficulty, :image_path, :ingredients, :steps)
    ");
    
    $ingredientsText = is_array($ingredients) ? implode("\n", $ingredients) : $ingredients;
    $stepsText = is_array($steps) ? implode("\n", $steps) : $steps;
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':prep_time', $prep_time);
    $stmt->bindParam(':cook_time', $cook_time);
    $stmt->bindParam(':servings', $servings);
    $stmt->bindParam(':difficulty', $difficulty);
    $stmt->bindParam(':image_path', $recipeImagePath);
    $stmt->bindParam(':ingredients', $ingredientsText);
    $stmt->bindParam(':steps', $stepsText);
    
    $stmt->execute();
    
    // Refresh user recipes
    $stmt = $conn->prepare("SELECT * FROM recipes WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $successMessage = "Recipe uploaded successfully!";
}

// Handle recipe deletion
if (isset($_GET['delete_recipe'])) {
    $recipe_id = $_GET['delete_recipe'];
    
    // Check if the recipe belongs to the user
    $stmt = $conn->prepare("SELECT * FROM recipes WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $recipe_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($recipe) {
        // Delete the recipe
        $stmt = $conn->prepare("DELETE FROM recipes WHERE id = :id");
        $stmt->bindParam(':id', $recipe_id);
        $stmt->execute();
        
        // Also delete from bookmarks
        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE recipe_id = :recipe_id");
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->execute();
        
        // Refresh user recipes
        $stmt = $conn->prepare("SELECT * FROM recipes WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $successMessage = "Recipe deleted successfully!";
    }
}

// Handle bookmark toggle
if (isset($_GET['toggle_bookmark'])) {
    $recipe_id = $_GET['toggle_bookmark'];
    
    // Check if already bookmarked
    $stmt = $conn->prepare("SELECT * FROM bookmarks WHERE user_id = :user_id AND recipe_id = :recipe_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':recipe_id', $recipe_id);
    $stmt->execute();
    $bookmark = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bookmark) {
        // Remove bookmark
        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = :user_id AND recipe_id = :recipe_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->execute();
    } else {
        // Add bookmark
        $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, recipe_id) VALUES (:user_id, :recipe_id)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->execute();
    }
    
    // Refresh bookmarked recipes
    $stmt = $conn->prepare("
        SELECT r.* FROM recipes r 
        JOIN bookmarks b ON r.id = b.recipe_id 
        WHERE b.user_id = :user_id 
        ORDER BY b.created_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $bookmarkedRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all recipes for the activity feed
$stmt = $conn->prepare("SELECT * FROM recipes ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$recentRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user stats
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM recipes WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recipeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookmarks WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bookmarkCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// For followers and following, you would need to implement a follow system
$followerCount = 0;
$followingCount = 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookpad - User Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --white-color: #fff;
            --dark-color: #252525;
            --primary-color: #3d0a37;
            --secondary-color: #f3961c;
            --light-pink-color: #faf4f5;
            --medium-gray-color: #ccc;
        }

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light-pink-color);
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background: var(--primary-color);
            color: var(--white-color);
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .logo-icon {
            margin-right: 10px;
            font-size: 28px;
            color: var(--secondary-color);
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        nav a {
            color: var(--white-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 15px;
            border-radius: 4px;
        }

        nav a:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-menu a {
            color: var(--white-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 15px;
            border-radius: 4px;
        }

        .user-menu a:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
        }

        /* Mobile menu */
        .menu-toggle {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .menu-toggle span {
            width: 100%;
            height: 3px;
            background-color: var(--white-color);
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        /* Dashboard Tabs */
        .dashboard-tabs {
            display: flex;
            gap: 10px;
            margin: 30px 0;
            border-bottom: 2px solid var(--primary-color);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--dark-color);
        }

        .tab-btn.active {
            border-bottom: 3px solid var(--secondary-color);
            color: var(--primary-color);
        }

        .tab-btn:hover {
            color: var(--primary-color);
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* User Profile */
        .profile-card {
            background: var(--white-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--light-pink-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: var(--primary-color);
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-image:hover .change-photo {
            opacity: 1;
        }

        .change-photo {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(61, 10, 55, 0.8);
            color: white;
            text-align: center;
            padding: 5px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-icon {
            cursor: pointer;
            color: var(--secondary-color);
            font-size: 18px;
        }

        .profile-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--light-pink-color);
            border-radius: 8px;
            min-width: 120px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 14px;
            color: var(--dark-color);
        }

        /* Recipe Cards */
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .recipe-card {
            background: var(--white-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(61, 10, 55, 0.15);
        }

        .bookmark-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--white-color);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .bookmark-icon.active {
            color: var(--secondary-color);
        }

        .recipe-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background-color: var(--light-pink-color);
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .recipe-image .image-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(61, 10, 55, 0.7), rgba(243, 150, 28, 0.7));
            color: var(--white-color);
            font-weight: bold;
            font-size: 18px;
            text-align: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .recipe-card:hover .recipe-image .image-placeholder {
            opacity: 1;
        }

        .recipe-info {
            padding: 20px;
        }

        .recipe-title {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            color: var(--dark-color);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .recipe-description {
            font-size: 14px;
            color: var(--dark-color);
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recipe-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-btn {
            background: var(--primary-color);
            color: var(--white-color);
        }

        .edit-btn {
            background: var(--secondary-color);
            color: var(--white-color);
        }

        .delete-btn {
            background: #dc3545;
            color: var(--white-color);
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Form Styles */
        .form-container {
            background: var(--white-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(243, 150, 28, 0.2);
            outline: none;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .ingredient-input, .step-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .ingredient-input input, .step-input input {
            flex: 1;
        }

        .add-btn {
            background: var(--primary-color);
            color: var(--white-color);
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            background: var(--secondary-color);
        }

        .ingredient-list, .step-list {
            margin-top: 15px;
            padding-left: 20px;
        }

        .ingredient-list li, .step-list li {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: var(--light-pink-color);
            border-radius: 4px;
        }

        .remove-btn {
            background: #dc3545;
            color: var(--white-color);
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .submit-btn {
            background: var(--primary-color);
            color: var(--white-color);
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--secondary-color);
        }

        /* Edit Profile Form */
        .edit-profile-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: var(--light-pink-color);
            border-radius: 8px;
        }

        /* Recipe Detail View */
        .recipe-detail {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--white-color);
            z-index: 1000;
            overflow-y: auto;
            padding: 30px;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 30px;
            cursor: pointer;
            color: var(--primary-color);
            z-index: 1001;
            background: var(--secondary-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: var(--primary-color);
            color: var(--secondary-color);
        }

        .detail-header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 20px;
        }

        .detail-title {
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .detail-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            color: var(--dark-color);
            margin-bottom: 20px;
        }

        .detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .ingredients-list,
        .steps-list {
            background: var(--light-pink-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .ingredients-list h3,
        .steps-list h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 24px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }

        .ingredients-list ul,
        .steps-list ol {
            padding-left: 20px;
        }

        .ingredients-list li,
        .steps-list li {
            margin-bottom: 12px;
            line-height: 1.6;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--dark-color);
        }

        .empty-state i {
            font-size: 60px;
            color: var(--medium-gray-color);
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* Footer Styles */
        footer {
            background: var(--primary-color);
            color: var(--white-color);
            text-align: center;
            padding: 30px 0;
            margin-top: 60px;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .social-icons {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }

        .social-icons a {
            color: var(--white-color);
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            color: var(--secondary-color);
            transform: translateY(-3px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            nav ul {
                display: none;
                flex-direction: column;
                width: 100%;
                text-align: center;
            }

            nav ul.show {
                display: flex;
            }

            .menu-toggle {
                display: flex;
            }

            .profile-card {
                flex-direction: column;
                text-align: center;
            }

            .profile-stats {
                justify-content: center;
                flex-wrap: wrap;
            }

            .dashboard-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }

            .recipe-grid {
                grid-template-columns: 1fr;
            }

            .detail-content {
                grid-template-columns: 1fr;
            }
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <header>
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-utensils logo-icon"></i>
                Cookpad!
            </div>
            <nav>
                <ul id="navMenu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="recipepage.php">Recipes</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <h1 class="section-title">My Dashboard</h1>
        
        <!-- Display messages -->
        <?php if (isset($successMessage)): ?>
            <div class="message success"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        
        <!-- Dashboard Tabs -->
        <div class="dashboard-tabs">
            <button class="tab-btn active" data-tab="profile">Profile</button>
            <button class="tab-btn" data-tab="my-recipes">My Recipes</button>
            <button class="tab-btn" data-tab="bookmarks">Bookmarks</button>
            <button class="tab-btn" data-tab="upload">Upload Recipe</button>
        </div>

        <!-- Profile Tab -->
        <div class="tab-content active" id="profile">
            <div class="profile-card">
                <div class="profile-image">
                    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'img/placeholder.png'; ?>" alt="Profile" id="profile-img">
                    <div class="change-photo" id="changePhotoBtn">Change Photo</div>
                </div>
                <div class="profile-info">
                    <h2 class="profile-name">
                        <span id="profile-name-text"><?php echo htmlspecialchars($user['username']); ?></span>
                        <i class="fas fa-edit edit-icon" id="editProfileBtn"></i>
                    </h2>
                    <p><strong>Email:</strong> <span id="profile-email"><?php echo htmlspecialchars($user['email']); ?></span></p>
                    <p><strong>Member since:</strong> <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    <p><strong>Bio:</strong> <span id="profile-bio"><?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Food enthusiast who loves to cook and try new recipes!'; ?></span></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $recipeCount; ?></div>
                            <div class="stat-label">Recipes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $bookmarkCount; ?></div>
                            <div class="stat-label">Bookmarks</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $followerCount; ?></div>
                            <div class="stat-label">Followers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $followingCount; ?></div>
                            <div class="stat-label">Following</div>
                        </div>
                    </div>
                    
                    <!-- Edit Profile Form (Initially Hidden) -->
                    <form method="POST" enctype="multipart/form-data" class="edit-profile-form" id="editProfileForm">
                        <div class="form-group">
                            <label for="edit-name" class="form-label">Username</label>
                            <input type="text" id="edit-name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit-email" class="form-label">Email</label>
                            <input type="email" id="edit-email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit-bio" class="form-label">Bio</label>
                            <textarea id="edit-bio" name="bio" class="form-control"><?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Food enthusiast who loves to cook and try new recipes!'; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label for="edit-password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" id="edit-password" name="password" class="form-control">
                        </div>
                        <button type="submit" name="update_profile" class="submit-btn">Save Changes</button>
                        <button type="button" class="action-btn delete-btn" id="cancelEditBtn">Cancel</button>
                    </form>
                </div>
            </div>
            
            <h2 class="section-title">Recent Activity</h2>
            <div class="recipe-grid">
                <?php if (!empty($recentRecipes)): ?>
                    <?php foreach ($recentRecipes as $recipe): ?>
                        <div class="recipe-card">
                            <div class="recipe-image" style="background-image: url('<?php echo htmlspecialchars($recipe['image_path']); ?>');">
                                <div class="image-placeholder"><?php echo htmlspecialchars($recipe['title']); ?></div>
                            </div>
                            <div class="recipe-info">
                                <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                <div class="recipe-meta">
                                    <span><?php echo htmlspecialchars($recipe['cook_time']); ?> mins</span>
                                    <span><?php echo htmlspecialchars($recipe['servings']); ?> servings</span>
                                    <span><?php echo ucfirst(htmlspecialchars($recipe['difficulty'])); ?></span>
                                </div>
                                <p class="recipe-description"><?php echo htmlspecialchars($recipe['description']); ?></p>
                                <div class="recipe-actions">
                                    <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent activity to display.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Recipes Tab -->
        <div class="tab-content" id="my-recipes">
            <h2 class="section-title">My Uploaded Recipes</h2>
            
            <div class="recipe-grid" id="my-recipes-grid">
                <?php if (!empty($userRecipes)): ?>
                    <?php foreach ($userRecipes as $recipe): ?>
                        <div class="recipe-card">
                            <a href="?toggle_bookmark=<?php echo $recipe['id']; ?>" class="bookmark-icon active">
                                <i class="fas fa-bookmark"></i>
                            </a>
                            <div class="recipe-image" style="background-image: url('<?php echo htmlspecialchars($recipe['image_path']); ?>');">
                                <div class="image-placeholder"><?php echo htmlspecialchars($recipe['title']); ?></div>
                            </div>
                            <div class="recipe-info">
                                <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                <div class="recipe-meta">
                                    <span><?php echo htmlspecialchars($recipe['cook_time']); ?> mins</span>
                                    <span><?php echo htmlspecialchars($recipe['servings']); ?> servings</span>
                                    <span><?php echo ucfirst(htmlspecialchars($recipe['difficulty'])); ?></span>
                                </div>
                                <p class="recipe-description"><?php echo htmlspecialchars($recipe['description']); ?></p>
                                <div class="recipe-actions">
                                    <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="action-btn edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete_recipe=<?php echo $recipe['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this recipe?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" id="no-recipes-message">
                        <i class="fas fa-utensils"></i>
                        <p>You haven't uploaded any recipes yet.</p>
                        <button class="submit-btn switch-tab" data-tab="upload">Upload Your First Recipe</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bookmarks Tab -->
        <div class="tab-content" id="bookmarks">
            <h2 class="section-title">My Bookmarked Recipes</h2>
            
            <div class="recipe-grid" id="bookmarks-grid">
                <?php if (!empty($bookmarkedRecipes)): ?>
                    <?php foreach ($bookmarkedRecipes as $recipe): ?>
                        <div class="recipe-card">
                            <a href="?toggle_bookmark=<?php echo $recipe['id']; ?>" class="bookmark-icon active">
                                <i class="fas fa-bookmark"></i>
                            </a>
                            <div class="recipe-image" style="background-image: url('<?php echo htmlspecialchars($recipe['image_path']); ?>');">
                                <div class="image-placeholder"><?php echo htmlspecialchars($recipe['title']); ?></div>
                            </div>
                            <div class="recipe-info">
                                <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                <div class="recipe-meta">
                                    <span><?php echo htmlspecialchars($recipe['cook_time']); ?> mins</span>
                                    <span><?php echo htmlspecialchars($recipe['servings']); ?> servings</span>
                                    <span><?php echo ucfirst(htmlspecialchars($recipe['difficulty'])); ?></span>
                                </div>
                                <p class="recipe-description"><?php echo htmlspecialchars($recipe['description']); ?></p>
                                <div class="recipe-actions">
                                    <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" id="no-bookmarks-message">
                        <i class="fas fa-bookmark"></i>
                        <p>You haven't bookmarked any recipes yet.</p>
                        <a href="recipepage.php" class="submit-btn">Browse Recipes</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Recipe Tab -->
        <div class="tab-content" id="upload">
            <div class="form-container">
                <h2 class="form-title">Upload New Recipe</h2>
                
                <form method="POST" enctype="multipart/form-data" id="recipe-upload-form">
                    <input type="hidden" name="upload_recipe" value="1">
                    
                    <div class="form-group">
                        <label for="recipe-title" class="form-label">Recipe Title</label>
                        <input type="text" id="recipe-title" name="title" class="form-control" placeholder="Enter recipe title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-description" class="form-label">Description</label>
                        <textarea id="recipe-description" name="description" class="form-control" placeholder="Describe your recipe" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-category" class="form-label">Category</label>
                        <select id="recipe-category" name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="dessert">Dessert</option>
                            <option value="snack">Snack</option>
                            <option value="beverage">Beverage</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-prep-time" class="form-label">Preparation Time (minutes)</label>
                        <input type="number" id="recipe-prep-time" name="prep_time" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-cook-time" class="form-label">Cooking Time (minutes)</label>
                        <input type="number" id="recipe-cook-time" name="cook_time" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-servings" class="form-label">Servings</label>
                        <input type="number" id="recipe-servings" name="servings" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-difficulty" class="form-label">Difficulty Level</label>
                        <select id="recipe-difficulty" name="difficulty" class="form-control" required>
                            <option value="">Select difficulty</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Ingredients</label>
                        <div class="ingredient-input">
                            <input type="text" id="ingredient-text" class="form-control" placeholder="Add an ingredient">
                            <button type="button" class="add-btn" id="add-ingredient">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <ul class="ingredient-list" id="ingredients-list"></ul>
                        <input type="hidden" name="ingredients" id="ingredients-data">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Steps</label>
                        <div class="step-input">
                            <input type="text" id="step-text" class="form-control" placeholder="Add a step">
                            <button type="button" class="add-btn" id="add-step">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <ol class="step-list" id="steps-list"></ol>
                        <input type="hidden" name="steps" id="steps-data">
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe-image" class="form-label">Recipe Image</label>
                        <input type="file" id="recipe-image" name="recipe_image" class="form-control" accept="image/*">
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-upload"></i> Upload Recipe
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recipe Detail View -->
    <div class="recipe-detail" id="recipeDetail">
        <span class="close-btn" id="closeDetail">&times;</span>
        <div class="container">
            <div class="detail-header">
                <h2 class="detail-title" id="detailTitle"></h2>
                <div class="detail-meta" id="detailMeta"></div>
            </div>
            <div class="detail-content">
                <div class="ingredients-list">
                    <h3>Ingredients</h3>
                    <ul id="detailIngredients"></ul>
                </div>
                <div class="steps-list">
                    <h3>Steps</h3>
                    <ol id="detailSteps"></ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <div class="logo">
                <i class="fas fa-utensils logo-icon"></i>
                Cookpad
            </div>
            <p>Discover and share delicious recipes from around the world</p>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-pinterest"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
            <p>&copy; 2025 Caption Project, Cookpad - All your cooking needs in one place</p>
        </div>
    </footer>

    <script>
        // Tab functionality
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                // Update active tab button
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show active tab content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Switch tab function for buttons
        document.querySelectorAll('.switch-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                document.querySelector(`.tab-btn[data-tab="${tabId}"]`).click();
            });
        });

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.getElementById('navMenu').classList.toggle('show');
        });

        // Ingredients and steps management
        const ingredients = [];
        const steps = [];

        document.getElementById('add-ingredient').addEventListener('click', () => {
            const ingredientInput = document.getElementById('ingredient-text');
            const ingredient = ingredientInput.value.trim();
            
            if (ingredient) {
                ingredients.push(ingredient);
                renderIngredientsList();
                ingredientInput.value = '';
                ingredientInput.focus();
            }
        });

        document.getElementById('add-step').addEventListener('click', () => {
            const stepInput = document.getElementById('step-text');
            const step = stepInput.value.trim();
            
            if (step) {
                steps.push(step);
                renderStepsList();
                stepInput.value = '';
                stepInput.focus();
            }
        });

        function renderIngredientsList() {
            const list = document.getElementById('ingredients-list');
            list.innerHTML = '';
            
            ingredients.forEach((ingredient, index) => {
                const li = document.createElement('li');
                li.innerHTML = `
                    ${ingredient}
                    <button type="button" class="remove-btn" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                list.appendChild(li);
            });
            
            // Update hidden input
            document.getElementById('ingredients-data').value = JSON.stringify(ingredients);
            
            // Add event listeners to remove buttons
            document.querySelectorAll('#ingredients-list .remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const index = parseInt(btn.getAttribute('data-index'));
                    ingredients.splice(index, 1);
                    renderIngredientsList();
                });
            });
        }

        function renderStepsList() {
            const list = document.getElementById('steps-list');
            list.innerHTML = '';
            
            steps.forEach((step, index) => {
                const li = document.createElement('li');
                li.innerHTML = `
                    ${step}
                    <button type="button" class="remove-btn" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                list.appendChild(li);
            });
            
            // Update hidden input
            document.getElementById('steps-data').value = JSON.stringify(steps);
            
            // Add event listeners to remove buttons
            document.querySelectorAll('#steps-list .remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const index = parseInt(btn.getAttribute('data-index'));
                    steps.splice(index, 1);
                    renderStepsList();
                });
            });
        }

        // Profile editing functionality
        const editProfileBtn = document.getElementById('editProfileBtn');
        const editProfileForm = document.getElementById('editProfileForm');
        const cancelEditBtn = document.getElementById('cancelEditBtn');

        editProfileBtn.addEventListener('click', () => {
            editProfileForm.style.display = 'block';
        });

        cancelEditBtn.addEventListener('click', () => {
            editProfileForm.style.display = 'none';
        });

        // Show recipe detail
        function showRecipeDetail(recipeId) {
            // In a real implementation, you would fetch recipe details from the server
            // For now, we'll use a placeholder function
            alert('Recipe detail view would show details for recipe ID: ' + recipeId);
            
            // You would implement AJAX to fetch recipe details and populate the modal
            // For now, redirect to recipe detail page
            window.location.href = 'recipe-detail.php?id=' + recipeId;
        }

        // Close detail view
        document.getElementById('closeDetail').addEventListener('click', () => {
            document.getElementById('recipeDetail').style.display = 'none';
            document.body.style.overflow = 'auto';
        });

        // View recipe details
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-btn')) {
                const viewBtn = e.target.closest('.view-btn');
                const recipeId = viewBtn.getAttribute('data-id');
                showRecipeDetail(recipeId);
            }
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Set up activity feed with some sample data
            const activityGrid = document.querySelector('#profile .recipe-grid');
            
            // This would be populated with real data from the server
            // For now, we'll keep the PHP-generated content
        });
    </script>
</body>

</html>