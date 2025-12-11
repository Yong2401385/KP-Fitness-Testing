<?php
define('PAGE_TITLE', 'My Profile');
require_once '../includes/config.php';
require_trainer();

$userId = $_SESSION['UserID'];
$feedback = [];
$active_tab = 'profile'; // Default active tab

// Fetch existing user data to pre-fill the form
try {
    $stmt = $pdo->prepare("SELECT FullName, Email, Phone, DateOfBirth, Height, Weight, ProfilePicture FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch your data. Please try again later.'];
    $user = [];
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    validate_csrf_token($_POST['csrf_token']);
    $active_tab = 'profile';
    
    $height = !empty($_POST['height']) ? intval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? intval($_POST['weight']) : null;
    $phone = !empty($_POST['phone']) ? sanitize_input($_POST['phone']) : null;
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? sanitize_input($_POST['dateOfBirth']) : null;

    // Handle File Upload
    $profilePicturePath = $user['ProfilePicture']; // Keep old picture if a new one isn't uploaded
    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] == 0) {
        $target_dir = "../uploads/profile_pictures/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES["profilePicture"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["profilePicture"]["tmp_name"]);
        if($check === false) {
            $feedback = ['type' => 'danger', 'message' => 'File is not an image.'];
        } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $feedback = ['type' => 'danger', 'message' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.'];
        } elseif (move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $target_file)) {
            $profilePicturePath = $target_file;
        } else {
            $feedback = ['type' => 'danger', 'message' => 'Sorry, there was an error uploading your file.'];
        }
    }
    
    if (empty($feedback)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET Height = ?, Weight = ?, Phone = ?, DateOfBirth = ?, ProfilePicture = ? WHERE UserID = ?");
            if ($stmt->execute([$height, $weight, $phone, $dateOfBirth, $profilePicturePath, $userId])) {
                $feedback = ['type' => 'success', 'message' => 'Profile updated successfully!'];
                // Refresh user data
                $stmt = $pdo->prepare("SELECT FullName, Email, Phone, DateOfBirth, Height, Weight, ProfilePicture FROM users WHERE UserID = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                $feedback = ['type' => 'danger', 'message' => 'Failed to update profile. Please try again.'];
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    validate_csrf_token($_POST['csrf_token']);
    $active_tab = 'password';
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all password fields.'];
    } elseif ($newPassword !== $confirmPassword) {
        $feedback = ['type' => 'danger', 'message' => 'New passwords do not match.'];
    } elseif (strlen($newPassword) < 8) {
        $feedback = ['type' => 'danger', 'message' => 'Password must be at least 8 characters long.'];
    } else {
        try {
            // Get current password hash
            $stmt = $pdo->prepare("SELECT Password FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $user_password_hash = $stmt->fetchColumn();

            // Verify current password
            if (password_verify($currentPassword, $user_password_hash)) {
                // Hash new password
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                // Update password
                $stmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                if ($stmt->execute([$newHashedPassword, $userId])) {
                    $feedback = ['type' => 'success', 'message' => 'Password changed successfully.'];
                } else {
                    $feedback = ['type' => 'danger', 'message' => 'Failed to change password. Please try again.'];
                }
            } else {
                $feedback = ['type' => 'danger', 'message' => 'Incorrect current password.'];
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

include 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Profile</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" href="#profile" data-bs-toggle="tab">Edit Profile</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'password' ? 'active' : ''; ?>" href="#password" data-bs-toggle="tab">Change Password</a>
            </li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <!-- Profile Tab -->
        <div class="tab-pane fade <?php echo $active_tab === 'profile' ? 'show active' : ''; ?>" id="profile">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="fullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user['FullName'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['Phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="dateOfBirth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($user['DateOfBirth'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="number" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($user['Height'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($user['Weight'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label for="profilePicture" class="form-label">Profile Picture (Optional)</label>
                        <input class="form-control" type="file" id="profilePicture" name="profilePicture">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="save_profile" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
        
        <!-- Password Tab -->
        <div class="tab-pane fade <?php echo $active_tab === 'password' ? 'show active' : ''; ?>" id="password">
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                    </div>
                    <div class="col-md-6">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/trainer_footer.php'; ?>
