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
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $gender = sanitize_input($_POST['gender']);
    $emergency_contact = sanitize_input($_POST['emergency_contact']);
    
    $errors = array();
    $success_message = '';
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // Check if email already exists for other members
    if (!empty($email)) {
        $email_check_query = "SELECT id FROM members WHERE email = '$email' AND id != '$member_id'";
        $email_check_result = $conn->query($email_check_query);
        if ($email_check_result->num_rows > 0) {
            $errors[] = "Email already exists for another member";
        }
    }
    
    if (empty($errors)) {
        // Get current member data for photo handling
        $current_member_query = "SELECT photo FROM members WHERE id = '$member_id'";
        $current_member_result = $conn->query($current_member_query);
        $current_member = $current_member_result->fetch_assoc();
        $current_photo = $current_member['photo'];
        
        // Handle photo upload/update
        $photo_filename = $current_photo; // Keep current photo by default
        $photo_message = '';
        
        // Check if new photo is uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $upload_result = upload_member_photo($_FILES['photo'], $member_id);
            
            if ($upload_result['success']) {
                // Delete old photo if exists
                if ($current_photo) {
                    delete_member_photo($current_photo);
                }
                $photo_filename = $upload_result['filename'];
            } else {
                $photo_message = " (Photo update failed: " . $upload_result['message'] . ")";
            }
        }
        
        // Check if photo should be removed
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            if ($current_photo) {
                delete_member_photo($current_photo);
            }
            $photo_filename = null;
        }
        
        $update_query = "UPDATE members SET 
                        name = '$name',
                        email = " . (!empty($email) ? "'$email'" : "NULL") . ",
                        phone = '$phone',
                        address = " . (!empty($address) ? "'$address'" : "NULL") . ",
                        date_of_birth = " . (!empty($date_of_birth) ? "'$date_of_birth'" : "NULL") . ",
                        gender = " . (!empty($gender) ? "'$gender'" : "NULL") . ",
                        emergency_contact = " . (!empty($emergency_contact) ? "'$emergency_contact'" : "NULL") . ",
                        photo = " . ($photo_filename ? "'$photo_filename'" : "NULL") . "
                        WHERE id = '$member_id'";
        
        if ($conn->query($update_query)) {
            $success_message = "Member updated successfully!" . $photo_message;
        } else {
            $errors[] = "Error updating member: " . $conn->error;
        }
    }
}

// Fetch member data
$member_query = "SELECT * FROM members WHERE id = '$member_id'";
$member_result = $conn->query($member_query);

if ($member_result->num_rows == 0) {
    header("Location: view_members.php");
    exit();
}

$member = $member_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - GYM Management</title>
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
            max-width: 800px;
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
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        textarea {
            height: 80px;
            resize: vertical;
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
        
        .member-info {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .photo-section {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f9f9f9;
        }
        
        .current-photo {
            margin-bottom: 15px;
        }
        
        .current-photo img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .photo-preview {
            margin-top: 15px;
        }
        
        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .photo-actions {
            margin-top: 15px;
        }
        
        .remove-photo-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .remove-photo-btn:hover {
            background-color: #c0392b;
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
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✏️ Edit Member</h1>
        <p>Update member information</p>
    </div>
    
    <div class="container">
        <a href="view_members.php" class="back-btn">← Back to Members</a>
        
        <div class="form-container">
            <div class="member-info">
                <strong>Editing Member:</strong> <?php echo htmlspecialchars($member['name']); ?> 
                (ID: <?php echo $member['id']; ?>) | 
                <strong>Joined:</strong> <?php echo format_date($member['join_date']); ?>
            </div>
            
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
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($member['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($member['phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($member['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo $member['date_of_birth']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $member['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $member['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $member['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact</label>
                        <input type="tel" id="emergency_contact" name="emergency_contact" 
                               value="<?php echo htmlspecialchars($member['emergency_contact']); ?>">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" 
                              placeholder="Enter full address..."><?php echo htmlspecialchars($member['address']); ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="photo">Member Photo</label>
                    <div class="photo-section">
                        <?php if ($member['photo']): ?>
                            <div class="current-photo">
                                <p><strong>Current Photo:</strong></p>
                                <img src="<?php echo get_member_photo_url($member['photo']); ?>" alt="Current Photo">
                                <div class="photo-actions">
                                    <label>
                                        <input type="checkbox" name="remove_photo" value="1" onchange="togglePhotoRemoval(this)">
                                        Remove current photo
                                    </label>
                                </div>
                            </div>
                            <hr style="margin: 15px 0; border: 1px solid #eee;">
                        <?php else: ?>
                            <p style="color: #666; margin-bottom: 15px;">No photo uploaded</p>
                        <?php endif; ?>
                        
                        <div id="photoUploadSection">
                            <input type="file" id="photo" name="photo" accept="image/*" onchange="previewPhoto(this)">
                            <div class="photo-preview" id="photoPreview"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="submit-btn">Update Member</button>
                    <a href="view_members.php" class="cancel-btn">Cancel</a>
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
                    preview.innerHTML = '<p><strong>New Photo Preview:</strong></p><img src="' + e.target.result + '" alt="Photo Preview">';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '';
            }
        }
        
        function togglePhotoRemoval(checkbox) {
            const photoUploadSection = document.getElementById('photoUploadSection');
            const photoPreview = document.getElementById('photoPreview');
            
            if (checkbox.checked) {
                photoUploadSection.style.opacity = '0.5';
                photoUploadSection.style.pointerEvents = 'none';
                photoPreview.innerHTML = '';
                document.getElementById('photo').value = '';
            } else {
                photoUploadSection.style.opacity = '1';
                photoUploadSection.style.pointerEvents = 'auto';
            }
        }
    </script>
</body>
</html>