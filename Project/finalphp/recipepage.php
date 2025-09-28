<?php
session_start();
require_once 'config/database.php';

// Get all recipes from database
$recipes = [];
$categories = [];

try {
    $stmt = $conn->prepare("
        SELECT r.*, u.username 
        FROM recipes r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'published'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for search suggestions
    $stmt = $conn->prepare("SELECT DISTINCT category FROM recipes WHERE category IS NOT NULL");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // If database error, use default recipes
    $recipes = [];
    $categories = ["English Breakfast", "Nepali Dish", "Hot Beverage", "Dessert", "Burger and Fries"];
}

// Bookmark functionality - Check if user has bookmarked recipes
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // For each recipe, check if it's bookmarked by the user
    foreach ($recipes as &$recipe) {
        try {
            $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = :user_id AND recipe_id = :recipe_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':recipe_id', $recipe['id']);
            $stmt->execute();
            $recipe['is_bookmarked'] = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $recipe['is_bookmarked'] = false;
        }
    }
    unset($recipe); // Unset reference
    
    // Handle bookmark toggle
    if (isset($_GET['bookmark']) && isset($_GET['action'])) {
        $recipe_id = $_GET['bookmark'];
        $action = $_GET['action']; // 'add' or 'remove'
        
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
        } catch (Exception $e) {
            // Error handling if needed
            error_log("Bookmark error: " . $e->getMessage());
        }
        
        // Redirect to avoid form resubmission
        header("Location: recipepage.php");
        exit();
    }
}

// Always include default recipes along with database recipes
$defaultRecipes = [
    [
         'id' => 1,
            'title' => "Full English Breakfast",
            'cook_time' => "30 mins",
            'servings' => 2,
            'difficulty' => "Medium",
            'category' => "English Breakfast",
            'description' => "A hearty traditional English breakfast with eggs, bacon, sausages, and more.",
            'image_url' => "images/english-breakfast.jpg",
            'ingredients' => json_encode([
                "4 eggs",
                "4 slices bacon",
                "4 pork sausages",
                "1 can baked beans",
                "2 tomatoes, halved",
                "4 slices bread",
                "Butter for frying",
                "Salt and pepper to taste"
            ]),
            'instructions' => json_encode([
                "Heat a large frying pan over medium heat",
                "Add bacon and sausages, cook until browned",
                "Add tomato halves, cut side down",
                "In a separate pan, heat baked beans",
                "Fry eggs to your preference",
                "Toast the bread and butter it",
                "Arrange all components on plates and serve"
            ]),
            'username' => 'Chef John'
        ],
        [
            'id' => 2,
            'title' => "Momo (Nepalese Dumplings)",
            'cook_time' => "45 mins",
            'servings' => 4,
            'difficulty' => "Medium",
            'category' => "Nepali Dish",
            'description' => "Delicious steamed dumplings filled with spiced vegetables or meat.",
            'image_url' => "images/momo.jpg",
            'ingredients' => json_encode([
                "2 cups all-purpose flour",
                "1/2 cup water",
                "1 lb ground chicken or vegetables",
                "1 onion, finely chopped",
                "2 cloves garlic, minced",
                "1 tbsp ginger, grated",
                "1 tsp cumin powder",
                "1 tsp coriander powder",
                "Salt and pepper to taste"
            ]),
            'instructions' => json_encode([
                "Prepare dough by mixing flour and water, knead until smooth",
                "For filling, mix all ingredients thoroughly",
                "Roll dough into small circles",
                "Place filling in center and fold into half-moon shape",
                "Steam for 10-12 minutes until cooked",
                "Serve with tomato chutney or sesame sauce"
            ]),
            'username' => 'Nepali Chef'
        ],
        [
            'id' => 3,
            'title' => "Classic Hot Chocolate",
            'cook_time' => "10 mins",
            'servings' => 2,
            'difficulty' => "Easy",
            'category' => "Hot Beverage",
            'description' => "Rich and creamy hot chocolate topped with marshmallows.",
            'image_url' => "images/Classic-Hot-Chocolate.jpg",
            'ingredients' => json_encode([
                "2 cups milk",
                "2 tbsp cocoa powder",
                "2 tbsp sugar",
                "1/4 cup chocolate chips",
                "1/4 tsp vanilla extract",
                "Pinch of salt",
                "Marshmallows for topping"
            ]),
            'instructions' => json_encode([
                "Heat milk in a saucepan over medium heat until warm",
                "Whisk in cocoa powder, sugar, and salt until dissolved",
                "Add chocolate chips and stir until melted",
                "Remove from heat and stir in vanilla extract",
                "Pour into mugs and top with marshmallows"
            ]),
            'username' => 'Beverage Master'
        ],
        [
            'id' => 4,
            'title' => "Chocolate Lava Cake",
            'cook_time' => "20 mins",
            'servings' => 4,
            'difficulty' => "Medium",
            'category' => "Dessert",
            'description' => "Decadent chocolate cake with a molten center.",
            'image_url' => "images/Chocolate-Cake.jpg",
            'ingredients' => json_encode([
                "1/2 cup butter",
                "4 oz dark chocolate, chopped",
                "2 eggs",
                "2 egg yolks",
                "1/4 cup sugar",
                "Pinch of salt",
                "2 tbsp all-purpose flour",
                "Butter and cocoa powder for ramekins",
                "Vanilla ice cream for serving"
            ]),
            'instructions' => json_encode([
                "Preheat oven to 425°F (220°C)",
                "Butter 4 ramekins and dust with cocoa powder",
                "Melt butter and chocolate in a double boiler, then cool slightly",
                "Whisk eggs, egg yolks, sugar, and salt until pale and thick",
                "Fold chocolate mixture into egg mixture",
                "Gently fold in flour",
                "Divide batter among ramekins",
                "Bake for 12-14 minutes until edges are set but center is soft",
                "Let rest for 1 minute, then invert onto plates",
                "Serve immediately with ice cream"
            ]),
            'username' => 'Dessert Expert'
        ],
        [
            'id' => 5,
            'title' => "Veggie Burger Deluxe",
            'cook_time' => "25 mins",
            'servings' => 4,
            'difficulty' => "Medium",
            'category' => "Burger and Fries",
            'description' => "Hearty vegetable burger with all the fixings.",
            'image_url' => "images/Veggie-Burger.jpg",
            'ingredients' => json_encode([
                "4 veggie burger patties",
                "4 burger buns",
                "4 slices cheese (ceddar or Swiss)",
                "1 tomato, sliced",
                "1 onion, sliced",
                "Lettuce leaves",
                "Pickles",
                "Ketchup and mustard",
                "1 tbsp vegetable oil"
            ]),
            'instructions' => json_encode([
                "Heat oil in a skillet over medium heat",
                "Cook veggie patties according to package instructions (usually 4-5 minutes per side)",
                "Add cheese slices during the last minute of cooking",
                "Toast burger buns lightly",
                "Assemble burgers with desired toppings and condiments"
            ]),
            'username' => 'Burger King'
        ]
    ];


