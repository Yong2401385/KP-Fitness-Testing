<?php
define('PAGE_TITLE', 'AI Workout Planner');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];
$workoutPlan = null;
$planName = '';
$goal = '';
$fitnessLevel = '';
$workoutDays = [];

// --- Helper functions for the AI engine ---
function generateDayWorkout($day, $goal, $fitnessLevel, $selectedDays) {
    $workout = [];
    if (!in_array($day, $selectedDays)) {
        return ['type' => 'Rest Day', 'exercises' => [['name' => 'Rest']]];
    }

    $sets = $fitnessLevel === 'beginner' ? 3 : ($fitnessLevel === 'intermediate' ? 4 : 5);
    $reps = '8-12';
    
    switch ($goal) {
        case 'bulking':
            if (in_array($day, ['Monday', 'Thursday'])) $workout = ['type' => 'Upper Body', 'exercises' => [['name' => 'Bench Press', 'sets' => $sets, 'reps' => $reps], ['name' => 'Barbell Rows', 'sets' => $sets, 'reps' => $reps], ['name' => 'Overhead Press', 'sets' => $sets, 'reps' => $reps]]];
            elseif (in_array($day, ['Tuesday', 'Friday'])) $workout = ['type' => 'Lower Body', 'exercises' => [['name' => 'Squats', 'sets' => $sets, 'reps' => $reps], ['name' => 'Deadlifts', 'sets' => $sets, 'reps' => $reps], ['name' => 'Calf Raises', 'sets' => $sets, 'reps' => '15-20']]];
            else $workout = ['type' => 'Cardio & Core', 'exercises' => [['name' => 'Treadmill Running', 'duration' => '20 min'], ['name' => 'Plank', 'sets' => 3, 'reps' => '60s']]];
            break;
        case 'cutting':
            $workout = ['type' => 'Full Body Circuit', 'exercises' => [['name' => 'Burpees', 'sets' => $sets, 'reps' => '15'], ['name' => 'Jump Squats', 'sets' => $sets, 'reps' => '20'], ['name' => 'Push-ups', 'sets' => $sets, 'reps' => 'Max'], ['name' => 'HIIT on treadmill', 'duration' => '20 min']]];
            break;
        default: // General Fitness
             if (in_array($day, ['Monday', 'Thursday'])) $workout = ['type' => 'Full Body A', 'exercises' => [['name' => 'Goblet Squats', 'sets' => $sets, 'reps' => $reps], ['name' => 'Push-ups', 'sets' => $sets, 'reps' => 'Max'], ['name' => 'Dumbbell Rows', 'sets' => $sets, 'reps' => $reps]]];
             else $workout = ['type' => 'Full Body B', 'exercises' => [['name' => 'Lunges', 'sets' => $sets, 'reps' => $reps], ['name' => 'Overhead Press', 'sets' => $sets, 'reps' => $reps], ['name' => 'Plank', 'sets' => 3, 'reps' => '60s']]];
            break;
    }
    return $workout;
}

// Handle Generate Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_plan'])) {
    validate_csrf_token($_POST['csrf_token']);
    $planName = sanitize_input($_POST['planName']);
    $goal = sanitize_input($_POST['goal']);
    $fitnessLevel = sanitize_input($_POST['fitnessLevel']);
    $workoutDays = $_POST['workoutDays'] ?? [];

    if (empty($planName) || empty($goal) || empty($fitnessLevel) || count($workoutDays) < 3) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all fields and select at least 3 workout days.'];
    } else {
        $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $schedule = [];
        foreach ($allDays as $day) {
            $schedule[$day] = generateDayWorkout($day, $goal, $fitnessLevel, $workoutDays);
        }
        $workoutPlan = ['schedule' => $schedule, 'name' => $planName, 'goal' => $goal, 'fitnessLevel' => $fitnessLevel, 'workoutDays' => $workoutDays];
        $_SESSION['generated_plan'] = $workoutPlan; // Store generated plan in session
        $feedback = ['type' => 'info', 'message' => 'Plan generated. Review and save below.'];
    }
}

// Handle Save Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    if (isset($_SESSION['generated_plan'])) {
        $planToSave = $_SESSION['generated_plan'];
        $planDetails = json_encode($planToSave);

        try {
            $stmt = $pdo->prepare("INSERT INTO workout_plans (UserID, PlanName, Goal, FitnessLevel, PlanDetails) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $planToSave['name'], $planToSave['goal'], $planToSave['fitnessLevel'], $planDetails]);
            $feedback = ['type' => 'success', 'message' => 'Workout plan saved successfully!'];
            unset($_SESSION['generated_plan']); // Clear from session after saving
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Could not save your plan: ' . $e->getMessage()];
        }
    } else {
        $feedback = ['type' => 'warning', 'message' => 'No generated plan to save. Please generate a plan first.'];
    }
}

// View a specific plan
if (isset($_GET['view_plan'])) {
    $planId = intval($_GET['view_plan']);
    $stmt = $pdo->prepare("SELECT * FROM workout_plans WHERE PlanID = ? AND UserID = ?");
    $stmt->execute([$planId, $userId]);
    $plan = $stmt->fetch();
    if ($plan) {
        $workoutPlan = json_decode($plan['PlanDetails'], true);
        $planName = $plan['PlanName'];
        $goal = $plan['Goal'];
        $fitnessLevel = $plan['FitnessLevel'];
        $workoutDays = $workoutPlan['workoutDays'] ?? [];
    }
} elseif (isset($_SESSION['generated_plan'])) {
    $workoutPlan = $_SESSION['generated_plan'];
    $planName = $workoutPlan['name'];
    $goal = $workoutPlan['goal'];
    $fitnessLevel = $workoutPlan['fitnessLevel'];
    $workoutDays = $workoutPlan['workoutDays'];
}

