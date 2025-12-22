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
            scroll-padding-top: 80px; /* Adjust for fixed navbar height */
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
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .team-card {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .team-card:hover {
            transform: translateY(-5px);
        }
        .team-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 2rem;
            font-weight: 800;
            margin: 0 auto 1rem;
        }
    /* Chatbot Styles */
    .chatbot-bubble {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        width: 140px; /* Wider for text */
        height: 50px; /* Shorter for rectangle */
        border-radius: 25px; /* Rounded rectangle */
        background-color: #ff8c00; /* Orange */
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem; /* Smaller font for text */
        cursor: pointer;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s ease-in-out;
        padding: 0 15px; /* Add some horizontal padding */
    }
    .chatbot-bubble:hover {
        transform: scale(1.05);
    }
    .chatbot-window {
        position: fixed;
        bottom: 90px;
        right: 20px;
        z-index: 1000;
        width: 450px;
        height: 600px;
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

    /* Quick Action Chips */
    .chatbot-chips {
        padding: 5px 15px;
        background-color: #f9f9f9;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        border-top: 1px solid #eee;
        flex-shrink: 0;
    }
    .chip {
        background-color: #fff;
        border: 1px solid #ff8c00;
        color: #ff8c00;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }
    .chip:hover {
        background-color: #ff8c00;
        color: white;
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
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#classes">Classes</a></li>

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
<section class="py-5" id="features">
    <div class="container">
        <h2 class="text-center mb-5 section-title">Why Choose KP Fitness?</h2>
        <div class="row g-4">
            <?php
            $features = [
                ['icon' => 'fa-calendar-alt', 'title' => 'Easy Booking', 'desc' => 'Book your favorite classes instantly with our real-time reservation system.'],
                ['icon' => 'fa-users', 'title' => 'Expert Trainers', 'desc' => 'Learn from certified fitness professionals who will guide you.'],
                ['icon' => 'fa-dumbbell', 'title' => 'AI Workout Planner', 'desc' => 'Get personalized workout plans based on your fitness level and goals.'],
                ['icon' => 'fa-chart-line', 'title' => 'Track Progress', 'desc' => 'Monitor your fitness journey with detailed analytics.'],
                ['icon' => 'fa-star', 'title' => 'Modern Facilities', 'desc' => 'Train in a state-of-the-art environment with premium equipment and amenities designed for optimal performance.'],
                ['icon' => 'fa-shield-alt', 'title' => 'Secure Payments', 'desc' => 'Multiple payment options including Credit/Debit cards, Online Banking, E-Wallet, and more.']
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

<!-- Our Classes -->
<section class="py-5" id="classes">
    <div class="container">
        <h2 class="text-center mb-5 section-title">Our Classes</h2>
        <div class="row g-4 justify-content-center">
            <?php
            $classCategories = [
                ['icon' => 'fa-heartbeat', 'title' => 'Cardio', 'desc' => 'High-energy classes to improve your stamina and heart health. <br><br><strong>Includes:</strong> Zumba, Spin Cycling'],
                ['icon' => 'fa-dumbbell', 'title' => 'Strength', 'desc' => 'Build muscle and strength with resistance training. <br><br><strong>Includes:</strong> BodyPump, Weight Training'],
                ['icon' => 'fa-spa', 'title' => 'Mind & Body', 'desc' => 'Improve flexibility, balance, and mental focus. <br><br><strong>Includes:</strong> Yoga, Pilates, Tai Chi'],
                ['icon' => 'fa-stopwatch', 'title' => 'HIIT & Circuit', 'desc' => 'Intense intervals for maximum calorie burn. <br><br><strong>Includes:</strong> Bootcamp, Metabolic Conditioning'],
                ['icon' => 'fa-fist-raised', 'title' => 'Combat', 'desc' => 'Empower yourself with martial arts-inspired workouts. <br><br><strong>Includes:</strong> Boxing']
            ];
            foreach ($classCategories as $c): ?>
                <div class="col-md-4">
                    <div class="card feature-card text-center h-100">
                        <div class="card-body">
                            <i class="fas <?= $c['icon'] ?> fa-3x mb-3" style="color: var(--primary-color);"></i>
                            <h5 class="mb-3"><?= $c['title'] ?></h5>
                            <p><?= $c['desc'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<!-- Membership -->
<section class="py-5" id="pricing">
    <div class="container">
        <h2 class="text-center mb-5 section-title">Membership Plans</h2>
        <div class="row g-4 justify-content-center">
            <?php
            // Updates to match existing content but structured for new design
            $plans = [
                [
                    'title' => '8 Class Membership',
                    'price' => 'RM 199.00',
                    'sub' => '*Autopay every 4 weeks',
                    'features' => [
                        '8 Classes per Cycle',
                        'Cancel Anytime',
                        'Expert Coaches',
                        'Full Gym Access',
                        'Basic AI Planner',
                        'Standard Booking'
                    ],
                    'badge' => false
                ],
                [
                    'title' => 'Unlimited Monthly',
                    'price' => 'RM 289.00',
                    'sub' => '*Autopay every 4 weeks',
                    'features' => [
                        'Unlimited Classes',
                        'Access to all Perks',
                        'Priority booking',
                        'Free fitness assessment',
                        '<strong>Full AI Planner (All Goals & Levels)</strong>',
                        '<strong>Save Unlimited Workout Plans</strong>',
                        '<strong>2-Week Recurring Booking</strong>'
                    ],
                    'badge' => 'MOST POPULAR',
                    'badge_class' => 'badge-popular'
                ],
                [
                    'title' => 'Annual Membership',
                    'price' => 'RM 2,899.00',
                    'sub' => '*One time purchase',
                    'features' => [
                        'Two months FREE',
                        'Unlimited Classes',
                        'Priority booking',
                        'Save RM 233/year',
                        'Access to all Perks',
                        '<strong>Full AI Planner (All Goals & Levels)</strong>',
                        '<strong>Save Unlimited Workout Plans</strong>',
                        '<strong>2-Week Recurring Booking</strong>'
                    ],
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
<section class="py-5" id="about">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title mb-3">About KP Fitness</h2>
            <p class="lead" style="max-width: 800px; margin: 0 auto;">Empowering lives through fitness, technology, and community since 2020.</p>
        </div>

        <div class="row g-5 align-items-center mb-5">
            <div class="col-lg-6">
                <h3 class="mb-4" style="color: var(--primary-color);">Our Story</h3>
                <p>KP Fitness was founded with a simple yet powerful mission: to make fitness accessible, enjoyable, and effective for everyone. What started as a small local gym has evolved into a comprehensive fitness ecosystem that combines cutting-edge technology with expert training.</p>
                <p>We believe that fitness is not just about physical transformation, but about building confidence, discipline, and a supportive community. Our state-of-the-art facility features specialized zones for various fitness needs.</p>
                
                <div class="row mt-4">
                    <div class="col-6 text-center">
                        <h4 style="color: var(--primary-color);">200+</h4>
                        <small>Active Members</small>
                    </div>
                    <div class="col-6 text-center">
                        <h4 style="color: var(--primary-color);">8+</h4>
                        <small>Expert Trainers</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="https://pbs.twimg.com/media/DYqOMlrXcAA2D7W.jpg" class="img-fluid rounded-3" alt="KP Fitness Team">
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="p-4 rounded-3" style="background: var(--light-bg); border: 1px solid var(--border-color);">
                    <h3 class="mb-3" style="color: var(--primary-color);">Mission & Vision</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <p><strong>Mission:</strong> To empower individuals to unlock their inner strength and achieve holistic well-being through innovative fitness solutions, expert guidance, and a supportive community environment.</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Vision:</strong> To become the leading fitness destination that seamlessly integrates technology, expertise, and community to create transformative fitness experiences for people of all fitness levels.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <h3 style="color: var(--primary-color);">Meet Our Expert Team</h3>
        </div>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-avatar">JD</div>
                <h4>John Doe</h4>
                <p class="mb-0 text-primary">Head Trainer</p>
            </div>
            <div class="team-card">
                <div class="team-avatar">SM</div>
                <h4>Sarah Miller</h4>
                <p class="mb-0 text-primary">Yoga Specialist</p>
            </div>
            <div class="team-card">
                <div class="team-avatar">MJ</div>
                <h4>Mike Johnson</h4>
                <p class="mb-0 text-primary">HIIT Expert</p>
            </div>
            <div class="team-card">
                <div class="team-avatar">AL</div>
                <h4>Amy Lee</h4>
                <p class="mb-0 text-primary">Pilates Instructor</p>
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
    <i class="fas fa-robot me-2"></i> KPF Bot
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

    <!-- Quick Action Chips -->
    <div class="chatbot-chips" id="chatbot-chips">
        <div class="chip" data-message="What are your membership plans?">💰 Membership Plans</div>
        <div class="chip" data-message="What classes do you offer?">🏋️ View Classes</div>
        <div class="chip" data-message="How do I join KP Fitness?">📝 How to Join</div>
        <div class="chip" data-message="What are your operating hours?">🕒 Operating Hours</div>
        <div class="chip" data-message="Where is the gym located?">📍 Gym Location</div>
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
        const chatbotChips = document.getElementById('chatbot-chips');

        chatbotBubble.addEventListener('click', () => {
            chatbotWindow.classList.toggle('d-none');
        });

        chatbotClose.addEventListener('click', () => {
            chatbotWindow.classList.add('d-none');
        });

        // Handle Chip Clicks
        chatbotChips.addEventListener('click', (e) => {
            if (e.target.classList.contains('chip')) {
                const message = e.target.getAttribute('data-message');
                chatbotInput.value = message;
                sendMessage();
            }
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