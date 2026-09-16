<?php
session_start();

// eSewa TEST credentials
$secretKey = "8gBm/:&EnhH.1/q"; // TEST SECRET KEY
$productCode = "EPAYTEST";

// Get appointment_id and amount from URL or POST
$appointment_id = $_GET['appointment_id'] ?? $_POST['appointment_id'] ?? null;
$amount = $_GET['amount'] ?? $_POST['amount'] ?? 700.00;

if (!$appointment_id) {
    header("Location: ../dashboard_user.php?error=invalid_appointment");
    exit();
}

// Store appointment_id in session for success callback
$_SESSION['esewa_appointment_id'] = $appointment_id;

$taxAmount = 0;
$totalAmount = floatval($amount) + $taxAmount;

// UNIQUE transaction UUID
$transactionUUID = "TXN_" . time() . "_" . $appointment_id;

// Signature fields
$signedFieldNames = "total_amount,transaction_uuid,product_code";
$message = "total_amount=$totalAmount,transaction_uuid=$transactionUUID,product_code=$productCode";

// Generate signature
$signature = base64_encode(
    hash_hmac('sha256', $message, $secretKey, true)
);

// Get base URL for success/failure URLs
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$baseDir = dirname(dirname($_SERVER['PHP_SELF']));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to eSewa...</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .redirect-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
        }
        .redirect-box i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }
        .redirect-box h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .redirect-box p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="redirect-box">
        <i class="fas fa-credit-card"></i>
        <h2>Redirecting to eSewa</h2>
        <p>Please wait while we redirect you to the payment gateway...</p>
        <div class="spinner"></div>
    </div>

<form action="https://rc-epay.esewa.com.np/api/epay/main/v2/form" method="POST" id="esewaForm">
    <input type="hidden" name="amount" value="<?= $amount ?>">
    <input type="hidden" name="tax_amount" value="<?= $taxAmount ?>">
    <input type="hidden" name="total_amount" value="<?= $totalAmount ?>">
    <input type="hidden" name="transaction_uuid" value="<?= $transactionUUID ?>">
    <input type="hidden" name="product_code" value="<?= $productCode ?>">
    <input type="hidden" name="product_service_charge" value="0">
    <input type="hidden" name="product_delivery_charge" value="0">
    <input type="hidden" name="success_url" value="<?= $baseUrl . $baseDir ?>/esewa/success.php">
    <input type="hidden" name="failure_url" value="<?= $baseUrl . $baseDir ?>/esewa/failure.php">
    <input type="hidden" name="signed_field_names" value="<?= $signedFieldNames ?>">
    <input type="hidden" name="signature" value="<?= $signature ?>">
</form>

<script>
    // Auto-submit form after a short delay
    setTimeout(function() {
        document.getElementById('esewaForm').submit();
    }, 1000);
</script>
</body>
</html>