// Fetch existing plans for display
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
                <h5 class="mb-0">Create Your Plan</h5>
            </div>
            <div class="card-body">
                <form id="generator-form" action="workout_planner.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="planName" class="form-label">Plan Name</label>
                        <input type="text" class="form-control" name="planName" id="planName" placeholder="e.g., Summer Shred" value="<?php echo htmlspecialchars($planName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="goal" class="form-label">Primary Goal</label>
                        <select class="form-select" name="goal" id="goal" required>
                            <option value="">-- Select a Goal --</option>
                            <option value="bulking" <?php echo $goal === 'bulking' ? 'selected' : ''; ?>>Muscle Gain (Bulking)</option>
                            <option value="cutting" <?php echo $goal === 'cutting' ? 'selected' : ''; ?>>Fat Loss (Cutting)</option>
                            <option value="general_fitness" <?php echo $goal === 'general_fitness' ? 'selected' : ''; ?>>General Fitness</option>
                            <option value="strength" <?php echo $goal === 'strength' ? 'selected' : ''; ?>>Strength</option>
                            <option value="endurance" <?php echo $goal === 'endurance' ? 'selected' : ''; ?>>Endurance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="fitnessLevel" class="form-label">Fitness Level</label>
                        <select class="form-select" name="fitnessLevel" id="fitnessLevel" required>
                            <option value="">-- Select Your Level --</option>
                            <option value="beginner" <?php echo $fitnessLevel === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $fitnessLevel === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo $fitnessLevel === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Workout Days (select at least 3)</label>
                        <div class="d-flex flex-wrap">
                            <?php 
                            $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($allDays as $day):
                            ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="workoutDays[]" value="<?php echo $day; ?>" id="day-<?php echo $day; ?>" <?php echo in_array($day, $workoutDays) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="day-<?php echo $day; ?>"><?php echo substr($day, 0, 3); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="generate_plan" class="btn btn-primary btn-lg">Generate Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Result -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Generated Plan</h5>
                <?php if (isset($_SESSION['generated_plan'])): ?>
                <form action="workout_planner.php" method="POST" class="d-inline">
                     <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                     <button type="submit" name="save_plan" class="btn btn-success btn-sm">Save Plan</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($workoutPlan && isset($workoutPlan['schedule'])): ?>
                    <div class="accordion accordion-flush" id="workoutAccordion">
                        <?php foreach($workoutPlan['schedule'] as $day => $workout): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $day; ?>">
                                    <button class="accordion-button collapsed <?php echo $workout['type'] === 'Rest Day' ? 'bg-light' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $day; ?>" aria-expanded="false" aria-controls="collapse<?php echo $day; ?>">
                                        <strong><?php echo $day; ?></strong> - <?php echo $workout['type']; ?>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $day; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $day; ?>" data-bs-parent="#workoutAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach($workout['exercises'] as $index => $exercise): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo $exercise['name']; ?>
                                                    <?php if(isset($exercise['sets'])): ?>
                                                        <span class="badge bg-primary rounded-pill"><?php echo "{$exercise['sets']} sets x {$exercise['reps']} reps"; ?></span>
                                                    <?php endif; ?>
                                                    <?php if(isset($exercise['duration'])): ?>
                                                        <span class="badge bg-secondary rounded-pill"><?php echo $exercise['duration']; ?></span>
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
                    <div class="text-center text-muted p-3">
                        <p>Fill out the form and click "Generate Plan" to create your personalized workout schedule.</p>
                        <i class="fas fa-robot fa-3x text-secondary"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Existing Plans -->
<div class="card mt-4">
    <div class="card-header">
       <h5 class="mb-0">Your Saved Plans</h5>
    </div>
    <div class="card-body">
        <?php if(empty($existingPlans)): ?>
            <p class="text-muted text-center p-3">You have no saved workout plans yet.</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach($existingPlans as $plan): ?>
                    <div class="col">
                        <div class="card h-100 hover-card">
                            <div class="card-body">
                                <h5 class="card-title text-primary text-capitalize"><?php echo htmlspecialchars($plan['PlanName']); ?></h5>
                                <p class="card-text mb-1"><strong>Goal:</strong> <span class="badge bg-info text-dark text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $plan['Goal'])); ?></span></p>
                                <p class="card-text"><strong>Level:</strong> <span class="badge bg-secondary text-capitalize"><?php echo htmlspecialchars($plan['FitnessLevel']); ?></span></p>
                                <small class="text-muted">Created: <?php echo format_date($plan['CreatedAt']); ?></small>
                                <a href="workout_planner.php?view_plan=<?php echo $plan['PlanID']; ?>" class="btn btn-sm btn-outline-primary mt-2 stretched-link">View Plan</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
.stretched-link::after {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1;
    content: "";
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('generator-form');
    form.addEventListener('submit', function(e) {
        const checkedDays = form.querySelectorAll('input[name="workoutDays[]"]:checked').length;
        if (checkedDays < 3) {
            e.preventDefault();
            alert('Please select at least 3 workout days.');
        }
    });
});
</script>

<?php include 'includes/client_footer.php'; ?>