// Combine database recipes with default recipes
$allRecipes = array_merge($recipes, $defaultRecipes);

// Remove duplicates based on title (in case a default recipe was also uploaded)
$uniqueRecipes = [];
$seenTitles = [];
foreach ($allRecipes as $recipe) {
    if (!in_array($recipe['title'], $seenTitles)) {
        $uniqueRecipes[] = $recipe;
        $seenTitles[] = $recipe['title'];
    }
}

$recipes = $uniqueRecipes;

// Handle search functionality
$searchTerm = '';
$filteredRecipes = null;

if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    if (!empty($searchTerm)) {
        $filteredRecipes = array_filter($recipes, function($recipe) use ($searchTerm) {
            return stripos($recipe['title'], $searchTerm) !== false ||
                   stripos($recipe['description'], $searchTerm) !== false ||
                   stripos($recipe['category'], $searchTerm) !== false;
        });
        $filteredRecipes = array_values($filteredRecipes); // Reindex array
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookpad - Recipe Sharing Platform</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
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

        /* Top Section Styles */
        .top-section {
            background: linear-gradient(rgba(61, 10, 55, 0.9), rgba(61, 10, 55, 0.9)), url('https://images.unsplash.com/photo-1495195134817-aeb325a55b65?ixlib=rb-4.0.3') center/cover no-repeat;
            color: var(--white-color);
            text-align: center;
            padding: 80px 0;
            margin-bottom: 40px;
        }

        .top-section h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }

        /* Search Bar Styles */
        .search-container {
            margin: 40px 0;
            text-align: center;
            position: relative;
        }

        .search-box {
            width: 100%;
            max-width: 500px;
            padding: 16px 25px;
            border: 2px solid var(--primary-color);
            border-radius: 30px;
            font-size: 16px;
            box-shadow: 0 5px 20px rgba(61, 10, 55, 0.2);
            outline: none;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            box-shadow: 0 5px 20px rgba(61, 10, 55, 0.4);
        }

        /* Search suggestions dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-suggestions ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .search-suggestions li {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .search-suggestions li:hover {
            background-color: #f5f5f5;
        }

        .search-suggestions li:last-child {
            border-bottom: none;
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
        transition: all 0.3s ease;
        }

.bookmark-icon.active {
    color: var(--secondary-color);
}

.bookmark-icon:hover {
    transform: scale(1.1);
}

        /* No results message */
        .no-results {
            text-align: center;
            padding: 30px;
            color: var(--dark-color);
            display: none;
        }

        /* Section Titles */
        .section-title {
            text-align: center;
            margin: 40px 0 30px;
            color: var(--primary-color);
            font-size: 32px;
            position: relative;
            padding-bottom: 15px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--secondary-color);
            border-radius: 2px;
        }

        /* Recipe Slider Styles */
        .recipe-slider {
            position: relative;
            padding: 20px 0 60px;
            overflow: hidden;
        }

        .recipe-slider .swiper {
            padding: 30px 10px 70px;
        }

        .recipe-slider .swiper-slide {
            background: var(--white-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: auto;
        }

        .recipe-slider .swiper-slide:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(61, 10, 55, 0.15);
        }

        /* IMPROVED RECIPE IMAGE STYLING */
        .recipe-slider .recipe-image {
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

        .recipe-slider .recipe-image .image-placeholder {
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

        .recipe-slider .swiper-slide:hover .recipe-image .image-placeholder {
            opacity: 1;
        }

        .recipe-slider .recipe-info {
            padding: 20px;
        }

        .recipe-slider .recipe-title {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .recipe-slider .recipe-meta {
            display: flex;
            justify-content: space-between;
            color: var(--dark-color);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .recipe-slider .recipe-description {
            font-size: 14px;
            color: var(--dark-color);
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recipe-slider .view-recipe-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: var(--primary-color);
            color: var(--white-color);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
        }

        .recipe-slider .view-recipe-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        /* Always show navigation arrows */
        .recipe-slider .swiper-button-next,
        .recipe-slider .swiper-button-prev {
            color: var(--primary-color);
            background: var(--white-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex !important;
        }

        .recipe-slider .swiper-button-next:after,
        .recipe-slider .swiper-button-prev:after {
            font-size: 18px;
            font-weight: bold;
        }

        .recipe-slider .swiper-button-next:hover,
        .recipe-slider .swiper-button-prev:hover {
            background: var(--secondary-color);
            color: var(--white-color);
        }

        .recipe-slider .swiper-pagination-bullet {
            background: var(--medium-gray-color);
            opacity: 1;
            width: 12px;
            height: 12px;
        }

        .recipe-slider .swiper-pagination-bullet-active {
            background: var(--primary-color);
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
            край: 12px;
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
            .detail-content {
                grid-template-columns: 1fr;
            }

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

            .top-section h1 {
                font-size: 36px;
            }

            .recipe-slider .swiper-button-next,
            .recipe-slider .swiper-button-prev {
                width: 35px;
                height: 35px;
            }

            .recipe-slider .swiper-button-next:after,
            .recipe-slider .swiper-button-prev:after {
                font-size: 16px;
            }

            .search-suggestions {
                width: 90%;
                left: 5%;
                transform: none;
            }
        }

        @media (max-width: 480px) {
            .top-section h1 {
                font-size: 28px;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .recipe-slider .recipe-title {
                font-size: 16px;
            }
            
            .detail-title {
                font-size: 28px;
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="user_dashboard.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                    <?php endif; ?>
                </ul>
            </nav>
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

        <!-- Top Section -->
    <section class="top-section">
        <div class="container">
            <h1>Discover & Share Delicious Recipes</h1>
            <p>Find your next favorite meal from our collection of recipes from around the world</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" class="search-box" placeholder="Search for recipes..." id="searchInput" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <div class="search-suggestions" id="searchSuggestions">
                <ul id="suggestionsList">
                    <?php foreach ($categories as $category): ?>
                    <li onclick="selectSuggestion('<?php echo $category; ?>')"><?php echo $category; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- No results message -->
        <div class="no-results" id="noResults">
            <h3>No recipes found</h3>
            <p>Try searching for something else or browse all recipes</p>
        </div>

        <!-- Featured Recipes Slider -->
        <h2 class="section-title">Featured Recipes</h2>
        <div class="recipe-slider">
            <div class="slider-wrapper swiper">
                <div class="recipes-list swiper-wrapper">
                    <!-- Recipe slides -->
                    <?php 
                    $recipesToShow = isset($filteredRecipes) ? $filteredRecipes : $recipes;
                    foreach ($recipesToShow as $recipe): 
                        $ingredients = isset($recipe['ingredients']) ? json_decode($recipe['ingredients'], true) : [];
                        $steps = isset($recipe['instructions']) ? json_decode($recipe['instructions'], true) : [];
                    ?>
                    <div class="swiper-slide">
                        <div class="recipe-image" style="background-image: url('<?php echo htmlspecialchars($recipe['image_url'] ?? 'https://images.unsplash.com/photo-1495195134817-aeb325a55b65?ixlib=rb-4.0.3'); ?>');">
                            <div class="image-placeholder"><?php echo htmlspecialchars($recipe['title']); ?></div>
                        </div>
                        <!-- Bookmark button - only show if user is logged in -->
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                        <div class="bookmark-icon <?php echo isset($recipe['is_bookmarked']) && $recipe['is_bookmarked'] ? 'active' : ''; ?>" 
                             onclick="toggleBookmark(<?php echo $recipe['id']; ?>, '<?php echo isset($recipe['is_bookmarked']) && $recipe['is_bookmarked'] ? 'true' : 'false'; ?>')">
                            <i class="fas fa-bookmark"></i>
                        </div>
                        <?php endif; ?>

                        <div class="recipe-info">
                            <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            <div class="recipe-meta">
                                <span><?php echo htmlspecialchars($recipe['cook_time']); ?></span>
                                <span><?php echo htmlspecialchars($recipe['servings']); ?> servings</span>
                                <span><?php echo htmlspecialchars(ucfirst($recipe['difficulty'])); ?></span>
                            </div>
                            <p class="recipe-description"><?php echo htmlspecialchars($recipe['description']); ?></p>
                            <p class="recipe-author"><small>By: <?php echo htmlspecialchars($recipe['username'] ?? 'System'); ?></small></p>
                            <button class="view-recipe-btn" 
                                data-title="<?php echo htmlspecialchars($recipe['title']); ?>"
                                data-cooktime="<?php echo htmlspecialchars($recipe['cook_time']); ?>"
                                data-servings="<?php echo htmlspecialchars($recipe['servings']); ?>"
                                data-difficulty="<?php echo htmlspecialchars($recipe['difficulty']); ?>"
                                data-ingredients='<?php echo json_encode($ingredients); ?>'
                                data-steps='<?php echo json_encode($steps); ?>'>
                                View Recipe
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
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

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize Recipe Swiper
        const recipeSwiper = new Swiper('.recipe-slider .swiper', {
            loop: true,
            spaceBetween: 25,
            slidesPerView: 3,
            pagination: {
                el: '.recipe-slider .swiper-pagination',
                clickable: true,
                dynamicBullets: true,
            },
            navigation: {
                nextEl: '.recipe-slider .swiper-button-next',
                prevEl: '.recipe-slider .swiper-button-prev',
            },
            breakpoints: {
                0: { slidesPerView: 1 },
                768: { slidesPerView: 2 },
                1024: { slidesPerView: 3 }
            }
        });

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.getElementById('navMenu').classList.toggle('show');
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const suggestionsList = document.getElementById('suggestionsList');
        const noResults = document.getElementById('noResults');
        const recipeSlides = document.querySelectorAll('.swiper-slide');

        // Show search suggestions
        function showSuggestions() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            if (searchTerm.length === 0) {
                searchSuggestions.style.display = 'none';
                filterRecipes('');
                return;
            }

            // Filter recipes that match the search term
            const matchedRecipes = <?php echo json_encode($recipes); ?>.filter(recipe =>
                recipe.title.toLowerCase().includes(searchTerm) ||
                recipe.description.toLowerCase().includes(searchTerm) ||
                recipe.category.toLowerCase().includes(searchTerm)
            );

            // Display suggestions
            if (matchedRecipes.length > 0) {
                suggestionsList.innerHTML = '';
                matchedRecipes.forEach(recipe => {
                    const li = document.createElement('li');
                    li.textContent = recipe.title;
                    li.addEventListener('click', () => {
                        searchInput.value = recipe.title;
                        searchSuggestions.style.display = 'none';
                        filterRecipes(recipe.title);
                    });
                    suggestionsList.appendChild(li);
                });
                searchSuggestions.style.display = 'block';
            } else {
                searchSuggestions.style.display = 'none';
            }

            filterRecipes(searchTerm);
        }

        // Filter recipes based on search term
        function filterRecipes(searchTerm) {
            if (searchTerm.trim() === '') {
                recipeSlides.forEach(slide => slide.style.display = '');
                noResults.style.display = 'none';
                recipeSwiper.update();
                return;
            }

            let hasResults = false;
            recipeSlides.forEach(slide => {
                const title = slide.querySelector('.recipe-title').textContent.toLowerCase();
                const description = slide.querySelector('.recipe-description').textContent.toLowerCase();
                
                if (title.includes(searchTerm.toLowerCase()) || description.includes(searchTerm.toLowerCase())) {
                    slide.style.display = '';
                    hasResults = true;
                } else {
                    slide.style.display = 'none';
                }
            });

            noResults.style.display = hasResults ? 'none' : 'block';
            recipeSwiper.update();
        }

        // Select suggestion
        function selectSuggestion(category) {
            searchInput.value = category;
            searchSuggestions.style.display = 'none';
            filterRecipes(category);
        }

        // Toggle bookmark function
        function toggleBookmark(recipeId, isCurrentlyBookmarked) {
            <?php if (isset($_SESSION['user_id'])): ?>
            // Convert string to boolean
            const isBookmarked = (isCurrentlyBookmarked === 'true');
            const action = isBookmarked ? 'remove' : 'add';
            window.location.href = `recipepage.php?bookmark=${recipeId}&action=${action}`;
            <?php else: ?>
            alert('Please log in to bookmark recipes');
            window.location.href = 'login.php';
            <?php endif; ?>
        }

        // Event listeners for search
        searchInput.addEventListener('input', showSuggestions);
        searchInput.addEventListener('focus', showSuggestions);

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });

        // Show recipe detail
        function showRecipeDetail(button) {
            const title = button.getAttribute('data-title');
            const cookTime = button.getAttribute('data-cooktime');
            const servings = button.getAttribute('data-servings');
            const difficulty = button.getAttribute('data-difficulty');
            const ingredients = JSON.parse(button.getAttribute('data-ingredients'));
            const steps = JSON.parse(button.getAttribute('data-steps'));

            const detailSection = document.getElementById('recipeDetail');
            const titleElement = document.getElementById('detailTitle');
            const metaElement = document.getElementById('detailMeta');
            const ingredientsElement = document.getElementById('detailIngredients');
            const stepsElement = document.getElementById('detailSteps');

            titleElement.textContent = title;
            metaElement.innerHTML = `
                <span>${cookTime}</span>
                <span>${servings} servings</span>
                <span>${difficulty}</span>
            `;

            // Clear previous content
            ingredientsElement.innerHTML = '';
            stepsElement.innerHTML = '';

            // Add ingredients
            ingredients.forEach(ingredient => {
                const li = document.createElement('li');
                li.textContent = ingredient;
                ingredientsElement.appendChild(li);
            });

            // Add steps
            steps.forEach(step => {
                const li = document.createElement('li');
                li.textContent = step;
                stepsElement.appendChild(li);
            });

            // Show detail section
            detailSection.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Close detail view
        document.getElementById('closeDetail').addEventListener('click', () => {
            document.getElementById('recipeDetail').style.display = 'none';
            document.body.style.overflow = 'auto';
        });

        // Add event listeners to view recipe buttons
        document.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('view-recipe-btn')) {
                showRecipeDetail(e.target);
            }
        });

        // Hide no results message initially
        noResults.style.display = 'none';
    </script>
</body>
</html>