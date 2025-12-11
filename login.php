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
    // CSRF Protection
    validate_csrf_token($_POST['csrf_token']);
    
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

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card mt-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center h3 mb-4">Member Login</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">Login</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>
