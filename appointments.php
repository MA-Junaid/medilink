<?php
session_start();
include '../../conn.php'; // Update path if needed

// 1. SESSION PROTECTION
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
$patient_id = null;
$patientName = 'Patient User';

// 2. FETCH PATIENT ID
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

// 3. FETCH ALL APPOINTMENTS LIST FOR THIS PATIENT
$appointments = null;
if ($patient_id !== null) {
    $apptQuery = $con->prepare("
        SELECT a.appointment_id, a.appointment_date, a.status, a.mode, a.created_at,
               u.full_name AS doctor_name 
        FROM appointments a
        JOIN users u ON a.doctor_id = u.user_id
        WHERE a.patient_id = ?
        ORDER BY 
            CASE 
                WHEN a.status = 'Completed' THEN 1 
                ELSE 0 
            END ASC,
            a.appointment_date DESC
    ");
    // Assuming patient_id is string/varchar based on initial code
    $apptQuery->bind_param("s", $patient_id); 
    $apptQuery->execute();
    $appointments = $apptQuery->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Appointments</title>
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
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .page-header p {
            font-size: 16px;
            color: var(--text-muted);
            margin-top: 5px;
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
            background-color: var(--bg-white);
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
            text-decoration: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            .nav-links {
                order: 3;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            .data-table th, .data-table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            /* Hide Doctor Name on small screens for better fit */
            .data-table th:nth-child(2), .data-table td:nth-child(2) {
                 display: none; 
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
            <h1>My Appointments</h1>
            <p>View all your scheduled and past medical appointments.</p>
        </div>


        <div class="card">
            <div class="card-header">
                <h2>Appointment History</h2>
                <a href="book_appointment.php" class="add-new-btn">
                    <i class="fas fa-plus-circle"></i> Book New Appointment
                </a>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php 
                if ($appointments && $appointments->num_rows > 0) {
                    while ($row = $appointments->fetch_assoc()) { 
                        // Determine status badge class
                        $statusClass = '';
                        if (strtoupper($row['status']) === 'COMPLETED') {
                            $statusClass = 'status-completed';
                        } elseif (strtoupper($row['status']) === 'ACTIVE' || strtoupper($row['status']) === 'SCHEDULED') {
                            $statusClass = 'status-active';
                        }
                    ?>
                        <tr>
                            <td>#<?php echo $row['appointment_id']; ?></td>
                            <td><?php echo $row['doctor_name']; ?></td>
                            <td><?php echo date('M d, Y H:i A', strtotime($row['appointment_date'])); ?></td>
                            <td><?php echo $row['mode']; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['status']; ?></span></td>
                            <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="view_appointment.php?id=<?php echo $row['appointment_id']; ?>" title="View Details">
                                    <i class="fas fa-eye action-icon"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                    } 
                } else {
                    ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted);">
                            No appointments found. Click "Book New Appointment" to schedule one.
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>