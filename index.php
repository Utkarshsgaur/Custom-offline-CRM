<?php
include 'config.php';

// Get dashboard statistics
$current_month = date('Y-m');

// Total members
$total_members_query = "SELECT COUNT(*) as total FROM members";
$total_members_result = $conn->query($total_members_query);
$total_members = $total_members_result->fetch_assoc()['total'];

// New members this month
$new_members_query = "SELECT COUNT(*) as new_members FROM members WHERE DATE_FORMAT(join_date, '%Y-%m') = '$current_month'";
$new_members_result = $conn->query($new_members_query);
$new_members = $new_members_result->fetch_assoc()['new_members'];

// Active memberships
$active_memberships_query = "SELECT COUNT(*) as active FROM member_subscriptions WHERE status = 'Active' AND end_date >= CURDATE()";
$active_memberships_result = $conn->query($active_memberships_query);
$active_memberships = $active_memberships_result->fetch_assoc()['active'];

// Expiring soon (within 7 days)
$expiring_soon_query = "SELECT COUNT(*) as expiring FROM member_subscriptions WHERE status = 'Active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$expiring_soon_result = $conn->query($expiring_soon_query);
$expiring_soon = $expiring_soon_result->fetch_assoc()['expiring'];

// Expired memberships
$expired_query = "SELECT COUNT(*) as expired FROM member_subscriptions WHERE status = 'Active' AND end_date < CURDATE()";
$expired_result = $conn->query($expired_query);
$expired = $expired_result->fetch_assoc()['expired'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYM Management System</title>
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
            background-color: #2c3e50;
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }
        
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            justify-content: center;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            flex: 1;
            min-width: 180px;
            max-width: 220px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-label {
            color: #7f8c8d;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .nav-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .nav-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
        }
        
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .nav-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .alert {
            background-color: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .warning {
            background-color: #f39c12;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                flex-direction: column;
            }
            
            .stat-card {
                max-width: none;
                min-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏋️ GYM Management System</h1>
        <p>Welcome to your gym management dashboard</p>
    </div>
    
    <div class="container">
        <?php if($expired > 0): ?>
        <div class="alert">
            ⚠️ Alert: <?php echo $expired; ?> memberships have expired and need renewal!
        </div>
        <?php endif; ?>
        
        <?php if($expiring_soon > 0): ?>
        <div class="warning">
            📅 Warning: <?php echo $expiring_soon; ?> memberships are expiring within 7 days!
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_members; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $new_members; ?></div>
                <div class="stat-label">New This Month</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_memberships; ?></div>
                <div class="stat-label">Active Memberships</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $expiring_soon; ?></div>
                <div class="stat-label">Expiring Soon</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $expired; ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="add_member.php" class="nav-card">
                <div class="nav-icon">👤</div>
                <h3>Add New Member</h3>
                <p>Register new gym members</p>
            </a>
            
            <a href="view_members.php" class="nav-card">
                <div class="nav-icon">👥</div>
                <h3>View All Members</h3>
                <p>See all registered members</p>
            </a>
            
            <a href="manage_plans.php" class="nav-card">
                <div class="nav-icon">📋</div>
                <h3>Manage Plans</h3>
                <p>Add and edit membership plans</p>
            </a>
            
            <a href="renewals.php" class="nav-card">
                <div class="nav-icon">🔄</div>
                <h3>Renewals & Upgrades</h3>
                <p>Handle plan renewals and upgrades</p>
            </a>
            
            <a href="reports.php" class="nav-card">
                <div class="nav-icon">📊</div>
                <h3>Reports</h3>
                <p>View monthly and yearly reports</p>
            </a>
        </div>
    </div>
    
    <footer class="footer">
        <p>Designed with <span class="heart">❤️</span> by <strong>Utkarsh Gaur</strong></p>
    </footer>
</body>
</html>