<?php
define('PAGE_TITLE', 'My Profile');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// Prepare feedback message for toast
$feedbackMessage = '';
$feedbackType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    validate_csrf_token($_POST['csrf_token']);
    
    $fullName = sanitize_input($_POST['fullName']);
    $phone = !empty($_POST['phone']) ? sanitize_input($_POST['phone']) : null;
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? sanitize_input($_POST['dateOfBirth']) : null;
    $height = !empty($_POST['height']) ? intval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? intval($_POST['weight']) : null;

    // Validation
    $validPhone = true;
    if ($phone) {
        // Malaysian phone number regex (supports +601..., 601..., 01...)
        if (!preg_match('/^(\+?6?01)[0-46-9]-*[0-9]{7,8}$/', $phone)) {
            $validPhone = false;
            $feedbackMessage = 'Invalid phone number format. Please use a valid Malaysian format (e.g., 0123456789).';
            $feedbackType = 'danger';
        }
    }

    if ($validPhone) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET FullName = ?, Phone = ?, DateOfBirth = ?, Height = ?, Weight = ? WHERE UserID = ?");
            if ($stmt->execute([$fullName, $phone, $dateOfBirth, $height, $weight, $userId])) {
                $feedbackMessage = 'Profile updated successfully!';
                $feedbackType = 'success';
            } else {
                $feedbackMessage = 'Failed to update profile.';
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
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
        $feedbackMessage = 'New passwords do not match.';
        $feedbackType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT Password FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $user_password_hash = $stmt->fetchColumn();

            if (password_verify($currentPassword, $user_password_hash)) {
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                if ($stmt->execute([$newHashedPassword, $userId])) {
                    $feedbackMessage = 'Password changed successfully.';
                    $feedbackType = 'success';
                } else {
                    $feedbackMessage = 'Failed to change password.';
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

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePicture'])) {
    validate_csrf_token($_POST['csrf_token']);
    if ($_FILES['profilePicture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profilePicture']['name'];
        $filetype = $_FILES['profilePicture']['type'];
        $filesize = $_FILES['profilePicture']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validate format
        if (!in_array($ext, $allowed)) {
            $feedbackMessage = 'Invalid file format. Please upload JPG, JPEG, PNG, or GIF.';
            $feedbackType = 'danger';
        } 
        // Validate size (max 2MB)
        elseif ($filesize > 2 * 1024 * 1024) {
            $feedbackMessage = 'File size is too large. Maximum allowed size is 2MB.';
            $feedbackType = 'danger';
        } else {
            $target_dir = "../uploads/profile_pictures/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            
            $newFileName = uniqid() . '.' . $ext;
            $target_file = $target_dir . $newFileName;
            
            if (move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $target_file)) {
                $stmt = $pdo->prepare("UPDATE users SET ProfilePicture = ? WHERE UserID = ?");
                $stmt->execute([$target_file, $userId]);
                $feedbackMessage = 'Profile picture updated.';
                $feedbackType = 'success';
            } else {
                $feedbackMessage = 'Error uploading file.';
                $feedbackType = 'danger';
            }
        }
    }
}


// Fetch user data for display
$stmt = $pdo->prepare("SELECT FullName, Email, Phone, DateOfBirth, Height, Weight, ProfilePicture FROM users WHERE UserID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-3 mb-3 border-bottom">
    <div class="d-flex align-items-center">
        <h1 class="h2 me-3 mb-0">My Profile</h1>
        <button class="btn btn-sm btn-outline-primary" id="edit-profile-btn">
            <i class="fas fa-edit"></i> Edit
        </button>
    </div>
</div>

<div class="container" style="max-width: 800px;">
    <form id="profile-form" action="profile.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        
        <!-- Profile Picture Section -->
        <div class="text-center mb-4">
            <div class="profile-picture-wrapper">
                <img src="<?php echo htmlspecialchars($user['ProfilePicture'] ?? '../assets/images/default-avatar.png'); ?>" alt="Profile Picture" id="profile-pic-preview" class="profile-img">
                <label for="profilePicture" class="profile-pic-btn" title="Change Profile Picture">
                    <i class="fas fa-camera"></i>
                </label>
                <!-- Separate form inputs for file upload to handle it independently via JS/Auto-submit if needed, or part of main form -->
            </div>
            <!-- Input is outside the wrapper to avoid layout issues, triggered by label -->
            <input type="file" id="profilePicture" name="profilePicture" class="d-none" accept="image/png, image/jpeg, image/gif">
        </div>

        <!-- Details Section -->
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="mb-3">
                    <label for="fullName" class="form-label fw-bold">Full Name</label>
                    <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['FullName'] ?? ''); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" readonly disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="****************" readonly disabled>
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label fw-bold">Contact (Malaysia Format)</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['Phone'] ?? ''); ?>" placeholder="e.g. 0123456789" readonly>
                </div>

                <div class="mb-3">
                    <label for="dateOfBirth" class="form-label fw-bold">Date of Birth</label>
                    <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($user['DateOfBirth'] ?? ''); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="height" class="form-label fw-bold">Height (cm)</label>
                    <input type="number" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($user['Height'] ?? ''); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="weight" class="form-label fw-bold">Weight (kg)</label>
                    <input type="number" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($user['Weight'] ?? ''); ?>" readonly>
                </div>

                <div class="text-end mt-4 d-none" id="save-btn-container">
                    <button type="submit" name="save_profile" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="currentPassword" class="form-label">Current Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control bg-white text-dark" id="currentPassword" name="currentPassword" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="newPassword" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control bg-white text-dark" id="newPassword" name="newPassword" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control bg-white text-dark" id="confirmPassword" name="confirmPassword" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>

<style>
    .profile-picture-wrapper {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto;
    }
    .profile-img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #dee2e6;
    }
    .profile-pic-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        background: #007bff;
        color: white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .profile-pic-btn:hover {
        background: #0056b3;
        transform: scale(1.1);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.getElementById('edit-profile-btn');
    const saveContainer = document.getElementById('save-btn-container');
    const editableFields = ['fullName', 'phone', 'dateOfBirth', 'height', 'weight'];

    // Edit Toggle Logic
    editBtn.addEventListener('click', (e) => {
        e.preventDefault(); // Prevent default if it's in a form context
        const isEditable = !document.getElementById('fullName').readOnly;
        
        if (isEditable) {
            // Currently editable, switching to read-only (Cancel)
            editableFields.forEach(id => document.getElementById(id).readOnly = true);
            saveContainer.classList.add('d-none');
            editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
            editBtn.classList.remove('btn-outline-danger');
            editBtn.classList.add('btn-outline-primary');
            // Ideally, reload or reset form to clear unsaved changes
            location.reload(); 
        } else {
            // Currently read-only, switching to editable
            editableFields.forEach(id => document.getElementById(id).readOnly = false);
            saveContainer.classList.remove('d-none');
            editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            editBtn.classList.remove('btn-outline-primary');
            editBtn.classList.add('btn-outline-danger');
        }
    });

    // Password Visibility Toggle
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Profile Picture Auto-Upload
    const profileInput = document.getElementById('profilePicture');
    if(profileInput) {
        profileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // We need to submit the form, but we want to trigger ONLY the profile picture update.
                // Since the main form handles both, we can simply submit the main form.
                // However, to avoid validation errors on empty fields if any (though they are populated),
                // we should ensure the backend handles the file upload check first (which it does).
                document.getElementById('profile-form').submit();
            }
        });
    }

    // Toast Feedback
    const feedbackMessage = <?php echo json_encode($feedbackMessage); ?>;
    const feedbackType = "<?php echo $feedbackType; ?>";
    if (feedbackMessage) {
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${feedbackType === 'success' ? 'success' : 'danger'} border-0 position-fixed top-0 end-0 p-3 m-3`;
        toastEl.style.zIndex = '1100';
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${feedbackMessage}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toastEl);
        new bootstrap.Toast(toastEl).show();
    }
});
</script>

<?php include 'includes/client_footer.php'; ?>
