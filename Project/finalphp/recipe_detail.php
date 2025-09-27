<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: recipepage.php");
    exit();
}

$recipe_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT r.*, u.username FROM recipes r JOIN users u ON r.user_id = u.id WHERE r.id = :id");
$stmt->bindParam(':id', $recipe_id);
$stmt->execute();
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    header("Location: recipepage.php");
    exit();
}

// Convert ingredients and steps from text to arrays
$ingredients = explode("\n", $recipe['ingredients']);
$steps = explode("\n", $recipe['steps']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - Cookpad</title>
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

        /* Recipe Detail Styles */
        .recipe-detail-section {
            padding: 40px 0;
        }

        .recipe-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .recipe-header h1 {
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .recipe-meta {
            color: var(--dark-color);
            font-size: 16px;
            margin-bottom: 20px;
        }

        .recipe-content {
            background: var(--white-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .recipe-image {
            text-align: center;
            margin-bottom: 30px;
        }

        .recipe-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .recipe-description {
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.8;
        }

        .recipe-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .ingredients, .steps {
            background: var(--light-pink-color);
            padding: 25px;
            border-radius: 8px;
        }

        .ingredients h3, .steps h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 24px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }

        .ingredients ul, .steps ol {
            padding-left: 20px;
        }

        .ingredients li, .steps li {
            margin-bottom: 10px;
            line-height: 1.6;
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
            .recipe-details {
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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="user_dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="recipe-detail-section">
            <div class="recipe-header">
                <h1><?php echo htmlspecialchars($recipe['title']); ?></h1>
                <p class="recipe-meta">
                    By <?php echo htmlspecialchars($recipe['username']); ?> | 
                    <?php echo $recipe['cook_time']; ?> | 
                    <?php echo $recipe['servings']; ?> servings | 
                    Difficulty: <?php echo $recipe['difficulty']; ?>
                </p>
            </div>
            
            <div class="recipe-content">
                <div class="recipe-image">
                    <img src="<?php echo $recipe['image_path'] ?: 'img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                </div>
                
                <div class="recipe-description">
                    <p><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
                </div>
                
                <div class="recipe-details">
                    <div class="ingredients">
                        <h3>Ingredients</h3>
                        <ul>
                            <?php foreach($ingredients as $ingredient): ?>
                                <?php if(trim($ingredient)): ?>
                                    <li><?php echo htmlspecialchars(trim($ingredient)); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="steps">
                        <h3>Steps</h3>
                        <ol>
                            <?php foreach($steps as $step): ?>
                                <?php if(trim($step)): ?>
                                    <li><?php echo htmlspecialchars(trim($step)); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
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
