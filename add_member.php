<?php
include 'config.php';

$message = '';
$message_type = '';

// Fetch all available plans for the dropdown
$plans_query = "SELECT * FROM plans ORDER BY plan_name";
$plans_result = $conn->query($plans_query);

if ($_POST) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $gender = sanitize_input($_POST['gender']);
    $join_date = sanitize_input($_POST['join_date']);
    $emergency_contact = sanitize_input($_POST['emergency_contact']);
    
    // Plan subscription details
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $start_date = sanitize_input($_POST['start_date']);
    $amount_paid = sanitize_input($_POST['amount_paid']);
    $payment_date = sanitize_input($_POST['payment_date']);
    
    // Start transaction
    $conn->autocommit(FALSE);
    
    try {
        // Insert member first to get the ID
        $query = "INSERT INTO members (name, email, phone, address, date_of_birth, gender, join_date, emergency_contact) 
                  VALUES ('$name', '$email', '$phone', '$address', '$date_of_birth', '$gender', '$join_date', '$emergency_contact')";
        
        if ($conn->query($query)) {
            $member_id = $conn->insert_id;
            $photo_filename = null;
            
            // Handle photo upload if file is selected
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $upload_result = upload_member_photo($_FILES['photo'], $member_id);
                
                if ($upload_result['success']) {
                    $photo_filename = $upload_result['filename'];
                    
                    // Update member record with photo filename
                    $update_query = "UPDATE members SET photo = '$photo_filename' WHERE id = $member_id";
                    $conn->query($update_query);
                }
            }
            
            // If a plan is selected, create subscription
            if ($plan_id > 0 && !empty($start_date) && !empty($amount_paid) && !empty($payment_date)) {
                // Get plan details to calculate end date
                $plan_query = "SELECT duration_months FROM plans WHERE id = $plan_id";
                $plan_result = $conn->query($plan_query);
                
                if ($plan_result && $plan_result->num_rows > 0) {
                    $plan = $plan_result->fetch_assoc();
                    $duration_months = $plan['duration_months'];
                    
                    // Calculate end date
                    $end_date = date('Y-m-d', strtotime($start_date . " + $duration_months months"));
                    
                    // Insert subscription
                    $subscription_query = "INSERT INTO member_subscriptions (member_id, plan_id, start_date, end_date, amount_paid, payment_date, status) 
                                          VALUES ($member_id, $plan_id, '$start_date', '$end_date', '$amount_paid', '$payment_date', 'Active')";
                    
                    if (!$conn->query($subscription_query)) {
                        throw new Exception("Failed to create subscription: " . $conn->error);
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            if ($plan_id > 0) {
                $message = "Member added successfully with subscription plan!";
            } else {
                $message = "Member added successfully!";
            }
            $message_type = 'success';
            
        } else {
            throw new Exception("Failed to add member: " . $conn->error);
        }
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
    
    // Reset autocommit
    $conn->autocommit(TRUE);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Member - GYM Management</title>
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
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            background-color: #34495e;
            color: white;
            padding: 10px 15px;
            margin: 0 -30px 20px -30px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .section-title.plan-section {
            background-color: #27ae60;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .photo-preview {
            margin-top: 10px;
            text-align: center;
        }
        
        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .plan-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 4px solid #27ae60;
            display: none;
        }
        
        .plan-info.show {
            display: block;
        }
        
        .optional-section {
            border: 2px dashed #27ae60;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            background-color: #f8fff8;
        }
        
        .optional-label {
            background-color: #27ae60;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
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
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👤 Add New Member</h1>
        <p>Register a new gym member with optional subscription plan</p>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="section-title">
                    👤 Member Information
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact</label>
                        <input type="tel" id="emergency_contact" name="emergency_contact">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="join_date">Join Date *</label>
                        <input type="date" id="join_date" name="join_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo">Member Photo</label>
                        <input type="file" id="photo" name="photo" accept="image/*" onchange="previewPhoto(this)">
                        <div class="photo-preview" id="photoPreview"></div>
                    </div>
                </div>
                
                <!-- Optional Subscription Plan Section -->
                <div class="optional-section">
                    <span class="optional-label">OPTIONAL</span>
                    <div class="section-title plan-section">
                        💳 Subscription Plan (Optional)
                    </div>
                    
                    <div class="form-group">
                        <label for="plan_id">Select Membership Plan</label>
                        <select id="plan_id" name="plan_id" onchange="updatePlanInfo()">
                            <option value="">No Plan (Add Later)</option>
                            <?php if ($plans_result->num_rows > 0): ?>
                                <?php while($plan = $plans_result->fetch_assoc()): ?>
                                    <option value="<?php echo $plan['id']; ?>" 
                                            data-price="<?php echo $plan['price']; ?>"
                                            data-duration="<?php echo $plan['duration_months']; ?>"
                                            data-description="<?php echo htmlspecialchars($plan['description']); ?>">
                                        <?php echo htmlspecialchars($plan['plan_name']); ?> - ₹<?php echo $plan['price']; ?> (<?php echo $plan['duration_months']; ?> months)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="plan-info" id="planInfo">
                        <h4>Plan Details:</h4>
                        <p><strong>Duration:</strong> <span id="planDuration"></span> months</p>
                        <p><strong>Price:</strong> ₹<span id="planPrice"></span></p>
                        <p><strong>Description:</strong> <span id="planDescription"></span></p>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="start_date">Subscription Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="amount_paid">Amount Paid (₹)</label>
                            <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_date">Payment Date</label>
                            <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn">Add Member</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <footer class="footer">
        <p>Designed with <span class="heart">❤️</span> by <strong>Utkarsh Gaur</strong></p>
    </footer>
    
    <script>
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Photo Preview">';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '';
            }
        }
        
        function updatePlanInfo() {
            const planSelect = document.getElementById('plan_id');
            const planInfo = document.getElementById('planInfo');
            const selectedOption = planSelect.options[planSelect.selectedIndex];
            
            if (planSelect.value && selectedOption.dataset.price) {
                // Show plan info
                planInfo.classList.add('show');
                
                // Update plan details
                document.getElementById('planDuration').textContent = selectedOption.dataset.duration;
                document.getElementById('planPrice').textContent = selectedOption.dataset.price;
                document.getElementById('planDescription').textContent = selectedOption.dataset.description;
                
                // Auto-fill amount paid with plan price
                document.getElementById('amount_paid').value = selectedOption.dataset.price;
            } else {
                // Hide plan info
                planInfo.classList.remove('show');
                
                // Clear amount paid
                document.getElementById('amount_paid').value = '';
            }
        }
    </script>
</body>
</html>