<?php
define('PAGE_TITLE', 'User Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];

// --- Handle Form Submissions ---

// Handle trainer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trainer'])) {
    $fullName = sanitize_input($_POST['fullName']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Basic Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        $feedback = ['type' => 'error', 'message' => 'Please fill in all required fields.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = ['type' => 'error', 'message' => 'Invalid email format.'];
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $feedback = ['type' => 'error', 'message' => 'An account with this email already exists.'];
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role) VALUES (?, ?, ?, 'trainer')");
                if ($stmt->execute([$fullName, $email, $hashedPassword])) {
                    $feedback = ['type' => 'success', 'message' => 'Trainer account created successfully.'];
                } else {
                    $feedback = ['type' => 'error', 'message' => 'Failed to create trainer account.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle user deactivation/reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['deactivate_user']) || isset($_POST['reactivate_user']))) {
    $userId = intval($_POST['userId']);
    $newStatus = isset($_POST['deactivate_user']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE users SET IsActive = ? WHERE UserID = ? AND Role != 'admin'");
        if ($stmt->execute([$newStatus, $userId])) {
            $feedback = ['type' => 'success', 'message' => "User account has been $action."];
        } else {
            $feedback = ['type' => 'error', 'message' => "Failed to $action user account."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    // Fetch all non-admin users
    $stmt = $pdo->prepare("SELECT UserID, FullName, Email, Role, IsActive FROM users WHERE Role != 'admin' ORDER BY CreatedAt DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedback = ['type' => 'error', 'message' => 'Could not fetch user data: ' . $e->getMessage()];
    $users = [];
}

include 'includes/admin_header.php';
?>

<style>
/* Add some specific styles for this page */
.card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    align-items: end;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.form-group input {
    width: 100%;
    padding: 0.75rem;
    background: var(--dark-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 1rem;
}
.table-container {
    overflow-x: auto;
}
.users-table {
    width: 100%;
    border-collapse: collapse;
}
.users-table th, .users-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.users-table th {
    color: var(--primary-color);
}
.users-table td .btn {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}
.btn-danger {
    background-color: var(--error-color);
}
.btn-success {
    background-color: var(--success-color);
}
.role-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: capitalize;
}
.role-client { background-color: #17a2b8; color: white; }
.role-trainer { background-color: #28a745; color: white; }
</style>

<div class="page-header">
    <h1>User Management</h1>
    <p>Create new trainer accounts and manage all existing users.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>">
        <?php echo $feedback['message']; ?>
    </div>
<?php endif; ?>

<!-- Create Trainer Form -->
<div class="card">
    <h2 class="card-title">Create New Trainer</h2>
    <form action="users.php" method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="fullName">Full Name</label>
                <input type="text" id="fullName" name="fullName" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" name="create_trainer" class="btn btn-primary" style="width: 100%; padding: 0.8rem;">Create Trainer</button>
            </div>
        </div>
    </form>
</div>

<!-- Users List -->
<div class="card">
    <h2 class="card-title">All Users</h2>
    <div class="table-container">
        <table class="users-table">
            <thead>
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
                        <td colspan="5" style="text-align: center;">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['FullName']); ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td><span class="role-badge role-<?php echo $user['Role']; ?>"><?php echo htmlspecialchars($user['Role']); ?></span></td>
                            <td><?php echo $user['IsActive'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <form action="users.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="userId" value="<?php echo $user['UserID']; ?>">
                                    <?php if ($user['IsActive']): ?>
                                        <button type="submit" name="deactivate_user" class="btn btn-danger">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="reactivate_user" class="btn btn-success">Reactivate</button>
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

<?php include 'includes/admin_footer.php'; ?>
