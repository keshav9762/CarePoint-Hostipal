<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit();
}

include 'connection.php';

$admin_id = $_SESSION['admin_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];
$doctor_msg = "";
$doctor_err = "";

// Handle creating doctor accounts
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_doctor'])) {
    $doc_name = trim($_POST['doctor_name'] ?? '');
    $doc_email = trim($_POST['doctor_email'] ?? '');
    $doc_phone = trim($_POST['doctor_phone'] ?? '');
    $doc_specialization = trim($_POST['doctor_specialization'] ?? '');
    $doc_qualification = trim($_POST['doctor_qualification'] ?? '');
    $doc_experience = intval($_POST['doctor_experience'] ?? 0);
    $doc_password = $_POST['doctor_password'] ?? '';

    if ($doc_name === '' || $doc_email === '' || $doc_password === '') {
        $doctor_err = "Name, email, and password are required.";
    } elseif (!filter_var($doc_email, FILTER_VALIDATE_EMAIL)) {
        $doctor_err = "Please enter a valid email for the doctor.";
    } else {
        // Check unique email
        $check = $conn->prepare("SELECT id FROM doctors WHERE email = ? LIMIT 1");
        $check->bind_param("s", $doc_email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $doctor_err = "Doctor email already exists.";
        } else {
            $hashed_password = password_hash($doc_password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO doctors (full_name, email, password, phone, specialization, qualification, experience_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("ssssssi", $doc_name, $doc_email, $hashed_password, $doc_phone, $doc_specialization, $doc_qualification, $doc_experience);

            if ($insert->execute()) {
                $doctor_msg = "Doctor account created. They can log in via Doctor Login.";
            } else {
                $doctor_err = "Could not create doctor. Please try again.";
            }
            $insert->close();
        }
        $check->close();
    }
}

// Handle updating doctor accounts
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_doctor'])) {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $doc_name = trim($_POST['doctor_name'] ?? '');
    $doc_email = trim($_POST['doctor_email'] ?? '');
    $doc_phone = trim($_POST['doctor_phone'] ?? '');
    $doc_specialization = trim($_POST['doctor_specialization'] ?? '');
    $doc_qualification = trim($_POST['doctor_qualification'] ?? '');
    $doc_experience = intval($_POST['doctor_experience'] ?? 0);

    if ($doctor_id <= 0) {
        $doctor_err = "Invalid doctor selected for update.";
    } elseif ($doc_name === '' || $doc_email === '') {
        $doctor_err = "Name and email are required for update.";
    } elseif (!filter_var($doc_email, FILTER_VALIDATE_EMAIL)) {
        $doctor_err = "Please enter a valid email for the doctor.";
    } else {
        // Ensure email is unique for other doctors
        $check = $conn->prepare("SELECT id FROM doctors WHERE email = ? AND id != ? LIMIT 1");
        $check->bind_param("si", $doc_email, $doctor_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $doctor_err = "Another doctor with this email already exists.";
        } else {
            $update = $conn->prepare("UPDATE doctors SET full_name = ?, email = ?, phone = ?, specialization = ?, qualification = ?, experience_years = ? WHERE id = ?");
            $update->bind_param("ssssssi", $doc_name, $doc_email, $doc_phone, $doc_specialization, $doc_qualification, $doc_experience, $doctor_id);

            if ($update->execute()) {
                $doctor_msg = "Doctor details updated successfully.";
            } else {
                $doctor_err = "Could not update doctor. Please try again.";
            }
            $update->close();
        }
        $check->close();
    }
}

// Fetch all users
$users_stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}
$users_stmt->close();

// Fetch all doctors
$doctors_stmt = $conn->prepare("SELECT * FROM doctors ORDER BY created_at DESC");
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();
$doctors = [];
while($doc = $doctors_result->fetch_assoc()) {
    $doctors[] = $doc;
}
$doctors_stmt->close();

