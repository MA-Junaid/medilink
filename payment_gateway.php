<?php
session_start();
require '../../conn.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['appt_id']) || !isset($_GET['amount'])) {
    die("Invalid access. Missing appointment details.");
}

$apptId = $_GET['appt_id'];
$amount = $_GET['amount'];
$returnUrl = $_GET['return_url'] ?? 'dashboard.php'; // Default to dashboard if no return url

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $method = $input['method'] ?? 'card'; 
    
    $transactionId = "TXN" . strtoupper(bin2hex(random_bytes(4))) . date('His');
    $paymentId = "PAY" . strtoupper(bin2hex(random_bytes(4)));
    $status = 'paid';
    
    // Insert into payments table
    $stmt = $con->prepare("INSERT INTO payments (payment_id, appointment_id, amount, method, status, transaction_id, payment_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $con->error]);
        exit;
    }
    
    $stmt->bind_param("ssdsss", $paymentId, $apptId, $amount, $method, $status, $transactionId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'redirect' => $returnUrl]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Payment failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment - MediLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --bg-page: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-main);
        }
        
        .payment-container {
            background: var(--bg-card);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            background: #e0e7ff;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 20px;
        }
        
        h2 { font-weight: 700; margin-bottom: 10px; }
        
        .amount-display {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
            margin: 20px 0;
        }
        
        .description {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .method-btn {
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .method-btn:hover {
            border-color: var(--primary);
            background: #eff6ff;
            transform: translateY(-2px);
        }
        
        .method-btn i {
            font-size: 24px;
            color: #4b5563;
        }
        
        .method-btn span {
            font-size: 14px;
            font-weight: 600;
        }
        
        .method-btn.selected {
            border-color: var(--primary);
            background: #eff6ff;
            color: var(--primary);
        }
        
        .method-btn.selected i { color: var(--primary); }
        
        .method-bkash { color: #e2136e; }
        .method-nagad { color: #f7941d; }
        .method-rocket { color: #8c3494; }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .submit-btn:hover { background: var(--primary-dark); }
        
        .loader {
            display: none;
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid var(--primary);
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
    </style>
</head>
<body>

<div class="payment-container">
    <div class="header-icon">
        <i class="fas fa-wallet"></i>
    </div>
    <h2>Complete Payment</h2>
    <p class="description">Consultation Fee</p>
    
    <div class="amount-display">৳<?php echo htmlspecialchars($amount); ?></div>
    
    <div class="payment-methods">
        <div class="method-btn" onclick="selectMethod('card', this)">
            <i class="fas fa-credit-card"></i>
            <span>Card</span>
        </div>
        <div class="method-btn" onclick="selectMethod('bkash', this)">
            <i class="fas fa-mobile-alt method-bkash"></i>
            <span>Bkash</span>
        </div>
        <div class="method-btn" onclick="selectMethod('nagad', this)">
            <i class="fas fa-money-bill-wave method-nagad"></i>
            <span>Nagad</span>
        </div>
        <div class="method-btn" onclick="selectMethod('rocket', this)">
            <i class="fas fa-space-shuttle method-rocket"></i>
            <span>Rocket</span>
        </div>
    </div>
    
    <button class="submit-btn" id="payBtn" onclick="processPayment()">
        <span id="btnText">Pay Now</span>
        <div class="loader" id="loader"></div>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedMethod = null;
    const returnUrl = "<?php echo htmlspecialchars($returnUrl); ?>";

    function selectMethod(method, el) {
        selectedMethod = method;
        document.querySelectorAll('.method-btn').forEach(btn => btn.classList.remove('selected'));
        el.classList.add('selected');
    }

    async function processPayment() {
        if (!selectedMethod) {
            Swal.fire('Error', 'Please select a payment method', 'error');
            return;
        }
        
        const btn = document.getElementById('payBtn');
        const btnText = document.getElementById('btnText');
        const loader = document.getElementById('loader');
        
        btn.disabled = true;
        btnText.style.display = 'none';
        loader.style.display = 'block';
        
        try {
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ method: selectedMethod })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                Swal.fire({
                    title: 'Payment Successful!',
                    text: 'Redirecting you...',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = result.redirect;
                });
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            Swal.fire('Failed', error.message, 'error');
            btn.disabled = false;
            btnText.style.display = 'inline';
            loader.style.display = 'none';
        }
    }
</script>

</body>
</html>
