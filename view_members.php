<?php
include 'config.php';

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = sanitize_input($_GET['search']);
}

// Handle status filter
$status_filter = '';
if (isset($_GET['status'])) {
    $status_filter = sanitize_input($_GET['status']);
}

// Build query
$query = "SELECT m.*, p.plan_name, p.duration_months, ms.start_date, ms.end_date, ms.status, ms.amount_paid
          FROM members m
          LEFT JOIN member_subscriptions ms ON m.id = ms.member_id
          LEFT JOIN plans p ON ms.plan_id = p.id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (m.name LIKE '%$search%' OR m.phone LIKE '%$search%' OR m.email LIKE '%$search%')";
}

if (!empty($status_filter)) {
    if ($status_filter == 'active') {
        $query .= " AND ms.status = 'Active' AND ms.end_date >= CURDATE()";
    } elseif ($status_filter == 'expired') {
        $query .= " AND (ms.status = 'Expired' OR ms.end_date < CURDATE())";
    } elseif ($status_filter == 'expiring') {
        $query .= " AND ms.status = 'Active' AND ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
}

$query .= " ORDER BY m.join_date DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Members - GYM Management</title>
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
            max-width: 1400px;
            margin: 20px auto;
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
        
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
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
        
        .search-btn {
            background-color: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            height: fit-content;
        }
        
        .search-btn:hover {
            background-color: #229954;
        }
        
        .clear-btn {
            background-color: #95a5a6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            height: fit-content;
            text-decoration: none;
        }
        
        .clear-btn:hover {
            background-color: #7f8c8d;
        }
        
        .members-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .member-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        
        .btn-edit {
            background-color: #3498db;
            color: white;
        }
        
        .btn-renew {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .member-count {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
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
            .search-form {
                flex-direction: column;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .members-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 900px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👥 View All Members</h1>
        <p>Manage and view all gym members</p>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">Search Members</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Search by name, phone, or email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Filter by Status</label>
                    <select id="status" name="status">
                        <option value="">All Members</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="expiring" <?php echo $status_filter == 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                    </select>
                </div>
                
                <button type="submit" class="search-btn">Search</button>
                <a href="view_members.php" class="clear-btn">Clear</a>
            </form>
        </div>
        
        <div class="member-count">
            <strong>Total Members Found: <?php echo $result->num_rows; ?></strong>
        </div>
        
        <div class="members-table">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Join Date</th>
                            <th>Current Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($member = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo get_member_photo_url($member['photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                         class="member-photo">
                                </td>
                                <td><?php echo $member['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                    <?php if($member['gender']): ?>
                                        <br><small><?php echo $member['gender']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo format_date($member['join_date']); ?></td>
                                <td>
                                    <?php if($member['plan_name']): ?>
                                        <?php echo htmlspecialchars($member['plan_name']); ?>
                                        <br><small>₹<?php echo $member['amount_paid']; ?></small>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d;">No Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $member['start_date'] ? format_date($member['start_date']) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $member['end_date'] ? format_date($member['end_date']) : '-'; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($member['end_date']) {
                                        if (is_expired($member['end_date'])) {
                                            echo '<span class="status-badge status-expired">Expired</span>';
                                        } elseif (is_expiring_soon($member['end_date'])) {
                                            echo '<span class="status-badge status-expiring">Expiring Soon</span>';
                                        } else {
                                            echo '<span class="status-badge status-active">Active</span>';
                                        }
                                    } else {
                                        echo '<span class="status-badge status-expired">No Plan</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-edit">Edit</a>
                                        <a href="renew_member.php?id=<?php echo $member['id']; ?>" class="btn btn-renew">Renew</a>
                                        <a href="delete_member.php?id=<?php echo $member['id']; ?>" 
                                           class="btn btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this member?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>No members found</h3>
                    <p>Try adjusting your search criteria or add new members.</p>
                    <a href="add_member.php" class="back-btn" style="margin-top: 15px;">Add New Member</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="footer">
        <p>Designed with <span class="heart">❤️</span> by <strong>Utkarsh Gaur</strong></p>
    </footer>
</body>
</html>