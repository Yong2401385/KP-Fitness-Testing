<?php
define('PAGE_TITLE', 'Register');
require_once 'includes/config.php';

$fullName = $email = $password = $confirmPassword = '';
$errors = [];

// If user is already logged in, redirect to their dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    validate_csrf_token($_POST['csrf_token']);
    
    $fullName = sanitize_input($_POST['fullName']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // --- Validation ---
    if (empty($fullName)) {
        $errors[] = 'Full Name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!isset($_POST['terms'])) {
        $errors[] = 'You must agree to the Terms and Conditions.';
    }

    // --- Check if email already exists ---
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists. Please <a href="login.php">login</a>.';
            }
        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again later.";
        }
    }
    
    // --- If no errors, create user ---
    if (empty($errors)) {
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with 'client' role
            $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role) VALUES (?, ?, ?, 'client')");
            
            if ($stmt->execute([$fullName, $email, $hashedPassword])) {
                $userId = $pdo->lastInsertId();
                
                // Create a welcome notification
                create_notification($userId, 'Welcome to KP Fitness!', 'Your account has been created successfully. Explore our features and book your first class!', 'success');

                // Log the user in automatically
                $_SESSION['UserID'] = $userId;
                $_SESSION['FullName'] = $fullName;
                $_SESSION['Role'] = 'client';
                
                // Redirect to the dashboard
                redirect('client/dashboard.php');
            } else {
                $errors[] = 'Failed to create account. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'An error occurred while creating your account. Please try again later.';
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card mt-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center text-warning h3 mb-4">Create Your Account</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I agree to the <a href="#">Terms and Conditions</a></label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">Register</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php" class="text-warning">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
