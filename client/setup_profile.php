<?php
define('PAGE_TITLE', 'Set Up Your Profile');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];

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
                // Redirect to dashboard after successful update
                redirect('dashboard.php');
            } else {
                $feedback = ['type' => 'danger', 'message' => 'Failed to update profile. Please try again.'];
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle "Set up later"
if (isset($_POST['skip'])) {
    redirect('dashboard.php');
}


include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Set Up Your Profile</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <p>Welcome! Let's set up your profile to personalize your experience. You can also do this later.</p>
        <form action="setup_profile.php" method="POST" enctype="multipart/form-data">
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
                <button type="submit" name="save_profile" class="btn btn-primary">Save Profile</button>
                <button type="submit" name="skip" class="btn btn-secondary" formnovalidate>Set up later</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
