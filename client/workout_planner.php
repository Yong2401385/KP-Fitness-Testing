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
    $goal = sanitize_input($_POST['goal']);
    $fitnessLevel = sanitize_input($_POST['fitnessLevel']);
    $planName = sanitize_input($_POST['planName']);
    
    if (empty($goal) || empty($fitnessLevel) || empty($planName)) {
        $feedback = ['type' => 'error', 'message' => 'Please fill in all fields.'];
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
            $feedback = ['type' => 'error', 'message' => 'Could not save your plan. Please try again.'];
        }
    }
}

// --- Fetch existing plans for display ---
$stmt = $pdo->prepare("SELECT PlanID, PlanName, Goal, FitnessLevel, CreatedAt FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC");
$stmt->execute([$userId]);
$existingPlans = $stmt->fetchAll();

include 'includes/client_header.php';
?>
<style>
.card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
.card-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.form-group input, .form-group select { width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 2px solid var(--border-color); border-radius: 8px; color: var(--text-light); font-size: 1rem; }
.workout-day { border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1.5rem; }
.workout-day h4 { color: var(--primary-color); }
.workout-day ul { list-style: none; padding-left: 1rem; }
.existing-plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
</style>

<div class="page-header">
    <h1>AI Workout Planner</h1>
    <p>Generate a personalized workout plan based on your goals and fitness level.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    <!-- Generator Form -->
    <div class="card">
        <h2 class="card-title">New Plan Generator</h2>
        <form action="workout_planner.php" method="POST">
            <div class="form-group">
                <label for="planName">Plan Name</label>
                <input type="text" name="planName" id="planName" placeholder="e.g., Summer Shred" required>
            </div>
            <div class="form-group">
                <label for="goal">Primary Goal</label>
                <select name="goal" id="goal" required>
                    <option value="">-- Select a Goal --</option>
                    <option value="bulking">Muscle Gain (Bulking)</option>
                    <option value="cutting">Fat Loss (Cutting)</option>
                    <option value="general_fitness">General Fitness</option>
                </select>
            </div>
            <div class="form-group">
                <label for="fitnessLevel">Fitness Level</label>
                <select name="fitnessLevel" id="fitnessLevel" required>
                    <option value="">-- Select Your Level --</option>
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
            <button type="submit" name="generate_plan" class="btn btn-primary" style="width: 100%;">Generate Plan</button>
        </form>
    </div>

    <!-- Result -->
    <div class="card">
        <h2 class="card-title">Generated Plan</h2>
        <?php if ($workoutPlan): ?>
            <?php foreach($workoutPlan['schedule'] as $day => $workout): ?>
                <div class="workout-day">
                    <h4><?php echo $day; ?> - <?php echo $workout['type']; ?></h4>
                    <ul>
                    <?php foreach($workout['exercises'] as $exercise): ?>
                        <li><?php echo $exercise['name']; ?> <?php echo isset($exercise['sets']) ? " - {$exercise['sets']} sets x {$exercise['reps']} reps" : ''; echo isset($exercise['duration']) ? "- {$exercise['duration']}" : ''; ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Your new workout plan will appear here once generated.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Existing Plans -->
<div class="card">
    <h2 class="card-title">Your Saved Plans</h2>
    <div class="existing-plans-grid">
    <?php if(empty($existingPlans)): ?>
        <p>You have no saved workout plans.</p>
    <?php else: ?>
        <?php foreach($existingPlans as $plan): ?>
            <div class="card" style="margin-bottom: 0;">
                <h4 style="color:var(--primary-color); text-transform: capitalize;"><?php echo htmlspecialchars($plan['PlanName']); ?></h4>
                <p><strong>Goal:</strong> <span style="text-transform: capitalize;"><?php echo htmlspecialchars(str_replace('_', ' ', $plan['Goal'])); ?></span></p>
                <p><strong>Level:</strong> <span style="text-transform: capitalize;"><?php echo htmlspecialchars($plan['FitnessLevel']); ?></span></p>
                <small>Created: <?php echo format_date($plan['CreatedAt']); ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
