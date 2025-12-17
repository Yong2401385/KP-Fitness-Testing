<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Unlock Your Inner Strength</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts & Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #ff6b00;
            --primary-hover: #ff6600;
            --dark-bg: #1a1a1a;
            --light-bg: #2d2d2d;
            --text-light: #ffffff;
            --text-dark: #ffffff;
            --border-color: rgba(255, 107, 0, 0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--light-bg) 100%);
            color: var(--text-light) !important;
            overflow-x: hidden;
        }

        .navbar {
            background: rgba(26, 26, 26, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 0, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-light);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: #fff;
        }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)),
                        url('https://i.ytimg.com/vi/VvcXOos4d-s/maxresdefault.jpg') center/cover no-repeat;
            color: var(--text-light);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .feature-card, .plan-card {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: var(--text-light);
        }

        .feature-card:hover, .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
        }

        .plan-card.popular {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }

        .footer {
            background: #0d0d0d;
            color: var(--text-light);
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid var(--border-color);
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Override Bootstrap text colors */
        .text-muted, .text-body-secondary, .text-dark {
            color: var(--text-light) !important;
        }

        .card-title, .card-text, .list-group-item, .lead, small {
            color: var(--text-light) !important;
        }

        /* Marquee Styles */
        .marquee-wrapper {
            background: #000;
            padding: 2rem 0;
            overflow: hidden;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        .marquee-container {
            display: flex;
            width: fit-content;
            animation: marquee 25s linear infinite;
        }
        .marquee-content {
            display: flex;
            align-items: center;
            gap: 8rem;
        }
        .partner-icon {
            height: 60px;
            width: auto;
            max-width: 150px;
            object-fit: contain;
            filter: grayscale(100%) brightness(1);
            transition: all 0.3s ease;
        }
        .partner-icon:hover {
            filter: grayscale(0%) brightness(1);
            transform: scale(1.1);
        }
        @keyframes marquee {
            from { transform: translateX(0); }
            to { transform: translateX(-25%); }
        }

        /* New Membership Card Styles */
        .plan-card {
            border-radius: 12px;
            padding: 2rem;
            position: relative;
            background: var(--light-bg);
            border: 1px solid var(--border-color);
        }
        .plan-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 2;
        }
        .badge-popular {
            background: var(--primary-color);
            color: #fff;
        }
        .badge-saving {
            background: #2ecc71;
            color: #fff;
        }
        .plan-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 1rem;
        }
        .plan-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
        }
        .plan-period {
            font-size: 0.9rem;
            opacity: 0.7;
            display: block;
            margin-top: -5px;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            text-align: left;
            margin: 0;
        }
        .plan-features li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        .check-icon {
            color: var(--primary-color); /* Green in image, keeping branding Orange or Image Green? User said keep colours. Site uses Orange. I will use Green check if user wants "similar to image", but user said keep colours. Site "check" was orange. I used success green for badge to differentiate. I'll stick to primary for check to be safe with "keep colours". */
            margin-right: 12px;
            background: rgba(255, 107, 0, 0.1);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
    /* Chatbot Styles */
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
        color: #333; /* Ensure text is visible on light bg */
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
        background: white;
        color: #333;
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
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-dumbbell"></i> KP FITNESS
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="login.php">Classes</a></li>

                <!-- Membership scrolls to pricing -->
                <li class="nav-item">
                    <a class="nav-link" href="#pricing">Membership</a>
                </li>

                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>

            <!-- Auth Buttons -->
            <div class="ms-3">
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="btn me-2" style="background-color:#ff6b00;color:#fff;border:none;">Dashboard</a>
                    <a href="logout.php" class="btn btn-outline-warning">Sign Out</a>
                <?php else: ?>
                    <a href="login.php" class="btn me-2" style="background-color:#ff6b00;color:#fff;border:none;">Login</a>
                    <a href="register.php" class="btn" style="background-color:#ff6b00;color:#fff;border:none;">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="container fade-in-up">
        <h1>WHY NOT YOU</h1>
        <p class="lead mb-4">Start Your Journey at KP Fitness and Transform Your Body with Our Mr.Olympia Approved Equipments, Certified Expert Trainers, and State-Of-The-Art Facilities.</p>
        <div>
            <a href="register.php" class="btn btn-primary btn-lg me-3">Start Your Journey</a>
            <a href="about.php" class="btn btn-secondary btn-lg">Learn More</a>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5 section-title">Why Choose KP Fitness?</h2>
        <div class="row g-4">
            <?php
            $features = [
                ['icon' => 'fa-calendar-alt', 'title' => 'Easy Booking', 'desc' => 'Book your favorite classes instantly with our real-time reservation system.'],
                ['icon' => 'fa-users', 'title' => 'Expert Trainers', 'desc' => 'Learn from certified fitness professionals who will guide you.'],
                ['icon' => 'fa-dumbbell', 'title' => 'AI Workout Planner', 'desc' => 'Get personalized workout plans based on your fitness level and goals.'],
                ['icon' => 'fa-chart-line', 'title' => 'Track Progress', 'desc' => 'Monitor your fitness journey with detailed analytics.'],
                ['icon' => 'fa-mobile-alt', 'title' => 'Mobile Friendly', 'desc' => 'Access your account, book classes, and track workouts from any device.'],
                ['icon' => 'fa-shield-alt', 'title' => 'Secure Payments', 'desc' => 'Multiple payment options including Touch & Go, credit cards, and more.']
            ];
            foreach ($features as $f): ?>
                <div class="col-md-4">
                    <div class="card feature-card text-center h-100">
                        <div class="card-body">
                            <i class="fas <?= $f['icon'] ?> fa-3x mb-3" style="color: var(--primary-color);"></i>
                            <h5><?= $f['title'] ?></h5>
                            <p><?= $f['desc'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="marquee-wrapper">
    <div class="marquee-container">
        <div class="marquee-content">
            <img src="https://buffery.us/cdn/shop/files/olympia-logo.png?v=1742141404&width=720" alt="Olympia" class="partner-icon">
            <img src="https://static.vecteezy.com/system/resources/previews/020/190/687/non_2x/nike-logo-nike-icon-free-free-vector.jpg" alt="Nike" class="partner-icon">
            <img src="https://ketchup-ventures.s3.eu-west-1.amazonaws.com/sites/5ea71abb58e53d30e76a42b6/assets/5eb28c4958e53d33e2e3ea9c/my_protein_logo_white.png" alt="MyProtein" class="partner-icon">
            <img src="https://www.freepnglogos.com/uploads/monster-png-logo/green-monster-energy-png-logo-18.png" alt="Monster Energy" class="partner-icon">
            <img src="https://icongym.es/wp-content/uploads/2025/04/logo_newtech.png" alt="New Tech" class="partner-icon">
        </div>
        <div class="marquee-content">
            <img src="https://buffery.us/cdn/shop/files/olympia-logo.png?v=1742141404&width=720" alt="Olympia" class="partner-icon">
            <img src="https://static.vecteezy.com/system/resources/previews/020/190/687/non_2x/nike-logo-nike-icon-free-free-vector.jpg" alt="Nike" class="partner-icon">
            <img src="https://ketchup-ventures.s3.eu-west-1.amazonaws.com/sites/5ea71abb58e53d30e76a42b6/assets/5eb28c4958e53d33e2e3ea9c/my_protein_logo_white.png" alt="MyProtein" class="partner-icon">
            <img src="https://www.freepnglogos.com/uploads/monster-png-logo/green-monster-energy-png-logo-18.png" alt="Monster Energy" class="partner-icon">
            <img src="https://icongym.es/wp-content/uploads/2025/04/logo_newtech.png" alt="New Tech" class="partner-icon">
        </div>
        <div class="marquee-content">
            <img src="https://buffery.us/cdn/shop/files/olympia-logo.png?v=1742141404&width=720" alt="Olympia" class="partner-icon">
            <img src="https://static.vecteezy.com/system/resources/previews/020/190/687/non_2x/nike-logo-nike-icon-free-free-vector.jpg" alt="Nike" class="partner-icon">
            <img src="https://ketchup-ventures.s3.eu-west-1.amazonaws.com/sites/5ea71abb58e53d30e76a42b6/assets/5eb28c4958e53d33e2e3ea9c/my_protein_logo_white.png" alt="MyProtein" class="partner-icon">
            <img src="https://www.freepnglogos.com/uploads/monster-png-logo/green-monster-energy-png-logo-18.png" alt="Monster Energy" class="partner-icon">
            <img src="https://icongym.es/wp-content/uploads/2025/04/logo_newtech.png" alt="New Tech" class="partner-icon">
        </div>
        <div class="marquee-content">
            <img src="https://buffery.us/cdn/shop/files/olympia-logo.png?v=1742141404&width=720" alt="Olympia" class="partner-icon">
            <img src="https://static.vecteezy.com/system/resources/previews/020/190/687/non_2x/nike-logo-nike-icon-free-free-vector.jpg" alt="Nike" class="partner-icon">
            <img src="https://ketchup-ventures.s3.eu-west-1.amazonaws.com/sites/5ea71abb58e53d30e76a42b6/assets/5eb28c4958e53d33e2e3ea9c/my_protein_logo_white.png" alt="MyProtein" class="partner-icon">
            <img src="https://www.freepnglogos.com/uploads/monster-png-logo/green-monster-energy-png-logo-18.png" alt="Monster Energy" class="partner-icon">
            <img src="https://icongym.es/wp-content/uploads/2025/04/logo_newtech.png" alt="New Tech" class="partner-icon">
        </div>
    </div>
</section>
<!-- Membership -->
<section class="py-5" id="pricing">
    <div class="container">
        <h2 class="text-center mb-5 section-title">Membership Plans</h2>
        <div class="row g-4 align-items-center justify-content-center">
            <?php
            // Updates to match existing content but structured for new design
            $plans = [
                [
                    'title' => 'One-Time Class',
                    'price' => 'RM 30',
                    'sub' => '*Pay per class session',
                    'features' => ['Single class access', 'No monthly commitment', 'Pay as you go', 'Access to all equipment', 'Expert coaches'],
                    'badge' => false
                ],
                [
                    'title' => 'Unlimited Monthly',
                    'price' => 'RM 118',
                    'sub' => '*Autopay every 4 weeks',
                    'features' => ['Unlimited group classes', 'Access to all Perks', 'Priority booking', 'Expert coaches', 'Free fitness assessment'],
                    'badge' => 'MOST POPULAR',
                    'badge_class' => 'badge-popular'
                ],
                [
                    'title' => 'Annual Membership',
                    'price' => 'RM 1,183',
                    'sub' => '*One time purchase',
                    'features' => ['Two months FREE', 'Unlimited classes', 'Priority booking', 'Save RM 233/year', 'Access to all Perks'],
                    'badge' => 'HUGE SAVINGS',
                    'badge_class' => 'badge-saving'
                ]
            ];
            foreach ($plans as $p): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card plan-card h-100">
                        <?php if ($p['badge']): ?>
                            <div class="plan-badge <?= $p['badge_class'] ?>"><?= $p['badge'] ?></div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="plan-header">
                                <h5 class="fw-bold mb-3"><?= $p['title'] ?></h5>
                                <div class="plan-price"><?= $p['price'] ?></div>
                                <small class="plan-period"><?= $p['sub'] ?></small>
                            </div>

                            <a href="register.php" class="btn btn-primary w-100 mb-4 py-2 fw-bold" style="border-radius: 50px;">JOIN TODAY</a>

                            <ul class="plan-features">
                                <?php foreach ($p['features'] as $f): ?>
                                    <li>
                                        <div class="check-icon"><i class="fas fa-check"></i></div>
                                        <?= $f ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- About -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="mb-4" style="color: var(--primary-color);">About KP Fitness</h2>
                <p>KP Fitness is dedicated to unlocking your inner strength, promoting holistic well-being, and fostering a vibrant community of fitness enthusiasts.</p>
                <p>Our state-of-the-art facility features specialized zones for various fitness needs, expert trainers, and a comprehensive digital platform that makes fitness accessible and enjoyable for everyone.</p>
                <div class="row mt-4">
                    <div class="col-6 text-center">
                        <h4 style="color: var(--primary-color);">500+</h4>
                        <small>Active Members</small>
                    </div>
                    <div class="col-6 text-center">
                        <h4 style="color: var(--primary-color);">20+</h4>
                        <small>Expert Trainers</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="https://pbs.twimg.com/media/DYqOMlrXcAA2D7W.jpg" class="img-fluid rounded-3" alt="KP Fitness Team">
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section id="contact" class="py-5" style="background-color:#0d0d0d;">
  <div class="container">
    <div class="row g-4">

      <!-- Left: Info -->
      <div class="col-md-5">
        <h3 class="mb-3" style="color:#ff6b00;">Get in Touch</h3>
        <p class="mb-4">Have questions? Reach out to our team and we’ll get back to you ASAP.</p>

        <div class="d-flex align-items-center mb-3">
          <i class="fas fa-envelope fa-lg me-3" style="color:#ff6b00;"></i>
          <span>staff@kpfit.com</span>
        </div>

        <div class="d-flex align-items-center mb-3">
          <i class="fas fa-phone fa-lg me-3" style="color:#ff6b00;"></i>
          <span> 60+ 10 388-4269 </span>
        </div>
      </div>

      <!-- Right: Legal Links -->
      <div class="col-md-6 offset-md-1">
        <h4 class="mb-3" style="color:#ff6b00;">Legal</h4>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="#" class="text-white text-decoration-none">Privacy Policy</a></li>
          <li class="mb-2"><a href="#" class="text-white text-decoration-none">Cookie Policy</a></li>
          <li class="mb-2"><a href="#" class="text-white text-decoration-none">Terms of Service</a></li>
        </ul>
      </div>

    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; 2025 KP Fitness. All rights reserved. | Designed with ❤️ for your fitness journey</p>
    </div>
</footer>

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
        <div class="message bot">Hello! How can I help you today?</div>
    </div>
    <div class="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Type your message...">
        <button id="chatbot-send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scroll Animation & Chatbot JS -->
<script>
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.feature-card, .plan-card').forEach(el => {
        observer.observe(el);
    });

    // Chatbot Logic
    document.addEventListener('DOMContentLoaded', () => {
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
            
            // Send message to chatbot API
            const formData = new FormData();
            formData.append('message', userMessage);

            fetch('api/chatbot_handler.php', {
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

</body>
</html>