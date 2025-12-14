<?php
define('PAGE_TITLE', 'My Profile');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$active_tab = $_POST['active_tab'] ?? 'profile'; // Default active tab, can be set by POST for persistence

// Fetch existing user data to pre-fill the form
try {
    $stmt = $pdo->prepare("SELECT FullName, Email, Phone, DateOfBirth, Height, Weight, ProfilePicture FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    // In a real application, you might log this error and show a generic message
    $user = []; // Ensure $user is empty if fetch fails
}

// Prepare feedback message for toast
$feedbackMessage = '';
$feedbackType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    validate_csrf_token($_POST['csrf_token']);
    $active_tab = 'profile';
    
    $height = !empty($_POST['height']) ? intval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? intval($_POST['weight']) : null;
    $phone = !empty($_POST['phone']) ? sanitize_input($_POST['phone']) : null;
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? sanitize_input($_POST['dateOfBirth']) : null;

    $profilePicturePath = $user['ProfilePicture']; // Keep old picture if a new one isn't uploaded
    $uploadError = false;

    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] == 0) {
        $target_dir = "../uploads/profile_pictures/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES["profilePicture"]["name"]);
        $target_file = $target_dir . $fileName;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["profilePicture"]["tmp_name"]);
        if($check === false) {
            $feedbackMessage = 'File is not an image.';
            $feedbackType = 'danger';
            $uploadError = true;
        } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $feedbackMessage = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
            $feedbackType = 'danger';
            $uploadError = true;
        } elseif ($_FILES["profilePicture"]["size"] > 500000) { // 500KB
            $feedbackMessage = 'Sorry, your file is too large.';
            $feedbackType = 'danger';
            $uploadError = true;
        } elseif (move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $target_file)) {
            $profilePicturePath = $target_file;
            // Optionally delete old profile picture if it's not the default
            // if (!empty($user['ProfilePicture']) && $user['ProfilePicture'] !== 'path/to/default.jpg' && file_exists($user['ProfilePicture'])) {
            //     unlink($user['ProfilePicture']);
            // }
        } else {
            $feedbackMessage = 'Sorry, there was an error uploading your file.';
            $feedbackType = 'danger';
            $uploadError = true;
        }
    }
    
    if (!$uploadError) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET Height = ?, Weight = ?, Phone = ?, DateOfBirth = ?, ProfilePicture = ? WHERE UserID = ?");
            if ($stmt->execute([$height, $weight, $phone, $dateOfBirth, $profilePicturePath, $userId])) {
                $feedbackMessage = 'Profile updated successfully!';
                $feedbackType = 'success';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT FullName, Email, Phone, DateOfBirth, Height, Weight, ProfilePicture FROM users WHERE UserID = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                // Save weight history if weight changed
                if ($weight !== $user['Weight']) {
                    $stmt = $pdo->prepare("INSERT INTO weight_history (UserID, Weight) VALUES (?, ?)");
                    $stmt->execute([$userId, $weight]);
                }

            } else {
                $feedbackMessage = 'Failed to update profile. Please try again.';
                $feedbackType = 'danger';
            }
        } catch (PDOException $e) {
            $feedbackMessage = 'Database error: ' . $e->getMessage();
            $feedbackType = 'danger';
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
        $feedbackMessage = 'Please fill in all password fields.';
        $feedbackType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $feedbackMessage = 'New passwords do not match.';
        $feedbackType = 'danger';
    } elseif (strlen($newPassword) < 8) {
        $feedbackMessage = 'Password must be at least 8 characters long.';
        $feedbackType = 'danger';
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
                    $feedbackMessage = 'Password changed successfully.';
                    $feedbackType = 'success';
                } else {
                    $feedbackMessage = 'Failed to change password. Please try again.';
                    $feedbackType = 'danger';
                }
            } else {
                $feedbackMessage = 'Incorrect current password.';
                $feedbackType = 'danger';
            }
        } catch (PDOException $e) {
            $feedbackMessage = 'Database error: ' . $e->getMessage();
            $feedbackType = 'danger';
        }
    }
}

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Profile</h1>
</div>

<div id="feedback-toast" class="toast align-items-center text-white border-0 position-fixed top-0 end-0 p-3" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>

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
                <input type="hidden" name="active_tab" value="profile">

                <div class="row mb-3">
                    <div class="col-md-4 text-center">
                        <img src="<?php echo htmlspecialchars($user['ProfilePicture'] ?? '../uploads/profile_pictures/default.png'); ?>" alt="Profile Picture" class="img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <label for="profilePicture" class="form-label btn btn-outline-primary btn-sm">Upload New Picture</label>
                        <input class="form-control d-none" type="file" id="profilePicture" name="profilePicture">
                    </div>
                    <div class="col-md-8">
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
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-end">
                    <button type="submit" name="save_profile" class="btn btn-primary btn-lg">Save Changes</button>
                </div>
            </form>
        </div>
        
        <!-- Password Tab -->
        <div class="tab-pane fade <?php echo $active_tab === 'password' ? 'show active' : ''; ?>" id="password">
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <input type="hidden" name="active_tab" value="password">
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
                <div class="mt-4 text-end">
                    <button type="submit" name="change_password" class="btn btn-primary btn-lg">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Show feedback toast if message exists
    const feedbackMessage = "<?php echo $feedbackMessage; ?>";
    const feedbackType = "<?php echo $feedbackType; ?>";

    if (feedbackMessage) {
        showFeedback(feedbackMessage, feedbackType === 'success');
    }

    function showFeedback(message, success) {
        const toastEl = document.getElementById('feedback-toast');
        const toastBody = toastEl.querySelector('.toast-body');
        
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(success ? 'bg-success' : 'bg-danger');
        toastBody.textContent = message;
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }

    // Optional: Preview profile picture before upload
    const profilePictureInput = document.getElementById('profilePicture');
    const profilePictureImg = document.querySelector('.img-thumbnail');

    if (profilePictureInput && profilePictureImg) {
        profilePictureInput.addEventListener('change', function(event) {
            if (event.target.files && event.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePictureImg.src = e.target.result;
                };
                reader.readAsDataURL(event.target.files[0]);
            }
        });
    }
});
</script>

<?php include 'includes/client_footer.php'; ?>
