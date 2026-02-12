<?php
include 'config.php';

$success_message = '';
$error_message = '';

// Handle form submission for adding/editing plans
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $plan_name = sanitize_input($_POST['plan_name']);
            $duration_months = sanitize_input($_POST['duration_months']);
            $price = sanitize_input($_POST['price']);
            $description = sanitize_input($_POST['description']);
            
            if (empty($plan_name) || empty($duration_months) || empty($price)) {
                $error_message = "Plan name, duration, and price are required!";
            } else {
                $sql = "INSERT INTO plans (plan_name, duration_months, price, description) 
                        VALUES ('$plan_name', $duration_months, $price, '$description')";
                
                if ($conn->query($sql) === TRUE) {
                    $success_message = "Plan added successfully!";
                } else {
                    $error_message = "Error adding plan: " . $conn->error;
                }
            }
        } elseif ($action == 'edit') {
            $plan_id = sanitize_input($_POST['plan_id']);
            $plan_name = sanitize_input($_POST['plan_name']);
            $duration_months = sanitize_input($_POST['duration_months']);
            $price = sanitize_input($_POST['price']);
            $description = sanitize_input($_POST['description']);
            
            if (empty($plan_name) || empty($duration_months) || empty($price)) {
                $error_message = "Plan name, duration, and price are required!";
            } else {
                $sql = "UPDATE plans SET 
                        plan_name = '$plan_name',
                        duration_months = $duration_months,
                        price = $price,
                        description = '$description'
                        WHERE id = $plan_id";
                
                if ($conn->query($sql) === TRUE) {
                    $success_message = "Plan updated successfully!";
                } else {
                    $error_message = "Error updating plan: " . $conn->error;
                }
            }
        }
    }
}

// Handle plan deletion
if (isset($_GET['delete'])) {
    $plan_id = sanitize_input($_GET['delete']);
    
    // Check if plan is being used by any member
    $check_usage = "SELECT COUNT(*) as count FROM member_subscriptions WHERE plan_id = $plan_id";
    $usage_result = $conn->query($check_usage);
    $usage_count = $usage_result->fetch_assoc()['count'];
    
    if ($usage_count > 0) {
        $error_message = "Cannot delete plan! It is currently being used by $usage_count member(s).";
    } else {
        $delete_sql = "DELETE FROM plans WHERE id = $plan_id";
        if ($conn->query($delete_sql) === TRUE) {
            $success_message = "Plan deleted successfully!";
        } else {
            $error_message = "Error deleting plan: " . $conn->error;
        }
    }
}

// Fetch all plans
$plans_query = "SELECT p.*, 
                COUNT(ms.id) as active_members 
                FROM plans p 
                LEFT JOIN member_subscriptions ms ON p.id = ms.plan_id AND ms.status = 'Active' AND ms.end_date >= CURDATE()
                GROUP BY p.id 
                ORDER BY p.duration_months";
$plans_result = $conn->query($plans_query);

// Get plan for editing
$edit_plan = null;
if (isset($_GET['edit'])) {
    $edit_id = sanitize_input($_GET['edit']);
    $edit_query = "SELECT * FROM plans WHERE id = $edit_id";
    $edit_result = $conn->query($edit_query);
    $edit_plan = $edit_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Plans - GYM Management</title>
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
            max-width: 1200px;
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
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        textarea {
            height: 80px;
            resize: vertical;
        }
        
        .submit-btn {
            background-color: #27ae60;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .submit-btn:hover {
            background-color: #229954;
        }
        
        .cancel-btn {
            background-color: #95a5a6;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            margin-left: 10px;
        }
        
        .cancel-btn:hover {
            background-color: #7f8c8d;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .plan-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .plan-header {
            background-color: #3498db;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .plan-price {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .plan-duration {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .plan-body {
            padding: 20px;
        }
        
        .plan-description {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .plan-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .plan-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            flex: 1;
            text-align: center;
        }
        
        .btn-edit {
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
        
        .required {
            color: #e74c3c;
        }
        
        .no-plans {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Manage Membership Plans</h1>
        <p>Add, edit, and manage gym membership plans</p>
    </div>
    
    <div class="container">
        <a href="index.php" class="back-btn">← Back to Dashboard</a>
        
        <?php if($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">
                <?php echo $edit_plan ? 'Edit Plan' : 'Add New Plan'; ?>
            </h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_plan ? 'edit' : 'add'; ?>">
                <?php if($edit_plan): ?>
                    <input type="hidden" name="plan_id" value="<?php echo $edit_plan['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="plan_name">Plan Name <span class="required">*</span></label>
                        <input type="text" id="plan_name" name="plan_name" 
                               value="<?php echo $edit_plan ? htmlspecialchars($edit_plan['plan_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_months">Duration (Months) <span class="required">*</span></label>
                        <input type="number" id="duration_months" name="duration_months" min="1" 
                               value="<?php echo $edit_plan ? $edit_plan['duration_months'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (₹) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0" 
                               value="<?php echo $edit_plan ? $edit_plan['price'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Describe what's included in this plan..."><?php echo $edit_plan ? htmlspecialchars($edit_plan['description']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center;">
                    <button type="submit" class="submit-btn">
                        <?php echo $edit_plan ? 'Update Plan' : 'Add Plan'; ?>
                    </button>
                    <?php if($edit_plan): ?>
                        <a href="manage_plans.php" class="cancel-btn">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px; color: #2c3e50;">Current Plans</h2>
        
        <?php if ($plans_result->num_rows > 0): ?>
            <div class="plans-grid">
                <?php while($plan = $plans_result->fetch_assoc()): ?>
                    <div class="plan-card">
                        <div class="plan-header">
                            <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <div class="plan-price">₹<?php echo number_format($plan['price'], 2); ?></div>
                            <div class="plan-duration"><?php echo $plan['duration_months']; ?> Month(s)</div>
                        </div>
                        <div class="plan-body">
                            <div class="plan-description">
                                <?php echo $plan['description'] ? htmlspecialchars($plan['description']) : 'No description available'; ?>
                            </div>
                            <div class="plan-stats">
                                <span><strong>Active Members:</strong> <?php echo $plan['active_members']; ?></span>
                                <span><strong>Created:</strong> <?php echo format_date($plan['created_at']); ?></span>
                            </div>
                            <div class="plan-actions">
                                <a href="manage_plans.php?edit=<?php echo $plan['id']; ?>" class="btn btn-edit">Edit</a>
                                <a href="manage_plans.php?delete=<?php echo $plan['id']; ?>" 
                                   class="btn btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this plan?')">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-plans">
                <h3>No plans found</h3>
                <p>Start by adding your first membership plan above.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>