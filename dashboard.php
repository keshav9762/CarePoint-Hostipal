<?php
session_start();
include 'connection.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];

// Split full name
$names = explode(' ', $full_name);
$first_name  = $names[0] ?? '';
$last_name   = $names[count($names)-1] ?? '';
$middle_name = (count($names) > 2) ? implode(' ', array_slice($names, 1, -1)) : '';

// Fetch existing profile if exists
$profileQuery = $conn->query("SELECT * FROM profile WHERE user_id='$user_id'");
$row = $profileQuery->fetch_assoc() ?? [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_profile'])) {
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $nationality = $_POST['nationality'];
    $phone = $_POST['phone'];
    $street = $_POST['street'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $postal = $_POST['postal'];
    $em_phone = $_POST['emergency_phone'];
    $blood_group = $_POST['blood_group'];
    $allergies = $_POST['allergies'];
    $chronic = $_POST['chronic_diseases'];
    $medications = $_POST['medications'];
    $primary_doctor = $_POST['primary_doctor'];

    if($row){
        // Update
        $conn->query("UPDATE profile SET 
            first_name='$first_name', middle_name='$middle_name', last_name='$last_name',
            dob='$dob', gender='$gender', nationality='$nationality', phone='$phone',
            street='$street', city='$city', state='$state', postal_code='$postal',
            emergency_phone='$em_phone', blood_group='$blood_group', allergies='$allergies',
            chronic_diseases='$chronic', medications='$medications', primary_doctor='$primary_doctor'
            WHERE user_id='$user_id'");
    } else {
        // Insert
        $conn->query("INSERT INTO profile (
            user_id, first_name, middle_name, last_name, dob, gender, nationality,
            phone, street, city, state, postal_code, emergency_phone, blood_group,
            allergies, chronic_diseases, medications, primary_doctor
        ) VALUES (
            '$user_id','$first_name','$middle_name','$last_name','$dob','$gender','$nationality',
            '$phone','$street','$city','$state','$postal','$em_phone','$blood_group','$allergies',
            '$chronic','$medications','$primary_doctor'
        )");
    }

    echo "<p style='color:green;'>Profile saved successfully!</p>";
    $profileQuery = $conn->query("SELECT * FROM profile WHERE user_id='$user_id'");
    $row = $profileQuery->fetch_assoc() ?? [];
}
?>

<!DOCTYPE html>



<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hospital Management System</title>

    <!-- Google Fonts for modern and simple look -->
    <link
      href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap"
      rel="stylesheet"
    />

    <!-- Main CSS -->
    <link rel="stylesheet" href="style.css" />

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
  
  .dashboard {
    margin-top: 70px;
    min-height: calc(100vh - 70px);
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
          <a href="#home">Home</a>
          <a href="#about">About</a>
          <a href="#service">Service</a>
          <a href="#gallery">Gallery</a>
          <a href="#contact">Contact</a>
        </div>

        <a href="logout.php" class="login-btn">Logout</a>
      </nav>

      <!-- Menu Icon -->
      <!-- <div class="icons">
        <div id="menubar" class="fas fa-bars"></div>
      </div> -->
    </header>

<div class="dashboard">
  <div class="sidebar">
    <button class="menu active" data-page="profile">Profile</button>
    <button class="menu" data-page="appointment">Appointments</button>
    <button class="menu" data-page="patients">Patients</button>
    <button class="menu" data-page="doctors">Doctors</button>
  </div>

  <div class="content">
    <section id="profile" class="page show">
      

      <form method="POST" class="profile-form">
        <div class="form-section">
          <h3>Personal Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>First Name</label>
              <input type="text" name="first_name" value="<?= $first_name ?>" placeholder="First Name" style="text-transform: capitalize;" readonly>
            </div>
            <div class="form-group">
              <label>Middle Name</label>
              <input type="text" name="middle_name" value="<?= $middle_name ?>" placeholder="Middle Name" style="text-transform: capitalize;" readonly>
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?= $last_name ?>" placeholder="Last Name" style="text-transform: capitalize;" readonly>
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
          <h3>Contact Information</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Phone</label>
              <input type="text" name="phone" placeholder="Phone" value="<?= @$row['phone'] ?>">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" value="<?= $email ?>" readonly>
            </div>
            <div class="form-group full-width">
              <label>Street Address</label>
              <input type="text" name="street" value="<?= @$row['street'] ?>" placeholder="Street Address">
            </div>
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" value="<?= @$row['city'] ?>" placeholder="City">
            </div>
            <div class="form-group">
              <label>State</label>
              <input type="text" name="state" value="<?= @$row['state'] ?>" placeholder="State">
            </div>
            <div class="form-group">
              <label>Postal Code</label>
              <input type="text" name="postal" value="<?= @$row['postal_code'] ?>" placeholder="Postal Code">
            </div>
            <div class="form-group">
              <label>Emergency Contact</label>
              <input type="text" name="emergency_phone" value="<?= @$row['emergency_phone'] ?>" placeholder="Emergency Number">
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3>Medical Information</h3>
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
              <textarea name="allergies" placeholder="List any allergies"><?= @$row['allergies'] ?></textarea>
            </div>
            <div class="form-group">
              <label>Chronic Diseases</label>
              <textarea name="chronic_diseases" placeholder="List chronic diseases"><?= @$row['chronic_diseases'] ?></textarea>
            </div>
            <div class="form-group">
              <label>Medications</label>
              <textarea name="medications" placeholder="Current medications"><?= @$row['medications'] ?></textarea>
            </div>
            <div class="form-group">
              <label>Primary Doctor</label>
              <input type="text" name="primary_doctor" value="<?= @$row['primary_doctor'] ?>" placeholder="Primary Doctor Name">
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" name="save_profile">Save Profile</button>
        </div>
      </form>
    </section>

    <section id="appointment" class="page">
      <h2>Appointments</h2>
      <p>Manage your appointments here.</p>
    </section>

    <section id="patients" class="page">
      <h2>Patients</h2>
      <p>Patient list and CRUD operations.</p>
    </section>

    <section id="doctors" class="page">
      <h2>Doctors</h2>
      <p>Doctor information.</p>
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
          <a href="signup.php">Sign Up</a>
          <a href="#appointment">Book Appointment</a>
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
</body>
</html>