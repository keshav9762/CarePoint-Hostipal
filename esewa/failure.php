<?php
session_start();
include '../connection.php';

$appointment_id = $_SESSION['esewa_appointment_id'] ?? null;

// If payment failed, delete or mark the appointment as cancelled
if ($appointment_id) {
    // Delete the pending appointment since payment failed
    $delete = $conn->prepare("DELETE FROM appointments WHERE id = ? AND payment_status = 'Pending' AND payment_method = 'esewa'");
    $delete->bind_param("i", $appointment_id);
    $delete->execute();
    $delete->close();
    
    // Clear session
    unset($_SESSION['esewa_appointment_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
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
        .failure-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .failure-icon {
            width: 100px;
            height: 100px;
            background: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }
        .failure-icon i {
            font-size: 50px;
            color: #dc3545;
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
        .failure-message {
            color: #dc3545;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-box p {
            color: #856404;
            margin: 0;
            line-height: 1.6;
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
        .reasons {
            text-align: left;
            margin-top: 20px;
            padding-left: 20px;
        }
        .reasons li {
            color: #856404;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="failure-container">
        <div class="failure-icon">
            <i class="fas fa-times"></i>
        </div>
        <h1>Payment Failed</h1>
        <p class="failure-message">Your payment could not be processed.</p>
        
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> What happened?</h3>
            <p>Your appointment was not confirmed because the payment was unsuccessful or cancelled. The appointment booking has been cancelled.</p>
            
            <div class="reasons">
                <strong>Possible reasons:</strong>
                <ul style="margin-top: 10px;">
                    <li>Payment was cancelled</li>
                    <li>Insufficient balance</li>
                    <li>Payment gateway error</li>
                    <li>Network connectivity issue</li>
                </ul>
            </div>
        </div>

        <div class="action-buttons">
            <a href="../dashboard_user.php#appointment" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Try Booking Again
            </a>
            <a href="../index.html" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
