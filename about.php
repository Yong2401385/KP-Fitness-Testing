<?php
define('PAGE_TITLE', 'About Us');
require_once 'includes/config.php';
include 'includes/header.php';
?>

<style>
.page-header {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--light-bg);
    border-radius: 12px;
    margin-top: 2rem;
    margin-bottom: 3rem;
}
.page-header h1 {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary-color);
}
.page-header p {
    font-size: 1.2rem;
    color: var(--text-dark);
    max-width: 600px;
    margin: 1rem auto 0;
}

.content-section {
    padding: 2rem 0;
}
.content-section h2 {
    font-size: 2.2rem;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--primary-color);
    padding-left: 1rem;
}
.content-section p {
    margin-bottom: 1.5rem;
    line-height: 1.8;
    color: var(--text-dark);
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}
.team-card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
}
.team-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-light);
    font-size: 3rem;
    font-weight: 800;
    margin: 0 auto 1.5rem;
}
.team-card h4 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
}
.team-card p {
    color: var(--primary-color);
    font-weight: 600;
}

</style>

<div class="page-header">
    <h1>About KP Fitness</h1>
    <p>Empowering lives through fitness, technology, and community since 2020.</p>
</div>

<section class="content-section">
    <h2>Our Story</h2>
    <p>KP Fitness was founded with a simple yet powerful mission: to make fitness accessible, enjoyable, and effective for everyone. What started as a small local gym has evolved into a comprehensive fitness ecosystem that combines cutting-edge technology with expert training.</p>
    <p>We believe that fitness is not just about physical transformation, but about building confidence, discipline, and a supportive community. Our state-of-the-art facility features specialized zones for various fitness needs. Through our innovative digital platform, we've revolutionized how our members interact with fitness services, making booking, tracking, and achieving fitness goals more convenient than ever before.</p>
</section>

<section class="content-section">
    <h2>Our Mission & Vision</h2>
    <p><strong>Mission:</strong> To empower individuals to unlock their inner strength and achieve holistic well-being through innovative fitness solutions, expert guidance, and a supportive community environment.</p>
    <p><strong>Vision:</strong> To become the leading fitness destination that seamlessly integrates technology, expertise, and community to create transformative fitness experiences for people of all fitness levels.</p>
</section>

<section class="content-section">
    <h2>Meet Our Expert Team</h2>
    <div class="team-grid">
        <div class="team-card">
            <div class="team-avatar">JD</div>
            <h4>John Doe</h4>
            <p>Head Trainer</p>
        </div>
        <div class="team-card">
            <div class="team-avatar">SM</div>
            <h4>Sarah Miller</h4>
            <p>Yoga Specialist</p>
        </div>
        <div class="team-card">
            <div class="team-avatar">MJ</div>
            <h4>Mike Johnson</h4>
            <p>HIIT Expert</p>
        </div>
        <div class="team-card">
            <div class="team-avatar">AL</div>
            <h4>Amy Lee</h4>
            <p>Pilates Instructor</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
