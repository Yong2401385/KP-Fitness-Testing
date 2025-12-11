<?php
define('PAGE_TITLE', 'AI Workout Planner');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];
$workoutPlan = null;

// --- Helper functions for the AI engine ---
function generateDayWorkout($day, $goal, $fitnessLevel) {
    $workout = [];
    $sets = $fitnessLevel === 'beginner' ? 3 : ($fitnessLevel === 'intermediate' ? 4 : 5);
    $reps = '8-12';
    if ($day === 'Sunday' || ($fitnessLevel === 'beginner' && ($day === 'Wednesday' || $day === 'Friday'))) return ['type' => 'Rest Day', 'exercises' => [['name' => 'Light walking or stretching']]];
    
    switch ($goal) {
        case 'bulking':
            if ($day === 'Monday' || $day === 'Thursday') $workout = ['type' => 'Upper Body', 'exercises' => [['name' => 'Bench Press', 'sets' => $sets, 'reps' => $reps], ['name' => 'Barbell Rows', 'sets' => $sets, 'reps' => $reps], ['name' => 'Overhead Press', 'sets' => $sets, 'reps' => $reps]]];
            elseif ($day === 'Tuesday' || $day === 'Friday') $workout = ['type' => 'Lower Body', 'exercises' => [['name' => 'Squats', 'sets' => $sets, 'reps' => $reps], ['name' => 'Deadlifts', 'sets' => $sets, 'reps' => $reps], ['name' => 'Calf Raises', 'sets' => $sets, 'reps' => '15-20']]];
            else $workout = ['type' => 'Cardio & Core', 'exercises' => [['name' => 'Treadmill Running', 'duration' => '20 min'], ['name' => 'Plank', 'sets' => 3, 'reps' => '60s']]];
            break;
        case 'cutting':
            $workout = ['type' => 'Full Body Circuit', 'exercises' => [['name' => 'Burpees', 'sets' => $sets, 'reps' => '15'], ['name' => 'Jump Squats', 'sets' => $sets, 'reps' => '20'], ['name' => 'Push-ups', 'sets' => $sets, 'reps' => 'Max'], ['name' => 'HIIT on treadmill', 'duration' => '20 min']]];
            break;
        default: // General Fitness
             if ($day === 'Monday' || $day === 'Thursday') $workout = ['type' => 'Full Body A', 'exercises' => [['name' => 'Goblet Squats', 'sets' => $sets, 'reps' => $reps], ['name' => 'Push-ups', 'sets' => $sets, 'reps' => 'Max'], ['name' => 'Dumbbell Rows', 'sets' => $sets, 'reps' => $reps]]];
             else $workout = ['type' => 'Full Body B', 'exercises' => [['name' => 'Lunges', 'sets' => $sets, 'reps' => $reps], ['name' => 'Overhead Press', 'sets' => $sets, 'reps' => $reps], ['name' => 'Plank', 'sets' => 3, 'reps' => '60s']]];
            break;
    }
    return $workout;
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_plan'])) {
    validate_csrf_token($_POST['csrf_token']);
    $goal = sanitize_input($_POST['goal']);
    $fitnessLevel = sanitize_input($_POST['fitnessLevel']);
    $planName = sanitize_input($_POST['planName']);
    
    if (empty($goal) || empty($fitnessLevel) || empty($planName)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all fields.'];
    } else {
        // AI Logic
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = generateDayWorkout($day, $goal, $fitnessLevel);
        }
        $workoutPlan = ['schedule' => $schedule];
        $planDetails = json_encode($workoutPlan);

        // Save to DB
        try {
            $stmt = $pdo->prepare("INSERT INTO workout_plans (UserID, PlanName, Goal, FitnessLevel, PlanDetails) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $planName, $goal, $fitnessLevel, $planDetails]);
            $feedback = ['type' => 'success', 'message' => 'New workout plan generated and saved!'];
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Could not save your plan. Please try again.'];
        }
    }
}

// --- Fetch existing plans for display ---
$stmt = $pdo->prepare("SELECT PlanID, PlanName, Goal, FitnessLevel, CreatedAt FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC");
$stmt->execute([$userId]);
$existingPlans = $stmt->fetchAll();

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">AI Workout Planner</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Generator Form -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                New Plan Generator
            </div>
            <div class="card-body">
                <form action="workout_planner.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="planName" class="form-label">Plan Name</label>
                        <input type="text" class="form-control" name="planName" id="planName" placeholder="e.g., Summer Shred" required>
                    </div>
                    <div class="mb-3">
                        <label for="goal" class="form-label">Primary Goal</label>
                        <select class="form-select" name="goal" id="goal" required>
                            <option value="">-- Select a Goal --</option>
                            <option value="bulking">Muscle Gain (Bulking)</option>
                            <option value="cutting">Fat Loss (Cutting)</option>
                            <option value="general_fitness">General Fitness</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="fitnessLevel" class="form-label">Fitness Level</label>
                        <select class="form-select" name="fitnessLevel" id="fitnessLevel" required>
                            <option value="">-- Select Your Level --</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="generate_plan" class="btn btn-primary">Generate & Save Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Result -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Generated Plan
            </div>
            <div class="card-body">
                <?php if ($workoutPlan): ?>
                    <div class="accordion" id="workoutAccordion">
                        <?php foreach($workoutPlan['schedule'] as $day => $workout): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $day; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $day; ?>" aria-expanded="false" aria-controls="collapse<?php echo $day; ?>">
                                        <strong><?php echo $day; ?></strong> - <?php echo $workout['type']; ?>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $day; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $day; ?>" data-bs-parent="#workoutAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach($workout['exercises'] as $exercise): ?>
                                                <li class="list-group-item">
                                                    <?php echo $exercise['name']; ?>
                                                    <?php if(isset($exercise['sets'])): ?>
                                                        <span class="text-muted float-end"><?php echo "{$exercise['sets']} sets x {$exercise['reps']} reps"; ?></span>
                                                    <?php endif; ?>
                                                    <?php if(isset($exercise['duration'])): ?>
                                                        <span class="text-muted float-end"><?php echo $exercise['duration']; ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <p>Your new workout plan will appear here once generated.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Existing Plans -->
<div class="card">
    <div class="card-header">
       Your Saved Plans
    </div>
    <div class="card-body">
        <div class="row">
        <?php if(empty($existingPlans)): ?>
            <p class="text-muted">You have no saved workout plans.</p>
        <?php else: ?>
            <?php foreach($existingPlans as $plan): ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-primary text-capitalize"><?php echo htmlspecialchars($plan['PlanName']); ?></h4>
                            <p class="card-text mb-1"><strong>Goal:</strong> <span class="text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $plan['Goal'])); ?></span></p>
                            <p class="card-text"><strong>Level:</strong> <span class="text-capitalize"><?php echo htmlspecialchars($plan['FitnessLevel']); ?></span></p>
                            <small class="text-muted">Created: <?php echo format_date($plan['CreatedAt']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
