<?php
define('PAGE_TITLE', 'Activity Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_activity = null;

// --- Handle Form Submissions ---

// Handle Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_activity']))) {
    validate_csrf_token($_POST['csrf_token']);
    $activityName = sanitize_input($_POST['activityName']);
    $description = sanitize_input($_POST['description']);
    $duration = intval($_POST['duration']);
    $maxCapacity = intval($_POST['maxCapacity']);
    $difficultyLevel = sanitize_input($_POST['difficultyLevel']);
    $categoryId = intval($_POST['categoryId']);
    $activityId = isset($_POST['activityId']) ? intval($_POST['activityId']) : null;

    // Validation
    if (empty($activityName) || empty($description) || $duration <= 0 || $maxCapacity <= 0 || empty($difficultyLevel) || empty($categoryId)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } else {
        try {
            if ($activityId) { // Update existing activity
                $stmt = $pdo->prepare("UPDATE activities SET ClassName = ?, Description = ?, Duration = ?, MaxCapacity = ?, DifficultyLevel = ?, CategoryID = ? WHERE ClassID = ?");
                if ($stmt->execute([$activityName, $description, $duration, $maxCapacity, $difficultyLevel, $categoryId, $activityId])) {
                    $feedback = ['type' => 'success', 'message' => 'Activity updated successfully.'];
                }
            } else { // Create new activity
                $stmt = $pdo->prepare("INSERT INTO activities (ClassName, Description, Duration, MaxCapacity, DifficultyLevel, CategoryID) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$activityName, $description, $duration, $maxCapacity, $difficultyLevel, $categoryId])) {
                    $feedback = ['type' => 'success', 'message' => 'Activity created successfully.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}


// Handle Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $activityId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE ClassID = ?");
    $stmt->execute([$activityId]);
    $edit_activity = $stmt->fetch();
}

// Handle Deactivate/Reactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['deactivate_activity']) || isset($_POST['reactivate_activity']))) {
    validate_csrf_token($_POST['csrf_token']);
    $activityId = intval($_POST['activityId']);
    $newStatus = isset($_POST['deactivate_activity']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE activities SET IsActive = ? WHERE ClassID = ?");
        if ($stmt->execute([$newStatus, $activityId])) {
            $feedback = ['type' => 'success', 'message' => "Activity has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    $stmt = $pdo->prepare("SELECT a.*, c.CategoryName FROM activities a JOIN class_categories c ON a.CategoryID = c.CategoryID ORDER BY a.CreatedAt DESC");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    $stmt_categories = $pdo->query("SELECT * FROM class_categories ORDER BY CategoryName");
    $categories = $stmt_categories->fetchAll();
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch data: ' . $e->getMessage()];
    $activities = [];
    $categories = [];
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Activity Management</h1>
</div>


<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Create/Edit Activity Form -->
<div class="card mb-4">
    <div class="card-header">
        <?php echo $edit_activity ? 'Edit Activity' : 'Create New Activity'; ?>
    </div>
    <div class="card-body">
        <form action="activities.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <?php if ($edit_activity): ?>
                <input type="hidden" name="activityId" value="<?php echo $edit_activity['ClassID']; ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="activityName" class="form-label">Activity Name</label>
                    <input type="text" class="form-control" id="activityName" name="activityName" value="<?php echo htmlspecialchars($edit_activity['ClassName'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="categoryId" class="form-label">Category</label>
                    <select class="form-select" id="categoryId" name="categoryId" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['CategoryID']; ?>" <?php echo (isset($edit_activity) && $edit_activity['CategoryID'] == $category['CategoryID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['CategoryName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="difficultyLevel" class="form-label">Difficulty Level</label>
                    <select class="form-select" id="difficultyLevel" name="difficultyLevel" required>
                        <option value="beginner" <?php echo (isset($edit_activity) && $edit_activity['DifficultyLevel'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo (isset($edit_activity) && $edit_activity['DifficultyLevel'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo (isset($edit_activity) && $edit_activity['DifficultyLevel'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="duration" class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-control" id="duration" name="duration" value="<?php echo htmlspecialchars($edit_activity['Duration'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="maxCapacity" class="form-label">Max Capacity</label>
                    <input type="number" class="form-control" id="maxCapacity" name="maxCapacity" value="<?php echo htmlspecialchars($edit_activity['MaxCapacity'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($edit_activity['Description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="save_activity" class="btn btn-primary"><?php echo $edit_activity ? 'Update Activity' : 'Create Activity'; ?></button>
                <?php if ($edit_activity): ?>
                    <a href="activities.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Activities List -->
<div class="card">
    <div class="card-header">
        Existing Activities
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Duration</th>
                        <th>Capacity</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['ClassName']); ?></td>
                            <td><?php echo htmlspecialchars($activity['CategoryName']); ?></td>
                            <td><?php echo $activity['Duration']; ?> mins</td>
                            <td><?php echo $activity['MaxCapacity']; ?></td>
                            <td class="text-capitalize"><?php echo $activity['DifficultyLevel']; ?></td>
                            <td>
                                <?php if ($activity['IsActive']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="activities.php?edit=<?php echo $activity['ClassID']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form action="activities.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="activityId" value="<?php echo $activity['ClassID']; ?>">
                                    <?php if ($activity['IsActive']): ?>
                                        <button type="submit" name="deactivate_activity" class="btn btn-danger btn-sm">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="reactivate_activity" class="btn btn-success btn-sm">Reactivate</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
