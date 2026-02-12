<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gym_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . ".<br> Please check if the database exists");
}

// Set charset to utf8
$conn->set_charset("utf8");

// Start Session
session_start();

// Define upload directory
define('UPLOAD_DIR', 'uploads/members/');

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to format date for display
function format_date($date) {
    return date('d M Y', strtotime($date));
}

// Function to check if plan is expiring soon (within 7 days)
function is_expiring_soon($end_date) {
    $today = date('Y-m-d');
    $days_diff = (strtotime($end_date) - strtotime($today)) / (60 * 60 * 24);
    return $days_diff <= 7 && $days_diff >= 0;
}

// Function to check if plan is expired
function is_expired($end_date) {
    $today = date('Y-m-d');
    return strtotime($end_date) < strtotime($today);
}

// Function to handle file upload for member photos
function upload_member_photo($file, $member_id) {
    $target_dir = UPLOAD_DIR;
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = "member_" . $member_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return array("success" => false, "message" => "File is not an image.");
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return array("success" => false, "message" => "Sorry, your file is too large. Maximum size is 5MB.");
    }
    
    // Allow certain file formats
    if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif" ) {
        return array("success" => false, "message" => "Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
    }
    
    // Try to upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return array("success" => true, "filename" => $new_filename);
    } else {
        return array("success" => false, "message" => "Sorry, there was an error uploading your file.");
    }
}

// Function to delete member photo
function delete_member_photo($filename) {
    if ($filename && file_exists(UPLOAD_DIR . $filename)) {
        unlink(UPLOAD_DIR . $filename);
    }
}

// Function to get member photo URL
function get_member_photo_url($filename) {
    if ($filename && file_exists(UPLOAD_DIR . $filename)) {
        return UPLOAD_DIR . $filename;
    }
    return 'assets/default-avatar.png'; // Default avatar if no photo
}
?>