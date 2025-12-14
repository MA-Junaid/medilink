<?php
session_start();
include '../../conn.php'; // Update path if needed

// 1. SESSION PROTECTION
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$email = $_SESSION['email'];
$message = ''; // For displaying success or error messages

// --- Default Data ---
$patient = [
    'user_id' => null,
    'patient_id' => 'N/A',
    'date_of_birth' => '',
    'gender' => '',
    'phone' => '',
    'address' => '',
    'name' => 'User Profile Not Found'
];
$patient_id = null;

// 2. FETCH PATIENT BASIC PROFILE (Using LEFT JOIN)
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
    $patient = array_merge($patient, $fetchedPatient);
    $patient_id = $patient['user_id'];
    if ($patient['patient_id'] === null) {
        $patient['patient_id'] = $patient_id;
    }
} else {
    die("User not found or role mismatch.");
}

// 3. HANDLE FORM SUBMISSION (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dob = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    // Sanitize and validate inputs here...

    // Check if a patient record already exists (patient_id will be $patient_id if it was NULL before)
    if ($fetchedPatient['patient_id'] !== null) {
        // UPDATE existing record
        $updateQuery = $con->prepare("
            UPDATE patients
            SET date_of_birth = ?, gender = ?, phone = ?, address = ?
            WHERE patient_id = ?
        ");
        $updateQuery->bind_param("sssss", $dob, $gender, $phone, $address, $patient_id);
    } else {
        // INSERT new record (since it didn't exist before)
        $updateQuery = $con->prepare("
            INSERT INTO patients (patient_id, date_of_birth, gender, phone, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $updateQuery->bind_param("sssss", $patient_id, $dob, $gender, $phone, $address);
    }

    if ($updateQuery->execute()) {
        $message = '<div style="color: var(--success-green); background-color: #dcfce7; border: 1px solid #a7f3d0; padding: 10px; border-radius: var(--radius-sm); margin-bottom: 20px;">Profile updated successfully!</div>';
        
        // Re-fetch the updated data to display immediately
        // (A simple merge is enough since only these fields change)
        $patient['date_of_birth'] = $dob;
        $patient['gender'] = $gender;
        $patient['phone'] = $phone;
        $patient['address'] = $address;
        
    } else {
        $message = '<div style="color: red; background-color: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: var(--radius-sm); margin-bottom: 20px;">Error updating profile: ' . $updateQuery->error . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Patient Profile</title>
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
    cursor: pointer; /* Added for back button functionality */
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

/* --- Form Specific Styles --- */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-dark);
    font-size: 14px;
}

.form-group input, 
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 14px;
    color: var(--text-dark);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus, 
.form-group select:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.action-buttons button, 
.action-buttons a {
    flex: 1;
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

/* --- Table Styles (Less needed here, but kept for future proofing) --- */
/* ... (Keep existing table styles if necessary for consistency, omitted for brevity here) ... */


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
        order: 3;
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
            <a href="appointments.php">Appointments</a>
            <a href="prescriptions_history.php">Prescriptions</a>
             <a href="edit_profile.php" class="active">Profile</a>  
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
           <a href="index.php"><i class="fas fa-arrow-left back-icon" ></i></a> 
            <div>
                <h1>Edit Patient Profile</h1>
                <p>Update your personal and contact information.</p>
            </div>
        </div>


        <div class="profile-layout">

            <div class="patient-info-card">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient['name']); ?>&background=random" class="patient-avatar">

                <h2><?php echo $patient['name']; ?></h2>
                <p class="text-muted">Patient ID: #<?php echo $patient_id; ?></p>
                <a href="index.php" class="btn btn-outline-primary" style="margin-top: 30px; width: 100%;">
                    <i class="fas fa-eye"></i> View Profile
                </a>
            </div>



            <div class="profile-details">

                <div class="card">
                    <div class="card-header">
                        <h2>Personal Details</h2>
                    </div>

                    <?php echo $message; // Display status message ?>

                    <form method="POST" action="edit_profile.php">
                        <div class="form-group">
                            <label for="name">Full Name (Read-Only)</label>
                            <input type="text" id="name" value="<?php echo $patient['name']; ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $patient['date_of_birth']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="" disabled <?php echo ($patient['gender'] === '' || $patient['gender'] === 'N/A') ? 'selected' : ''; ?>>Select Gender</option>
                                <option value="Male" <?php echo ($patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo $patient['phone'] === 'N/A' ? '' : $patient['phone']; ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo $patient['address'] === 'N/A' ? '' : $patient['address']; ?>">
                        </div>

                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
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