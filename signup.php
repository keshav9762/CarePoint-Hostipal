<?php
include 'connection.php';

$signup_error = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Required fields
    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $errors[] = "All fields are required.";
    }

    // Full name: must not contain numbers
    if ($full_name !== '' && preg_match('/\d/', $full_name)) {
        $errors[] = "Full name must not contain numbers.";
    }

    // Email: validate using PHP filter
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Password length: greater than 8 characters
    if ($password !== '' && strlen($password) <= 8) {
        $errors[] = "Password must be greater than 8 characters.";
    }

    // Password must contain both letters and numbers
    if ($password !== '' && (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
        $errors[] = "Password must contain both letters and numbers.";
    }

    // Confirm password match
    if ($password !== '' && $confirm_password !== '' && $password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }

    if (empty($errors)) {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Email already exists!";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into database
            $insert = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $full_name, $email, $hashed_password);

            if ($insert->execute()) {
                header("Location: login_user.php?registered=1");
                exit();
            } else {
                $errors[] = "Unable to create account. Please try again.";
            }

            $insert->close();
        }

        $check->close();
    }

    // Combine errors into existing message variable for display
    if (!empty($errors)) {
        $signup_error = implode(' ', $errors);
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up</title>

  <!-- Google Fonts for modern and simple look -->
  <link
    href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap"
    rel="stylesheet"
  />

  <!-- Main CSS -->
  <link rel="stylesheet" href="style.css?v=2" />

  <!-- Font Awesome for icons -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />

  <!-- Bootstrap (for grid & utility classes) -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />

  <style>
    .password-wrapper {
      position: relative;
      width: 90%;
      margin: 10px 0;
      display: inline-block;
    }
    .password-wrapper input {
      width: 100%;
      padding: 12px 40px 12px 12px;
      margin: 0;
      border: 1px solid #aaa;
      border-radius: 6px;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6c757d;
      font-size: 16px;
      transition: color 0.3s;
      pointer-events: auto;
    }
    .toggle-password:hover {
      color: #0f9fbf;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header class="header">
    <img src="images/logo.png" alt="Logo" />

    <!-- Navigation -->
    <nav class="nav">
      <div class="nav-links">
        <a href="index.html#home">Home</a>
        <a href="index.html#about">About</a>
        <a href="index.html#service">Service</a>
        <a href="index.html#gallery">Gallery</a>
        <a href="index.html#contact">Contact</a>
      </div>

      <div class="login-dropdown desktop-only">
        <button class="login-dropdown-btn" onclick="toggleLoginDropdown()">
          <i class="fas fa-sign-in-alt"></i> Login
          <i
            class="fas fa-chevron-down"
            style="font-size: 12px; margin-left: 5px"
          ></i>
        </button>
        <div class="login-dropdown-content" id="loginDropdown">
          <a href="login_user.php" class="login-option">
            <div class="login-option-icon user">
              <i class="fas fa-user"></i>
            </div>
            <span class="login-option-text"> User Login</span>
          </a>
          <a href="login_doctor.php" class="login-option">
            <div class="login-option-icon doctor">
              <i class="fas fa-user-md"></i>
            </div>
            <span class="login-option-text">Doctor Login&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
          </a>
          <a href="login_admin.php" class="login-option">
            <div class="login-option-icon admin">
              <i class="fas fa-user-shield"></i>
            </div>
            <span class="login-option-text">Admin Login</span>
          </a>
        </div>
      </div>
    </nav>

    <!-- Menu Icon -->
    <div class="icons">
      <div id="menubar" class="fas fa-bars"></div>
    </div>
  </header>

<div class="form-container signup-container">
  <h2>Sign Up</h2>

  <?php if($signup_error !== ""): ?>
    <p class="error-msg" style="color:#c0392b; background:#fbeaea; padding:10px 14px; border-radius:6px; margin-bottom:15px;">
      <?= htmlspecialchars($signup_error) ?>
    </p>
  <?php endif; ?>

  <form class="signup-form" action="signup.php" method="post">
    <input type="text" placeholder="Full Name" name="full_name" required>
    <input type="email" placeholder="Email" name="email"  style="text-transform: none;" required>
    <div class="password-wrapper">
      <input type="password" placeholder="Password" name="password" id="password" required>
      <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
    </div>
    <div class="password-wrapper">
      <input type="password" placeholder="Confirm Password" name="confirm_password" id="confirm_password" required>
      <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
    </div>
    <button type="submit">Sign Up</button>
  </form>

  <p>Already have an account? <a href="login_user.php">Login</a></p>
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
        <a href="login_user.php">Login</a>
        <a href="signup.php">Sign Up</a>
        <a href="login_user.php?next=appointment">Book Appointment</a>
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
          Gaindakot, Nepal
        </p>
      </div>
    </div>
    <p class="footer-copy">
      &copy; 2025 CarePoint Hospital. All rights reserved.
    </p>
  </footer>

<script>
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const icon = field.nextElementSibling;
  
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>

  <!-- JavaScript -->
  <script src="script.js"></script>
</body>
</html> 


