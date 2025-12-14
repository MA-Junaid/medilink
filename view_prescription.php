<?php
session_start();
include '../../conn.php'; // Update path if needed

// 1. SESSION PROTECTION & SETUP
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
$appointment_id = $_GET['id'] ?? null; 

// 2. FETCH PATIENT & VALIDATION
$patient_id = null; 
$patientName = 'Patient User';
$error_message = null; 

$patientQuery = $con->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND role = 'patient'");
$patientQuery->bind_param("s", $email);
$patientQuery->execute();
$fetchedPatient = $patientQuery->get_result()->fetch_assoc();

if ($fetchedPatient) {
    $patient_id = $fetchedPatient['user_id'];
    $patientName = $fetchedPatient['full_name'];
} else {
    die("User not found or role mismatch.");
}

if ($appointment_id === null || empty($appointment_id)) {
    die("Missing required Appointment ID.");
}

// 3. FETCH PRESCRIPTION HEADER (p.notes, p.created_at)
$prescriptionData = null;
$medicationList = []; 

$presQuery = $con->prepare("
    SELECT 
        p.prescription_id,  
        p.notes AS diagnosis,   
        p.created_at AS date_prescribed,
        a.appointment_date,
        u.full_name AS doctor_name,
        u.user_id AS doctor_id,
        d.specialization
    FROM prescriptions p
    JOIN appointments a ON p.appointment_id = a.appointment_id
    JOIN users u ON a.doctor_id = u.user_id
    JOIN doctors d ON u.user_id = d.doctor_id
    WHERE p.appointment_id = ? 
    AND p.patient_id = ?
");

$presQuery->bind_param("ss", $appointment_id, $patient_id);
$presQuery->execute();
$presResult = $presQuery->get_result();
$prescriptionData = $presResult->fetch_assoc();

if ($prescriptionData) {
    // 4. FETCH MEDICATION LINE ITEMS (JOINING all 3 tables)
    $medsQuery = $con->prepare("
        SELECT 
            pm.dosage, 
            pm.duration, 
            pm.instructions,
            m.generic_name,   
            m.brand_name
        FROM prescription_medicines pm
        JOIN medicines m ON pm.medicine_id = m.medicine_id
        WHERE pm.prescription_id = ?
        ORDER BY pm.row_id ASC
    ");
    
    $medsQuery->bind_param("s", $prescriptionData['prescription_id']);
    $medsQuery->execute();
    $medsResult = $medsQuery->get_result();
    
    while ($medRow = $medsResult->fetch_assoc()) {
        $medicationList[] = $medRow;
    }

} else {
    $error_message = "No prescription found for Appointment #$appointment_id or access is denied. Please check your appointment status.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Prescription #<?php echo htmlspecialchars($appointment_id); ?></title>
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
    --radius-lg: 12px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --success-green: #22c55e;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
    color: var(--text-dark); 
    background-color: var(--bg-light); 
    min-height: 100vh;
}

/* --- Navigation & Layout --- */
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
.main-content { padding: 40px 0; }

header {
    background-color: var(--bg-white);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 10px 0;
}
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.logo {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-blue);
}
.nav-links a {
    text-decoration: none;
    color: var(--text-dark);
    padding: 8px 15px;
    transition: color 0.2s;
    font-size: 15px;
}
.nav-links a:hover, .nav-links a.active {
    color: var(--primary-blue);
    border-bottom: 2px solid var(--primary-blue);
}
.nav-icons i {
    margin-left: 15px;
    color: var(--text-muted);
    cursor: pointer;
}
.profile-pic {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    margin-left: 15px;
}

/* --- Page Header --- */
.page-header { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; }
.page-header h1 { font-size: 24px; font-weight: 800; margin-bottom: 0; }
.page-header p { font-size: 15px; color: var(--text-muted); margin-top: 5px; }
.page-header .back-icon { font-size: 24px; color: var(--text-dark); cursor: pointer; }

/* --- Prescription Card Styles --- */
.prescription-card {
    background-color: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    padding: 40px;
    max-width: 800px;
    margin: 0 auto;
}

.header-logo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid var(--primary-blue);
    padding-bottom: 15px;
    margin-bottom: 25px;
}

.header-logo h2 {
    color: var(--primary-blue);
    font-size: 28px;
    font-weight: 700;
}

.header-logo p {
    font-size: 14px;
    color: var(--text-muted);
}

.patient-doctor-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.info-block h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary-blue);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 5px;
    margin-bottom: 10px;
}

.info-block p {
    margin-bottom: 5px;
    font-size: 15px;
}

.info-block strong {
    font-weight: 600;
    margin-right: 5px;
}

/* --- Content Sections --- */
.section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #f0f0f0;
    border-radius: var(--radius-lg);
    background-color: #fcfcfc;
}

.section h4 {
    font-size: 18px;
    color: var(--text-dark);
    margin-bottom: 15px;
    border-bottom: 2px solid #ddd;
    padding-bottom: 5px;
}

