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
                redirect('dashboard.php');
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
    <h1>Create Your Account</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="fullName">Full Name</label>
            <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirmPassword">Confirm Password</label>
            <input type="password" id="confirmPassword" name="confirmPassword" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
    </form>
    
    <div class="form-footer">
        <p>Already have an account? <a href="login.php" style="color: var(--primary-color);">Login here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
