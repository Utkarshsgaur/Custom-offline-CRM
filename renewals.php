<?php
include 'config.php';

$success_message = '';
$error_message = '';

// Handle renewal/upgrade
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $member_id = sanitize_input($_POST['member_id']);
    $plan_id = sanitize_input($_POST['plan_id']);
    $start_date = sanitize_input($_POST['start_date']);
    $amount_paid = sanitize_input($_POST['amount_paid']);
    $action_type = sanitize_input($_POST['action_type']);
    
    if (empty($member_id) || empty($plan_id) || empty($start_date) || empty($amount_paid)) {
        $error_message = "All fields are required!";
    } else {
        // Get plan details
        $plan_query = "SELECT * FROM plans WHERE id = $plan_id";
        $plan_result = $conn->query($plan_query);
        $plan = $plan_result->fetch_assoc();
        
        // Calculate end date
        $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['duration_months'] . ' months'));
        
        // Mark previous subscription as expired if it exists
        $update_old = "UPDATE member_subscriptions SET status = 'Expired' WHERE member_id = $member_id AND status = 'Active'";
        $conn->query($update_old);
        
        // Insert new subscription
        $subscription_sql = "INSERT INTO member_subscriptions (member_id, plan_id, start_date, end_date, amount_paid, payment_date, status) 
                            VALUES ($member_id, $plan_id, '$start_date', '$end_date', $amount_paid, '$start_date', 'Active')";
        
        if ($conn->query($subscription_sql) === TRUE) {
            $action_text = ($action_type == 'upgrade') ? 'upgraded' : 'renewed';
            $success_message = "Membership $action_text successfully! New expiry date: " . format_date($end_date);
        } else {
            $error_message = "Error processing renewal: " . $conn->error;
        }
    }
}

// Get members who need renewal (expired or expiring soon)
$renewal_query = "SELECT m.id, m.name, m.phone, m.email, p.plan_name, ms.end_date, ms.amount_paid,
                  DATEDIFF(ms.end_date, CURDATE()) as days_remaining
                  FROM members m
                  LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
                  LEFT JOIN plans p ON ms.plan_id = p.id
                  WHERE ms.status = 'Active' AND ms.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY ms.end_date ASC";
$renewal_result = $conn->query($renewal_query);

// Get all members for manual renewal
$all_members_query = "SELECT m.id, m.name, m.phone, p.plan_name, ms.end_date, ms.status
                      FROM members m
                      LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
                      LEFT JOIN plans p ON ms.plan_id = p.id
                      ORDER BY m.name";
$all_members_result = $conn->query($all_members_query);

// Get all plans
$plans_query = "SELECT * FROM plans ORDER BY duration_months";
$plans_result = $conn->query($plans_query);