.medication-list {
    list-style: none;
    padding: 0;
}

.medication-list li {
    padding: 10px 0;
    border-bottom: 1px dashed #eee;
    font-size: 15px;
}

.medication-list li:last-child {
    border-bottom: none;
}

.medication-list strong {
    color: #4a4a4a;
}

.instructions-area {
    white-space: pre-wrap; 
    color: var(--text-muted);
    line-height: 1.8;
}

/* --- Footer and Actions --- */
.footer-signature {
    text-align: right;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.footer-signature p {
    margin: 3px 0;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: background-color 0.2s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-print {
    background-color: var(--success-green);
    color: white;
}
.btn-print:hover { background-color: #16a34a; }

.error-card {
    background-color: #ffe0b2;
    color: var(--text-dark);
    padding: 20px;
    border-radius: var(--radius-lg);
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
}

/* --- Print Styles --- */
@media print {
    body { background-color: white; }
    header, .page-header, .action-buttons, .back-icon { display: none !important; }
    .prescription-card { box-shadow: none; border: none; padding: 0; max-width: 100%; margin: 0; }
    .main-content { padding: 0; }
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
            <a href="index.php">Dashboard</a>
            <a href="appointments.php">Appointments</a>
            <a href="prescriptions_history.php" class="active">Prescriptions</a>
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
                <h1>Prescription Details</h1>
                <p>Viewing official prescription for Appointment **#<?php echo htmlspecialchars($appointment_id); ?>**</p>
            </div>
        </div>
        
        <?php if ($prescriptionData): ?>
        
            <div class="prescription-card">
                
                <div class="header-logo">
                    <div>
                        <h2><i class="fas fa-file-medical"></i> Prescription</h2>
                        <p>Date Issued: **<?php echo date('F j, Y', strtotime($prescriptionData['date_prescribed'])); ?>**</p>
                    </div>
                    <p>MediLink Health Record System</p>
                </div>

                <div class="patient-doctor-info">
                    <div class="info-block">
                        <h3>Patient Information</h3>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patientName); ?></p>
                        <p><strong>Record ID:</strong> <?php echo htmlspecialchars($patient_id); ?></p>
                        <p><strong>Appointment Date:</strong> <?php echo date('M j, Y H:i A', strtotime($prescriptionData['appointment_date'])); ?></p>
                    </div>
                    <div class="info-block">
                        <h3>Prescribing Doctor</h3>
                        <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($prescriptionData['doctor_name']); ?></p>
                        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($prescriptionData['specialization']); ?></p>
                        <p><strong>Doctor ID:</strong> <?php echo htmlspecialchars($prescriptionData['doctor_id']); ?></p>
                    </div>
                </div>
                
                <div class="section">
                    <h4><i class="fas fa-stethoscope"></i> Diagnosis (Doctor's Notes)</h4>
                    <p><?php echo htmlspecialchars($prescriptionData['diagnosis'] ?? 'Diagnosis not recorded in notes field.'); ?></p>
                </div>

                <div class="section">
                    <h4><i class="fas fa-pills"></i> Medications</h4>
                    <ul class="medication-list">
                        <?php if (!empty($medicationList)): ?>
                            <?php foreach ($medicationList as $med): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($med['generic_name']); ?></strong> 
                                    (Brand: <?php echo htmlspecialchars($med['brand_name']); ?>): 
                                    <?php echo htmlspecialchars($med['dosage']); ?>, 
                                    for **<?php echo htmlspecialchars($med['duration']); ?>**. 
                                    (Specific Instructions: *<?php echo htmlspecialchars($med['instructions'] ?? 'N/A'); ?>*)
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No specific medications prescribed.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="section">
                    <h4><i class="fas fa-clipboard-list"></i> Additional Instructions</h4>
                    <div class="instructions-area">
                        *Note: General instructions for the patient are likely included in the 'Diagnosis' section above or as specific instructions for each medication.*
                    </div>
                </div>

                <div class="footer-signature">
                    <p>___________________________________</p>
                    <p>Dr. <?php echo htmlspecialchars($prescriptionData['doctor_name']); ?> Signature</p>
                </div>

            </div>

            <div class="action-buttons">
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Prescription
                </button>
            </div>
        
        <?php else: ?>
        
            <div class="error-card">
                <i class="fas fa-exclamation-triangle" style="color: orange; font-size: 24px; margin-bottom: 10px;"></i>
                <h4>Prescription Unavailable</h4>
                <p><?php echo htmlspecialchars($error_message ?? "An unexpected error occurred."); ?></p>
                <p style="margin-top: 10px;"><a href="appointments.php" style="color: var(--primary-blue); font-weight: 600;">Return to Appointments List</a></p>
            </div>
            
        <?php endif; ?>

    </div>
</main>

<script>
    document.querySelector('.back-icon').addEventListener('click', function() {
        window.history.back();
    });
</script>

</body>
</html>