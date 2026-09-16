<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login_user.php");
    exit();
}

include 'connection.php';

// Check and add profile_picture column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM profile LIKE 'profile_picture'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE profile ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER primary_doctor");
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];
$appointment_success = "";
$appointment_error = "";
$initial_letter = strtoupper(substr($full_name, 0, 1));
$errors = [];

// Split full name
$names = explode(' ', $full_name);
$first_name  = $names[0] ?? '';
$last_name   = $names[count($names)-1] ?? '';
$middle_name = (count($names) > 2) ? implode(' ', array_slice($names, 1, -1)) : '';

// Fetch existing profile if exists
$stmt = $conn->prepare("SELECT * FROM profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc() ?? [];
$stmt->close();

$profile_picture = $row['profile_picture'] ?? null;

// Handle profile picture update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile_picture'])) {
    $picture_path = null;
    $upload_error = "";
    
    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed)) {
            $upload_dir = 'uploads/profiles/';
            $upload_dir_abs = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;

            if (!is_dir($upload_dir_abs)) {
                if (!mkdir($upload_dir_abs, 0777, true)) {
                    $upload_error = "Could not create upload folder.";
                }
            }

            if ($upload_error === "") {
                $filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $filename; // stored in DB (web path)
                $target_path_abs = $upload_dir_abs . $filename; // filesystem path

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path_abs)) {
                    if ($profile_picture && strpos($profile_picture, 'uploads/profiles/') !== false) {
                        $old_abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $profile_picture);
                        if (is_file($old_abs)) {
                            unlink($old_abs);
                        }
                    }
                    $picture_path = $target_path;
                } else {
                    $upload_error = "Upload failed. Please try again.";
                }
            }
        } else {
            $upload_error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    } else {
        $upload_error = "Please select an image file to upload.";
    }
    
    if ($picture_path) {
        if ($row) {
            $stmt = $conn->prepare("UPDATE profile SET profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("si", $picture_path, $user_id);
        } else {
            // Create a minimal profile row for first-time users, then store picture
            $stmt = $conn->prepare("INSERT INTO profile (user_id, first_name, middle_name, last_name, profile_picture) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $first_name, $middle_name, $last_name, $picture_path);
        }
        $executed = $stmt->execute();
        $stmt->close();
        
        if ($executed) {
            // Refresh profile data
            $stmt = $conn->prepare("SELECT * FROM profile WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc() ?? [];
            $stmt->close();
            $profile_picture = $row['profile_picture'] ?? null;
            
            $success_msg = "Profile picture updated successfully!";
        } else {
            $errors[] = "Could not save profile image to database.";
        }
    } elseif ($upload_error !== "") {
        $errors[] = $upload_error;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_profile'])) {
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $nationality = $_POST['nationality'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $street = $_POST['street'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal = $_POST['postal'] ?? '';
    $em_phone = $_POST['emergency_phone'] ?? '';
    $blood_group = $_POST['blood_group'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    $chronic = $_POST['chronic_diseases'] ?? '';
    $medications = $_POST['medications'] ?? '';
    $primary_doctor = $_POST['primary_doctor'] ?? '';

    // Validate full name (combined) must not contain numbers
    $combined_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    if ($combined_name !== '' && preg_match('/\d/', $combined_name)) {
        $errors[] = "Full name must not contain numbers.";
    }

    // Validate email using PHP filter (from session)
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Stored email address is not valid. Please contact support.";
    }

    // Phone number must contain exactly 10 digits
    if ($phone !== '') {
        // Allow only digits and ensure exactly 10
        if (!preg_match('/^\d{10}$/', $phone)) {
            $errors[] = "Phone number must contain exactly 10 digits.";
        }
    }

    if (empty($errors)) {
        if($row){
            // Update
            $stmt = $conn->prepare("UPDATE profile SET first_name=?, middle_name=?, last_name=?, dob=?, gender=?, nationality=?, phone=?, street=?, city=?, state=?, postal_code=?, emergency_phone=?, blood_group=?, allergies=?, chronic_diseases=?, medications=?, primary_doctor=? WHERE user_id=?");
            $stmt->bind_param("sssssssssssssssssi", $first_name, $middle_name, $last_name, $dob, $gender, $nationality, $phone, $street, $city, $state, $postal, $em_phone, $blood_group, $allergies, $chronic, $medications, $primary_doctor, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO profile (user_id, first_name, middle_name, last_name, dob, gender, nationality, phone, street, city, state, postal_code, emergency_phone, blood_group, allergies, chronic_diseases, medications, primary_doctor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssssssssssss", $user_id, $first_name, $middle_name, $last_name, $dob, $gender, $nationality, $phone, $street, $city, $state, $postal, $em_phone, $blood_group, $allergies, $chronic, $medications, $primary_doctor);
            $stmt->execute();
            $stmt->close();
        }

        $success_msg = "Profile saved successfully!";
        $stmt = $conn->prepare("SELECT * FROM profile WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
}

// Handle appointment booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';

    // Validate doctor exists
    $doctor_check = $conn->prepare("SELECT id FROM doctors WHERE id = ? LIMIT 1");
    $doctor_check->bind_param("i", $doctor_id);
    $doctor_check->execute();
    $doctor_check->store_result();

    if ($doctor_id <= 0 || $doctor_check->num_rows === 0) {
        $appointment_error = "Please select a doctor.";
    } elseif ($appointment_date === '' || $appointment_time === '') {
        $appointment_error = "Date and time are required.";
    } elseif ($payment_method === '') {
        $appointment_error = "Please select a payment method.";
    } elseif (strtotime($appointment_date . ' ' . $appointment_time) <= time()) {
        $appointment_error = "Choose a future date and time.";
    } else {
        // Validate time is between 10 AM and 4 PM
        $time_parts = explode(':', $appointment_time);
        $hour = (int)$time_parts[0];
        if ($hour < 10 || $hour >= 16) {
            $appointment_error = "Appointments are only available from 10:00 AM to 4:00 PM.";
        } else {
            // Prevent duplicate bookings at same time
            // Prevent bookings within +/- 10 minutes of an existing appointment
            $appointment_time_db = $appointment_time;
            if (strlen($appointment_time_db) === 5) {
                // TIME input usually returns HH:MM; MySQL will treat it as HH:MM:00 anyway,
                // but using :00 makes the comparison consistent when concatenated into DATETIME.
                $appointment_time_db .= ':00';
            }

            $new_datetime = $appointment_date . ' ' . $appointment_time_db;

            $conflict = $conn->prepare("
                SELECT a.id
                FROM appointments a
                WHERE a.doctor_id = ?
                  AND a.appointment_date = ?
                  AND (a.status = 'pending' OR a.status = 'confirmed')
                  AND ABS(TIMESTAMPDIFF(SECOND, CONCAT(a.appointment_date, ' ', a.appointment_time), ?)) <= 600
                LIMIT 1
            ");
            $conflict->bind_param("iss", $doctor_id, $appointment_date, $new_datetime);
            $conflict->execute();
            $conflict->store_result();

            if ($conflict->num_rows > 0) {
                $appointment_error = "This time slot is already booked.";
            } else {
                $appointment_fee = 700.00; // Fixed fee

                // Handle payment based on method
                if ($payment_method === 'cash') {
                    // Save appointment immediately for cash payment
                    $insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, reason, status, payment_method, payment_status, appointment_fee) VALUES (?, ?, ?, ?, ?, 'pending', 'cash', 'Pending', ?)");
                    // Parameters: user_id(i), doctor_id(i), date(s), time(s), reason(s), fee(d) = 6 params  
                    $param_types = "i" . "i" . "s" . "s" . "s" . "d"; // 6 chars: i(user_id), i(doctor_id), s(date), s(time), s(reason), d(fee)
                    $insert->bind_param($param_types, $user_id, $doctor_id, $appointment_date, $appointment_time, $reason, $appointment_fee);

                    if ($insert->execute()) {
                        $appointment_success = "Appointment booked successfully. Pay cash at counter (Rs. 700).";
                    } else {
                        $appointment_error = "Could not book appointment. Please try again.";
                    }
                    $insert->close();
                } elseif ($payment_method === 'esewa') {
                    // For eSewa, first create appointment with pending payment, then redirect
                    $insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, reason, status, payment_method, payment_status, appointment_fee) VALUES (?, ?, ?, ?, ?, 'pending', 'esewa', 'Pending', ?)");
                    // Parameters: user_id(i), doctor_id(i), date(s), time(s), reason(s), fee(d) = 6 params  
                    $param_types = "i" . "i" . "s" . "s" . "s" . "d"; // 6 chars: i(user_id), i(doctor_id), s(date), s(time), s(reason), d(fee)
                    $insert->bind_param($param_types, $user_id, $doctor_id, $appointment_date, $appointment_time, $reason, $appointment_fee);

                    if ($insert->execute()) {
                        $appointment_id = $conn->insert_id;
                        $insert->close();
                        
                        // Store appointment_id in session for eSewa success callback
                        $_SESSION['pending_appointment_id'] = $appointment_id;
                        
                        // Redirect to eSewa payment
                        header("Location: esewa/pay.php?appointment_id=" . $appointment_id . "&amount=" . $appointment_fee);
                        exit();
                    } else {
                        $appointment_error = "Could not book appointment. Please try again.";
                        $insert->close();
                    }
                } else {
                    $appointment_error = "Invalid payment method selected.";
                }
            }
            $conflict->close();
        }
    }
    $doctor_check->close();
}

// Fetch appointments (after possible insert)
$appointments_stmt = $conn->prepare("SELECT a.*, d.full_name as doctor_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE a.user_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointments = [];
while($app = $appointments_result->fetch_assoc()) {
    $appointments[] = $app;
}
$appointments_stmt->close();

// Fetch doctors once for both booking form and listing
$doctors_stmt = $conn->prepare("SELECT * FROM doctors ORDER BY full_name");
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();
$doctors = [];
while($doc = $doctors_result->fetch_assoc()) {
    $doctors[] = $doc;
}
$doctors_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Dashboard - Hospital Management System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet" />

    <!-- Main CSS -->
    <link rel="stylesheet" href="style.css" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <style>
       * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      } 

      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f5f5;
        color: #2c3e50;
        padding-top: 0 !important;
      }
      
      body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
      }
      
      .dashboard {
        margin-top: 70px;
        flex: 1;
        display: flex;
        flex-direction: column;
      }
      
      .content {
        padding: 30px;
        background: linear-gradient(to bottom, #f8f9fa 0%, #e9ecef 100%);
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
        flex: 1;
        padding-bottom: 20px;
      }
      
      .footer {
        margin-top: auto;
        position: relative;
        width: 100%;
      }
      
      .welcome-msg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
      }
      
      .welcome-msg h2 {
        margin: 0 0 8px 0;
        font-size: 28px;
        font-weight: 700;
      }
      
      .welcome-msg p {
        margin: 0;
        font-size: 16px;
        opacity: 0.95;
      }
      
      /* Page sections */
      .page {
        display: none;
      }
      
      .page.show {
        display: block;
      }
      
      /* Success/Error Messages */
      .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }
      
      .alert-success {
        color: #155724;
        background: #d4edda;
        border-left: 4px solid #28a745;
      }
      
      .alert-error {
        color: #721c24;
        background: #f8d7da;
        border-left: 4px solid #dc3545;
      }
      
      .alert-info {
        color: #0c5460;
        background: #d1ecf1;
        border-left: 4px solid #17a2b8;
      }
      
      /* Header Profile */
      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
      }
      
      .header .logo-link {
        display: flex;
        align-items: center;
      }
      
      .profile-dropdown-wrapper {
        position: relative;
      }
      
      .profile-trigger {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 8px 15px;
        border-radius: 25px;
      }
      
      .profile-trigger:hover {
        background: rgba(0,0,0,0.05);
      }
      
      .profile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: bold;
        color: white;
        background: #667eea;
        overflow: hidden;
      }
      
      .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      
      .profile-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
      }
      
      .dropdown-arrow {
        color: #6c757d;
        font-size: 12px;
      }
      
      .profile-dropdown-wrapper.active .dropdown-arrow {
        transform: rotate(180deg);
      }
      
      .profile-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        min-width: 200px;
        opacity: 0;
        visibility: hidden;
        z-index: 1000;
      }
      
      .profile-dropdown-wrapper.active .profile-dropdown {
        opacity: 1;
        visibility: visible;
      }
      
      .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #2c3e50;
        text-decoration: none;
        border-bottom: 1px solid #f0f0f0;
      }
      
      .dropdown-item:last-child {
        border-bottom: none;
      }
      
      .dropdown-item:hover {
        background: #f8f9fa;
        color: #667eea;
      }
      
      .dropdown-item i {
        width: 20px;
        color: #6c757d;
      }
      
      .dropdown-item:hover i {
        color: #667eea;
      }
      
      /* Profile Picture Modal */
      .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 2000;
        align-items: center;
        justify-content: center;
      }
      
      .modal-overlay.active {
        display: flex;
      }
      
      .modal-box {
        background: white;
        border-radius: 15px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      }
      
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
      }
      
      .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 22px;
      }
      
      .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
      }
      
      .close-modal:hover {
        background: #f0f0f0;
        color: #2c3e50;
      }
      
      .upload-section {
        margin-top: 20px;
      }
      
      .file-upload-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        display: inline-block;
      }
      
      .file-upload-wrapper input[type=file] {
        display: none;
      }
      
      .image-preview {
        margin-top: 15px;
        display: none;
      }
      
      .image-preview img {
        max-width: 150px;
        border-radius: 50%;
        border: 3px solid #667eea;
      }
      
      .modal-actions {
        margin-top: 25px;
        text-align: right;
        padding-top: 20px;
        border-top: 2px solid #e9ecef;
      }
      
      .modal-actions button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        margin-left: 10px;
      }
      
      .modal-actions .btn-cancel {
        background: #6c757d;
      }
      
      /* Form Styling */
      .profile-form {
        background: white;
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 30px;
      }
      
      .form-section {
        margin-bottom: 35px;
        padding-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
      }
      
      .form-section:last-of-type {
        border-bottom: none;
        margin-bottom: 0;
      }
      
      .form-section h3 {
        color: #667eea;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 25px;
        padding-bottom: 12px;
        border-bottom: 3px solid #667eea;
        display: inline-block;
      }
      
      .form-section h3 i {
        margin-right: 10px;
      }
      
      .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
      
      .form-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 14px;
      }
      
      .form-group input,
      .form-group select,
      .form-group textarea {
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
        background: #fff;
        color: #2c3e50;
      }
      
      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
      }
      
      .form-group input[readonly] {
        background: #f8f9fa;
        cursor: not-allowed;
      }
      
      .form-group textarea {
        resize: vertical;
        min-height: 100px;
      }

      /* Payment Options Styling */
      .payment-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 10px;
      }

      .payment-option {
        position: relative;
        cursor: pointer;
        display: block;
      }

      .payment-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
      }

      .payment-option-content {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        background: #fff;
        transition: all 0.3s ease;
      }

      .payment-option-content i {
        font-size: 32px;
        color: #667eea;
        min-width: 40px;
      }

      .payment-option-content div {
        display: flex;
        flex-direction: column;
        gap: 5px;
      }

      .payment-option-content strong {
        color: #2c3e50;
        font-size: 16px;
        font-weight: 600;
      }

      .payment-option-content span {
        color: #6c757d;
        font-size: 13px;
      }

      .payment-option input[type="radio"]:checked + .payment-option-content {
        border-color: #667eea;
        background: #f0f4ff;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
      }

      .payment-option:hover .payment-option-content {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      }
      
      .form-actions {
        margin-top: 30px;
        text-align: right;
        padding-top: 25px;
        border-top: 2px solid #f0f0f0;
      }
      
      .form-actions button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 35px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
      }
      
      /* Card Styling */
      .card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
      }
      
      .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
      }
      
      .card-header h3 {
        margin: 0;
        color: #667eea;
        font-size: 20px;
        font-weight: 700;
      }
      
      .card-header i {
        color: #667eea;
        font-size: 22px;
      }
      
      /* Appointment Table */
      .appointment-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      }
      
      .appointment-table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
      }
      
      .appointment-table th {
        padding: 18px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .appointment-table tbody tr {
        border-bottom: 1px solid #f0f0f0;
      }
      
      .appointment-table tbody tr:last-child {
        border-bottom: none;
      }
      
      .appointment-table td {
        padding: 18px;
        color: #2c3e50;
        font-size: 15px;
      }
      
      .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
      }
      
      .status-pending {
        background: #fff3cd;
        color: #856404;
      }
      
      .status-confirmed {
        background: #d4edda;
        color: #155724;
      }
      
      .status-cancelled {
        background: #f8d7da;
        color: #721c24;
      }

      .status-paid {
        background: #d4edda;
        color: #155724;
      }

      .status-pending {
        background: #fff3cd;
        color: #856404;
      }
      
      /* Notification Bell Styles */
      .notification-wrapper {
        position: relative;
        margin-right: 15px;
      }
      
      .notification-bell {
        position: relative;
        cursor: pointer;
        padding: 10px;
        font-size: 20px;
        color: var(--text-dark);
        transition: color 0.3s ease;
      }
      
      .notification-bell:hover {
        color: var(--mainclr);
      }
      
      .notification-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 11px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
      }
      
      .notification-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 350px;
        max-height: 500px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        display: none;
        z-index: 1001;
        overflow: hidden;
      }
      
      .notification-dropdown.show {
        display: block;
      }
      
      .notification-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
      }
      
      .notification-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
      }
      
      .notification-count {
        background: rgba(255, 255, 255, 0.3);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
      }
      
      .notification-list {
        max-height: 400px;
        overflow-y: auto;
      }
      
      .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background-color 0.2s ease;
      }
      
      .notification-item:hover {
        background-color: #f8f9fa;
      }
      
      .notification-item.unread {
        background-color: #f0f8ff;
        font-weight: 600;
      }
      
      .notification-item.unread:hover {
        background-color: #e6f3ff;
      }
      
      .notification-message {
        color: #2c3e50;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 5px;
      }
      
      .notification-time {
        color: #6c757d;
        font-size: 12px;
      }
      
      .notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
        font-size: 14px;
      }
      
      .notification-loading {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
        font-size: 14px;
      }
      
      /* Doctor Cards */
      .doctor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-top: 25px;
      }
      
      .doctor-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border-top: 4px solid #667eea;
      }
      
      .doctor-card .book-btn {
        margin-top: 15px;
        width: 100%;
      }
      
      .doctor-card h3 {
        color: #667eea;
        margin: 0 0 15px 0;
        font-size: 22px;
        font-weight: 700;
      }
      
      .doctor-card .doctor-info {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        color: #6c757d;
        font-size: 15px;
      }
      
      .doctor-card .doctor-info i {
        color: #667eea;
        width: 20px;
      }
      
      .doctor-card .doctor-info strong {
        color: #2c3e50;
        margin-right: 5px;
      }
      
      /* Empty State */
      .empty-state {
        background: white;
        padding: 60px 20px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      }
      
      .empty-state i {
        font-size: 64px;
        color: #dee2e6;
        margin-bottom: 20px;
      }
      
      .empty-state p {
        color: #6c757d;
        font-size: 16px;
        margin: 0;
      }
      
      /* Button Styling */
      .btn {
        padding: 12px 25px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }
      
      .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
      }
      
      /* Responsive */
      @media (max-width: 768px) {
        .header {
          padding: 12px 15px;
          gap: 10px;
        }

        .nav {
          display: none;
        }

        .notification-wrapper {
          order: 2;
          margin-right: 10px;
        }
        
        .notification-dropdown {
          width: 300px;
          right: -10px;
        }

        .profile-dropdown-wrapper {
          order: 3;
          margin-left: auto;
          margin-right: 10px;
        }

        .icons {
          order: 4;
        }

        .profile-name {
          display: none;
        }

        .dropdown-arrow {
          display: none;
        }

        .profile-trigger {
          padding: 6px 10px;
        }

        .profile-avatar {
          width: 36px;
          height: 36px;
          font-size: 16px;
        }
        
        .content {
          padding: 20px;
        }
        
        .form-row {
          grid-template-columns: 1fr;
        }
        
        .doctor-grid {
          grid-template-columns: 1fr;
        }
        
        .appointment-table {
          font-size: 14px;
        }
        
        .appointment-table th,
        .appointment-table td {
          padding: 12px 8px;
        }
      }
      
    </style>
  </head>

  <body>
    <!-- Header -->
    <header class="header">
      <a href="index.html" class="logo-link">
        <img src="images/logo.png" alt="Logo" />
      </a>

      <!-- Navigation -->
      <nav class="nav">
        <div class="nav-links">
          <a href="index.html#home">Home</a>
          <a href="index.html#about">About</a>
          <a href="index.html#service">Service</a>
          <a href="index.html#gallery">Gallery</a>
          <a href="index.html#contact">Contact</a>
        </div>
      </nav>

      <!-- Notification Bell -->
      <div class="notification-wrapper" id="notificationWrapper">
        <div class="notification-bell" onclick="toggleNotificationDropdown()">
          <i class="fas fa-bell"></i>
          <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
        </div>
        <div class="notification-dropdown" id="notificationDropdown">
          <div class="notification-header">
            <h3>Notifications</h3>
            <span class="notification-count" id="notificationCount">0</span>
          </div>
          <div class="notification-list" id="notificationList">
            <div class="notification-loading">Loading notifications...</div>
          </div>
        </div>
      </div>

      <!-- Profile Dropdown -->
      <div class="profile-dropdown-wrapper" id="profileDropdown">
        <div class="profile-trigger" onclick="toggleDropdown()">
          <div class="profile-avatar">
            <?php 
            if (!empty($profile_picture)):
              $profile_picture_abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $profile_picture);
              $cache_version = is_file($profile_picture_abs) ? filemtime($profile_picture_abs) : time();
              $profile_picture_url = $profile_picture . '?v=' . $cache_version;
            ?>
              <img src="<?= htmlspecialchars($profile_picture_url) ?>" alt="Profile">
            <?php else: ?>
              <span><?= htmlspecialchars($initial_letter) ?></span>
            <?php endif; ?>
          </div>
          <span class="profile-name"><?= htmlspecialchars(explode(' ', $full_name)[0]) ?></span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="profile-dropdown">
          <a href="#" class="dropdown-item" onclick="openProfileModal(); return false;">
            <i class="fas fa-image"></i>
            <span>Change Picture</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('profile'); return false;">
            <i class="fas fa-user"></i>
            <span>Profile</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('appointment'); return false;">
            <i class="fas fa-calendar"></i>
            <span>Appointments</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('doctors'); return false;">
            <i class="fas fa-user-md"></i>
            <span>Doctors</span>
          </a>
          <a href="logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>

      <!-- Menu Icon -->
      <!-- <div class="icons">
        <div id="menubar" class="fas fa-bars"></div>
      </div> -->
    </header>

