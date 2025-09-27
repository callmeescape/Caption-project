<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
// Check if user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Database connection
require_once 'config/database.php';

// Get statistics for dashboard
$total_recipes = $conn->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_recipes = $conn->query("SELECT COUNT(*) FROM recipes WHERE status = 'pending'")->fetchColumn();

try {
    $support_mails = $conn->query("SELECT COUNT(*) FROM support_mails")->fetchColumn();
} catch (Exception $e) {
    $support_mails = 0; // Set to 0 if table doesn't exist
}

// ... rest of your code continues

// Get recent activities
$activities_query = "
    SELECT 'recipe' as type, r.title, u.username, r.created_at, r.status 
    FROM recipes r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'user' as type, 'New registration' as title, username, created_at, 'active' as status 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 5
";
$activities_stmt = $conn->query($activities_query);
$activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipes for management
$recipes_query = "
    SELECT r.*, u.username 
    FROM recipes r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
";
$recipes_stmt = $conn->query($recipes_query);
$recipes = $recipes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for management
$users_query = "
    SELECT u.*, COUNT(r.id) as recipe_count 
    FROM users u 
    LEFT JOIN recipes r ON u.id = r.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
";
$users_stmt = $conn->query($users_query);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get support mails
$mails_query = "SELECT * FROM support_mails ORDER BY created_at DESC";
$mails_stmt = $conn->query($mails_query);
$mails = $mails_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_recipe_status'])) {
        $recipe_id = $_POST['recipe_id'];
        $status = $_POST['status'];
        
        $update_stmt = $conn->prepare("UPDATE recipes SET status = ? WHERE id = ?");
        $update_stmt->execute([$status, $recipe_id]);
        
        // Add activity log
        $recipe_title = $conn->query("SELECT title FROM recipes WHERE id = $recipe_id")->fetchColumn();
        $username = $_SESSION['admin_username'];
        $activity_msg = "$username updated recipe '$recipe_title' to $status";
        $log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
        $log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
        
        header("Location: admin-dashboard.php?success=Recipe status updated");
        exit;
    }
    
    if (isset($_POST['update_user_status'])) {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        
        $update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $update_stmt->execute([$status, $user_id]);
        
        // Add activity log
        $user_username = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetchColumn();
        $username = $_SESSION['admin_username'];
        $activity_msg = "$username updated user '$user_username' to $status";
        $log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
        $log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
        
        header("Location: admin-dashboard.php?success=User status updated");
        exit;
    }
    
    if (isset($_POST['update_settings'])) {
        $site_name = $_POST['site_name'];
        $admin_email = $_POST['admin_email'];
        $support_email = $_POST['support_email'];
        
        // Update settings in database
        $update_stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'site_name'");
        $update_stmt->execute([$site_name]);
        
        $update_stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'admin_email'");
        $update_stmt->execute([$admin_email]);
        
        $update_stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'support_email'");
        $update_stmt->execute([$support_email]);
        
        header("Location: admin-dashboard.php?success=Settings updated");
        exit;
    }
    
    if (isset($_POST['send_email'])) {
        $user_email = $_POST['user_email'];
        $email_subject = $_POST['email_subject'];
        $email_message = $_POST['email_message'];
        
        // In a real application, you would send the email here
        // For this example, we'll just log it
        
        $username = $_SESSION['admin_username'];
        $activity_msg = "$username sent email to $user_email with subject: $email_subject";
        $log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
        $log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
        
        header("Location: admin-dashboard.php?success=Email sent");
        exit;
    }
}

