</main> <!-- Closes the .main-content div from client_header.php -->

<!-- Chatbot Bubble -->
<div class="chatbot-bubble" id="chatbot-bubble">
    <i class="fas fa-robot me-2"></i> KPF Bot
</div>

<!-- Chatbot Window -->
<div class="chatbot-window d-none" id="chatbot-window">
    <div class="chatbot-header">
        <span>KP Fitness Bot</span>
        <i class="fas fa-times" id="chatbot-close" style="cursor: pointer;"></i>
    </div>
    <div class="chatbot-messages" id="chatbot-messages">
        <div class="message bot">Hello <?php echo htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]); ?>! How can I help you today?</div>
    </div>
    
    <!-- Quick Action Chips -->
    <div class="chatbot-chips" id="chatbot-chips">
        <div class="chip" data-message="What is my next class?">📅 My Next Class</div>
        <div class="chip" data-message="How do I book a class?">🏋️ Book Class</div>
        <div class="chip" data-message="Show me my membership details.">💳 My Membership</div>
        <div class="chip" data-message="What is my latest workout plan?">💪 Workout Plan</div>
    </div>

    <div class="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="chatbot-send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script src="../assets/js/vanilla-js-calendar/vanilla-calendar.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Global configuration for client-side scripts
    // This ensures csrfToken is available on all client pages where the chatbot is present.
    window.clientConfig = {
        csrfToken: '<?php echo get_csrf_token(); ?>'
    };
</script>
<script src="../assets/js/client-chatbot.js"></script>

</body>
</html>