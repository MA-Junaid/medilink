<?php

include 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full-name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone-number'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match. Please try again.');</script>";
    } else {
        
        // --- START: ID GENERATION LOGIC ---
        
        // 1. Select the last ID that starts with 'p'
        // We order by the numeric part of the ID (substring) to ensure p10 comes after p9
        $id_query = "SELECT user_id FROM users WHERE user_id LIKE 'p%' ORDER BY CAST(SUBSTRING(user_id, 2) AS UNSIGNED) DESC LIMIT 1";
        $result = $con->query($id_query);
        if ($result && $result->num_rows > 0) {
            // If an ID exists, fetch it
            $row = $result->fetch_assoc();
            $last_id = $row['user_id']; // Corrected column name to 'user_id'
            
            // Extract the number (remove the 'p')
            $number = (int)substr($last_id, 1);
            
            // Increment the number
            $new_number = $number + 1;
            
            // Format: Add 'p' and pad with zero if less than 10 (e.g., p01, p09, p10)
            // The padding length should be dynamic to accommodate numbers > 99 if needed,
            // or fixed to 2 if that's the desired format for all IDs.
            // For now, keeping it as 2 as per the original logic's example.
            $custom_id = 'p' . str_pad($new_number, 2, '0', STR_PAD_LEFT);
        } else {
            // If no ID exists (table is empty or no 'p' IDs), start with p01
            $custom_id = 'p01';
        }
        
        // --- END: ID GENERATION LOGIC ---

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Update the INSERT statement to include the 'id' column
        $stmt = $con->prepare("INSERT INTO `users`(`user_id`, `full_name`, `email`, `phone`, `password`) VALUES (?, ?, ?, ?, ?)");
        
        // Update bind_param: added 's' for the ID, making it "sssss"
        $stmt->bind_param("sssss", $custom_id, $full_name, $email, $phone_number, $hashed_password);

        if ($stmt->execute()) {
            echo "<script>alert('Registration successful! Your ID is $custom_id. You can now log in.'); window.location.href = 'login.php';</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediLink - Create an Account</title>
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
            display: flex;
            flex-direction: column; /* To stack the card and the "already have account" link */
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- Registration Card Styles --- */
        .registration-card {
            background-color: var(--bg-white);
            width: 100%;
            max-width: 450px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 40px;
            margin-bottom: 20px; /* Space between card and login link */
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            text-align: center;
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

        input[type="text"],
        input[type="email"],
        input[type="tel"],
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

        .terms-checkbox {
            display: flex;
            align-items: center;
            margin-top: 5px; /* Adjust spacing as needed */
            margin-bottom: 24px;
        }

        .terms-checkbox input {
            margin-right: 10px;
            width: 16px;
            height: 16px;
            accent-color: var(--primary-blue);
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .terms-checkbox label {
            margin-bottom: 0;
            font-weight: 400;
            color: var(--text-dark);
        }

        .btn-signup {
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
        }

        .btn-signup:hover {
            background-color: var(--primary-blue-hover);
        }

        /* --- Footer Link Styles --- */
        .footer-link {
            font-size: 14px;
            color: var(--text-dark);
            text-decoration: none;
            text-align: center;
        }
        
        .footer-link a {
            color: var(--primary-blue);
            font-weight: 600;
            text-decoration: none;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="registration-card">
        <h1>Create an Account</h1>
        <form action="#" method="post">
            <div class="form-group">
                <label for="full-name">Full Name</label>
                <input type="text" id="full-name" name="full-name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone-number">Phone Number</label>
                <input type="tel" id="phone-number" name="phone-number" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm-password" required>
            </div>
            <div class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the Terms & Conditions</label>
            </div>
            <button type="submit" class="btn-signup">Sign Up</button>
        </form>
    </div>

    <p class="footer-link">Already have an account? <a href="login.php">Login</a></p>

</body>
</html>