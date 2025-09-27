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

$mail_id = (int)$_GET['id'];

// Get mail details
$stmt = $conn->prepare("SELECT * FROM support_mails WHERE id = :id");
$stmt->bindParam(':id', $mail_id);
$stmt->execute();
$mail = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mail) {
    header("Location: admin-dashboard.php");
    exit();
}

// Update status to 'read' if it's unread
if ($mail['status'] == 'unread') {
    $update_stmt = $conn->prepare("UPDATE support_mails SET status = 'read' WHERE id = :id");
    $update_stmt->bindParam(':id', $mail_id);
    $update_stmt->execute();
}

// Log activity
$username = $_SESSION['admin_username'];
$activity_msg = "$username viewed support mail from " . $mail['name'];
$log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
$log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Details - Cookpad Admin</title>
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
            max-width: 800px;
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
        
        .mail-details {
            margin-bottom: 30px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--light-pink-color);
            border-radius: 6px;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: var(--primary-color);
        }
        
        .message-content {
            background: var(--light-pink-color);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            line-height: 1.8;
        }
        
        .mail-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
        
        .reply-btn {
            background: var(--secondary-color);
            color: var(--white-color);
        }
        
        .delete-btn {
            background: #dc3545;
            color: var(--white-color);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Support Mail Details</h1>
            <a href="admin-dashboard.php?tab=mails" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="mail-details">
            <div class="detail-row">
                <span class="detail-label">From:</span>
                <span><?php echo htmlspecialchars($mail['name']); ?> (<?php echo htmlspecialchars($mail['email']); ?>)</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                <span><?php echo htmlspecialchars($mail['subject']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span><?php echo date('F j, Y g:i A', strtotime($mail['created_at'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span style="color: <?php echo $mail['status'] == 'unread' ? '#dc3545' : '#28a745'; ?>">
                    <?php echo ucfirst($mail['status']); ?>
                </span>
            </div>
            
            <div class="message-content">
                <h3>Message:</h3>
                <p><?php echo nl2br(htmlspecialchars($mail['message'])); ?></p>
            </div>
        </div>
        
        <div class="mail-actions">
            <a href="reply_mail.php?id=<?php echo $mail['id']; ?>" class="action-btn reply-btn">
                <i class="fas fa-reply"></i> Reply
            </a>
            <a href="admin-dashboard.php?delete_mail=<?php echo $mail['id']; ?>" class="action-btn delete-btn" 
               onclick="return confirm('Are you sure you want to delete this message?')">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>
</body>
</html>