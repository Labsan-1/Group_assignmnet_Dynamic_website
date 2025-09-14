<?php
session_start();
include 'db.php';

$error_message = '';
$banner_message = '';

// Show banner if redirected from verify.php
if (isset($_GET['verified'])) {
    if ($_GET['verified'] === '1') {
        $banner_message = "<div class='success-message'>✅ Your email has been verified. Please log in.</div>";
    } else {
        $banner_message = "<div class='error-message'>❌ Invalid or expired verification link.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Input validation
    if ($username === '' || $password === '') {
        $error_message = "Please enter both username and password.";
    } else {
        // ---------- Try ADMIN first ----------
        $admin_stmt = $con->prepare("SELECT admin_id, username, password, 'admin' as user_type FROM admin WHERE username = ?");
        $admin_stmt->bind_param("s", $username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();

        if ($admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            // NOTE: If your admin passwords are hashed, replace this with password_verify($password, $admin['password'])
            if ($password === $admin['password']) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['admin_id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['user_type'] = $admin['user_type'];

                error_log("Admin {$username} logged in successfully at " . date('Y-m-d H:i:s'));
                header("Location: admin/index.php");
                exit();
            } else {
                $error_message = "Invalid credentials. Please try again.";
            }
            $admin_stmt->close();
        } else {
            // ---------- Fall back to USER ----------
            $admin_stmt->close();

            $user_stmt = $con->prepare("
                SELECT user_id, username, password, is_verified, 'user' as user_type
                FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $user_stmt->bind_param("s", $username);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();

            if ($user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();

                if (!password_verify($password, $user['password'])) {
                    $error_message = "Invalid username or password.";
                } elseif ((int)$user['is_verified'] !== 1) {
                    // Block unverified users
                    $error_message = "Please verify your email before logging in. Check your inbox for the verification link.";
                    // (Optional) You can add a 'Resend verification' link here that posts to a resend endpoint.
                } else {
                    // User login successful
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int)$user['user_id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];

                    error_log("User {$username} logged in successfully at " . date('Y-m-d H:i:s'));
                    header("Location: index.php");
                    exit();
                }
                $user_stmt->close();
            } else {
                $error_message = "No account found with that username.";
            }
        }
    }

    // Close database connection
    $con->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./Styles/Login.css">
</head>
<body>
    <div class="login-container">
        <form action="login.php" method="POST">
            <h1>Welcome Back</h1>

            <?php
                // Show verification banner if present
                if (!empty($banner_message)) {
                    echo $banner_message;
                }
            ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                    maxlength="50"
                    value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    maxlength="50">
            </div>

            <input type="submit" value="Login">

            <div class="register-link">
                <a href="ForgotPassword.php">Forgot Password?</a>
                <br><br>
                <span>New User? </span>
                <a href="Register.php">Register Here</a>
            </div>
        </form>
    </div>
</body>
</html>
