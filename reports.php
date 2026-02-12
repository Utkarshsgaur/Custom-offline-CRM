<?php
include 'config.php';

// Get current year and month for default filters
$current_year = date('Y');
$current_month = date('Y-m');

// Handle filter parameters
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;

// Revenue Reports
// Monthly Revenue for selected year
$monthly_revenue_query = "
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount_paid) as revenue,
        COUNT(*) as transactions
    FROM member_subscriptions 
    WHERE YEAR(payment_date) = $selected_year
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month
";
$monthly_revenue_result = $conn->query($monthly_revenue_query);

// Total Revenue by Plan
$plan_revenue_query = "
    SELECT 
        p.plan_name,
        SUM(ms.amount_paid) as total_revenue,
        COUNT(*) as subscriptions_sold
    FROM member_subscriptions ms
    INNER JOIN plans p ON ms.plan_id = p.id
    WHERE YEAR(ms.payment_date) = $selected_year
    GROUP BY p.id, p.plan_name
    ORDER BY total_revenue DESC
";
$plan_revenue_result = $conn->query($plan_revenue_query);

// Member Statistics
// Monthly member registrations for selected year
$monthly_members_query = "
    SELECT 
        DATE_FORMAT(join_date, '%Y-%m') as month,
        COUNT(*) as new_members
    FROM members 
    WHERE YEAR(join_date) = $selected_year
    GROUP BY DATE_FORMAT(join_date, '%Y-%m')
    ORDER BY month
";
$monthly_members_result = $conn->query($monthly_members_query);

// Gender Distribution
$gender_distribution_query = "
    SELECT 
        gender,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members)), 2) as percentage
    FROM members 
    GROUP BY gender
";
$gender_distribution_result = $conn->query($gender_distribution_query);

// Age Groups
$age_groups_query = "
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36-45'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 55 THEN '46-55'
            ELSE 'Above 55'
        END as age_group,
        COUNT(*) as count
    FROM members 
    WHERE date_of_birth IS NOT NULL
    GROUP BY age_group
    ORDER BY 
        CASE 
            WHEN age_group = 'Under 18' THEN 1
            WHEN age_group = '18-25' THEN 2
            WHEN age_group = '26-35' THEN 3
            WHEN age_group = '36-45' THEN 4
            WHEN age_group = '46-55' THEN 5
            ELSE 6
        END
";
$age_groups_result = $conn->query($age_groups_query);

// Current Status Overview
$status_overview_query = "
    SELECT 
        CASE 
            WHEN ms.end_date >= CURDATE() THEN 'Active'
            WHEN ms.end_date < CURDATE() THEN 'Expired'
        END as status,
        COUNT(*) as count
    FROM member_subscriptions ms
    WHERE ms.status = 'Active'
    GROUP BY status
";
$status_overview_result = $conn->query($status_overview_query);

// Top 10 Recent Members
$recent_members_query = "
    SELECT 
        m.name,
        m.join_date,
        p.plan_name,
        ms.amount_paid
    FROM members m
    LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
    LEFT JOIN plans p ON ms.plan_id = p.id
    ORDER BY m.join_date DESC
    LIMIT 10
";
$recent_members_result = $conn->query($recent_members_query);

