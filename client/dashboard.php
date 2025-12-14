<?php
define('PAGE_TITLE', 'Client Dashboard');
require_once '../includes/config.php';
require_client(); // Ensure only clients can access

// Get user data
$userId = $_SESSION['UserID'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Calculate BMI
    $bmi = calculate_bmi($user['Height'], $user['Weight']);
    $bmiCategory = get_bmi_category($bmi);
    
    // Get upcoming bookings (next 5)
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, c.ClassName 
        FROM reservations r
        JOIN sessions s ON r.SessionID = s.SessionID
        JOIN classes c ON s.ClassID = c.ClassID
        WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE()
        ORDER BY s.SessionDate, s.Time
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcomingBookings = $stmt->fetchAll();

    // Get workout plan count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workout_plans WHERE UserID = ?");
    $stmt->execute([$userId]);
    $workoutPlanCount = $stmt->fetchColumn();

    // Get current membership
    $stmt = $pdo->prepare("
        SELECT m.Type, p.Status as PaymentStatus
        FROM users u 
        LEFT JOIN membership m ON u.MembershipID = m.MembershipID
        LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID
        WHERE u.UserID = ?
        ORDER BY p.PaymentDate DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $membership = $stmt->fetch();

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $user = [];
    $upcomingBookings = [];
    $workoutPlanCount = 0;
    $membership = null;
    $bmi = 'N/A';
    $bmiCategory = 'N/A';
}

$motivationalQuotes = [
    "The only bad workout is the one that didn't happen.",
    "Your body can stand almost anything. It’s your mind that you have to convince.",
    "Success isn’t always about greatness. It’s about consistency. Consistent hard work gains success. Greatness will come.",
    "The secret of getting ahead is getting started.",
    "Don't limit your challenges. Challenge your limits."
];
$quote = $motivationalQuotes[array_rand($motivationalQuotes)];

include 'includes/client_header.php';
?>

<style>
    /* Original Orange Chatbot styles */
    .chatbot-bubble {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #ff8c00; /* Orange */
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        cursor: pointer;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s ease-in-out;
    }
    .chatbot-bubble:hover {
        transform: scale(1.05);
    }
    .chatbot-window {
        position: fixed;
        bottom: 90px;
        right: 20px;
        z-index: 1000;
        width: 350px;
        height: 450px;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .chatbot-header {
        background-color: #ff8c00; /* Orange */
        color: white;
        padding: 15px;
        font-size: 1.1rem;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chatbot-messages {
        flex-grow: 1;
        padding: 15px;
        overflow-y: auto;
        background-color: #f9f9f9;
        display: flex;
        flex-direction: column;
    }
    .chatbot-input-area {
        padding: 10px 15px;
        background-color: #f1f1f1;
        display: flex;
    }
    .chatbot-input-area input {
        flex-grow: 1;
        border: 1px solid #ddd;
        border-radius: 20px;
        padding: 8px 15px;
        margin-right: 10px;
    }
    .chatbot-input-area button {
        background-color: #ff8c00; /* Orange */
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .message {
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        max-width: 80%;
    }
    .message.user {
        background-color: #ff8c00; /* Orange */
        color: white;
        align-self: flex-end;
        margin-left: auto;
    }
    .message.bot {
        background-color: #e2e2e2;
        color: #333;
        align-self: flex-start;
        margin-right: auto;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
</div>

<div class="card p-4 mb-4 welcome-card">
    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h2>
    <p class="lead">Ready to continue your fitness journey? Here's a snapshot of your progress.</p>
    <p class="fst-italic">"<?php echo $quote; ?>"</p>
    <div class="mt-3">
        <a href="booking.php" class="btn btn-primary btn-lg">Book a Class</a>
        <a href="workout_planner.php" class="btn btn-primary btn-lg">AI Workout Planner</a>
    </div></div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo count($upcomingBookings); ?></div>
                <h6>Upcoming Classes</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo $workoutPlanCount; ?></div>
                <h6>Saved Workouts</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo $bmi; ?></div>
                <h6><?php echo $bmiCategory; ?></h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="h3 fw-bold text-primary text-capitalize">
                    <?php echo $membership ? htmlspecialchars($membership['Type']) : 'None'; ?>
                </div>
                <h6>Membership</h6>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Upcoming Classes
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($upcomingBookings)): ?>
                        <li class="list-group-item">No upcoming classes. Why not book one?</li>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($booking['ClassName']); ?></strong><br>
                                <small><?php echo format_date($booking['SessionDate']); ?> at <?php echo format_time($booking['Time']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Health Stats
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><strong>Height:</strong> <?php echo htmlspecialchars($user['Height'] ?? 'N/A'); ?> cm</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Weight:</strong> <?php echo htmlspecialchars($user['Weight'] ?? 'N/A'); ?> kg</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>BMI:</strong> <?php echo $bmi; ?> (<?php echo $bmiCategory; ?>)</li>
                </ul>
                <canvas id="bmiChart" class="mt-3"></canvas>
                <a href="profile.php" class="btn btn-secondary mt-3">Update Profile</a>
            </div>
        </div>
    </div>
</div>

<!-- Chatbot Bubble -->
<div class="chatbot-bubble" id="chatbot-bubble">
    <i class="fas fa-comments"></i>
</div>

<!-- Chatbot Window -->
<div class="chatbot-window d-none" id="chatbot-window">
    <div class="chatbot-header">
        <span>KP Fitness Bot</span>
        <i class="fas fa-times" id="chatbot-close" style="cursor: pointer;"></i>
    </div>
    <div class="chatbot-messages" id="chatbot-messages">
        <div class="message bot">Hello <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>! How can I help you today?</div>
    </div>
    <div class="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="chatbot-send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // BMI Chart
    fetch('../api/get_weight_history.php')
        .then(response => response.json())
        .then(data => {
            const labels = data.map(item => new Date(item.CreatedAt).toLocaleDateString());
            const weights = data.map(item => item.Weight);
            const height = <?php echo $user['Height'] ?? 0; ?>;
            const bmiData = weights.map(weight => {
                if (height > 0) {
                    const heightInMeters = height / 100;
                    return (weight / (heightInMeters * heightInMeters)).toFixed(1);
                }
                return 0;
            });

            const ctx = document.getElementById('bmiChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'BMI',
                        data: bmiData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error fetching weight history:', error);
        });

    // Chatbot functionality
    const chatbotBubble = document.getElementById('chatbot-bubble');
    const chatbotWindow = document.getElementById('chatbot-window');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');

    chatbotBubble.addEventListener('click', () => {
        chatbotWindow.classList.toggle('d-none');
    });

    chatbotClose.addEventListener('click', () => {
        chatbotWindow.classList.add('d-none');
    });

    chatbotSend.addEventListener('click', sendMessage);
    chatbotInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function sendMessage() {
        const userMessage = chatbotInput.value.trim();
        if (userMessage === '') return;

        appendMessage(userMessage, 'user');
        chatbotInput.value = '';
        
        const formData = new FormData();
        formData.append('message', userMessage);

        fetch('../api/client_chatbot_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            appendMessage(data.reply, 'bot');
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        })
        .catch(error => {
            console.error('Error:', error);
            appendMessage("Oops! Something went wrong. Please try again later.", 'bot');
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        });
    }

    function appendMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', sender);
        messageDiv.innerHTML = text; // Use innerHTML to allow for links
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
});
</script>

<?php include 'includes/client_footer.php'; ?>