// Get selected member details if member_id is provided
$selected_member = null;
if (isset($_GET['member_id'])) {
    $member_id = sanitize_input($_GET['member_id']);
    $member_query = "SELECT m.*, p.plan_name, p.price as current_price, ms.end_date, ms.plan_id as current_plan_id
                     FROM members m
                     LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
                     LEFT JOIN plans p ON ms.plan_id = p.id
                     WHERE m.id = $member_id AND ms.status = 'Active'";
    $member_result = $conn->query($member_query);
    if ($member_result->num_rows > 0) {
        $selected_member = $member_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewals & Upgrades - GYM Management</title>
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
            max-width: 1400px;
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
        
        .section-tabs {
            display: flex;
            background: white;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            background: #ecf0f1;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            background: white;
            border-bottom-color: #3498db;
            color: #3498db;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .renewal-alert {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        .urgent-alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .renewal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .renewal-table th,
        .renewal-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .renewal-table th {
            background-color: #34495e;
            color: white;
        }
        
        .renewal-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-urgent {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .status-warning {
            color: #f39c12;
            font-weight: bold;
        }
        
        .form-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            text-align: center;
        }
        
        .btn-renew {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-upgrade {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-select {
            background-color: #3498db;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .member-selector {
            margin-bottom: 20px;
        }
        
        .member-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .current-plan {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔄 Renewals & Upgrades</h1>
        <p>Manage membership renewals and plan upgrades</p>
    </div>
    
    <div class="container">
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
        
        <?php if($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="section-tabs">
            <button class="tab-btn active" onclick="switchTab('pending')">Pending Renewals</button>
            <button class="tab-btn" onclick="switchTab('manual')">Manual Renewal/Upgrade</button>
        </div>
        
        <!-- Pending Renewals Tab -->
        <div id="pending" class="tab-content active">
            <?php if ($renewal_result->num_rows > 0): ?>
                <?php
                $urgent_count = 0;
                $warning_count = 0;
                $renewal_result->data_seek(0);
                while($row = $renewal_result->fetch_assoc()) {
                    if ($row['days_remaining'] < 0) $urgent_count++;
                    elseif ($row['days_remaining'] <= 7) $warning_count++;
                }
                $renewal_result->data_seek(0);
                ?>
                
                <?php if($urgent_count > 0): ?>
                    <div class="urgent-alert">
                        🚨 <strong><?php echo $urgent_count; ?> memberships have already expired!</strong>
                    </div>
                <?php endif; ?>
                
                <?php if($warning_count > 0): ?>
                    <div class="renewal-alert">
                        ⚠️ <strong><?php echo $warning_count; ?> memberships expire within 7 days!</strong>
                    </div>
                <?php endif; ?>
                
                <table class="renewal-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Phone</th>
                            <th>Current Plan</th>
                            <th>Expiry Date</th>
                            <th>Days Remaining</th>
                            <th>Last Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($member = $renewal_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                <td><?php echo format_date($member['end_date']); ?></td>
                                <td>
                                    <?php if($member['days_remaining'] < 0): ?>
                                        <span class="status-urgent">Expired <?php echo abs($member['days_remaining']); ?> days ago</span>
                                    <?php elseif($member['days_remaining'] <= 7): ?>
                                        <span class="status-warning"><?php echo $member['days_remaining']; ?> days left</span>
                                    <?php else: ?>
                                        <span><?php echo $member['days_remaining']; ?> days left</span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo $member['amount_paid']; ?></td>
                                <td>
                                    <a href="renewals.php?member_id=<?php echo $member['id']; ?>#manual" 
                                       class="btn btn-renew" onclick="switchTab('manual')">Renew/Upgrade</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>🎉 Great! No pending renewals</h3>
                    <p>All memberships are up to date.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Manual Renewal Tab -->
        <div id="manual" class="tab-content">
            <div class="member-selector">
                <h3>Select Member for Renewal/Upgrade</h3>
                <table class="renewal-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Current Plan</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($member = $all_members_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td><?php echo $member['plan_name'] ? htmlspecialchars($member['plan_name']) : 'No Plan'; ?></td>
                                <td>
                                    <?php
                                    if ($member['end_date']) {
                                        if (is_expired($member['end_date'])) {
                                            echo '<span class="status-urgent">Expired</span>';
                                        } elseif (is_expiring_soon($member['end_date'])) {
                                            echo '<span class="status-warning">Expiring Soon</span>';
                                        } else {
                                            echo '<span style="color: #27ae60;">Active</span>';
                                        }
                                    } else {
                                        echo '<span class="status-urgent">No Plan</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $member['end_date'] ? format_date($member['end_date']) : '-'; ?></td>
                                <td>
                                    <a href="renewals.php?member_id=<?php echo $member['id']; ?>#manual" 
                                       class="btn btn-select">Select</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($selected_member): ?>
                <div class="form-container">
                    <h3>Renew/Upgrade Membership</h3>
                    
                    <div class="member-info">
                        <h4><?php echo htmlspecialchars($selected_member['name']); ?></h4>
                        <p>Phone: <?php echo htmlspecialchars($selected_member['phone']); ?></p>
                        <p>Email: <?php echo htmlspecialchars($selected_member['email']); ?></p>
                    </div>
                    
                    <?php if($selected_member['plan_name']): ?>
                        <div class="current-plan">
                            <strong>Current Plan:</strong> <?php echo htmlspecialchars($selected_member['plan_name']); ?> 
                            (₹<?php echo $selected_member['current_price']; ?>) - 
                            Expires: <?php echo format_date($selected_member['end_date']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="member_id" value="<?php echo $selected_member['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="plan_id">Select New Plan *</label>
                                <select id="plan_id" name="plan_id" required onchange="updatePrice()">
                                    <option value="">Choose Plan</option>
                                    <?php 
                                    $plans_result->data_seek(0);
                                    while($plan = $plans_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $plan['id']; ?>" 
                                                data-price="<?php echo $plan['price']; ?>"
                                                <?php echo ($selected_member['current_plan_id'] == $plan['id']) ? 'selected' : ''; ?>>
                                            <?php echo $plan['plan_name']; ?> - ₹<?php echo $plan['price']; ?> (<?php echo $plan['duration_months']; ?> months)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount_paid">Amount Paid *</label>
                                <input type="number" id="amount_paid" name="amount_paid" 
                                       step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="action_type">Action Type</label>
                                <select id="action_type" name="action_type">
                                    <option value="renewal">Renewal</option>
                                    <option value="upgrade">Upgrade</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-renew" style="padding: 15px 30px; font-size: 16px;">
                            Process Renewal/Upgrade
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab and activate button
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function updatePrice() {
            const select = document.getElementById('plan_id');
            const amountInput = document.getElementById('amount_paid');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const price = option.getAttribute('data-price');
                amountInput.value = price;
            } else {
                amountInput.value = '';
            }
        }
        
        // Auto-update price when page loads if plan is selected
        document.addEventListener('DOMContentLoaded', function() {
            updatePrice();
        });
    </script>
</body>
</html>