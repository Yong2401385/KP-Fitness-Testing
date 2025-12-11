<?php
define('PAGE_TITLE', 'Class Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_class = null;

// --- Handle Form Submissions ---

// Handle Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_class']))) {
    validate_csrf_token($_POST['csrf_token']);
    $className = sanitize_input($_POST['className']);
    $description = sanitize_input($_POST['description']);
    $duration = intval($_POST['duration']);
    $maxCapacity = intval($_POST['maxCapacity']);
    $difficultyLevel = sanitize_input($_POST['difficultyLevel']);
    $classId = isset($_POST['classId']) ? intval($_POST['classId']) : null;

    // Validation
    if (empty($className) || empty($description) || $duration <= 0 || $maxCapacity <= 0 || empty($difficultyLevel)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } else {
        try {
            if ($classId) { // Update existing class
                $stmt = $pdo->prepare("UPDATE classes SET ClassName = ?, Description = ?, Duration = ?, MaxCapacity = ?, DifficultyLevel = ? WHERE ClassID = ?");
                if ($stmt->execute([$className, $description, $duration, $maxCapacity, $difficultyLevel, $classId])) {
                    $feedback = ['type' => 'success', 'message' => 'Class updated successfully.'];
                }
            } else { // Create new class
                $stmt = $pdo->prepare("INSERT INTO classes (ClassName, Description, Duration, MaxCapacity, DifficultyLevel) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$className, $description, $duration, $maxCapacity, $difficultyLevel])) {
                    $feedback = ['type' => 'success', 'message' => 'Class created successfully.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}


// Handle Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $classId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE ClassID = ?");
    $stmt->execute([$classId]);
    $edit_class = $stmt->fetch();
}

// Handle Deactivate/Reactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['deactivate_class']) || isset($_POST['reactivate_class']))) {
    validate_csrf_token($_POST['csrf_token']);
    $classId = intval($_POST['classId']);
    $newStatus = isset($_POST['deactivate_class']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE classes SET IsActive = ? WHERE ClassID = ?");
        if ($stmt->execute([$newStatus, $classId])) {
            $feedback = ['type' => 'success', 'message' => "Class has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    $stmt = $pdo->prepare("SELECT * FROM classes ORDER BY CreatedAt DESC");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch class data: ' . $e->getMessage()];
    $classes = [];
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Class Management</h1>
</div>


<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Create/Edit Class Form -->
<div class="card mb-4">
    <div class="card-header">
        <?php echo $edit_class ? 'Edit Class' : 'Create New Class'; ?>
    </div>
    <div class="card-body">
        <form action="classes.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <?php if ($edit_class): ?>
                <input type="hidden" name="classId" value="<?php echo $edit_class['ClassID']; ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="className" class="form-label">Class Name</label>
                    <input type="text" class="form-control" id="className" name="className" value="<?php echo htmlspecialchars($edit_class['ClassName'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="difficultyLevel" class="form-label">Difficulty Level</label>
                    <select class="form-select" id="difficultyLevel" name="difficultyLevel" required>
                        <option value="beginner" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="duration" class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-control" id="duration" name="duration" value="<?php echo htmlspecialchars($edit_class['Duration'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="maxCapacity" class="form-label">Max Capacity</label>
                    <input type="number" class="form-control" id="maxCapacity" name="maxCapacity" value="<?php echo htmlspecialchars($edit_class['MaxCapacity'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($edit_class['Description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="save_class" class="btn btn-primary"><?php echo $edit_class ? 'Update Class' : 'Create Class'; ?></button>
                <?php if ($edit_class): ?>
                    <a href="classes.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Classes List -->
<div class="card">
    <div class="card-header">
        Existing Classes
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Duration</th>
                        <th>Capacity</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['ClassName']); ?></td>
                            <td><?php echo $class['Duration']; ?> mins</td>
                            <td><?php echo $class['MaxCapacity']; ?></td>
                            <td class="text-capitalize"><?php echo $class['DifficultyLevel']; ?></td>
                            <td>
                                <?php if ($class['IsActive']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="classes.php?edit=<?php echo $class['ClassID']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form action="classes.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="classId" value="<?php echo $class['ClassID']; ?>">
                                    <?php if ($class['IsActive']): ?>
                                        <button type="submit" name="deactivate_class" class="btn btn-danger btn-sm">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="reactivate_class" class="btn btn-success btn-sm">Reactivate</button>
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
