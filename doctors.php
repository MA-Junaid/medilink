<?php
session_start();
include '../../conn.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Fetch Admin Info for Header
$adminEmail = $_SESSION['email'];
$adminName = 'Administrator';
$q = $con->query("SELECT full_name FROM admins WHERE email = '$adminEmail'");
if ($q && $q->num_rows > 0) $adminName = $q->fetch_assoc()['full_name'];
else {
    $q2 = $con->query("SELECT full_name FROM users WHERE email = '$adminEmail'");
    if ($q2 && $q2->num_rows > 0) $adminName = $q2->fetch_assoc()['full_name'];
}

// Fetch Doctors
$docs = [];
$query = "
    SELECT u.full_name, u.email, d.doctor_id, d.specialization, d.phone, d.verified
    FROM users u
    JOIN doctors d ON u.user_id = d.doctor_id
    WHERE u.role = 'doctor'
";
$res = $con->query($query);
if ($res) {
    while($row = $res->fetch_assoc()) {
        $docs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Doctors | MediLink Admin</title>
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
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', sans-serif; 
        }
        body { 
            display: flex; 
            background-color: var(--bg-light); 
            min-height: 100vh; 
        }
        
        .sidebar { 
            width: 250px; 
            background: var(--bg-white); 
            border-right: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            position: fixed; 
            height: 100%; 
            top: 0; 
        }
        .logo { 
            padding: 20px; 
            font-size: 24px; 
            font-weight: bold; 
            color: var(--primary-blue); 
            border-bottom: 1px solid var(--border-color); 
        }
        .menu { 
            list-style: none; 
            padding: 20px 0; 
            flex-grow: 1; 
        }
        .menu li a {
             display: flex; 
            align-items: center; 
            padding: 12px 20px; 
            text-decoration: none; 
            color: var(--text-dark); 
            gap: 12px; 
            font-weight: 500; }
        .menu li a:hover, .menu li a.active { 
            background-color: #eff6ff; 
            color: var(--primary-blue); 
            border-right: 3px solid var(--primary-blue); 
        }
        .logout { 
            margin-top: auto; 
            border-top: 1px solid var(--border-color); 
        }
        .logout a { 
            color: var(--danger-red); 
        }

        .main-content { 
            margin-left: 250px; 
            flex-grow: 1; 
            padding: 30px; 
        }
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }
        .page-title h2 { 
            font-size: 24px; 
            color: var(--text-dark); 
        }
        .page-title p { 
            color: var(--text-muted); 
            font-size: 14px; 
        }
        
        .card { 
            background: var(--bg-white); 
            border-radius: var(--radius); 
            box-shadow: var(--shadow); 
            overflow: hidden; 
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .table th, .table td { 
            padding: 15px 20px; 
            text-align: left; 
            border-bottom: 1px solid var(--border-color);
             font-size: 14px; 
        }
        .table th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: var(--text-muted); 
        }
        
        .badge { 
            padding: 4px 10px;
             border-radius: 12px; 
            font-size: 12px; 
            font-weight: 500; 
        }
        .badge.verified { 
            background: #d1fae5; 
            color: var(--success-green);
        }
        .badge.unverified { 
            background: #fee2e2; 
            color: var(--danger-red); 
        }

        .action-btn { 
            padding: 6px 10px; 
            border-radius: 4px; 
            color: white;
             text-decoration: none; 
            font-size: 12px; 
            margin-right: 5px; 
        }
        .btn-view { 
            background: var(--primary-blue); 
        }
        .btn-delete { 
            background: var(--danger-red); 
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo"><i class="fas fa-heartbeat"></i> MediLink Admin</div>
    <ul class="menu">
        <li><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a></li>
        <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
        <li><a href="verifications.php"><i class="fas fa-user-shield"></i> Verifications</a></li>
        <li><a href="payments.php"><i class="fas fa-wallet"></i> Payments</a></li>
               <li class="logout"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="page-header">

        <div class="page-title">
            <h2>Doctors Management</h2>
            <p>View and manage all registered doctors.</p>
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
                    <th>Status</th>
                    <!-- <th>Joined At</th> -->
                    <!-- <th>Actions</th> -->
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($docs)): ?>
                    <?php foreach($docs as $d): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($d['full_name']); ?>&background=random&size=30" style="border-radius: 50%;">
                                    <strong>Dr. <?php echo htmlspecialchars($d['full_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($d['email']); ?></td>
                            <td><?php echo htmlspecialchars($d['specialization']); ?></td>
                            <td>
                                <?php if ($d['verified']): ?>
                                    <span class="badge verified">Verified</span>
                                <?php else: ?>
                                    <span class="badge unverified">Unverified</span>
                                <?php endif; ?>
                            </td>
                          
                            <!-- <td>
                                <a href="#" class="action-btn btn-view"><i class="fas fa-eye"></i></a>
                            </td> -->
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 30px;">No doctors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
