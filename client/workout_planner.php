<?php
define('PAGE_TITLE', 'AI Workout Planner');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// --- Get user membership status ---
$stmt = $pdo->prepare("
    SELECT m.PlanName 
    FROM users u
    JOIN membership m ON u.MembershipID = m.MembershipID
    WHERE u.UserID = ?
");
$stmt->execute([$userId]);
$membership = $stmt->fetch();

$isPremiumMember = $membership && in_array($membership['PlanName'], ['yearly', 'monthly']);

// Fetch existing plans for display
$stmt = $pdo->prepare("SELECT PlanID, PlanName, Goal, FitnessLevel, CreatedAt FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC");
$stmt->execute([$userId]);
$existingPlans = $stmt->fetchAll();
$savedPlansCount = count($existingPlans);

$feedback = [];
$workoutPlan = null;
$planName = '';
$goal = '';
$fitnessLevel = '';
$workoutDays = [];

// --- Helper functions for the AI engine ---

// Exercise Library with Difficulty Levels
const EXERCISE_LIBRARY = [
    'Chest' => [
        'beginner' => [['name' => 'Push-ups'], ['name' => 'Machine Chest Press'], ['name' => 'Dumbbell Floor Press']],
        'intermediate' => [['name' => 'Barbell Bench Press'], ['name' => 'Incline Dumbbell Press'], ['name' => 'Dips']],
        'advanced' => [['name' => 'Weighted Dips'], ['name' => 'Incline Barbell Bench Press'], ['name' => 'Cable Flyes']]
    ],
    'Back' => [
        'beginner' => [['name' => 'Lat Pulldowns'], ['name' => 'Seated Cable Rows'], ['name' => 'Assisted Pull-ups']],
        'intermediate' => [['name' => 'Barbell Rows'], ['name' => 'Pull-ups'], ['name' => 'Single-arm Dumbbell Row']],
        'advanced' => [['name' => 'Deadlifts'], ['name' => 'T-Bar Rows'], ['name' => 'Weighted Pull-ups']]
    ],
    'Legs' => [
        'beginner' => [['name' => 'Goblet Squats'], ['name' => 'Leg Press'], ['name' => 'Lunges']],
        'intermediate' => [['name' => 'Barbell Squats'], ['name' => 'Romanian Deadlifts'], ['name' => 'Bulgarian Split Squats']],
        'advanced' => [['name' => 'Front Squats'], ['name' => 'Pistol Squats'], ['name' => 'Hack Squats']]
    ],
    'Shoulders' => [
        'beginner' => [['name' => 'Dumbbell Overhead Press'], ['name' => 'Lateral Raises'], ['name' => 'Front Raises']],
        'intermediate' => [['name' => 'Military Press'], ['name' => 'Arnold Press'], ['name' => 'Face Pulls']],
        'advanced' => [['name' => 'Handstand Push-ups'], ['name' => 'Push Press'], ['name' => 'Rear Delt Flyes']]
    ],
    'Arms' => [
        'beginner' => [['name' => 'Dumbbell Curls'], ['name' => 'Tricep Pushdowns']],
        'intermediate' => [['name' => 'Barbell Curls'], ['name' => 'Skull Crushers']],
        'advanced' => [['name' => 'Hammer Curls'], ['name' => 'Close-grip Bench Press']]
    ],
    'Core' => [
        'beginner' => [['name' => 'Plank'], ['name' => 'Crunches']],
        'intermediate' => [['name' => 'Leg Raises'], ['name' => 'Russian Twists']],
        'advanced' => [['name' => 'Ab Wheel Rollouts'], ['name' => 'Hanging Leg Raises']]
    ],
    'Cardio' => [
        'beginner' => [['name' => 'Brisk Walking'], ['name' => 'Cycling (Moderate)']],
        'intermediate' => [['name' => 'Jogging'], ['name' => 'Rowing (Intervals)']],
        'advanced' => [['name' => 'Sprinting (HIIT)'], ['name' => 'Jump Rope']]
    ],
    'Full Body' => [
        'beginner' => [['name' => 'Burpees (Modified)'], ['name' => 'Jumping Jacks']],
        'intermediate' => [['name' => 'Burpees'], ['name' => 'Mountain Climbers']],
        'advanced' => [['name' => 'Box Jumps'], ['name' => 'Kettlebell Swings']]
    ]
];

function generateDayWorkout($day, $goal, $fitnessLevel, $selectedDays) {
    if (!in_array($day, $selectedDays)) {
        return ['type' => 'Rest Day', 'exercises' => [['name' => 'Rest & Recovery']]];
    }

    // Determine Workout Split based on Goal
    $focus = '';
    $muscleGroups = [];
    
    switch ($goal) {
        case 'bulking': // Push/Pull/Legs or Upper/Lower split logic
            if (in_array($day, ['Monday', 'Thursday'])) {
                $focus = 'Upper Body Power';
                $muscleGroups = ['Chest', 'Back', 'Shoulders'];
            } elseif (in_array($day, ['Tuesday', 'Friday'])) {
                $focus = 'Lower Body Power';
                $muscleGroups = ['Legs', 'Legs', 'Core']; // Double legs for volume
            } else {
                $focus = 'Arm & Shoulder Hypertrophy';
                $muscleGroups = ['Shoulders', 'Arms', 'Arms'];
            }
            break;
            
        case 'strength':
            if (in_array($day, ['Monday', 'Friday'])) {
                $focus = 'Full Body Strength';
                $muscleGroups = ['Legs', 'Chest', 'Back'];
            } else {
                $focus = 'Accessory & Core';
                $muscleGroups = ['Shoulders', 'Arms', 'Core'];
            }
            break;

        case 'cutting': // HIIT & Circuits
            $focus = 'High Intensity Circuit';
            $muscleGroups = ['Full Body', 'Legs', 'Core', 'Cardio'];
            break;

        case 'endurance':
            $focus = 'Endurance Training';
            $muscleGroups = ['Cardio', 'Legs', 'Core'];
            break;

        default: // General Fitness
            if (in_array($day, ['Monday', 'Thursday'])) {
                $focus = 'Full Body A';
                $muscleGroups = ['Legs', 'Chest', 'Back'];
            } else {
                $focus = 'Full Body B';
                $muscleGroups = ['Legs', 'Shoulders', 'Core'];
            }
            break;
    }

    // Generate Exercises
    $exercises = [];
    foreach ($muscleGroups as $group) {
        // Fallback to beginner if level not found, or random selection
        $options = EXERCISE_LIBRARY[$group][$fitnessLevel] ?? EXERCISE_LIBRARY[$group]['beginner'];
        $exercise = $options[array_rand($options)];
        
        // Define Sets/Reps/Rest based on Goal & Level
        $sets = 3;
        $reps = '10-12';
        $rest = '60s';

        if ($fitnessLevel === 'intermediate') $sets = 4;
        if ($fitnessLevel === 'advanced') $sets = 5;

        if ($goal === 'strength') {
            $sets = 5;
            $reps = '3-5';
            $rest = '3-5 min';
        } elseif ($goal === 'endurance' || $goal === 'cutting') {
            $sets = 3;
            $reps = '15-20';
            $rest = '30-45s';
        } elseif ($goal === 'bulking') {
            $sets = 4;
            $reps = '8-12'; // Hypertrophy range
            $rest = '90s';
        }

        // Add details to exercise
        $exercise['sets'] = $sets;
        $exercise['reps'] = $reps;
        $exercise['rest'] = $rest;
        
        // Add duration for cardio instead of reps
        if ($group === 'Cardio') {
            unset($exercise['sets'], $exercise['reps']);
            $exercise['duration'] = ($fitnessLevel === 'beginner' ? '20' : '30-45') . ' min';
        }

        $exercises[] = $exercise;
    }

    return ['type' => $focus, 'exercises' => $exercises];
}

// Handle Generate Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_plan'])) {
    validate_csrf_token($_POST['csrf_token']);
    $planName = sanitize_input($_POST['planName']);
    $goal = sanitize_input($_POST['goal']);
    $fitnessLevel = sanitize_input($_POST['fitnessLevel']);
    $workoutDays = $_POST['workoutDays'] ?? [];

    if (!$isPremiumMember) {
        $goal = 'general_fitness';
        $fitnessLevel = 'beginner';
    }

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
    if (!$isPremiumMember && $savedPlansCount > 0) {
        $feedback = ['type' => 'danger', 'message' => 'Your plan allows for one saved plan. Upgrade to save more.'];
    } elseif (isset($_SESSION['generated_plan'])) {
        $planToSave = $_SESSION['generated_plan'];
        $planDetails = json_encode($planToSave);

        try {
            $stmt = $pdo->prepare("INSERT INTO workout_plans (UserID, PlanName, Goal, FitnessLevel, PlanDetails) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $planToSave['name'], $planToSave['goal'], $planToSave['fitnessLevel'], $planDetails]);
            $feedback = ['type' => 'success', 'message' => 'Workout plan saved successfully!'];
            unset($_SESSION['generated_plan']); // Clear from session after saving
            // Refresh plan count
            $savedPlansCount++;
            $stmt = $pdo->prepare("SELECT PlanID, PlanName, Goal, FitnessLevel, CreatedAt FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC");
            $stmt->execute([$userId]);
            $existingPlans = $stmt->fetchAll();
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Could not save your plan: ' . $e->getMessage()];
        }
    } else {
        $feedback = ['type' => 'warning', 'message' => 'No generated plan to save. Please generate a plan first.'];
    }
}

// Handle Delete Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    validate_csrf_token($_POST['csrf_token']);
    $planId = intval($_POST['plan_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM workout_plans WHERE PlanID = ? AND UserID = ?");
        $stmt->execute([$planId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $feedback = ['type' => 'success', 'message' => 'Workout plan removed successfully!'];
            // Refresh the plans list
            $stmt = $pdo->prepare("SELECT PlanID, PlanName, Goal, FitnessLevel, CreatedAt FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC");
            $stmt->execute([$userId]);
            $existingPlans = $stmt->fetchAll();
            $savedPlansCount = count($existingPlans);
        } else {
            $feedback = ['type' => 'danger', 'message' => 'Could not find the plan to remove.'];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
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

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">AI Workout Planner</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($feedback['message']); ?>
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
                    <div class="mb-3 position-relative">
                        <label for="goal" class="form-label">Primary Goal</label>
                        <select class="form-select" name="goal" id="goal" required <?php echo !$isPremiumMember ? 'disabled' : ''; ?>>
                            <option value="">-- Select a Goal --</option>
                            <option value="bulking" <?php echo $goal === 'bulking' ? 'selected' : ''; ?>>Muscle Gain (Bulking)</option>
                            <option value="cutting" <?php echo $goal === 'cutting' ? 'selected' : ''; ?>>Fat Loss (Cutting)</option>
                            <option value="general_fitness" <?php echo ($goal === 'general_fitness' || !$isPremiumMember) ? 'selected' : ''; ?>>General Fitness</option>
                            <option value="strength" <?php echo $goal === 'strength' ? 'selected' : ''; ?>>Strength</option>
                            <option value="endurance" <?php echo $goal === 'endurance' ? 'selected' : ''; ?>>Endurance</option>
                        </select>
                        <?php if (!$isPremiumMember): ?>
                            <div class="lock-overlay" title="Upgrade to an Unlimited or higher tier member to unlock more goals."></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="fitnessLevel" class="form-label">Fitness Level</label>
                        <select class="form-select" name="fitnessLevel" id="fitnessLevel" required <?php echo !$isPremiumMember ? 'disabled' : ''; ?>>
                            <option value="">-- Select Your Level --</option>
                            <option value="beginner" <?php echo ($fitnessLevel === 'beginner' || !$isPremiumMember) ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $fitnessLevel === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo $fitnessLevel === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                        </select>
                        <?php if (!$isPremiumMember): ?>
                            <div class="lock-overlay" title="Upgrade to an Unlimited or higher tier member to unlock more levels."></div>
                        <?php endif; ?>
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
                     <button type="submit" name="save_plan" class="btn btn-success btn-sm" <?php echo !$isPremiumMember && $savedPlansCount > 0 ? 'disabled' : ''; ?>>
                        <?php echo !$isPremiumMember && $savedPlansCount > 0 ? 'Upgrade to Save More' : 'Save Plan'; ?>
                     </button>
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
                                <small class="text-muted">Created: <?php echo htmlspecialchars(format_date($plan['CreatedAt'])); ?></small>
                                <div class="mt-2">
                                    <a href="workout_planner.php?view_plan=<?php echo $plan['PlanID']; ?>" class="btn btn-sm btn-outline-primary">View Plan</a>
                                    <form action="workout_planner.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this plan?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['PlanID']; ?>">
                                        <button type="submit" name="delete_plan" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>                        </div>
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
.lock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.5);
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="24" height="24"><path fill-rule="evenodd" d="M8 1a3 3 0 00-3 3v2H4a2 2 0 00-2 2v5a2 2 0 002 2h8a2 2 0 002-2V8a2 2 0 00-2-2h-1V4a3 3 0 00-3-3zM6 4a2 2 0 114 0v2H6V4zm-1 4h6v5H5V8z" clip-rule="evenodd" /></svg>');
    background-repeat: no-repeat;
    background-position: center;
    cursor: not-allowed;
    z-index: 10;
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
