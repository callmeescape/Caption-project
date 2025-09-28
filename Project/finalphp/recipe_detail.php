<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$recipe_id = (int)$_GET['id'];

// Get recipe details with user information
$stmt = $conn->prepare("SELECT r.*, u.username, u.email FROM recipes r JOIN users u ON r.user_id = u.id WHERE r.id = :id");
$stmt->bindParam(':id', $recipe_id);
$stmt->execute();
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    header("Location: admin-dashboard.php");
    exit();
}

// Convert ingredients and steps from text to arrays
$ingredients = explode("\n", $recipe['ingredients']);
$steps = explode("\n", $recipe['steps']);

// Log activity
$username = $_SESSION['admin_username'];
$activity_msg = "$username viewed recipe: " . $recipe['title'];
$log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
$log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Details - Cookpad Admin</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--white-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary-color);
        }
        
        .back-btn {
            background: var(--primary-color);
            color: var(--white-color);
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
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
        
        .recipe-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            background: var(--light-pink-color);
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        
        .meta-label {
            font-weight: 600;
            color: var(--primary-color);
            display: block;
        }
        
        .recipe-content {
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
        
        .recipe-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .approve-btn {
            background: #28a745;
            color: var(--white-color);
        }
        
        .reject-btn {
            background: #dc3545;
            color: var(--white-color);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 15px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-published {
            background: #cce7ff;
            color: #004085;
        }
        
        @media (max-width: 768px) {
            .recipe-content {
                grid-template-columns: 1fr;
            }
            
            .recipe-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recipe Details 
                <span class="status-badge status-<?php echo $recipe['status']; ?>">
                    <?php echo ucfirst($recipe['status']); ?>
                </span>
            </h1>
            <a href="admin-dashboard.php?tab=recipes" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Recipes
            </a>
        </div>
        
        <div class="recipe-image">
            <img src="<?php echo $recipe['image_path'] ? htmlspecialchars($recipe['image_path']) : 'img/placeholder.png'; ?>" 
                 alt="<?php echo htmlspecialchars($recipe['title']); ?>">
        </div>
        
        <div class="recipe-meta">
            <div class="meta-item">
                <span class="meta-label">Uploaded by:</span>
                <span><?php echo htmlspecialchars($recipe['username']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Cook Time:</span>
                <span><?php echo htmlspecialchars($recipe['cook_time']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Servings:</span>
                <span><?php echo htmlspecialchars($recipe['servings']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Difficulty:</span>
                <span><?php echo htmlspecialchars($recipe['difficulty']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Category:</span>
                <span><?php echo htmlspecialchars($recipe['category']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Date:</span>
                <span><?php echo date('F j, Y', strtotime($recipe['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="recipe-description">
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
        </div>
        
        <div class="recipe-content">
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
        
        <div class="recipe-actions">
            <?php if ($recipe['status'] == 'pending'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                    <input type="hidden" name="status" value="published">
                    <button type="submit" name="update_recipe_status" class="action-btn approve-btn">
                        <i class="fas fa-check"></i> Approve & Publish
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($recipe['status'] == 'published'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                    <input type="hidden" name="status" value="pending">
                    <button type="submit" name="update_recipe_status" class="action-btn reject-btn">
                        <i class="fas fa-times"></i> Unpublish
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