// Get settings
$settings_query = "SELECT * FROM settings";
$settings_stmt = $conn->query($settings_query);
$settings = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert settings to associative array
$settings_array = [];
foreach ($settings as $setting) {
    $settings_array[$setting['name']] = $setting['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookpad - Admin Dashboard</title>
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
            display: flex;
        }
        
        .sidebar {
            width: 250px;
            background: var(--primary-color);
            color: var(--white-color);
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .logo {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo h2 {
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-icon {
            margin-right: 10px;
            font-size: 22px;
            color: var(--secondary-color);
        }
        
        .menu-items {
            margin-top: 30px;
        }
        
        .menu-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            color: var(--white-color);
            text-decoration: none;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--secondary-color);
            transform: translateX(5px);
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--secondary-color);
        }
        
        .menu-item i {
            margin-right: 15px;
            font-size: 18px;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--white-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title {
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .home-btn {
            padding: 8px 15px;
            background: var(--secondary-color);
            color: var(--white-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .home-btn:hover {
            background: #e58a19;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .user-info img:hover {
            transform: scale(1.1);
        }
        
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
            background-color: var(--primary-color);
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--white-color);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(61, 10, 55, 0.15);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            transition: all 0.3s ease;
        }
        
        .card:hover .card-icon {
            transform: scale(1.1);
        }
        
        .recipes .card-icon {
            background: rgba(61, 10, 55, 0.1);
            color: var(--primary-color);
        }
        
        .users .card-icon {
            background: rgba(243, 150, 28, 0.1);
            color: var(--secondary-color);
        }
        
        .pending .card-icon {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .mails .card-icon {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }
        
        .card h3 {
            font-size: 32px;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .card p {
            color: var(--dark-color);
            font-size: 14px;
        }
        
        .content-box {
            background: var(--white-color);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .content-box:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .content-box h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }
        
        table th {
            background-color: rgba(61, 10, 55, 0.05);
            color: var(--primary-color);
        }
        
        table tr {
            transition: all 0.3s ease;
        }
        
        table tr:hover {
            background-color: rgba(243, 150, 28, 0.05);
            transform: translateY(-2px);
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .published {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .draft {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .suspended {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .approve {
            background: var(--secondary-color);
            color: var(--white-color);
        }
        
        .approve:hover {
            background: #e58a19;
        }
        
        .reject {
            background: #dc3545;
            color: var(--white-color);
        }
        
        .reject:hover {
            background: #c82333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(243, 150, 28, 0.2);
            outline: none;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: var(--white-color);
        }
        
        .btn-primary:hover {
            background: #2d0729;
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: var(--white-color);
        }
        
        .btn-secondary:hover {
            background: #e58a19;
        }
        
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(243, 150, 28, 0.2);
            outline: none;
        }
        
        .search-btn {
            padding: 10px 20px;
            background: var(--secondary-color);
            color: var(--white-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: #e58a19;
            transform: translateY(-2px);
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: rgba(243, 150, 28, 0.05);
        }
        
        .activity-item i {
            font-size: 18px;
            min-width: 24px;
        }
        
        .activity-item p {
            flex: 1;
            margin: 0;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 12px;
        }
        
        .page-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-content.active {
            display: block;
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
            display: none;
        }
        
        .user-search-results {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            display: none;
        }
        
        .user-result-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .user-result-card h4 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .user-result-card p {
            margin: 5px 0;
            color: #666;
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: none;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 992px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-left {
                width: 100%;
                justify-content: space-between;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .home-btn {
                width: 100%;
                justify-content: center;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .activity-time {
                align-self: flex-end;
            }
            
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
            }
            
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
            }
            
            td:before {
                position: absolute;
                top: 12px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
            }
            
            td:nth-of-type(1):before { content: "Username"; }
            td:nth-of-type(2):before { content: "Email"; }
            td:nth-of-type(3):before { content: "Recipes"; }
            td:nth-of-type(4):before { content: "Status"; }
            td:nth-of-type(5):before { content: "Actions"; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h2><i class="fas fa-utensils logo-icon"></i> <span>Cookpad</span></h2>
        </div>
        <div class="menu-items">
            <div class="menu-item active" data-page="dashboard">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </div>
            <div class="menu-item" data-page="recipes">
                <i class="fas fa-book"></i> <span>Manage Recipes</span>
            </div>
            <div class="menu-item" data-page="users">
                <i class="fas fa-users"></i> <span>Manage Users</span>
            </div>
            <div class="menu-item" data-page="mails">
                <i class="fas fa-envelope"></i> <span>Support Mails</span>
            </div>
            <div class="menu-item" data-page="settings">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </div>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <a href="../index.php" class="home-btn" target="_blank">
                    <i class="fas fa-home"></i> Home
                </a>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['admin_username']; ?></span>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" id="errorAlert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Page -->
        <div class="page-content active" id="dashboard">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search activities..." id="dashboardSearch">
                <button class="search-btn" onclick="searchActivities()">Search</button>
            </div>
            
            <div class="dashboard-cards">
                <div class="card recipes">
                    <div class="card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3><?php echo $total_recipes; ?></h3>
                    <p>Total Recipes</p>
                </div>
                <div class="card users">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo $total_users; ?></h3>
                    <p>Registered Users</p>
                </div>
                <div class="card pending">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3><?php echo $pending_recipes; ?></h3>
                    <p>Pending Approvals</p>
                </div>
                <div class="card mails">
                    <div class="card-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3><?php echo $support_mails; ?></h3>
                    <p>Support Mails</p>
                </div>
            </div>

            <div class="content-box">
                <h3>Recent Activities</h3>
                <div class="activity-list" id="activitiesList">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item" data-user="<?php echo htmlspecialchars($activity['username'] ?? 'System'); ?>">
                            <?php if ($activity['type'] == 'recipe'): ?>
                                <i class="fas fa-plus-circle" style="color: #28a745;"></i>
                                <p>
                                    <?php echo htmlspecialchars($activity['username']); ?> uploaded a new recipe "<?php echo htmlspecialchars($activity['title']); ?>" - 
                                    <span class="status <?php echo $activity['status']; ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </p>
                            <?php else: ?>
                                <i class="fas fa-user-plus" style="color: var(--secondary-color);"></i>
                                <p>New user registered: <?php echo htmlspecialchars($activity['username']); ?></p>
                            <?php endif; ?>
                            <span class="activity-time">
                                <?php 
                                $date = new DateTime($activity['created_at']);
                                echo $date->format('M j, Y'); 
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="no-results" id="dashboardNoResults">No activities found.</div>
            </div>
        </div>

        <!-- Manage Recipes Page -->
        <div class="page-content" id="recipes">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search recipes..." id="recipesSearch">
                <button class="search-btn" onclick="searchRecipes()">Search</button>
            </div>
            
            <div class="content-box">
                <h3>Manage Recipes</h3>
                <table id="recipesTable">
                    <thead>
                        <tr>
                            <th>Recipe Name</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                            <tr data-user="<?php echo htmlspecialchars($recipe['username']); ?>" data-recipe="<?php echo htmlspecialchars($recipe['title']); ?>">
                                <td><?php echo htmlspecialchars($recipe['title']); ?></td>
                                <td><?php echo htmlspecialchars($recipe['username']); ?></td>
                                <td>
                                    <?php 
                                    $date = new DateTime($recipe['created_at']);
                                    echo $date->format('M j, Y'); 
                                    ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $recipe['status']; ?>">
                                        <?php echo ucfirst($recipe['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                        <?php if ($recipe['status'] == 'pending'): ?>
                                            <input type="hidden" name="status" value="published">
                                            <button type="submit" name="update_recipe_status" class="action-btn approve">Approve</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($recipe['status'] == 'published'): ?>
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" name="update_recipe_status" class="action-btn reject">Unpublish</button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="recipe_details.php?id=<?php echo $recipe['id']; ?>" class="action-btn approve">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="no-results" id="recipesNoResults">No recipes found.</div>
            </div>
        </div>

        <!-- Manage Users Page -->
        <div class="page-content" id="users">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search users..." id="usersSearch">
                <button class="search-btn" onclick="searchUsers()">Search</button>
            </div>
            
            <div class="content-box">
                <h3>Manage Users</h3>
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Recipes Uploaded</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr data-user="<?php echo htmlspecialchars($user['username']); ?>">
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['recipe_count']; ?></td>
                                <td>
                                    <span class="status <?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if ($user['status'] == 'active'): ?>
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" name="update_user_status" class="action-btn reject">Suspend</button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" name="update_user_status" class="action-btn approve">Activate</button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="action-btn approve">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="no-results" id="usersNoResults">No users found.</div>
            </div>
        </div>

        <!-- Support Mails Page -->
        <div class="page-content" id="mails">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search mails..." id="mailsSearch">
                <button class="search-btn" onclick="searchMails()">Search</button>
            </div>
            
            <div class="content-box">
                <h3>Support Mails (<?php echo $settings_array['support_email'] ?? 'support@cookpad.com'; ?>)</h3>
                <table id="mailsTable">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mails as $mail): ?>
                            <tr data-user="<?php echo htmlspecialchars($mail['name']); ?>">
                                <td><?php echo htmlspecialchars($mail['name']); ?></td>
                                <td><?php echo htmlspecialchars($mail['subject']); ?></td>
                                <td>
                                    <?php 
                                    $date = new DateTime($mail['created_at']);
                                    echo $date->format('M j, Y'); 
                                    ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $mail['status']; ?>">
                                        <?php echo ucfirst($mail['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="mail_details.php?id=<?php echo $mail['id']; ?>" class="action-btn approve">View</a>
                                    <a href="reply_mail.php?id=<?php echo $mail['id']; ?>" class="action-btn approve">Reply</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="no-results" id="mailsNoResults">No support mails found.</div>
            </div>
        </div>

        <!-- Settings Page -->
        <div class="page-content" id="settings">
            <div class="content-box">
                <h3>Website Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="siteName">Website Name</label>
                        <input type="text" id="siteName" name="siteName" class="form-control" value="<?php echo htmlspecialchars($settings_array['site_name'] ?? 'Cookpad'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="adminEmail">Admin Email</label>
                        <input type="email" id="adminEmail" name="adminEmail" class="form-control" value="<?php echo htmlspecialchars($settings_array['admin_email'] ?? 'admin@cookpad.com'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="supportEmail">Support Email</label>
                        <input type="email" id="supportEmail" name="supportEmail" class="form-control" value="<?php echo htmlspecialchars($settings_array['support_email'] ?? 'support@cookpad.com'); ?>">
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                </form>
            </div>

            <div class="content-box">
                <h3>Send Email to User</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="userEmail">User Email</label>
                        <input type="email" id="userEmail" name="userEmail" class="form-control" placeholder="Enter user email" required>
                    </div>
                    <div class="form-group">
                        <label for="emailSubject">Subject</label>
                        <input type="text" id="emailSubject" name="emailSubject" class="form-control" placeholder="Email subject" required>
                    </div>
                    <div class="form-group">
                        <label for="emailMessage">Message</label>
                        <textarea id="emailMessage" name="emailMessage" class="form-control" rows="5" placeholder="Your message" required></textarea>
                    </div>
                    <button type="submit" name="send_email" class="btn btn-primary">Send Email</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Navigation functionality
        document.querySelectorAll('.menu-item').forEach(item => {
            if (!item.getAttribute('href')) {
                item.addEventListener('click', function() {
                    // Remove active class from all menu items
                    document.querySelectorAll('.menu-item').forEach(i => {
                        i.classList.remove('active');
                    });
                    
                    // Add active class to clicked menu item
                    this.classList.add('active');
                    
                    // Hide all page content
                    document.querySelectorAll('.page-content').forEach(page => {
                        page.classList.remove('active');
                    });
                    
                    // Show the selected page
                    const pageId = this.getAttribute('data-page');
                    document.getElementById(pageId).classList.add('active');
                    
                    // Update page title
                    document.querySelector('.page-title').textContent = this.querySelector('span').textContent;
                    
                    // Close sidebar on mobile after selection
                    if (window.innerWidth <= 768) {
                        document.getElementById('sidebar').classList.remove('active');
                    }
                });
            }
        });

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Search functionality for activities
        function searchActivities() {
            const searchTerm = document.getElementById('dashboardSearch').value.toLowerCase().trim();
            const activities = document.querySelectorAll('#activitiesList .activity-item');
            const noResults = document.getElementById('dashboardNoResults');
            
            let hasResults = false;
            
            activities.forEach(activity => {
                const userName = activity.getAttribute('data-user').toLowerCase();
                const activityText = activity.textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || activityText.includes(searchTerm) || searchTerm === '') {
                    activity.style.display = 'flex';
                    hasResults = true;
                } else {
                    activity.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (!hasResults && searchTerm !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Search functionality for recipes
        function searchRecipes() {
            const searchTerm = document.getElementById('recipesSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#recipesTable tbody tr');
            const noResults = document.getElementById('recipesNoResults');
            
            let hasResults = false;
            
            rows.forEach(row => {
                const userName = row.getAttribute('data-user').toLowerCase();
                const recipeName = row.getAttribute('data-recipe').toLowerCase();
                const rowText = row.textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || recipeName.includes(searchTerm) || rowText.includes(searchTerm) || searchTerm === '') {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (!hasResults && searchTerm !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Search functionality for users
        function searchUsers() {
            const searchTerm = document.getElementById('usersSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            const noResults = document.getElementById('usersNoResults');
            
            let hasResults = false;
            
            rows.forEach(row => {
                const userName = row.getAttribute('data-user').toLowerCase();
                const rowText = row.textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || rowText.includes(searchTerm) || searchTerm === '') {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (!hasResults && searchTerm !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Search functionality for mails
        function searchMails() {
            const searchTerm = document.getElementById('mailsSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#mailsTable tbody tr');
            const noResults = document.getElementById('mailsNoResults');
            
            let hasResults = false;
            
            rows.forEach(row => {
                const userName = row.getAttribute('data-user').toLowerCase();
                const rowText = row.textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || rowText.includes(searchTerm) || searchTerm === '') {
                    row.style.display = '';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (!hasResults && searchTerm !== '') {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // Hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Simulate data loading
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin dashboard loaded');
        });
    </script>
</body>
</html>