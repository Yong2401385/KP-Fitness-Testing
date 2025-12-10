<?php
define('PAGE_TITLE', 'Login');
require_once 'includes/config.php';

$email = '';
$errors = [];

// If user is already logged in, redirect to their dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    // --- Validation ---
    if (empty($email)) {
        $errors[] = 'Email is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    // --- If no validation errors, proceed ---
    if (empty($errors)) {
        try {
            // Find user by email
            $stmt = $pdo->prepare("SELECT UserID, FullName, Email, Password, Role, IsActive FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verify user and password
            if ($user && password_verify($password, $user['Password'])) {
                
                // Check if account is active
                if ($user['IsActive'] == 0) {
                    $errors[] = 'Your account has been deactivated. Please contact support.';
                } else {
                    // --- Login Success ---
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Store user data in session
                    $_SESSION['UserID'] = $user['UserID'];
                    $_SESSION['FullName'] = $user['FullName'];
                    $_SESSION['Role'] = $user['Role'];

                    // Redirect to the central dashboard
                    redirect('dashboard.php');
                }

            } else {
                // Login failed
                $errors[] = 'Invalid email or password.';
            }

        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again later.";
        }
    }
}

include 'includes/header.php';
?>

<style>
    .auth-form-container {
        max-width: 500px;
        margin: 3rem auto;
        background: var(--light-bg);
        padding: 2rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    .auth-form-container h1 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 2rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .form-group input {
        width: 100%;
        padding: 1rem;
        background: var(--dark-bg);
        border: 2px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-light);
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    .form-group input:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    .form-footer {
        text-align: center;
        margin-top: 1.5rem;
    }
</style>

<div class="auth-form-container">
    <h1>Member Login</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
    </form>
    
    <div class="form-footer">
        <p>Don't have an account? <a href="register.php" style="color: var(--primary-color);">Register here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
