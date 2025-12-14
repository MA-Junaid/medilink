<?php
session_start();
include '../../conn.php'; // Update path if needed

// 1. SESSION PROTECTION
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
// Ensure you are using the correct variable name: $appointment_id
$appointment_id = $_GET['id'] ?? null; // Get ID from URL

// 2. FETCH PATIENT BASIC PROFILE (For Header/Validation)
$patient_id = null; 
$patientName = 'Patient User';

$patientQuery = $con->prepare("
    SELECT u.user_id, u.full_name AS name
    FROM users u 
    WHERE u.email = ? AND u.role = 'patient'
");
$patientQuery->bind_param("s", $email);
$patientQuery->execute();
$patientResult = $patientQuery->get_result();
$fetchedPatient = $patientResult->fetch_assoc();

if ($fetchedPatient) {
    $patient_id = $fetchedPatient['user_id'];
    $patientName = $fetchedPatient['name'];
} else {
    die("User not found or role mismatch.");
}

// 3. FETCH APPOINTMENT DETAILS

$appointmentDetails = null;

// FIX 1: Update validation to check for empty string instead of numeric
if ($appointment_id === null || empty($appointment_id)) {
    die("Invalid or missing Appointment ID. The ID should be a string (e.g., APPT-251201-0001).");
}

// Fetch the specific appointment and ensure it belongs to the logged-in patient
$apptQuery = $con->prepare("
    SELECT a.*, u.full_name AS doctor_name
    FROM appointments a
    JOIN users u ON a.doctor_id = u.user_id
    WHERE a.appointment_id = ? AND a.patient_id = ?
");

// FIX 2: Ensure $appointment_id and $patient_id are bound as strings ('ss')
$apptQuery->bind_param("ss", $appointment_id, $patient_id);
$apptQuery->execute();
$apptResult = $apptQuery->get_result();
$appointmentDetails = $apptResult->fetch_assoc();

if (!$appointmentDetails) {
    die("Appointment not found or access denied for ID: " . htmlspecialchars($appointment_id));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Appointment #<?php echo htmlspecialchars($appointmentDetails['appointment_id']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- CSS Variables & Reset --- */
:root {
    --primary-blue: #3b82f6;
    --primary-blue-hover: #2563eb;
    --text-dark: #1f2937;
    --text-muted: #6b7280;
    --bg-light: #f9fafb;
    --bg-white: #ffffff;
    --border-color: #e5e7eb;
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --success-green: #22c55e;
    --active-blue: #3b82f6;
    --download-green-bg: #dcfce7;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-dark);
    line-height: 1.5;
    background-color: var(--bg-light);
    min-height: 100vh;
}

/* --- Utilities --- */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    font-weight: 600;
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: background-color 0.2s, border-color 0.2s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background-color: var(--primary-blue);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-blue-hover);
}

.btn-outline-primary {
    background-color: transparent;
    color: var(--primary-blue);
    border: 1px solid var(--primary-blue);
}

.btn-outline-primary:hover {
    background-color: var(--primary-blue);
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
}

.status-completed {
    background-color: #dcfce7;
    color: var(--success-green);
}

.status-active {
    background-color: #dbeafe;
    color: var(--active-blue);
}

/* --- Header & Nav --- */
header {
    padding: 15px 0;
    background: white;
    border-bottom: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-weight: 700;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-dark);
}

.logo i {
    color: var(--primary-blue);
    font-size: 24px;
}

.nav-links {
    display: flex;
    gap: 30px;
}

.nav-links a {
    text-decoration: none;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 15px;
    padding: 5px 0;
    position: relative;
}

.nav-links a.active {
    color: var(--primary-blue);
}

.nav-links a.active::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -5px;
    width: 100%;
    height: 2px;
    background-color: var(--primary-blue);
}

.nav-icons {
    display: flex;
    align-items: center;
    gap: 20px;
}

.nav-icons i {
    font-size: 18px;
    color: var(--text-muted);
    cursor: pointer;
}

.nav-icons .profile-pic {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-blue);
}

/* --- Main Content Area --- */
.main-content {
    padding: 40px 0;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 0;
}

