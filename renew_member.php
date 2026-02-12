<?php
include 'config.php';

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_members.php");
    exit();
}

$member_id = sanitize_input($_GET['id']);

// Handle form submission
if ($_POST) {
    $plan_id = sanitize_input($_POST['plan_id']);
    $amount_paid = sanitize_input($_POST['amount_paid']);
    $payment_date = sanitize_input($_POST['payment_date']);
    $start_date = sanitize_input($_POST['start_date']);
    
    $errors = array();
    
    // Validation
    if (empty($plan_id)) {
        $errors[] = "Please select a plan";
    }
    
    if (empty($amount_paid) || !is_numeric($amount_paid)) {
        $errors[] = "Please enter a valid amount paid";
    }
    
    if (empty($payment_date)) {
        $errors[] = "Please enter payment date";
    }
    
    if (empty($start_date)) {
        $errors[] = "Please enter start date";
    }
    
    if (empty($errors)) {
        // Get plan details to calculate end date
        $plan_query = "SELECT duration_months FROM plans WHERE id = '$plan_id'";
        $plan_result = $conn->query($plan_query);
        $plan = $plan_result->fetch_assoc();
        
        // Calculate end date
        $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_months'] . ' months'));
        
        // First, expire any active subscriptions for this member
        $expire_query = "UPDATE member_subscriptions SET status = 'Expired' WHERE member_id = '$member_id' AND status = 'Active'";
        $conn->query($expire_query);
        
        // Insert new subscription
        $insert_query = "INSERT INTO member_subscriptions (member_id, plan_id, start_date, end_date, amount_paid, payment_date, status) 
                        VALUES ('$member_id', '$plan_id', '$start_date', '$end_date', '$amount_paid', '$payment_date', 'Active')";
        
        if ($conn->query($insert_query)) {
            $success_message = "Member subscription renewed successfully!";
            // Optionally redirect after a few seconds
            header("refresh:3;url=view_members.php");
        } else {
            $errors[] = "Error renewing subscription: " . $conn->error;
        }
    }
}

// Fetch member data
$member_query = "SELECT m.*, p.plan_name, p.duration_months, ms.start_date, ms.end_date, ms.status 
                FROM members m
                LEFT JOIN member_subscriptions ms ON m.id = ms.member_id AND ms.status = 'Active'
                LEFT JOIN plans p ON ms.plan_id = p.id
                WHERE m.id = '$member_id'";
$member_result = $conn->query($member_query);

if ($member_result->num_rows == 0) {
    header("Location: view_members.php");
    exit();
}

$member = $member_result->fetch_assoc();

// Fetch all available plans
$plans_query = "SELECT * FROM plans ORDER BY duration_months ASC";
$plans_result = $conn->query($plans_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Member - GYM Management</title>
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
        }
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
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
        
        .member-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .current-plan {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .plan-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .plan-option:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }
        
        .plan-option.selected {
            border-color: #27ae60;
            background-color: #d4edda;
        }
        
        .plan-name {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .plan-details {
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .plan-price {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
            float: right;
        }
        
        .submit-btn {
            background-color: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .submit-btn:hover {
            background-color: #229954;
        }
        
        .cancel-btn {
            background-color: #95a5a6;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        
        .cancel-btn:hover {
            background-color: #7f8c8d;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-expiring {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .developer-footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 14px;
        }
        
        .developer-footer .heart {
            color: #ff6b6b;
            animation: heartbeat 1.5s ease-in-out infinite;
        }
        
        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .developer-name {
            font-weight: bold;
            color: #ffd700;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .plan-price {
                float: none;
                display: block;
                margin-top: 10px;
            }
        }
    </style>
    <script>
        function selectPlan(planId, price) {
            // Remove selected class from all plans
            document.querySelectorAll('.plan-option').forEach(function(el) {
                el.classList.remove('selected');
            });
            
            // Add selected class to clicked plan
            document.getElementById('plan_' + planId).classList.add('selected');
            
            // Set the hidden input value
            document.getElementById('plan_id').value = planId;
            
            // Set the amount paid to plan price
            document.getElementById('amount_paid').value = price;
        }
        
        function calculateEndDate() {
            const startDate = document.getElementById('start_date').value;
            const planSelect = document.getElementById('plan_id').value;
            
            if (startDate && planSelect) {
                // This would require AJAX to get plan duration, or we can show it dynamically
                // For now, we'll let the server handle the calculation
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>🔄 Renew Member Subscription</h1>
        <p>Renew or upgrade membership plan</p>
    </div>
    
    <div class="container">
        <a href="view_members.php" class="back-btn">← Back to Members</a>
        
        <div class="member-info">
            <h3>Member Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
            <p><strong>Join Date:</strong> <?php echo format_date($member['join_date']); ?></p>
            
            <?php if ($member['plan_name']): ?>
                <div class="current-plan">
                    <h4>Current Plan</h4>
                    <p><strong>Plan:</strong> <?php echo htmlspecialchars($member['plan_name']); ?></p>
                    <p><strong>Duration:</strong> <?php echo $member['duration_months']; ?> month(s)</p>
                    <p><strong>Start Date:</strong> <?php echo format_date($member['start_date']); ?></p>
                    <p><strong>End Date:</strong> <?php echo format_date($member['end_date']); ?></p>
                    <p><strong>Status:</strong> 
                        <?php
                        if (is_expired($member['end_date'])) {
                            echo '<span class="status-badge status-expired">Expired</span>';
                        } elseif (is_expiring_soon($member['end_date'])) {
                            echo '<span class="status-badge status-expiring">Expiring Soon</span>';
                        } else {
                            echo '<span class="status-badge status-active">Active</span>';
                        }
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="current-plan">
                    <p><strong>Current Status:</strong> <span class="status-badge status-expired">No Active Plan</span></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-container">
            <h3>Renew Subscription</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="success">
                    <?php echo $success_message; ?>
                    <br><small>Redirecting to members list in 3 seconds...</small>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" id="plan_id" name="plan_id" value="">
                
                <div class="form-group full-width">
                    <label>Select New Plan</label>
                    <?php while($plan = $plans_result->fetch_assoc()): ?>
                        <div class="plan-option" id="plan_<?php echo $plan['id']; ?>" 
                             onclick="selectPlan(<?php echo $plan['id']; ?>, <?php echo $plan['price']; ?>)">
                            <div class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></div>
                            <div class="plan-details">
                                Duration: <?php echo $plan['duration_months']; ?> month(s) | 
                                <?php echo htmlspecialchars($plan['description']); ?>
                            </div>
                            <div class="plan-price">₹<?php echo number_format($plan['price'], 2); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="amount_paid">Amount Paid *</label>
                        <input type="number" id="amount_paid" name="amount_paid" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="date" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Subscription Start Date *</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo date('Y-m-d'); ?>" 
                               onchange="calculateEndDate()" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="submit-btn">Renew Subscription</button>
                    <a href="view_members.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
        
        <div class="developer-footer">
            💻 Developed with <span class="heart">❤️</span> by <span class="developer-name">Utkarsh Gaur</span> 🚀
        </div>
    </div>
</body>
</html>