<div class="dashboard">
  <div class="content">
    <section id="profile" class="page">
      <div class="welcome-msg">
        <h2>Welcome, <?= htmlspecialchars($full_name) ?>!</h2>
        <p>Manage your profile and medical information</p>
      </div>

      <?php if(isset($success_msg)): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <span><?= htmlspecialchars($success_msg) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <div>
            <?php foreach ($errors as $err): ?>
              <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" class="profile-form">
        <div class="form-section">
          <h3><i class="fas fa-user"></i> Personal Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>First Name</label>
              <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" placeholder="First Name" style="text-transform: capitalize;" readonly>
            </div>
            <div class="form-group">
              <label>Middle Name</label>
              <input type="text" name="middle_name" value="<?= htmlspecialchars($middle_name) ?>" placeholder="Middle Name" style="text-transform: capitalize;" readonly>
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" placeholder="Last Name" style="text-transform: capitalize;" readonly>
            </div>
            <div class="form-group">
              <label>Date of Birth</label>
              <input type="date" name="dob" value="<?= @$row['dob'] ?>" required>
            </div>
            <div class="form-group">
              <label>Gender</label>
              <select name="gender" required>
                <option value="">Select Gender</option>
                <option <?= (@$row['gender']=='Male')?'selected':'' ?>>Male</option>
                <option <?= (@$row['gender']=='Female')?'selected':'' ?>>Female</option>
                <option <?= (@$row['gender']=='Other')?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Nationality</label>
              <select name="nationality">
                <option value="">Select Nationality</option>
                <option <?= (@$row['nationality']=='Nepali')?'selected':'' ?>>Nepali</option>
                <option <?= (@$row['nationality']=='Indian')?'selected':'' ?>>Indian</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3><i class="fas fa-address-book"></i> Contact Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Phone</label>
              <input type="text" name="phone" placeholder="Phone" value="<?= htmlspecialchars(@$row['phone']) ?>">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" value="<?= htmlspecialchars($email) ?>" readonly>
            </div>
            <div class="form-group full-width">
              <label>Street Address</label>
              <input type="text" name="street" value="<?= htmlspecialchars(@$row['street']) ?>" placeholder="Street Address">
            </div>
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" value="<?= htmlspecialchars(@$row['city']) ?>" placeholder="City">
            </div>
            <div class="form-group">
              <label>State</label>
              <input type="text" name="state" value="<?= htmlspecialchars(@$row['state']) ?>" placeholder="State">
            </div>
            <div class="form-group">
              <label>Postal Code</label>
              <input type="text" name="postal" value="<?= htmlspecialchars(@$row['postal_code']) ?>" placeholder="Postal Code">
            </div>
            <div class="form-group">
              <label>Emergency Contact</label>
              <input type="text" name="emergency_phone" value="<?= htmlspecialchars(@$row['emergency_phone']) ?>" placeholder="Emergency Number">
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3><i class="fas fa-heartbeat"></i> Medical Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Blood Group</label>
              <select name="blood_group">
                <option value="">Select Blood Group</option>
                <?php 
                $groups=['Unknown','A+','A-','B+','B-','O+','O-','AB+','AB-'];
                foreach($groups as $g){
                  echo "<option ".(@$row['blood_group']==$g?"selected":"").">$g</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-group">
              <label>Allergies</label>
              <textarea name="allergies" placeholder="List any allergies"><?= htmlspecialchars(@$row['allergies']) ?></textarea>
            </div>
            <div class="form-group">
              <label>Chronic Diseases</label>
              <textarea name="chronic_diseases" placeholder="List chronic diseases"><?= htmlspecialchars(@$row['chronic_diseases']) ?></textarea>
            </div>
            <div class="form-group">
              <label>Medications</label>
              <textarea name="medications" placeholder="Current medications"><?= htmlspecialchars(@$row['medications']) ?></textarea>
            </div>
            <div class="form-group">
              <label>Primary Doctor</label>
              <input type="text" name="primary_doctor" value="<?= htmlspecialchars(@$row['primary_doctor']) ?>" placeholder="Primary Doctor Name">
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" name="save_profile">Save Profile</button>
        </div>
      </form>
    </section>

    <section id="appointment" class="page show">
      <div class="welcome-msg">
        <h2>My Appointments</h2>
        <p>View and manage your appointments</p>
      </div>

      <?php if($appointment_success): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <span><?= htmlspecialchars($appointment_success) ?></span>
        </div>
      <?php endif; ?>

      <?php if($appointment_error): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($appointment_error) ?></span>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <i class="fas fa-calendar-plus"></i>
          <h3>Book a Doctor</h3>
        </div>
        <form method="POST">
          <div class="form-row">
            <div class="form-group">
              <label><i class="fas fa-user-md"></i> Doctor</label>
              <select name="doctor_id" id="doctorSelect" required <?= count($doctors) === 0 ? 'disabled' : '' ?>>
                <option value="">Select doctor</option>
                <?php foreach($doctors as $doc): ?>
                  <option value="<?= (int)$doc['id'] ?>" <?= (isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($doc['full_name']) ?> - <?= htmlspecialchars($doc['specialization'] ?? 'General') ?></option>
                <?php endforeach; ?>
              </select>
              <?php if(count($doctors) === 0): ?>
                <small style="color:#999; margin-top:5px;">No doctors available right now.</small>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label><i class="fas fa-calendar"></i> Date</label>
              <input type="date" name="appointment_date" required>
            </div>
            <div class="form-group">
              <label><i class="fas fa-clock"></i> Time (10:00 AM - 4:00 PM)</label>
              <input type="time" name="appointment_time" min="10:00" max="16:00" required>
              <small style="color:#6c757d; margin-top:5px; display:block;">Appointments available from 10:00 AM to 4:00 PM only</small>
            </div>
            <div class="form-group full-width">
              <label><i class="fas fa-sticky-note"></i> Reason / Notes</label>
              <textarea name="reason" placeholder="Describe your symptoms or reason for the appointment (optional)" rows="4"></textarea>
            </div>
            <div class="form-group full-width">
              <label><i class="fas fa-money-bill-wave"></i> Payment Method <span style="color: #667eea;">(Fee: Rs. 700)</span></label>
              <div class="payment-options">
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="cash" required>
                  <div class="payment-option-content">
                    <i class="fas fa-money-bill"></i>
                    <div>
                      <strong>Cash at Counter</strong>
                      <span>Pay when you visit</span>
                    </div>
                  </div>
                </label>
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="esewa" required>
                  <div class="payment-option-content">
                    <i class="fas fa-mobile-alt"></i>
                    <div>
                      <strong>Online Payment (eSewa)</strong>
                      <span>Pay securely online</span>
                    </div>
                  </div>
                </label>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="book_appointment" class="btn btn-primary">
              <i class="fas fa-check"></i> Book Appointment
            </button>
          </div>
        </form>
      </div>
      
      <?php if(count($appointments) > 0): ?>
        <div class="card">
          <div class="card-header">
            <i class="fas fa-list"></i>
            <h3>My Appointments</h3>
          </div>
          <table class="appointment-table">
            <thead>
              <tr>
                <th><i class="fas fa-calendar"></i> Date</th>
                <th><i class="fas fa-clock"></i> Time</th>
                <th><i class="fas fa-user-md"></i> Doctor</th>
                <th><i class="fas fa-sticky-note"></i> Reason</th>
                <th><i class="fas fa-info-circle"></i> Status</th>
                <th><i class="fas fa-money-bill-wave"></i> Payment</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($appointments as $apt): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($apt['appointment_date']) ?></strong></td>
                  <td><?= htmlspecialchars($apt['appointment_time']) ?></td>
                  <td><?= htmlspecialchars($apt['doctor_name'] ?? 'Not Assigned') ?></td>
                  <td><?= htmlspecialchars($apt['reason'] ?? 'N/A') ?></td>
                  <td>
                    <span class="status-badge status-<?= $apt['status'] ?>">
                      <?= ucfirst($apt['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($apt['payment_method'])): ?>
                      <div style="display: flex; flex-direction: column; gap: 5px;">
                        <span style="font-size: 12px; color: #6c757d;">
                          <?= ucfirst($apt['payment_method']) == 'Esewa' ? 'eSewa' : ucfirst($apt['payment_method']) ?>
                        </span>
                        <span class="status-badge status-<?= strtolower($apt['payment_status'] ?? 'pending') ?>" style="font-size: 11px;">
                          <?= htmlspecialchars($apt['payment_status'] ?? 'N/A') ?>
                        </span>
                      </div>
                    <?php else: ?>
                      <span style="color: #6c757d; font-size: 12px;">N/A</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <p>No appointments found. Book your first appointment above!</p>
        </div>
      <?php endif; ?>
    </section>

    <section id="doctors" class="page">
      <div class="welcome-msg">
        <h2>Available Doctors</h2>
        <p>Browse our medical professionals</p>
      </div>
      
      <?php if(count($doctors) > 0): ?>
        <div class="doctor-grid">
          <?php foreach($doctors as $doc): ?>
            <div class="doctor-card">
              <h3><i class="fas fa-user-md"></i> <?= htmlspecialchars($doc['full_name']) ?></h3>
              <div class="doctor-info">
                <i class="fas fa-stethoscope"></i>
                <span><strong>Specialization:</strong> <?= htmlspecialchars($doc['specialization'] ?? 'General') ?></span>
              </div>
              <div class="doctor-info">
                <i class="fas fa-briefcase"></i>
                <span><strong>Experience:</strong> <?= htmlspecialchars($doc['experience_years'] ?? 0) ?> years</span>
              </div>
              <div class="doctor-info">
                <i class="fas fa-envelope"></i>
                <span><strong>Email:</strong> <?= htmlspecialchars($doc['email']) ?></span>
              </div>
              <button type="button" class="btn btn-primary book-btn" onclick="bookWithDoctor(<?= (int)$doc['id'] ?>)">
                <i class="fas fa-calendar-plus"></i> Book Appointment
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-user-md"></i>
          <p>No doctors available at the moment.</p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <h2>CarePoint Hospital</h2>
      <p>
        Caring for you with modern facilities
        <br />
        and expert medical teams every day.
      </p>
    </div>

    <div class="footer-links">
      <a href="index.html#home">Home</a>
      <a href="index.html#about">About</a>
      <a href="index.html#service">Service</a>
      <a href="index.html#gallery">Gallery</a>
      <a href="index.html#contact">Contact</a>
    </div>

    <div class="footer-links">
      <a href="logout.php">Logout</a>
      <a href="index.html">Back to Home</a>
    </div>

    <div class="footer-contact">
      <p>
        <i class="fas fa-phone"></i>
        +977-9800000000
      </p>
      <p>
        <i class="fas fa-envelope"></i>
        info@carepoint.com
      </p>
      <p>
        <i class="fas fa-map-marker-alt"></i>
        Kathmandu, Nepal
      </p>
    </div>
  </div>
  <p class="footer-copy">&copy; 2025 CarePoint Hospital. All rights reserved.</p>
</footer>

<!-- Profile Picture Modal -->
<div class="modal-overlay" id="profileModal" onclick="closeProfileModal(event)">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3>Change Profile Picture</h3>
      <button class="close-modal" onclick="closeProfileModal()">&times;</button>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
      <div class="upload-section">
        <label>
          <span class="file-upload-btn">
            <i class="fas fa-cloud-upload-alt"></i> Choose Image
          </span>
          <input type="file" name="profile_image" id="profileImage" accept="image/*" onchange="previewImage(this)">
        </label>
        <div class="image-preview" id="imagePreview">
          <img id="previewImg" src="" alt="Preview">
        </div>
      </div>
      
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeProfileModal()">Cancel</button>
        <button type="submit" name="update_profile_picture">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="script.js"></script>
<script>
function toggleDropdown() {
  document.getElementById('profileDropdown').classList.toggle('active');
}

function navigateTo(page) {
  document.getElementById('profileDropdown').classList.remove('active');
  const pages = document.querySelectorAll('.page');
  
  pages.forEach(p => p.classList.remove('show'));
  
  const targetPage = document.getElementById(page);
  if (targetPage) targetPage.classList.add('show');
  
  window.location.hash = page;
}

function bookWithDoctor(doctorId) {
  navigateTo('appointment');
  // Set the doctor in the select dropdown
  setTimeout(() => {
    const doctorSelect = document.getElementById('doctorSelect');
    if (doctorSelect) {
      doctorSelect.value = doctorId;
      // Scroll to the form
      const card = document.querySelector('#appointment .card');
      if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }, 100);
}

// Handle URL hash on page load
document.addEventListener('DOMContentLoaded', function() {
  const hash = window.location.hash.replace('#', '');
  if (hash) {
    navigateTo(hash);
  }
  
  // Check for doctor_id in URL
  const urlParams = new URLSearchParams(window.location.search);
  const doctorId = urlParams.get('doctor_id');
  if (doctorId) {
    setTimeout(() => {
      navigateTo('appointment');
      const doctorSelect = document.querySelector('select[name="doctor_id"]');
      if (doctorSelect) {
        doctorSelect.value = doctorId;
      }
    }, 100);
  }
});

function openProfileModal() {
  document.getElementById('profileDropdown').classList.remove('active');
  document.getElementById('profileModal').classList.add('active');
}

function closeProfileModal(event) {
  if (!event || event.target.classList.contains('modal-overlay') || event.target.classList.contains('close-modal')) {
    document.getElementById('profileModal').classList.remove('active');
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('profileImage').value = '';
  }
}

function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('imagePreview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const dropdown = document.getElementById('profileDropdown');
  if (!dropdown.contains(event.target)) {
    dropdown.classList.remove('active');
  }
  
  // Close notification dropdown when clicking outside
  const notificationWrapper = document.getElementById('notificationWrapper');
  const notificationDropdown = document.getElementById('notificationDropdown');
  if (notificationWrapper && notificationDropdown && !notificationWrapper.contains(event.target)) {
    notificationDropdown.classList.remove('show');
  }
});

// ============================================
// NOTIFICATION SYSTEM
// ============================================

// Toggle notification dropdown
function toggleNotificationDropdown() {
  const dropdown = document.getElementById('notificationDropdown');
  if (dropdown) {
    dropdown.classList.toggle('show');
    // Load notifications when dropdown is opened
    if (dropdown.classList.contains('show')) {
      loadNotifications();
    }
  }
}

// Load notifications from server
function loadNotifications() {
  const notificationList = document.getElementById('notificationList');
  if (!notificationList) return;
  
  notificationList.innerHTML = '<div class="notification-loading">Loading notifications...</div>';
  
  fetch('get_notifications.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayNotifications(data.notifications, data.unread_count);
        updateNotificationBadge(data.unread_count);
      } else {
        notificationList.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
      }
    })
    .catch(error => {
      console.error('Error loading notifications:', error);
      notificationList.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
    });
}

// Display notifications in the dropdown
function displayNotifications(notifications, unreadCount) {
  const notificationList = document.getElementById('notificationList');
  const notificationCount = document.getElementById('notificationCount');
  
  if (!notificationList) return;
  
  if (notifications.length === 0) {
    notificationList.innerHTML = '<div class="notification-empty">No new notifications</div>';
    if (notificationCount) notificationCount.textContent = '0';
    return;
  }
  
  if (notificationCount) {
    notificationCount.textContent = unreadCount > 0 ? unreadCount : '0';
  }
  
  let html = '';
  notifications.forEach(notif => {
    const unreadClass = !notif.is_read ? 'unread' : '';
    const timeAgo = getTimeAgo(notif.created_at);
    
    html += `
      <div class="notification-item ${unreadClass}" onclick="markNotificationRead(${notif.id}, this)">
        <div class="notification-message">${escapeHtml(notif.message)}</div>
        <div class="notification-time">${timeAgo}</div>
      </div>
    `;
  });
  
  notificationList.innerHTML = html;
}

// Mark notification as read
function markNotificationRead(notificationId, element) {
  fetch('mark_notification_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'notification_id=' + notificationId
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Remove unread styling
        element.classList.remove('unread');
        element.style.fontWeight = 'normal';
        
        // Update badge count
        loadNotifications();
      }
    })
    .catch(error => {
      console.error('Error marking notification as read:', error);
    });
}

