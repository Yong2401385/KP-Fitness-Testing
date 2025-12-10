<?php
define('PAGE_TITLE', 'Class Management');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_class = null;

// --- Handle Form Submissions ---

// Handle Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_class']))) {
    $className = sanitize_input($_POST['className']);
    $description = sanitize_input($_POST['description']);
    $duration = intval($_POST['duration']);
    $maxCapacity = intval($_POST['maxCapacity']);
    $difficultyLevel = sanitize_input($_POST['difficultyLevel']);
    $classId = isset($_POST['classId']) ? intval($_POST['classId']) : null;

    // Validation
    if (empty($className) || empty($description) || $duration <= 0 || $maxCapacity <= 0 || empty($difficultyLevel)) {
        $feedback = ['type' => 'error', 'message' => 'Please fill in all required fields.'];
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
            $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
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
    $classId = intval($_POST['classId']);
    $newStatus = isset($_POST['deactivate_class']) ? 0 : 1;
    $action = $newStatus === 0 ? 'deactivated' : 'reactivated';

    try {
        $stmt = $pdo->prepare("UPDATE classes SET IsActive = ? WHERE ClassID = ?");
        if ($stmt->execute([$newStatus, $classId])) {
            $feedback = ['type' => 'success', 'message' => "Class has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    $stmt = $pdo->prepare("SELECT * FROM classes ORDER BY CreatedAt DESC");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedback = ['type' => 'error', 'message' => 'Could not fetch class data: ' . $e->getMessage()];
    $classes = [];
}

include 'includes/admin_header.php';
?>

<style>
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
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
.form-group { display: flex; flex-direction: column; }
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { margin-bottom: 0.5rem; font-weight: 600; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 0.75rem;
    background: var(--dark-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 1rem;
}
.form-group textarea { min-height: 100px; }

.table-container { overflow-x: auto; }
.classes-table { width: 100%; border-collapse: collapse; }
.classes-table th, .classes-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.classes-table th { color: var(--primary-color); }
.classes-table td .btn { padding: 0.5rem 1rem; font-size: 0.8rem; }
.btn-danger { background-color: var(--error-color); }
.btn-success { background-color: var(--success-color); }
</style>

<div class="page-header">
    <h1>Class Management</h1>
    <p>Define the types of classes offered at the fitness center.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>">
        <?php echo $feedback['message']; ?>
    </div>
<?php endif; ?>

<!-- Create/Edit Class Form -->
<div class="card">
    <h2 class="card-title"><?php echo $edit_class ? 'Edit Class' : 'Create New Class'; ?></h2>
    <form action="classes.php" method="POST">
        <?php if ($edit_class): ?>
            <input type="hidden" name="classId" value="<?php echo $edit_class['ClassID']; ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="className">Class Name</label>
                <input type="text" id="className" name="className" value="<?php echo htmlspecialchars($edit_class['ClassName'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="difficultyLevel">Difficulty Level</label>
                <select id="difficultyLevel" name="difficultyLevel" required>
                    <option value="beginner" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                    <option value="intermediate" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                    <option value="advanced" <?php echo ($edit_class['DifficultyLevel'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                </select>
            </div>

            <div class="form-group">
                <label for="duration">Duration (minutes)</label>
                <input type="number" id="duration" name="duration" value="<?php echo htmlspecialchars($edit_class['Duration'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="maxCapacity">Max Capacity</label>
                <input type="number" id="maxCapacity" name="maxCapacity" value="<?php echo htmlspecialchars($edit_class['MaxCapacity'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($edit_class['Description'] ?? ''); ?></textarea>
            </div>
        </div>
        <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
            <button type="submit" name="save_class" class="btn btn-primary"><?php echo $edit_class ? 'Update Class' : 'Create Class'; ?></button>
            <?php if ($edit_class): ?>
                <a href="classes.php" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Classes List -->
<div class="card">
    <h2 class="card-title">Existing Classes</h2>
    <div class="table-container">
        <table class="classes-table">
            <thead>
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
                        <td style="text-transform: capitalize;"><?php echo $class['DifficultyLevel']; ?></td>
                        <td><?php echo $class['IsActive'] ? 'Active' : 'Inactive'; ?></td>
                        <td style="display: flex; gap: 0.5rem;">
                            <a href="classes.php?edit=<?php echo $class['ClassID']; ?>" class="btn btn-secondary">Edit</a>
                            <form action="classes.php" method="POST" style="display:inline;">
                                <input type="hidden" name="classId" value="<?php echo $class['ClassID']; ?>">
                                <?php if ($class['IsActive']): ?>
                                    <button type="submit" name="deactivate_class" class="btn btn-danger">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" name="reactivate_class" class="btn btn-success">Reactivate</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
