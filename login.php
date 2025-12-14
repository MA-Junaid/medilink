<?php
session_start();
include 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch password + role from DB
    $stmt = $con->prepare("SELECT  password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {

        $stmt->bind_result( $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {

            // Store session values
           // $_SESSION['user_id'] = $id;
            $_SESSION['email']   = $email;
            $_SESSION['role']    = $role;

            // Redirect based on role
            if ($role === 'admin') {
                header("Location: dashboard/admin/index.php");
            } elseif ($role === 'doctor') {
                header("Location: dashboard/doctor/index.php");
            } elseif ($role === 'patient') {
                header("Location: dashboard/patient/index.php");
            } else {
                echo "<script>alert('Role not found. Contact admin.');</script>";
            }

            exit();

        } else {
            echo "<script>alert('Invalid password. Please try again.');</script>";
        }
    } else {
        echo "<script>alert('No account found with that email. Please sign up.');</script>";
    }

    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediLink - Login</title>
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
            --card-footer-bg: #f9fafb;
            --radius-md: 8px;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
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
            /* Center the card vertically and horizontally */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- Login Card Styles --- */
        .login-card {
            background-color: var(--bg-white);
            width: 100%;
            max-width: 450px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden; /* To clip the footer's background */
        }

        .card-body {
            padding: 40px 40px 30px;
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            text-align: left;
            margin-bottom: 32px;
            color: var(--text-dark);
        }

        /* --- Form Styles --- */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            color: var(--text-dark);
            background: var(--bg-white);
        }
        
        input::placeholder {
            color: #9ca3af;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .remember-me input {
            margin-right: 10px;
            width: 16px;
            height: 16px;
            accent-color: var(--primary-blue);
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .remember-me label {
            margin-bottom: 0;
            font-weight: 400;
            color: var(--text-dark);
        }

        .btn-login {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-blue);
            color: white;
            text-align: center;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 24px;
        }

        .btn-login:hover {
            background-color: var(--primary-blue-hover);
        }

        .form-links {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .form-links a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .form-links a:hover {
            text-decoration: underline;
        }

        /* --- Card Footer Styles --- */
        .card-footer {
            background-color: var(--card-footer-bg);
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .card-footer p {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="card-body">
            <h1>Login to MediLink</h1>
            <form action="#" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn-login">Login</button>
                <div class="form-links">
                    <a href="signup.php">Create an account</a>
                    <a href="forgot.php">Forgot password?</a>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <p>By continuing, you agree to MediLink's Terms of Service and Privacy Policy</p>
        </div>
    </div>

</body>
</html>