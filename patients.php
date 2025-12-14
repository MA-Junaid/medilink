<?php
session_start();
include '../../conn.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$adminEmail = $_SESSION['email'];
$adminName = 'Administrator';
$q = $con->query("SELECT full_name FROM admins WHERE email = '$adminEmail'");
if ($q && $q->num_rows > 0) $adminName = $q->fetch_assoc()['full_name'];
else {
    $q2 = $con->query("SELECT full_name FROM users WHERE email = '$adminEmail'");
    if ($q2 && $q2->num_rows > 0) $adminName = $q2->fetch_assoc()['full_name'];
}

// Fetch Patients
$patients = [];
// Assuming 'patients' table has extra info, but 'users' has the core info
$query = "
    SELECT u.full_name, u.email, p.patient_id, p.phone, p.gender, p.date_of_birth, u.created_at
    FROM users u
    JOIN patients p ON u.user_id = p.patient_id
    WHERE u.role = 'patient'
    ORDER BY u.created_at DESC
";
$res = $con->query($query);
if ($res) {
    while($row = $res->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Patients | MediLink Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reusing consistent Admin CSS */
        :root {
            --primary-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f3f4f6;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --success-green: #10b981;
            --danger-red: #ef4444;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: var(--bg-light); min-height: 100vh; }
        
        .sidebar { width: 250px; background: var(--bg-white); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; height: 100%; top: 0; }
        .logo { padding: 20px; font-size: 24px; font-weight: bold; color: var(--primary-blue); border-bottom: 1px solid var(--border-color); }
        .menu { list-style: none; padding: 20px 0; flex-grow: 1; }
        .menu li a { display: flex; align-items: center; padding: 12px 20px; text-decoration: none; color: var(--text-dark); gap: 12px; font-weight: 500; }
        .menu li a:hover, .menu li a.active { background-color: #eff6ff; color: var(--primary-blue); border-right: 3px solid var(--primary-blue); }
        .logout { margin-top: auto; border-top: 1px solid var(--border-color); }
        .logout a { color: var(--danger-red); }

        .main-content { margin-left: 250px; flex-grow: 1; padding: 30px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title h2 { font-size: 24px; color: var(--text-dark); }
        .page-title p { color: var(--text-muted); font-size: 14px; }
        
        .card { background: var(--bg-white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .table th { background: #f9fafb; font-weight: 600; color: var(--text-muted); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo"><i class="fas fa-heartbeat"></i> MediLink Admin</div>
    <ul class="menu">
        <li><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>  
        <li><a href="users.php"><i class="fas fa-users-cog"></i> Users</a></li>
        <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
        <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
        <li><a href="verifications.php"><i class="fas fa-user-shield"></i> Verifications</a></li>
        <li><a href="payments.php"><i class="fas fa-wallet"></i> Payments</a></li>
              <li class="logout"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h2>Patients Directory</h2>
            <p>List of all registered patients.</p>
        </div>
        <div class="user-profile">
            <span style="font-weight: 600; margin-right: 10px;"><?php echo htmlspecialchars($adminName); ?></span>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($adminName); ?>&background=random" style="width: 35px; border-radius: 50%;">
        </div>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Gender</th>
                    <th>Date of Birth</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($patients)): ?>
                    <?php foreach($patients as $p): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($p['full_name']); ?>&background=random&size=30" style="border-radius: 50%;">
                                    <strong><?php echo htmlspecialchars($p['full_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                            <td><?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($p['gender'] ?? '')); ?></td>
                            <td><?php echo ($p['date_of_birth']) ? date('M d, Y', strtotime($p['date_of_birth'])) : 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 30px;">No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
