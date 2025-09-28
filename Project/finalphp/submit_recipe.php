<?php
session_start();
require_once 'config/database.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You need to login to submit a recipe";
    header("Location: index.php");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data with proper validation
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $steps = trim($_POST['steps'] ?? '');
    $cook_time = trim($_POST['cook_time'] ?? '');
    $servings = (int)($_POST['servings'] ?? 0);
    $difficulty = $_POST['difficulty'] ?? 'Easy';
    $category = $_POST['category'] ?? 'Other';
    $user_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($ingredients) || empty($steps) || empty($cook_time) || $servings <= 0) {
        $_SESSION['error'] = "All fields are required";
        header("Location: submit_recipe.php");
        exit();
    }
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate image file
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if($check === false) {
                $_SESSION['error'] = "File is not an image.";
                header("Location: submit_recipe.php");
                exit();
            }
            
            // Check file size (5MB max)
            if ($_FILES["image"]["size"] > 5000000) {
                $_SESSION['error'] = "Sorry, your file is too large. Max 5MB allowed.";
                header("Location: submit_recipe.php");
                exit();
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_path = $file_path;
            } else {
                $_SESSION['error'] = "Sorry, there was an error uploading your file.";
                header("Location: submit_recipe.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
            header("Location: submit_recipe.php");
            exit();
        }
    }

    try {
        // Insert recipe into database with pending status
        $stmt = $conn->prepare("INSERT INTO recipes (user_id, title, description, ingredients, steps, cook_time, servings, difficulty, category, image_path, status) 
                               VALUES (:user_id, :title, :description, :ingredients, :steps, :cook_time, :servings, :difficulty, :category, :image_path, 'pending')");
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ingredients', $ingredients);
        $stmt->bindParam(':steps', $steps);
        $stmt->bindParam(':cook_time', $cook_time);
        $stmt->bindParam(':servings', $servings);
        $stmt->bindParam(':difficulty', $difficulty);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':image_path', $image_path);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Recipe submitted successfully! It will be reviewed by our admin.";
            header("Location: user_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to submit recipe. Please try again.";
            header("Location: submit_recipe.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Database error occurred. Please try again.";
        header("Location: submit_recipe.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Recipe - Cookpad</title>
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

        /* Form Styles */
        .form-section {
            padding: 40px 0;
        }

        .section-title {
            text-align: center;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 30px;
        }

        .recipe-form {
            background: var(--white-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(243, 150, 28, 0.2);
            outline: none;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white-color);
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
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
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="user_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="form-section">
            <h2 class="section-title">Submit a Recipe</h2>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <form action="submit_recipe.php" method="POST" enctype="multipart/form-data" class="recipe-form">
                <div class="form-group">
                    <label for="title">Recipe Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ingredients">Ingredients * (one per line)</label>
                    <textarea id="ingredients" name="ingredients" rows="5" required placeholder="Enter each ingredient on a new line"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="steps">Steps * (one per line)</label>
                    <textarea id="steps" name="steps" rows="5" required placeholder="Enter each step on a new line"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cook_time">Cook Time *</label>
                        <input type="text" id="cook_time" name="cook_time" placeholder="e.g., 30 mins" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="servings">Servings *</label>
                        <input type="number" id="servings" name="servings" min="1" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="difficulty">Difficulty *</label>
                        <select id="difficulty" name="difficulty" required>
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="Breakfast">Breakfast</option>
                            <option value="Lunch">Lunch</option>
                            <option value="Dinner">Dinner</option>
                            <option value="Dessert">Dessert</option>
                            <option value="Snack">Snack</option>
                            <option value="Beverage">Beverage</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Recipe Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <small>Optional: JPG, PNG, or GIF files only (max 5MB)</small>
                </div>
                
                <button type="submit" class="btn-primary">Submit Recipe</button>
            </form>
        </section>
    </main>

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
</body>
</html>