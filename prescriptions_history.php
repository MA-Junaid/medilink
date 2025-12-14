<?php
session_start();
include '../../conn.php'; // Database connection

// 1. SESSION PROTECTION & SETUP
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
$patient_id = null;
$patientName = 'Patient User';
$prescriptions = [];

// 2. FETCH PATIENT INFO
$patientQuery = $con->prepare("
    SELECT user_id, full_name
    FROM users 
    WHERE email = ? AND role = 'patient'
");
$patientQuery->bind_param("s", $email);
$patientQuery->execute();
$fetchedPatient = $patientQuery->get_result()->fetch_assoc();

if ($fetchedPatient) {
    $patient_id = $fetchedPatient['user_id'];
    $patientName = $fetchedPatient['full_name'];
} else {
    die("Patient user data not found.");
}

// 3. FETCH ALL PRESCRIPTIONS LIST
//    - Join with users to get the doctor's name
$presQuery = $con->prepare("
    SELECT 
        pr.prescription_id, 
        pr.appointment_id, 
        pr.created_at,
        pr.notes,
        u.full_name AS doctor_name,
        d.specialization
    FROM prescriptions pr
    JOIN users u ON pr.doctor_id = u.user_id
    JOIN doctors d ON u.user_id = d.doctor_id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
");

$presQuery->bind_param("s", $patient_id);
$presQuery->execute();
$presResult = $presQuery->get_result();

while ($row = $presResult->fetch_assoc()) {
    $prescriptions[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Prescription History | MediLink</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../css/patient_styles.css"> 
<style>
/* --- Specific Styles for Prescription History Page (adjustments for the list) --- */
.prescription-list-container {
    background-color: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.prescription-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.prescription-table th, .prescription-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}

.prescription-table th {
    background-color: var(--bg-light);
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    font-size: 13px;
}

.prescription-table td:nth-child(2) {
    font-weight: 600; /* Emphasize Appointment ID */
}

/* Action Links */
.action-link {
    color: var(--primary-blue);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}

.action-link:hover {
    color: var(--primary-blue-hover);
    text-decoration: underline;
}

.no-data-message {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

.search-bar {
    margin-bottom: 20px;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    width: 100%;
    font-size: 16px;
}
  /* --- CSS Variables & Reset --- */
        :root {
            --primary-blue: #3b82f6; /* Matching the blue in the image */
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
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: start;
        }

        /* --- Left Sidebar (Patient Info) --- */
        .patient-info-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .patient-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid var(--primary-blue);
        }

        .patient-info-card h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .patient-info-card p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .basic-info {
            width: 100%;
            text-align: left;
            margin-top: 20px;
        }

        .basic-info h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

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
        }

        .edit-profile-btn {
            margin-top: 30px;
            width: 100%;
        }

        /* --- Right Content Area (Appointments & Prescriptions) --- */
        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 30px;
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

        .add-new-btn {
            font-size: 14px;
            color: var(--primary-blue);
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .add-new-btn i {
            font-size: 12px;
        }

        /* --- Table Styles --- */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
            color: var(--text-dark);
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            font-weight: 600;
            color: var(--text-muted);
            background-color: var(--bg-white); /* Ensures header background is white */
            text-transform: uppercase;
            font-size: 12px;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table .action-icon {
            font-size: 16px;
            color: var(--primary-blue);
            cursor: pointer;
        }

        /* --- Download Report Button --- */
        .download-report-card {
            text-align: center;
        }

        .download-report-btn {
            background-color: var(--download-green-bg);
            color: var(--success-green);
            border: 1px solid #86efac;
            padding: 12px 25px;
        }

        .download-report-btn:hover {
            background-color: #a7f3d0;
            border-color: #4ade80;
        }


        /* --- Responsive adjustments --- */
        @media (max-width: 992px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            .nav-links {
                order: 3; /* Move links below logo and icons */
                width: 100%;
                justify-content: center;
            }
            .nav-links a.active::after {
                bottom: -10px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            .page-header h1 {
                font-size: 20px;
            }
            .page-header p {
                font-size: 14px;
            }
            .data-table th, .data-table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            .data-table th:nth-child(2), .data-table td:nth-child(2), /* Doctor/Medication */
            .data-table th:nth-child(4), .data-table td:nth-child(4), /* Diagnosis/Duration */
            .data-table th:nth-child(5), .data-table td:nth-child(5) { /* Status/Prescribed By */
                display: none; /* Hide some columns on smaller screens */
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
            <i class="fas fa-file-prescription"></i>
            <div>
                <h1>My Prescription History</h1>
                <p>A complete record of all prescriptions issued to you, <?php echo htmlspecialchars($patientName); ?>.</p>
            </div>
        </div>
        
        <div class="prescription-list-container">
            
            <input type="text" id="searchInput" onkeyup="searchTable()" class="search-bar" placeholder="Search by Doctor Name or Appointment ID...">

            <?php if (!empty($prescriptions)): ?>
                <table class="prescription-table" id="prescriptionTable">
                    <thead>
                        <tr>
                            <th>Date Issued</th>
                            <th>Appointment ID</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Diagnosis Summary</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $pres): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($pres['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($pres['appointment_id']); ?></td>
                                <td><?php echo htmlspecialchars($pres['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($pres['specialization']); ?></td>
                                <td><?php echo htmlspecialchars(substr($pres['notes'], 0, 40)) . (strlen($pres['notes']) > 40 ? '...' : ''); ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?php echo htmlspecialchars($pres['appointment_id']); ?>" class="action-link">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data-message">
                    <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <p>No prescription records found for your account.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
// Simple JavaScript for client-side search functionality
function searchTable() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toUpperCase();
    const table = document.getElementById("prescriptionTable");
    const tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) { // Start at 1 to skip the header row
        let visible = false;
        
        // Get columns for Appointment ID (col 1) and Doctor Name (col 2)
        const tdApptId = tr[i].getElementsByTagName("td")[1]; 
        const tdDoctor = tr[i].getElementsByTagName("td")[2]; 

        if (tdApptId && tdDoctor) {
            if (tdApptId.textContent.toUpperCase().indexOf(filter) > -1 || 
                tdDoctor.textContent.toUpperCase().indexOf(filter) > -1) {
                visible = true;
            }
        }
        
        tr[i].style.display = visible ? "" : "none";
    }
}
</script>

</body>
</html>