// Update notification badge
function updateNotificationBadge(count) {
  const badge = document.getElementById('notificationBadge');
  if (badge) {
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }
}

// Get time ago string
function getTimeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const seconds = Math.floor((now - date) / 1000);
  
  if (seconds < 60) return 'Just now';
  if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
  if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
  if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
  return date.toLocaleDateString();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Check for new notifications on page load
document.addEventListener('DOMContentLoaded', function() {
  // Check and create notifications for upcoming appointments
  fetch('notification_check.php?ajax=1')
    .then(response => response.json())
    .then(data => {
      // After checking, load notifications
      setTimeout(() => {
        loadNotifications();
      }, 500);
    })
    .catch(error => {
      console.error('Error checking notifications:', error);
      // Still try to load existing notifications
      loadNotifications();
    });
  
  // Auto-refresh notifications every 1 minute (optional AJAX feature)
  setInterval(function() {
    // Only refresh if dropdown is not open (to avoid interrupting user)
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown || !dropdown.classList.contains('show')) {
      // Check for new notifications
      fetch('notification_check.php?ajax=1')
        .then(response => response.json())
        .then(data => {
          // Reload notifications
          loadNotifications();
        })
        .catch(error => {
          console.error('Error auto-checking notifications:', error);
        });
    }
  }, 60000); // 1 minute = 60000 milliseconds
});
</script>
</body>
</html>

