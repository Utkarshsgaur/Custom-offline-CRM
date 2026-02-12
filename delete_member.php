<?php
include 'config.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Member ID is required.";
    header("Location: view_members.php");
    exit();
}

$member_id = (int)$_GET['id'];

// First, get member information (including photo) before deletion
$member_query = "SELECT name, photo FROM members WHERE id = ?";
$stmt = $conn->prepare($member_query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_result = $stmt->get_result();

if ($member_result->num_rows == 0) {
    $_SESSION['error'] = "Member not found.";
    header("Location: view_members.php");
    exit();
}

$member = $member_result->fetch_assoc();
$member_name = $member['name'];
$member_photo = $member['photo'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
        
        // Start transaction
        $conn->autocommit(FALSE);
        
        try {
            // Delete member (subscriptions will be deleted automatically due to CASCADE)
            $delete_query = "DELETE FROM members WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $member_id);
            
            if ($delete_stmt->execute()) {
                // Delete member photo if exists
                if ($member_photo) {
                    delete_member_photo($member_photo);
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Member '" . htmlspecialchars($member_name) . "' has been successfully deleted.";
                header("Location: view_members.php");
                exit();
                
            } else {
                throw new Exception("Failed to delete member.");
            }
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
            header("Location: view_members.php");
            exit();
        }
        
        // Reset autocommit
        $conn->autocommit(TRUE);
        
    } else {
        // User cancelled deletion
        $_SESSION['info'] = "Member deletion cancelled.";
        header("Location: view_members.php");
        exit();
    }
}

// Get member's subscription count for display
$subscription_query = "SELECT COUNT(*) as subscription_count FROM member_subscriptions WHERE member_id = ?";
$sub_stmt = $conn->prepare($subscription_query);
$sub_stmt->bind_param("i", $member_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();
$subscription_count = $sub_result->fetch_assoc()['subscription_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Member - GYM Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background-color: #e74c3c;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
        }
        
        .back-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background-color: #2980b9;
        }
        
        .delete-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .warning-icon {
            font-size: 60px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .member-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #e74c3c;
        }
        
        .member-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
            margin-bottom: 10px;
        }
        
        .warning-text {
            color: #e74c3c;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .info-text {
            color: #666;
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .footer {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: auto;
        }
        
        .footer p {
            margin: 0;
            font-size: 14px;
        }
        
        .footer .heart {
            color: #e74c3c;
            font-size: 16px;
            animation: heartbeat 1.5s ease-in-out infinite;
        }
        
        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Delete Member</h1>
        <p>Confirm member deletion</p>
    </div>
    
    <div class="container">
        <a href="view_members.php" class="back-btn">← Back to Members</a>
        
        <div class="delete-card">
            <div class="warning-icon">⚠️</div>
            
            <h2>Are you sure you want to delete this member?</h2>
            
            <div class="member-info">
                <img src="<?php echo get_member_photo_url($member_photo); ?>" 
                     alt="<?php echo htmlspecialchars($member_name); ?>" 
                     class="member-photo">
                <h3><?php echo htmlspecialchars($member_name); ?></h3>
                <p><strong>Member ID:</strong> <?php echo $member_id; ?></p>
                <?php if ($subscription_count > 0): ?>
                    <p><strong>Active Subscriptions:</strong> <?php echo $subscription_count; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="warning-text">
                ⚠️ This action cannot be undone!
            </div>
            
            <div class="info-text">
                Deleting this member will permanently remove:
                <br>• Member profile and personal information
                <br>• All subscription history and records
                <br>• Member photo (if uploaded)
                <br>• All associated data from the system
            </div>
            
            <form method="POST">
                <div class="button-group">
                    <button type="submit" name="confirm_delete" value="yes" class="btn btn-danger">
                        🗑️ Yes, Delete Member
                    </button>
                    <a href="view_members.php" class="btn btn-secondary">
                        ❌ Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <footer class="footer">
        <p>Designed with <span class="heart">❤️</span> by <strong>Utkarsh Gaur</strong></p>
    </footer>
</body>
</html>