// Get available years for filter
$years_query = "SELECT DISTINCT YEAR(join_date) as year FROM members ORDER BY year DESC";
$years_result = $conn->query($years_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GYM Management System</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-btn {
            background-color: #3498db;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            background-color: #2980b9;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .filter-btn:hover {
            background-color: #2980b9;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .report-title {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th,
        .table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .stat-highlight {
            background-color: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .summary-stat {
            text-align: center;
            padding: 15px;
            background-color: #ecf0f1;
            border-radius: 8px;
        }
        
        .summary-stat .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .summary-stat .label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Reports & Analytics</h1>
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="year">Select Year:</label>
                        <select name="year" id="year">
                            <?php 
                            $years_result->data_seek(0);
                            while($year_row = $years_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $year_row['year']; ?>" 
                                    <?php echo ($year_row['year'] == $selected_year) ? 'selected' : ''; ?>>
                                    <?php echo $year_row['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">Apply Filter</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="report-card">
            <h2 class="report-title">Year <?php echo $selected_year; ?> Summary</h2>
            <div class="summary-stats">
                <?php
                // Calculate summary stats for selected year
                $year_summary_query = "
                    SELECT 
                        COUNT(DISTINCT m.id) as total_members,
                        COUNT(DISTINCT ms.id) as total_subscriptions,
                        COALESCE(SUM(ms.amount_paid), 0) as total_revenue
                    FROM members m
                    LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
                    WHERE YEAR(COALESCE(ms.payment_date, m.join_date)) = $selected_year
                ";
                $year_summary_result = $conn->query($year_summary_query);
                $year_summary = $year_summary_result->fetch_assoc();
                ?>
                <div class="summary-stat">
                    <div class="number"><?php echo $year_summary['total_members']; ?></div>
                    <div class="label">Members Joined</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo $year_summary['total_subscriptions']; ?></div>
                    <div class="label">Subscriptions Sold</div>
                </div>
                <div class="summary-stat">
                    <div class="number">₹<?php echo number_format($year_summary['total_revenue'], 2); ?></div>
                    <div class="label">Total Revenue</div>
                </div>
            </div>
        </div>
        
        <div class="reports-grid">
            <!-- Monthly Revenue Report -->
            <div class="report-card">
                <h2 class="report-title">Monthly Revenue (<?php echo $selected_year; ?>)</h2>
                <?php if($monthly_revenue_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Revenue</th>
                                <th>Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $monthly_revenue_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                <td><span class="stat-highlight">₹<?php echo number_format($row['revenue'], 2); ?></span></td>
                                <td><?php echo $row['transactions']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No revenue data available for <?php echo $selected_year; ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Plan Revenue Report -->
            <div class="report-card">
                <h2 class="report-title">Revenue by Plan (<?php echo $selected_year; ?>)</h2>
                <?php if($plan_revenue_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Revenue</th>
                                <th>Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $plan_revenue_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                                <td><span class="stat-highlight">₹<?php echo number_format($row['total_revenue'], 2); ?></span></td>
                                <td><?php echo $row['subscriptions_sold']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No plan revenue data available for <?php echo $selected_year; ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Monthly Member Registrations -->
            <div class="report-card">
                <h2 class="report-title">Monthly Member Registrations (<?php echo $selected_year; ?>)</h2>
                <?php if($monthly_members_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>New Members</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $monthly_members_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                <td><span class="stat-highlight"><?php echo $row['new_members']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No member registration data available for <?php echo $selected_year; ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Gender Distribution -->
            <div class="report-card">
                <h2 class="report-title">Gender Distribution</h2>
                <?php if($gender_distribution_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Gender</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $gender_distribution_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['gender'] ?: 'Not Specified'); ?></td>
                                <td><span class="stat-highlight"><?php echo $row['count']; ?></span></td>
                                <td><?php echo $row['percentage']; ?>%</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No gender distribution data available</div>
                <?php endif; ?>
            </div>
            
            <!-- Age Groups -->
            <div class="report-card">
                <h2 class="report-title">Age Group Distribution</h2>
                <?php if($age_groups_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Age Group</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $age_groups_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['age_group']); ?></td>
                                <td><span class="stat-highlight"><?php echo $row['count']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No age group data available</div>
                <?php endif; ?>
            </div>
            
            <!-- Membership Status Overview -->
            <div class="report-card">
                <h2 class="report-title">Current Membership Status</h2>
                <?php if($status_overview_result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $status_overview_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><span class="stat-highlight"><?php echo $row['count']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No membership status data available</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Members -->
        <div class="report-card">
            <h2 class="report-title">10 Most Recent Members</h2>
            <?php if($recent_members_result->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Join Date</th>
                            <th>Plan</th>
                            <th>Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $recent_members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo format_date($row['join_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['plan_name'] ?: 'No Plan'); ?></td>
                            <td><?php echo $row['amount_paid'] ? '₹' . number_format($row['amount_paid'], 2) : 'N/A'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No recent members data available</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>