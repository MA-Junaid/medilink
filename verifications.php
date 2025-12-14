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

// Fetch Verification Requests
$verifications = [];
$query = "
    SELECT dvr.request_id, u.full_name, u.email, d.specialization, dvr.submitted_at, dvr.status
    FROM doctor_verification_requests dvr
    JOIN users u ON dvr.doctor_id = u.user_id
    JOIN doctors d ON dvr.doctor_id = d.doctor_id
    ORDER BY dvr.submitted_at DESC
";
$res = $con->query($query);
if ($res) {
    while($row = $res->fetch_assoc()) {
        $verifications[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Verifications | MediLink Admin</title>
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
            --warning-yellow: #f59e0b;
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

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge.pending { background: #fef3c7; color: var(--warning-yellow); }
        .badge.approved { background: #d1fae5; color: var(--success-green); }
        .badge.rejected { background: #fee2e2; color: var(--danger-red); }

        .btn-action { padding: 5px 10px; border-radius: 4px; color: white; text-decoration: none; font-size: 12px; margin-right: 5px; }
        .btn-approve { background: var(--success-green); }
        .btn-reject { background: var(--danger-red); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo"><i class="fas fa-heartbeat"></i> MediLink Admin</div>
    <ul class="menu">
        <li><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users-cog"></i> Users</a></li>

        <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
        <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
        <li><a href="verifications.php" class="active"><i class="fas fa-user-shield"></i> Verifications</a></li>
        <li><a href="payments.php"><i class="fas fa-wallet"></i> Payments</a></li>
               <li class="logout"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h2>Verification Requests</h2>
            <p>Manage doctor identity verifications.</p>
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
                    <th>Doctor Name</th>
                    <th>Email</th>
                    <th>Specialization</th>
                    <th>Submitted At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($verifications)): ?>
                    <?php foreach($verifications as $v): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($v['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($v['email']); ?></td>
                            <td><?php echo htmlspecialchars($v['specialization']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($v['submitted_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($v['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($v['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($v['status'] === 'pending'): ?>
                                    <a href="verify_doctor.php?id=<?php echo $v['request_id']; ?>&action=approve" class="btn-action btn-approve" title="Approve"><i class="fas fa-check"></i></a>
                                    <a href="verify_doctor.php?id=<?php echo $v['request_id']; ?>&action=reject" class="btn-action btn-reject" title="Reject"><i class="fas fa-times"></i></a>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--text-muted);">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 30px;">No verification records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
