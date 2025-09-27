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

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_subject = trim($_POST['subject']);
    $reply_message = trim($_POST['message']);
    
    // In a real application, you would send the email here
    // For this example, we'll just log it and update the status
    
    // Update mail status to 'replied'
    $update_stmt = $conn->prepare("UPDATE support_mails SET status = 'replied' WHERE id = :id");
    $update_stmt->bindParam(':id', $mail_id);
    $update_stmt->execute();
    
    // Log activity
    $username = $_SESSION['admin_username'];
    $activity_msg = "$username replied to support mail from " . $mail['name'];
    $log_stmt = $conn->prepare("INSERT INTO admin_activities (admin_user, activity) VALUES (?, ?)");
    $log_stmt->execute([$_SESSION['admin_username'], $activity_msg]);
    
    header("Location: admin-dashboard.php?tab=mails&success=Reply sent successfully");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply to Mail - Cookpad Admin</title>
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-color);
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
            min-height: 200px;
            resize: vertical;
        }
        
        .submit-btn {
            background: var(--primary-color);
            color: var(--white-color);
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .submit-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .original-message {
            background: var(--light-pink-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .original-message h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reply to Support Mail</h1>
            <a href="mail_details.php?id=<?php echo $mail_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Message
            </a>
        </div>
        
        <div class="original-message">
            <h3>Original Message from <?php echo htmlspecialchars($mail['name']); ?></h3>
            <p><strong>Subject:</strong> <?php echo htmlspecialchars($mail['subject']); ?></p>
            <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($mail['message'])); ?></p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="to_email" class="form-label">To:</label>
                <input type="email" id="to_email" class="form-control" value="<?php echo htmlspecialchars($mail['email']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="subject" class="form-label">Subject:</label>
                <input type="text" id="subject" name="subject" class="form-control" 
                       value="Re: <?php echo htmlspecialchars($mail['subject']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="message" class="form-label">Your Reply:</label>
                <textarea id="message" name="message" class="form-control" required placeholder="Type your reply here..."></textarea>
            </div>
            
            <button type="submit" name="send_reply" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Send Reply
            </button>
            <a href="mail_details.php?id=<?php echo $mail_id; ?>" class="back-btn" style="background: #6c757d; margin-left: 10px;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </form>
    </div>
</body>
</html>