<?php
include 'config.php';

$error = "";
$success = "";
$redirect = false;

$full_name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm_password = trim($_POST["confirm_password"] ?? "");

    // Hardcoded RBAC role: only candidate can register here
    $role = "candidate";

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $success = "Account created successfully. Redirecting to login...";
                $redirect = true;

                $full_name = "";
                $email = "";
            } else {
                $error = "Failed to create account. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | ATS System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #eef2ff;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
        }

        .register-card {
            width: 100%;
            max-width: 450px;
            background: #ffffff;
            border-radius: 22px;
            padding: 36px 32px 28px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        }

        .icon-circle {
            width: 72px;
            height: 72px;
            margin: 0 auto 22px;
            background: #4361ee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle svg {
            width: 34px;
            height: 34px;
            stroke: #ffffff;
            stroke-width: 2;
            fill: none;
        }

        .title {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 15px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            stroke: #9ca3af;
            stroke-width: 2;
            fill: none;
        }

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            height: 50px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 0 14px 0 44px;
            font-size: 16px;
            outline: none;
            transition: 0.2s ease;
            background: #fff;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.12);
        }

        .readonly-role {
            background: #f8fafc;
            color: #0f172a;
            cursor: not-allowed;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .message.error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .register-btn {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 12px;
            background: #4361ee;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
            margin-top: 4px;
        }

        .register-btn:hover {
            background: #3651db;
        }

        .bottom-text {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
            color: #64748b;
        }

        .bottom-text a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="icon-circle">
            <svg viewBox="0 0 24 24">
                <path d="M20 21a8 8 0 0 0-16 0"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>

        <h1 class="title">Create Account</h1>
        <p class="subtitle">Register for ATS System</p>

        <?php if (!empty($error)) : ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        placeholder="John Doe"
                        value="<?php echo htmlspecialchars($full_name); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 6h16v12H4z"></path>
                        <path d="M4 7l8 6 8-6"></path>
                    </svg>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="role_display">Role</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input
                        type="text"
                        id="role_display"
                        value="Candidate"
                        class="readonly-role"
                        readonly
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24">
                        <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                        <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                    </svg>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="At least 6 characters"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24">
                        <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                        <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                    </svg>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="register-btn">Create Account</button>
        </form>

        <p class="bottom-text">
            Already have an account? <a href="login.php">Sign In</a>
        </p>
    </div>

    <?php if ($redirect): ?>
        <script>
            setTimeout(function () {
                window.location.href = "login.php";
            }, 1500);
        </script>
    <?php endif; ?>
</body>
</html>