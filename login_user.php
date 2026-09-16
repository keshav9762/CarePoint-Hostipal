<?php
include 'connection.php';
session_start();

$error = "";
$errors = [];

  //  AUTO-FILL
$remembered_email = $_COOKIE['remember_email'] ?? '';
$remembered_password = $_COOKIE['remember_password'] ?? '';

/* -----------------------------
   WHEN LOGIN FORM SUBMITTED
------------------------------ */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);

    // Basic required-field validation
    if ($email === '' || $password === '') {
        $errors[] = "Email and password are required.";
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

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                // Session data
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = 'user';

                /* -----------------------------
                   REMEMBER ME (SAVE COOKIES)
                ------------------------------ */
                if ($remember) {
                    setcookie("remember_email", $email, time() + (86400 * 30), "/");
                    setcookie("remember_password", $password, time() + (86400 * 30), "/");
                } else {
                    setcookie("remember_email", "", time() - 3600, "/");
                    setcookie("remember_password", "", time() - 3600, "/");
                }

                if (isset($_GET['next']) && $_GET['next'] === "appointment") {
                    header("Location: dashboard_user.php#appointment");
                    exit();
                }

                header("Location: dashboard_user.php");
                exit();
            } else {
                $errors[] = "Incorrect password";
            }
        } else {
            $errors[] = "Email not found";
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        // Store combined error message for existing HTML output
        $error = implode(' ', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Login</title>
<link rel="stylesheet" href="style.css?v=2">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

<body class="auth-page">
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
          <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: 5px"></i>
        </button>
        <div class="login-dropdown-content" id="loginDropdown">
          <a href="login_user.php" class="login-option">
            <div class="login-option-icon user">
              <i class="fas fa-user"></i>
            </div>
            <span class="login-option-text">User Login</span>
          </a>
          <a href="login_doctor.php" class="login-option">
            <div class="login-option-icon doctor">
              <i class="fas fa-user-md"></i>
            </div>
            <span class="login-option-text">Doctor Login</span>
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

<main class="auth-main">
<div class="form-container login-container">
  <div class="login-icon-wrapper">
    <div class="login-icon-circle">
      <i class="fas fa-user"></i>
    </div>
    <div class="login-badge">USER LOGIN</div>
  </div>

  <form class="login-form" action="login_user.php" method="post">

    <?php if($error != ""): ?>
        <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <!-- AUTO-FILL EMAIL + PASSWORD -->
    <input type="email" name="email" placeholder="Email" required 
           value="<?= htmlspecialchars($remembered_email) ?>">

    <div class="password-wrapper">
      <input type="password" name="password" id="password" placeholder="Password" required
             value="<?= htmlspecialchars($remembered_password) ?>">
      <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
    </div>

    
    <div class="checkbox-container">
        <input type="checkbox" name="remember_me" id="remember_me"
               <?php if ($remembered_email != '') echo "checked"; ?>>
        <label for="remember_me">Remember Me</label>
    </div>

    <button type="submit" class="login-submit-btn">
      <span>LOGIN AS USER</span>
      <i class="fas fa-arrow-right"></i>
    </button>
  </form>

  <p style="text-align: center; margin-top: 20px;">Don't have an account? <a href="signup.php">Sign Up</a></p>
  
  <div style="text-align: center; margin-top: 20px;">
    <a href="index.html" class="back-home-btn">
      <i class="fas fa-arrow-left"></i> Back to Home
    </a>
  </div>
</div>
</main>

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
      <a href="login_.php">Login</a>
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
        Kathmandu, Nepal
      </p>
    </div>
  </div>
  <p class="footer-copy">
    &copy; 2025 CarePoint Hospital. All rights reserved.
  </p>
</footer>

<script src="script.js"></script>
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

</body>
</html>

