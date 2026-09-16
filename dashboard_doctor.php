<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login_doctor.php");
    exit();
}

include 'connection.php';

$doctor_id = $_SESSION['doctor_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];
$specialization = $_SESSION['specialization'] ?? '';
$status_msg = "";
$status_err = "";

// Handle accept action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accept_id'])) {
    $appt_id = intval($_POST['accept_id']);

    // Only confirm if it's still pending (prevents duplicate notifications)
    $update = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ? AND doctor_id = ? AND status = 'pending'");
    $update->bind_param("ii", $appt_id, $doctor_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        $status_msg = "Appointment marked as confirmed.";

        // Create notification for the patient
        $patient_stmt = $conn->prepare("
            SELECT a.user_id, u.full_name as patient_name, a.appointment_date, a.appointment_time
            FROM appointments a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND a.doctor_id = ?
            LIMIT 1
        ");
        $patient_stmt->bind_param("ii", $appt_id, $doctor_id);
        $patient_stmt->execute();
        $patient_result = $patient_stmt->get_result();
        $patient = $patient_result->fetch_assoc();
        $patient_stmt->close();

        if ($patient) {
            $patient_user_id = (int)$patient['user_id'];
            $patient_name = $patient['patient_name'] ?? 'Patient';
            $appointment_date = $patient['appointment_date'] ?? '';
            $appointment_time = $patient['appointment_time'] ?? '';

            $message = "Hello " . $patient_name . ", your appointment on " . $appointment_date . " at " . $appointment_time . " has been confirmed.";

            $insert = $conn->prepare("
                INSERT INTO notifications (user_id, message, appointment_id, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $insert->bind_param("isi", $patient_user_id, $message, $appt_id);
            $insert->execute();
            $insert->close();
        }
    } else {
        $status_err = "Unable to update appointment.";
    }
    $update->close();
}

// Handle reject action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_id'])) {
    $appt_id = intval($_POST['reject_id']);

    // Only cancel if it's still pending (prevents duplicate notifications)
    $update = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND doctor_id = ? AND status = 'pending'");
    $update->bind_param("ii", $appt_id, $doctor_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        $status_msg = "Appointment marked as cancelled.";

        // Create notification for the patient
        $patient_stmt = $conn->prepare("
            SELECT a.user_id, u.full_name as patient_name, a.appointment_date, a.appointment_time
            FROM appointments a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND a.doctor_id = ?
            LIMIT 1
        ");
        $patient_stmt->bind_param("ii", $appt_id, $doctor_id);
        $patient_stmt->execute();
        $patient_result = $patient_stmt->get_result();
        $patient = $patient_result->fetch_assoc();
        $patient_stmt->close();

        if ($patient) {
            $patient_user_id = (int)$patient['user_id'];
            $patient_name = $patient['patient_name'] ?? 'Patient';
            $appointment_date = $patient['appointment_date'] ?? '';
            $appointment_time = $patient['appointment_time'] ?? '';

            $message = "Hello " . $patient_name . ", your appointment on " . $appointment_date . " at " . $appointment_time . " has been cancelled by the doctor.";

            $insert = $conn->prepare("
                INSERT INTO notifications (user_id, message, appointment_id, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $insert->bind_param("isi", $patient_user_id, $message, $appt_id);
            $insert->execute();
            $insert->close();
        }
    } else {
        $status_err = "Unable to cancel appointment.";
    }
    $update->close();
}

// Fetch doctor's appointments (after any updates)
$appointments_stmt = $conn->prepare("SELECT a.*, u.full_name as patient_name, u.email as patient_email FROM appointments a LEFT JOIN users u ON a.user_id = u.id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$appointments_stmt->bind_param("i", $doctor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointments = [];
while($app = $appointments_result->fetch_assoc()) {
    $appointments[] = $app;
}
$appointments_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Doctor Dashboard - Hospital Management System</title>

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
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);
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
        background: #f5576c;
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
        color: #f5576c;
      }
      
      .dropdown-item i {
        width: 20px;
        color: #6c757d;
      }
      
      .dropdown-item:hover i {
        color: #f5576c;
      }

      /* Alert Messages */
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
            <i class="fas fa-user-md"></i>
          </div>
          <span class="profile-name"><?= htmlspecialchars(explode(' ', $full_name)[0]) ?></span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        <div class="profile-dropdown">
          <a href="#" class="dropdown-item" onclick="navigateTo('dashboard'); return false;">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('appointments'); return false;">
            <i class="fas fa-calendar"></i>
            <span>Appointments</span>
          </a>
          <a href="#" class="dropdown-item" onclick="navigateTo('patients'); return false;">
            <i class="fas fa-users"></i>
            <span>Patients</span>
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
    <section id="dashboard" class="page show">
      <div class="welcome-msg">
        <h2>Welcome, Dr. <?= htmlspecialchars($full_name) ?>!</h2>
        <p>Specialization: <?= htmlspecialchars($specialization) ?></p>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <h3 style="color: #0f9fbf; margin-bottom: 10px;">Total Appointments</h3>
          <p style="font-size: 32px; font-weight: bold; color: #667eea;"><?= count($appointments) ?></p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <h3 style="color: #0f9fbf; margin-bottom: 10px;">Pending</h3>
          <p style="font-size: 32px; font-weight: bold; color: #f5576c;"><?= count(array_filter($appointments, function($a) { return $a['status'] == 'pending'; })) ?></p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <h3 style="color: #0f9fbf; margin-bottom: 10px;">Confirmed</h3>
          <p style="font-size: 32px; font-weight: bold; color: #4facfe;"><?= count(array_filter($appointments, function($a) { return $a['status'] == 'confirmed'; })) ?></p>
        </div>
      </div>
    </section>

    <section id="appointments" class="page">
      <div class="welcome-msg">
        <h2>My Appointments</h2>
        <p>Manage patient appointments</p>
      </div>
      
      <?php if(count($appointments) > 0): ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #f0f9ff; border-bottom: 2px solid #0f9fbf;">
                <th style="padding: 12px; text-align: left;">Date</th>
                <th style="padding: 12px; text-align: left;">Time</th>
                <th style="padding: 12px; text-align: left;">Patient</th>
                <th style="padding: 12px; text-align: left;">Reason</th>
                <th style="padding: 12px; text-align: left;">Status</th>
                <th style="padding: 12px; text-align: left;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($appointments as $apt): ?>
                <tr style="border-bottom: 1px solid #e9ecef;">
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['appointment_date']) ?></td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['appointment_time']) ?></td>
                  <td style="padding: 12px;">
                    <strong><?= htmlspecialchars($apt['patient_name'] ?? 'N/A') ?></strong><br>
                    <small style="color: #6c757d;"><?= htmlspecialchars($apt['patient_email'] ?? '') ?></small>
                  </td>
                  <td style="padding: 12px;"><?= htmlspecialchars($apt['reason'] ?? 'N/A') ?></td>
                  <td style="padding: 12px;">
                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                      background: <?= $apt['status']=='confirmed'?'#d4edda':($apt['status']=='pending'?'#fff3cd':'#f8d7da') ?>;
                      color: <?= $apt['status']=='confirmed'?'#155724':($apt['status']=='pending'?'#856404':'#721c24') ?>;">
                      <?= ucfirst($apt['status']) ?>
                    </span>
                  </td>
                  <td style="padding: 12px;">
                    <?php if($apt['status'] === 'pending'): ?>
                      <div style="display:flex; gap:8px; align-items:center;">
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="accept_id" value="<?= (int)$apt['id'] ?>">
                          <button type="submit" class="btn btn-success btn-sm" style="background:#28a745; border:none;">Accept</button>
                        </form>
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="reject_id" value="<?= (int)$apt['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" style="background:#dc3545; border:none;">Reject</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <?php if($apt['status'] === 'confirmed'): ?>
                        <span style="color:#28a745; font-weight:600;">Confirmed</span>
                      <?php elseif($apt['status'] === 'cancelled'): ?>
                        <span style="color:#721c24; font-weight:600;">Cancelled</span>
                      <?php else: ?>
                        <span style="font-weight:600;"><?= ucfirst(htmlspecialchars($apt['status'] ?? '')) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
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

    <section id="patients" class="page">
      <div class="welcome-msg">
        <h2>My Patients</h2>
        <p>View patients assigned to you</p>
      </div>
      
      <?php
      $patients_stmt = $conn->prepare("SELECT DISTINCT u.* FROM users u INNER JOIN appointments a ON u.id = a.user_id WHERE a.doctor_id = ?");
      $patients_stmt->bind_param("i", $doctor_id);
      $patients_stmt->execute();
      $patients_result = $patients_stmt->get_result();
      $patients = [];
      while($pat = $patients_result->fetch_assoc()) {
        $patients[] = $pat;
      }
      $patients_stmt->close();
      ?>
      
      <?php if(count($patients) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
          <?php foreach($patients as $pat): ?>
            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
              <h3 style="color: #0f9fbf; margin-bottom: 10px;"><?= htmlspecialchars($pat['full_name']) ?></h3>
              <p style="color: #6c757d; margin: 5px 0;"><strong>Email:</strong> <?= htmlspecialchars($pat['email']) ?></p>
              <p style="color: #6c757d; margin: 5px 0;"><strong>Phone:</strong> <?= htmlspecialchars($pat['phone'] ?? 'N/A') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="background: white; padding: 20px; border-radius: 10px; text-align: center; color: #6c757d;">No patients assigned.</p>
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
</script>
</body>
</html>

