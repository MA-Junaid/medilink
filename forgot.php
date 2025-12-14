<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediLink - Forgot Password</title>
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
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- Forgot Password Card Styles --- */
        .forgot-password-card {
            background-color: var(--bg-white);
            width: 100%;
            max-width: 450px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 40px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 20px;
            color: var(--text-dark);
        }

        p.description {
            font-size: 14px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
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

        input[type="email"] {
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

        .btn-reset {
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
            margin-top: 10px;
        }

        .btn-reset:hover {
            background-color: var(--primary-blue-hover);
        }

        /* --- Back to Login Link --- */
        .back-to-login {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .back-to-login a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="forgot-password-card">
        <h1>Forgot Password?</h1>
        <p class="description">
            Enter the email address associated with your account and we'll send you a link to reset your password.
        </p>
        <form action="#" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn-reset">Send Reset Link</button>
        </form>
    </div>

    <p class="back-to-login">
        Remember your password? <a href="#">Back to Login</a>
    </p>

</body>
</html>