<?php
session_start();
include '../../conn.php';   // update path if needed

// 1. SESSION PROTECTION
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];

// --- Default Data for Non-Existent Patient Profile ---
$patient = [
    'patient_id' => 'N/A',
    'date_of_birth' => 'N/A',
    'gender' => 'N/A',
    'phone' => 'N/A',
    'address' => 'N/A',
    'name' => 'User Profile Not Found' // Default name if join fails
];
$patient_id = null; // Initialize as null

// 2. FETCH PATIENT BASIC PROFILE (Using LEFT JOIN)
// Use LEFT JOIN to ensure user data (name/email) is returned even if the patient record is missing.
$patientQuery = $con->prepare("
    SELECT u.user_id, u.full_name AS name,
           p.patient_id, p.date_of_birth, p.gender, p.phone, p.address
    FROM users u 
    LEFT JOIN patients p ON u.user_id = p.patient_id
    WHERE u.email = ? AND u.role = 'patient'
");
$patientQuery->bind_param("s", $email);
$patientQuery->execute();
$patientResult = $patientQuery->get_result();
$fetchedPatient = $patientResult->fetch_assoc();

if ($fetchedPatient) {
    // Overwrite defaults with fetched data
    $patient = array_merge($patient, $fetchedPatient);
    $patient_id = $patient['user_id']; // Use user_id as the primary identifier if patient_id is NULL
} else {
    // If user record itself isn't found, stop execution (shouldn't happen with session check)
    die("User not found or role mismatch.");
}

// Check if the patient_id (from the 'patients' table) is available
if ($patient['patient_id'] === null) {
    // If the patient record doesn't exist, use the user_id for lookups
    $patient_id = $patient['user_id'];
    $patient['patient_id'] = $patient_id; // Display the user ID instead
    $patient['name'] .= '';
}

// 3. FETCH APPOINTMENTS LIST FOR THIS PATIENT
$appointments = null;
if ($patient_id !== 'N/A') {
    $apptQuery = $con->prepare("SELECT a.appointment_id, a.doctor_id, a.appointment_date, a.status, a.mode, a.created_at,
               u.full_name AS doctor_name -- Corrected column reference to u.full_name
        FROM appointments a
        JOIN users u ON a.doctor_id = u.user_id
        WHERE a.patient_id = ?
        ORDER BY 
            CASE 
                WHEN a.status = 'Completed' THEN 1 
                ELSE 0 
            END ASC,
            a.appointment_date ASC LIMIT 3");
    // Change 'i' (integer) to 's' (string/varchar) for $patient_id
    $apptQuery->bind_param("s", $patient_id);
    $apptQuery->execute();
    $appointments = $apptQuery->get_result();
}

// 4. FETCH PRESCRIPTIONS LIST
$prescriptions = null;
if ($patient_id !== 'N/A') {
    $presQuery = $con->prepare("
        SELECT pr.prescription_id, pr.appointment_id, pr.doctor_id, pr.notes, pr.created_at,
               u.full_name AS doctor_name -- Corrected column reference to u.full_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.user_id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC LIMIT 3
    ");
    // Change 'i' (integer) to 's' (string/varchar) for $patient_id
    $presQuery->bind_param("s", $patient_id);
    $presQuery->execute();
    $prescriptions = $presQuery->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

 <style>
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
            <a href="index.php" class="active">Dashboard</a>
            <a href="appointments.php">Appointments</a>
            <a href="prescriptions_history.php">Prescriptions</a>
             <a href="edit_profile.php">Profile</a>
             <a href="logout.php">Logout</a>
        </nav>

        <div class="nav-icons">
          
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient['name']); ?>&background=random" class="profile-pic">
        </div>
    </div>
</header>


<main class="main-content">
    <div class="container">

        <div class="page-header">

            <div>
                <h1>Patient Profile</h1>
                <p>Complete medical history and patient information</p>
            </div>
        </div>


        <div class="profile-layout">

            <!-- LEFT PROFILE CARD -->
            <div class="patient-info-card">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient['name']); ?>&background=random" class="patient-avatar">

                <h2><?php echo $patient['name']; ?></h2>
                <p class="text-muted">Patient ID: #<?php echo $patient_id; ?></p>

                <div class="basic-info">
                    <h3>Basic Information</h3>

                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo $patient['name']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">DOB</span>
                        <span class="info-value"><?php echo $patient['date_of_birth']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo $patient['gender']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo $patient['phone']; ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo $patient['address']; ?></span>
                    </div>
                </div>

                <a class="btn btn-primary edit-profile-btn" href="edit_profile.php?patient_id=<?php echo $patient_id; ?>">
                    <i class="fas fa-edit"></i> Edit Profile
    </a>
            </div>



            <!-- RIGHT SIDE CONTENT -->
            <div class="profile-details">

                <!-- APPOINTMENTS -->
                <div class="card">
                    <div class="card-header">
                        <h2>Past Appointments</h2>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php while ($row = $appointments->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $row['appointment_date']; ?></td>
                                <td><?php echo $row['doctor_name']; ?></td>
                                <td><span class="status-badge status-completed"><?php echo $row['status']; ?></span></td>
                                <td><?php echo $row['mode']; ?></td>
                                <td><a href="view_appointment.php?id=<?php echo $row['appointment_id']; ?>"><i class="fas fa-eye action-icon"></i></a></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>



                <!-- PRESCRIPTIONS -->
                <div class="card">
                    <div class="card-header">
                        <h2>Prescriptions</h2>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Doctor</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php while ($row = $prescriptions->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $row['created_at']; ?></td>
                                <td><?php echo $row['notes']; ?></td>
                                <td><?php echo $row['doctor_name']; ?></td>
                                <td><a href=" view_prescription.php?id=<?php echo htmlspecialchars($row['appointment_id']); ?>" ><i class="fas fa-eye action-icon"></i></a></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>


                <div class="card download-report-card">
                    <button class="btn download-report-btn">
                        <i class="fas fa-download"></i> Download PDF Report
                    </button>
                </div>

            </div>
        </div>
    </div>
</main>

</body>
</html>