.page-header p {
    font-size: 15px;
    color: var(--text-muted);
    margin-top: 5px;
}

.page-header .back-icon {
    font-size: 24px;
    color: var(--text-dark);
    cursor: pointer;
}

/* --- Layout for Detail Page --- */
.detail-layout {
    display: grid;
    grid-template-columns: 1fr;
}

/* --- Card Styles --- */
.card {
    background-color: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    padding: 30px;
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.card-header h2 {
    font-size: 20px;
    font-weight: 700;
}

/* --- Detail Rows (Reused from Profile) --- */
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px dashed var(--border-color);
}

.info-row:last-of-type {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
    color: var(--text-dark);
    width: 40%;
}

.info-value {
    color: var(--text-muted);
    text-align: right;
    width: 60%;
    word-break: break-word; /* Ensure long notes don't break layout */
}

/* --- Responsive adjustments --- */
@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    .page-header h1 {
        font-size: 20px;
    }
    .info-row {
        flex-direction: column;
        align-items: flex-start;
    }
    .info-label, .info-value {
        width: 100%;
        text-align: left;
    }
    .info-label {
        margin-bottom: 5px;
    }
}
</style>
</head>

<body>

<header>
      <div class="container navbar">
        <div class="logo">
            <i class="fas fa-user-doctor"></i> MediLink
        </div>
        <nav class="nav-links">
            <a href="index.php" >Dashboard</a>
            <a href="appointments.php" class="active">Appointments</a>
            <a href="prescriptions_history.php">Prescriptions</a>
             <a href="edit_profile.php">Profile</a>
                <a href="logout.php">Logout</a>
        </nav>

        <div class="nav-icons">
          
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patientName); ?>&background=random" class="profile-pic">
        </div>
    </div>
</header>


<main class="main-content">
    <div class="container">

        <div class="page-header">
            <i class="fas fa-arrow-left back-icon" onclick="window.location=document.referrer"></i>
            <div>
                <h1>Appointment Details</h1>
                <p>Viewing appointment details for **#<?php echo htmlspecialchars($appointmentDetails['appointment_id']); ?>**</p>
            </div>
        </div>

        <div class="detail-layout">
            <div class="card">
                <div class="card-header">
                    <h2>Appointment Information</h2>
                    <span class="status-badge <?php echo ($appointmentDetails['status'] === 'Completed' ? 'status-completed' : 'status-active'); ?>">
                        <?php echo htmlspecialchars($appointmentDetails['status']); ?>
                    </span>
                </div>

                <div class="basic-info">

                    <div class="info-row">
                        <span class="info-label">Doctor</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointmentDetails['doctor_name']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Date & Time</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointmentDetails['appointment_date']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Mode</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointmentDetails['mode']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Reason / Notes</span>
                        <span class="info-value">
                            <?php 
                                // Assuming 'notes' or 'reason' is stored in the appointment table. 
                                echo htmlspecialchars($appointmentDetails['reason'] ?? 'N/A'); 
                            ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Created On</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointmentDetails['created_at']); ?></span>
                    </div>
                    <?php if ($appointmentDetails['status'] === 'Scheduled' && $appointmentDetails['mode'] === 'Online'): ?>
                        <div class="info-row">
                            <span class="info-label">Action</span>
                            <span class="info-value">
                                <a href="join_consultation.php?id=<?php echo htmlspecialchars($appointmentDetails['appointment_id']); ?>" class="btn btn-primary">
                                    <i class="fas fa-video"></i> &nbsp; Join Consultation
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Related Actions</h2>
                </div>
                <div style="display: flex; gap: 15px;">
                    <a href="book_appointment.php" class="btn btn-outline-primary" style="flex: 1;">
                        <i class="fas fa-plus-circle"></i> Book New Appointment
                    </a>
                    <a href="view_prescription.php?id=<?php echo htmlspecialchars($appointmentDetails['appointment_id']); ?>" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-prescription"></i> View Prescription (If Exists)
                    </a>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
    // Simple JavaScript for the back button
    document.querySelector('.back-icon').addEventListener('click', function() {
        window.history.back();
    });
</script>

</body>
</html>