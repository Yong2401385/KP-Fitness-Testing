<?php
define('PAGE_TITLE', 'User Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];

// --- Handle Form Submissions ---

// Handle trainer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trainer'])) {
    validate_csrf_token($_POST['csrf_token']);
    $fullName = sanitize_input($_POST['fullName']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Basic Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = ['type' => 'danger', 'message' => 'Invalid email format.'];
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $feedback = ['type' => 'danger', 'message' => 'An account with this email already exists.'];
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role) VALUES (?, ?, ?, 'trainer')");
                if ($stmt->execute([$fullName, $email, $hashedPassword])) {
                    $feedback = ['type' => 'success', 'message' => 'Trainer account created successfully.'];
                } else {
                    $feedback = ['type' => 'danger', 'message' => 'Failed to create trainer account.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle user deactivation/reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['deactivate_user']) || isset($_POST['reactivate_user']))) {
    validate_csrf_token($_POST['csrf_token']);
    $userId = intval($_POST['userId']);
    $newStatus = isset($_POST['deactivate_user']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE users SET IsActive = ? WHERE UserID = ? AND Role != 'admin'");
        if ($stmt->execute([$newStatus, $userId])) {
            $feedback = ['type' => 'success', 'message' => "User account has been $action."];
        } else {
            $feedback = ['type' => 'danger', 'message' => "Failed to $action user account."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    // Fetch all non-admin users
    $stmt = $pdo->prepare("SELECT UserID, FullName, Email, Role, IsActive FROM users WHERE Role != 'admin' ORDER BY CreatedAt DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch user data: ' . $e->getMessage()];
    $users = [];
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">User Management</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Create Trainer Form -->
<div class="card mb-4">
    <div class="card-header">
        Create New Trainer
    </div>
    <div class="card-body">
        <form action="users.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="fullName" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullName" name="fullName" required>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-12">
                    <button type="submit" name="create_trainer" class="btn btn-primary">Create Trainer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        All Users
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                <td>
                                    <?php 
                                    $roleClass = 'bg-secondary';
                                    if ($user['Role'] === 'client') $roleClass = 'bg-info';
                                    if ($user['Role'] === 'trainer') $roleClass = 'bg-success';
                                    ?>
                                    <span class="badge <?php echo $roleClass; ?>"><?php echo htmlspecialchars($user['Role']); ?></span>
                                </td>
                                <td>
                                    <?php if ($user['IsActive']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="users.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="userId" value="<?php echo $user['UserID']; ?>">
                                        <?php if ($user['IsActive']): ?>
                                            <button type="submit" name="deactivate_user" class="btn btn-danger btn-sm">Deactivate</button>
                                        <?php else: ?>
                                            <button type="submit" name="reactivate_user" class="btn btn-success btn-sm">Reactivate</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