// Fetch all appointments with payment info
$appointments_stmt = $conn->prepare("SELECT a.*, u.full_name as patient_name, d.full_name as doctor_name FROM appointments a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointments = [];
while($app = $appointments_result->fetch_assoc()) {
    $appointments[] = $app;
}
$appointments_stmt->close();

// Calculate payment stats
$paid_count = count(array_filter($appointments, function($a) { 
    return isset($a['payment_status']) && $a['payment_status'] == 'Paid'; 
}));
$pending_count = count(array_filter($appointments, function($a) { 
    return isset($a['payment_status']) && $a['payment_status'] == 'Pending'; 
}));
$total_revenue = array_sum(array_map(function($a) {
    return ($a['payment_status'] == 'Paid' && isset($a['appointment_fee'])) ? floatval($a['appointment_fee']) : 0;
}, $appointments));
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Hospital Management System</title>

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
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(79, 172, 254, 0.3);
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
      
      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
      }
      
      .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      
      .stat-card h3 {
        color: #0f9fbf;
        margin-bottom: 10px;
        font-size: 14px;
      }
      
      .stat-card p {
        font-size: 32px;
        font-weight: bold;
        color: #667eea;
        margin: 0;
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
        background: #4facfe;
        overflow: hidden;
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
        color: #4facfe;
      }
      
      .dropdown-item i {
        width: 20px;
        color: #6c757d;
      }
      
      .dropdown-item:hover i {
        color: #4facfe;
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

        .profile-dropdown-wrapper {
          order: 2;
          margin-left: auto;
          margin-right: 10px;
        }

        .icons {
          order: 3;
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

      <!-- Profile Dropdown -->
      <div class="profile-dropdown-wrapper" id="profileDropdown">
        <div class="profile-trigger" onclick="toggleDropdown()">
          <div class="profile-avatar">
            <i class="fas fa-user-shield"></i>
          </div>
          <span class="profile-name"><?= htmlspecialchars(explode(' ', $full_name)[0]) ?></span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="profile-dropdown">
          <a href="#" class="dropdown-item" onclick="navigateTo('dashboard'); return false;">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('users'); return false;">
            <i class="fas fa-users"></i>
            <span>Users</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('doctors'); return false;">
            <i class="fas fa-user-md"></i>
            <span>Doctors</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('appointments'); return false;">
            <i class="fas fa-calendar"></i>
            <span>Appointments</span>
          </a>
          <a href="logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>

      <!-- Menu Icon -->
      <div class="icons">
        <div id="menubar" class="fas fa-bars"></div>
      </div>
    </header>

<div class="dashboard">
  <div class="content">
    <section id="dashboard" class="page show">
      <div class="welcome-msg">
        <h2>Welcome, <?= htmlspecialchars($full_name) ?>!</h2>
        <p>Administrator Control Panel</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Users</h3>
          <p><?= count($users) ?></p>
        </div>
        <div class="stat-card">
          <h3>Total Doctors</h3>
          <p><?= count($doctors) ?></p>
        </div>
        <div class="stat-card">
          <h3>Total Appointments</h3>
          <p><?= count($appointments) ?></p>
        </div>
        <div class="stat-card">
          <h3>Pending Appointments</h3>
          <p><?= count(array_filter($appointments, function($a) { return $a['status'] == 'pending'; })) ?></p>
        </div>
        <div class="stat-card">
          <h3>Paid Appointments</h3>
          <p><?= $paid_count ?></p>
        </div>
        <div class="stat-card">
          <h3>Pending Payments</h3>
          <p><?= $pending_count ?></p>
        </div>
        <div class="stat-card">
          <h3>Total Revenue</h3>
          <p>Rs. <?= number_format($total_revenue, 2) ?></p>
        </div>
      </div>
    </section>

    <section id="users" class="page">
      <div class="welcome-msg">
        <h2>All Users</h2>
        <p>Manage patient accounts</p>
      </div>
      
      <?php if(count($users) > 0): ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead>
              <tr style="background: #f0f9ff; border-bottom: 2px solid #0f9fbf;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Full Name</th>
                <th style="padding: 12px; text-align: left;">Email</th>
                <th style="padding: 12px; text-align: left;">Phone</th>
                <th style="padding: 12px; text-align: left;">Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($users as $user): ?>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 12px;"><?= htmlspecialchars($user['id']) ?></td>
                  <td style="padding: 12px;"><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($user['email']) ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($user['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="background: white; padding: 20px; border-radius: 10px; text-align: center; color: #6c757d;">No users found.</p>
      <?php endif; ?>
    </section>

    <section id="doctors" class="page">
      <div class="welcome-msg">
        <h2>All Doctors</h2>
        <p>Manage doctor accounts</p>
      </div>

      <?php if($doctor_msg): ?>
        <p style="color:#155724; background:#d4edda; padding:12px 16px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($doctor_msg) ?></p>
      <?php endif; ?>
      <?php if($doctor_err): ?>
        <p style="color:#721c24; background:#f8d7da; padding:12px 16px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($doctor_err) ?></p>
      <?php endif; ?>

      <div style="background:white; padding:20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); margin-bottom:20px;">
        <h3 style="margin-bottom:12px; color:#0f9fbf;">Add Doctor Account</h3>
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="doctor_name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="doctor_email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="doctor_phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="text" name="doctor_password" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Specialization</label>
            <input type="text" name="doctor_specialization" class="form-control" placeholder="e.g., Cardiology">
          </div>
          <div class="col-md-6">
            <label class="form-label">Qualification</label>
            <input type="text" name="doctor_qualification" class="form-control" placeholder="e.g., MBBS, MD">
          </div>
          <div class="col-md-6">
            <label class="form-label">Experience (years)</label>
            <input type="number" name="doctor_experience" class="form-control" min="0" value="0">
          </div>
          <div class="col-12">
            <button type="submit" name="create_doctor" class="btn btn-primary" style="background:#0f9fbf; border:none;">Create Doctor</button>
          </div>
        </form>
      </div>
      
      <?php if(count($doctors) > 0): ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
            <thead>
              <tr style="background: #f0f9ff; border-bottom: 2px solid #0f9fbf;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Full Name</th>
                <th style="padding: 12px; text-align: left;">Email</th>
                <th style="padding: 12px; text-align: left;">Specialization</th>
                <th style="padding: 12px; text-align: left;">Experience</th>
                <th style="padding: 12px; text-align: left;">Phone</th>
                <th style="padding: 12px; text-align: left;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($doctors as $doc): ?>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 12px;"><?= htmlspecialchars($doc['id']) ?></td>
                  <td style="padding: 12px;"><strong><?= htmlspecialchars($doc['full_name']) ?></strong></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($doc['email']) ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($doc['specialization'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($doc['experience_years'] ?? 0) ?> years</td>
                  <td style="padding: 12px;"><?= htmlspecialchars($doc['phone'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;">
                    <button 
                      type="button" 
                      class="btn btn-sm btn-outline-primary"
                      onclick="openDoctorEditModal(this)"
                      data-id="<?= (int)$doc['id'] ?>"
                      data-name="<?= htmlspecialchars($doc['full_name'], ENT_QUOTES) ?>"
                      data-email="<?= htmlspecialchars($doc['email'], ENT_QUOTES) ?>"
                      data-phone="<?= htmlspecialchars($doc['phone'] ?? '', ENT_QUOTES) ?>"
                      data-specialization="<?= htmlspecialchars($doc['specialization'] ?? '', ENT_QUOTES) ?>"
                      data-qualification="<?= htmlspecialchars($doc['qualification'] ?? '', ENT_QUOTES) ?>"
                      data-experience="<?= htmlspecialchars($doc['experience_years'] ?? 0, ENT_QUOTES) ?>"
                    >
                      <i class="fas fa-edit"></i> Edit
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="background: white; padding: 20px; border-radius: 10px; text-align: center; color: #6c757d;">No doctors found.</p>
      <?php endif; ?>

      <!-- Edit Doctor Modal -->
      <div class="modal fade" id="editDoctorModal" tabindex="-1" aria-labelledby="editDoctorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <form method="POST" class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editDoctorModalLabel">Edit Doctor</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="doctor_id" id="editDoctorId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full Name</label>
                  <input type="text" name="doctor_name" id="editDoctorName" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="doctor_email" id="editDoctorEmail" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" name="doctor_phone" id="editDoctorPhone" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Specialization</label>
                  <input type="text" name="doctor_specialization" id="editDoctorSpecialization" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Qualification</label>
                  <input type="text" name="doctor_qualification" id="editDoctorQualification" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Experience (years)</label>
                  <input type="number" name="doctor_experience" id="editDoctorExperience" class="form-control" min="0" value="0">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="update_doctor" class="btn btn-primary" style="background:#0f9fbf; border:none;">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </section>
    
    <section id="appointments" class="page">
      <div class="welcome-msg">
        <h2>All Appointments</h2>
        <p>View and manage all appointments</p>
      </div>
      
      <?php if(count($appointments) > 0): ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
            <thead>
              <tr style="background: #f0f9ff; border-bottom: 2px solid #0f9fbf;">
                <th style="padding: 12px; text-align: left;">Date</th>
                <th style="padding: 12px; text-align: left;">Time</th>
                <th style="padding: 12px; text-align: left;">Patient</th>
                <th style="padding: 12px; text-align: left;">Doctor</th>
                <th style="padding: 12px; text-align: left;">Reason</th>
                <th style="padding: 12px; text-align: left;">Status</th>
                <th style="padding: 12px; text-align: left;">Payment Method</th>
                <th style="padding: 12px; text-align: left;">Payment Status</th>
                <th style="padding: 12px; text-align: left;">Fee</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($appointments as $apt): ?>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['appointment_date']) ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['appointment_time']) ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['patient_name'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['doctor_name'] ?? 'Not Assigned') ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['reason'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;">
                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                      background: <?= $apt['status']=='confirmed'?'#d4edda':($apt['status']=='pending'?'#fff3cd':($apt['status']=='completed'?'#cce5ff':'#f8d7da')) ?>;
                      color: <?= $apt['status']=='confirmed'?'#155724':($apt['status']=='pending'?'#856404':($apt['status']=='completed'?'#004085':'#721c24')) ?>;">
                      <?= ucfirst($apt['status']) ?>
                    </span>
                  </td>
                  <td style="padding: 12px;">
                    <?php if (!empty($apt['payment_method'])): ?>
                      <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
                        background: <?= $apt['payment_method']=='esewa'?'#e7f3ff':'#fff3cd' ?>;
                        color: <?= $apt['payment_method']=='esewa'?'#004085':'#856404' ?>;">
                        <i class="fas fa-<?= $apt['payment_method']=='esewa'?'mobile-alt':'money-bill' ?>"></i>
                        <?= ucfirst($apt['payment_method']) == 'Esewa' ? 'eSewa' : ucfirst($apt['payment_method']) ?>
                      </span>
                    <?php else: ?>
                      <span style="color: #6c757d; font-size: 12px;">N/A</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 12px;">
                    <?php if (!empty($apt['payment_status'])): ?>
                      <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
                        background: <?= $apt['payment_status']=='Paid'?'#d4edda':'#fff3cd' ?>;
                        color: <?= $apt['payment_status']=='Paid'?'#155724':'#856404' ?>;">
                        <i class="fas fa-<?= $apt['payment_status']=='Paid'?'check-circle':'clock' ?>"></i>
                        <?= htmlspecialchars($apt['payment_status']) ?>
                      </span>
                    <?php else: ?>
                      <span style="color: #6c757d; font-size: 12px;">N/A</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 12px;">
                    <strong style="color: <?= (!empty($apt['payment_status']) && $apt['payment_status']=='Paid') ? '#155724' : '#856404' ?>;">
                      Rs. <?= number_format($apt['appointment_fee'] ?? 700, 2) ?>
                    </strong>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="background: white; padding: 20px; border-radius: 10px; text-align: center; color: #6c757d;">No appointments found.</p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
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

// Handle URL hash on page load
document.addEventListener('DOMContentLoaded', function() {
  const hash = window.location.hash.replace('#', '');
  if (hash) {
    navigateTo(hash);
  }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const dropdown = document.getElementById('profileDropdown');
  if (!dropdown.contains(event.target)) {
    dropdown.classList.remove('active');
  }
});

// Open Edit Doctor modal and populate fields
function openDoctorEditModal(button) {
  const modalEl = document.getElementById('editDoctorModal');
  if (!modalEl) return;

  document.getElementById('editDoctorId').value = button.getAttribute('data-id') || '';
  document.getElementById('editDoctorName').value = button.getAttribute('data-name') || '';
  document.getElementById('editDoctorEmail').value = button.getAttribute('data-email') || '';
  document.getElementById('editDoctorPhone').value = button.getAttribute('data-phone') || '';
  document.getElementById('editDoctorSpecialization').value = button.getAttribute('data-specialization') || '';
  document.getElementById('editDoctorQualification').value = button.getAttribute('data-qualification') || '';
  document.getElementById('editDoctorExperience').value = button.getAttribute('data-experience') || 0;

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
</script>
</body>
</html>

