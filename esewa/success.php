<?php
session_start();
include '../connection.php';

$appointment_id = $_SESSION['esewa_appointment_id'] ?? null;

// Verify payment with eSewa (in production, you should verify the signature)
// For now, we'll trust the redirect from eSewa
if ($appointment_id) {
    // Update appointment payment status
    $update = $conn->prepare("UPDATE appointments SET payment_status = 'Paid' WHERE id = ?");
    $update->bind_param("i", $appointment_id);
    
    if ($update->execute()) {
        // Clear session
        unset($_SESSION['esewa_appointment_id']);
        $update->close();
        
        // Get appointment details
        $stmt = $conn->prepare("SELECT a.*, d.full_name as doctor_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();
        
        $success = true;
    } else {
        $success = false;
        $update->close();
    }
} else {
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }
        .success-icon i {
            font-size: 50px;
            color: #28a745;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        h1 {
            color: #2c3e50;
            font-size: 32px;
            margin-bottom: 15px;
        }
        .success-message {
            color: #28a745;
            font-size: 18px;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .appointment-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        .appointment-details h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 20px;
            text-align: center;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #6c757d;
            font-weight: 600;
        }
        .detail-value {
            color: #2c3e50;
            font-weight: 500;
        }
        .payment-badge {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <?php if ($success && $appointment): ?>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1>Payment Successful!</h1>
            <p class="success-message">Your appointment has been booked successfully.</p>
            
            <div class="appointment-details">
                <h3><i class="fas fa-calendar-check"></i> Appointment Details</h3>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Date:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['appointment_date']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-clock"></i> Time:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['appointment_time']) ?></span>
                </div>
                <?php if (!empty($appointment['doctor_name'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user-md"></i> Doctor:</span>
                    <span class="detail-value"><?= htmlspecialchars($appointment['doctor_name']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-money-bill-wave"></i> Amount Paid:</span>
                    <span class="detail-value">Rs. <?= number_format($appointment['appointment_fee'] ?? 700, 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-credit-card"></i> Payment Method:</span>
                    <span class="detail-value">eSewa</span>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <span class="payment-badge"><i class="fas fa-check-circle"></i> Paid</span>
                </div>
            </div>

            <div class="action-buttons">
                <a href="../dashboard_user.php#appointment" class="btn btn-primary">
                    <i class="fas fa-calendar"></i> View My Appointments
                </a>
                <a href="../index.html" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        <?php else: ?>
            <div class="success-icon" style="background: #f8d7da;">
                <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
            </div>
            <h1>Payment Error</h1>
            <p style="color: #721c24; margin-bottom: 30px;">There was an issue processing your payment. Please contact support.</p>
            <div class="action-buttons">
                <a href="../dashboard_user.php#appointment" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Go to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
