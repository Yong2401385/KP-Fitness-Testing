<?php
define('PAGE_TITLE', 'Notifications');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// Handle "Mark All as Read"
if (isset($_POST['mark_all_read'])) {
    validate_csrf_token($_POST['csrf_token']);
    $stmt = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ?");
    $stmt->execute([$userId]);
    // Refresh to show updates
    header("Location: notifications.php");
    exit;
}

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

include 'includes/client_header.php';
?>
<style>
    /* Custom hover effect for notifications */
    .list-group-item-action:hover {
        background-color: rgba(255, 107, 0, 0.2) !important; /* Slightly more visible light orange with transparency */
        color: #fff !important; /* Ensure main text is white */
    }
    .list-group-item-action:hover .text-muted {
        color: #ccc !important; /* Ensure secondary text is light gray */
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Notifications</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-check-double me-1"></i> Mark All Read
            </button>
        </form>
    </div>
</div>

<!-- Notification Filters -->
<div class="mb-3">
    <div class="btn-group" role="group" aria-label="Notification Filters">
        <button type="button" class="btn btn-outline-secondary active" onclick="filterNotifications('all', this)">All</button>
        <button type="button" class="btn btn-outline-secondary" onclick="filterNotifications('booking', this)">Bookings</button>
        <button type="button" class="btn btn-outline-secondary" onclick="filterNotifications('system', this)">System</button>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="list-group list-group-flush" id="notificationList">
                <?php if (empty($notifications)): ?>
                    <div class="list-group-item text-center p-5 text-muted">
                        <i class="fas fa-bell-slash fa-3x mb-3"></i>
                        <p>You have no notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php 
                            $icon = 'info-circle';
                            $color = 'primary';
                            $link = ''; // Default no link
                            $category = 'system'; // Default category

                            $titleLower = strtolower($notif['Title']);
                            if (strpos($titleLower, 'booking') !== false || strpos($titleLower, 'booked') !== false || strpos($titleLower, 'recurring') !== false || strpos($titleLower, 'cancell') !== false) {
                                $category = 'booking';
                            }
                            
                            switch($notif['Type']) {
                                case 'success': $icon = 'check-circle'; $color = 'success'; break;
                                case 'warning': $icon = 'exclamation-triangle'; $color = 'warning'; break;
                                case 'error': $icon = 'times-circle'; $color = 'danger'; break;
                            }
                            
                            if ($notif['Title'] === 'Action Required: Complete Profile') {
                                $link = 'profile.php';
                            }
                        ?>
                        <div class="list-group-item list-group-item-action notification-item <?php echo !$notif['IsRead'] ? 'bg-light unread' : ''; ?>" 
                             data-id="<?php echo $notif['NotificationID']; ?>" 
                             data-link="<?php echo $link; ?>"
                             data-category="<?php echo $category; ?>"
                             style="cursor: pointer;">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="me-3 text-<?php echo $color; ?>">
                                        <i class="fas fa-<?php echo $icon; ?> fa-2x"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1 <?php echo !$notif['IsRead'] ? 'fw-bold' : ''; ?> title-text"><?php echo htmlspecialchars($notif['Title']); ?></h5>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($notif['Message']); ?></p>
                                    </div>
                                </div>
                                <small class="text-muted text-nowrap ms-3"><?php echo format_date($notif['CreatedAt']) . ' ' . format_time($notif['CreatedAt']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function filterNotifications(category, btn) {
    // Update active button state
    document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const items = document.querySelectorAll('.notification-item');
    items.forEach(item => {
        if (category === 'all' || item.dataset.category === category) {
            item.classList.remove('d-none');
        } else {
            item.classList.add('d-none');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const notifications = document.querySelectorAll('.notification-item');
    const badge = document.querySelector('.sidebar .badge'); // Assuming sidebar badge has .badge class

    notifications.forEach(item => {
        item.addEventListener('click', function() {
            const id = this.dataset.id;
            const link = this.dataset.link;
            const isUnread = this.classList.contains('unread');

            if (isUnread) {
                // Visual update immediately
                this.classList.remove('bg-light', 'unread');
                this.querySelector('.title-text').classList.remove('fw-bold');
                
                // Update badge count
                if (badge) {
                    let count = parseInt(badge.textContent);
                    if (count > 0) {
                        count--;
                        if (count === 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                }

                // Send request
                fetch('../api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    // Add CSRF token from global config
                    body: JSON.stringify({ 
                        id: id,
                        csrf_token: window.clientConfig.csrfToken 
                    })
                }).then(() => {
                    if (link) window.location.href = link;
                }).catch(err => {
                    console.error('Error marking read:', err);
                    if (link) window.location.href = link; // Redirect anyway
                });
            } else if (link) {
                // Already read, just redirect
                window.location.href = link;
            }
        });
    });
});
</script>

<?php include 'includes/client_footer.php'; ?>
