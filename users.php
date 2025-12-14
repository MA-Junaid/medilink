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

// Fetch All Users
$users = [];
$query = "SELECT user_id, full_name, email, role, phone, created_at FROM users ORDER BY created_at DESC";
$res = $con->query($query);
if ($res) {
    while($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | MediLink Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reusing consistent Admin CSS */
        :root {
            --primary-blue: #3b82f6;
            --primary-dark: #1e40af;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f3f4f6;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
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
        
        .card { background: var(--bg-white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 30px;}
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .table th { background: #f9fafb; font-weight: 600; color: var(--text-muted); }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge.patient { background: #dbeafe; color: var(--primary-blue); }
        .badge.doctor { background: #d1fae5; color: var(--success-green); }
        .badge.admin { background: #fee2e2; color: var(--danger-red); }

        .btn-add {
            background-color: var(--primary-blue);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-add:hover { background-color: var(--primary-dark); }

        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; 
            padding: 30px;
            border-radius: var(--radius);
            width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
        }
        .close {
            color: var(--text-muted);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: var(--text-dark); }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-dark); }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 14px;
        }
        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn-submit { width: 100%; padding: 12px; background: var(--primary-blue); color: white; border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: var(--primary-dark); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo"><i class="fas fa-heartbeat"></i> MediLink Admin</div>
    <ul class="menu">
        <li><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="users.php" class="active"><i class="fas fa-users-cog"></i> Users</a></li>
        <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
        <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
        <!-- <li><a href="verifications.php"><i class="fas fa-user-shield"></i> Verifications</a></li> -->
        <li><a href="payments.php"><i class="fas fa-wallet"></i> Payments</a></li>
             <li class="logout"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h2>User Management</h2>
            <p>View and manage all system users (Patients, Doctors, Admins).</p>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <button id="openModalBtn" class="btn-add"><i class="fas fa-plus"></i> Create New User</button>
            <div class="user-profile">
                <span style="font-weight: 600; margin-right: 10px;"><?php echo htmlspecialchars($adminName); ?></span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($adminName); ?>&background=random" style="width: 35px; border-radius: 50%;">
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div style="padding: 15px; margin-bottom: 20px; background: #d1fae5; color: #065f46; border-radius: var(--radius);">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div style="padding: 15px; margin-bottom: 20px; background: #fee2e2; color: #991b1b; border-radius: var(--radius);">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['full_name']); ?>&background=random&size=30" style="border-radius: 50%;">
                                    <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($u['role']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($u['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['phone']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 30px;">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 style="margin-bottom: 20px;">Create New User</h2>
        <form action="process_add_user.php" method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Gender (Optional)</label>
                <select name="gender" class="form-control">
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <!-- <option value="other">Other</option> -->
                </select>
            </div>
            <button type="submit" class="btn-submit">Create User</button>
        </form>
    </div>
</div>

<script>
    // Modal Logic
    var modal = document.getElementById("addUserModal");
    var btn = document.getElementById("openModalBtn");
    var span = document.getElementsByClassName("close")[0];

    btn.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

</body>
</html>
