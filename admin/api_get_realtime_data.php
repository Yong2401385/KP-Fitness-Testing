<?php
require_once '../includes/config.php';
require_admin(); // Security check

header('Content-Type: application/json');

$last24Hours = date('Y-m-d H:i:s', strtotime('-24 hours'));

// --- Handle Detail Request ---
if (isset($_GET['detail_type'])) {
    $type = $_GET['detail_type'];
    $details = [];

    switch ($type) {
        case 'registrations':
            $stmt = $pdo->prepare("
                SELECT FullName, Email, Role, DATE_FORMAT(CreatedAt, '%h:%i %p') as Time
                FROM users 
                WHERE CreatedAt >= ? AND Role != 'admin'
                ORDER BY CreatedAt DESC
            ");
            $stmt->execute([$last24Hours]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'memberships':
            $stmt = $pdo->prepare("
                SELECT u.FullName, m.PlanName, p.Amount, p.PaymentMethod, DATE_FORMAT(p.PaymentDate, '%h:%i %p') as Time
                FROM payments p
                JOIN users u ON p.UserID = u.UserID
                JOIN membership m ON p.MembershipID = m.MembershipID
                WHERE p.PaymentDate >= ? AND p.Status = 'completed'
                ORDER BY p.PaymentDate DESC
            ");
            $stmt->execute([$last24Hours]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bookings':
            $stmt = $pdo->prepare("
                SELECT u.FullName, a.ClassName, DATE_FORMAT(s.SessionDate, '%d %b') as SessionDate, s.StartTime, DATE_FORMAT(r.BookingDate, '%h:%i %p') as Time
                FROM reservations r
                JOIN users u ON r.UserID = u.UserID
                JOIN sessions s ON r.SessionID = s.SessionID
                JOIN activities a ON s.ClassID = a.ClassID
                WHERE r.BookingDate >= ?
                ORDER BY r.BookingDate DESC
            ");
            $stmt->execute([$last24Hours]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'sessions':
            $stmt = $pdo->prepare("
                SELECT a.ClassName, u.FullName as TrainerName, DATE_FORMAT(s.SessionDate, '%d %b') as Date, s.StartTime, s.CurrentBookings
                FROM sessions s
                JOIN activities a ON s.ClassID = a.ClassID
                JOIN users u ON s.TrainerID = u.UserID
                WHERE s.Status = 'completed' AND s.SessionDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY s.SessionDate DESC, s.StartTime DESC
            ");
            $stmt->execute();
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

    echo json_encode(['success' => true, 'data' => $details]);
    exit;
}

// --- Default: Summary Stats & Chart Data ---
try {
    // 1. Stats Counters
    $total_registrations = $pdo->query("SELECT COUNT(*) FROM users WHERE CreatedAt >= '$last24Hours' AND Role != 'admin'")->fetchColumn();
    $total_memberships = $pdo->query("SELECT COUNT(*) FROM payments WHERE PaymentDate >= '$last24Hours' AND Status = 'completed'")->fetchColumn();
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM reservations WHERE BookingDate >= '$last24Hours'")->fetchColumn();
    $total_sessions = $pdo->query("SELECT COUNT(*) FROM sessions WHERE Status = 'completed' AND SessionDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

    // 2. Chart Data
    
    // Registrations by Hour
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(CreatedAt, '%H:00') as hour, COUNT(*) as count
        FROM users 
        WHERE CreatedAt >= ? AND Role != 'admin'
        GROUP BY DATE_FORMAT(CreatedAt, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute([$last24Hours]);
    $registrations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Memberships by Type
    $stmt = $pdo->prepare("
        SELECT m.PlanName, COUNT(p.PaymentID) as count
        FROM payments p
        JOIN membership m ON p.MembershipID = m.MembershipID
        WHERE p.PaymentDate >= ? AND p.Status = 'completed'
        GROUP BY m.MembershipID
    ");
    $stmt->execute([$last24Hours]);
    $memberships_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bookings by Hour
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(BookingDate, '%H:00') as hour, COUNT(*) as count
        FROM reservations 
        WHERE BookingDate >= ?
        GROUP BY DATE_FORMAT(BookingDate, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute([$last24Hours]);
    $bookings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sessions by Hour
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(s.SessionDate, '%H:00') as hour, COUNT(*) as count
        FROM sessions s
        WHERE s.Status = 'completed' AND s.SessionDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(s.SessionDate, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute();
    $sessions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => [
            'registrations' => $total_registrations,
            'memberships' => $total_memberships,
            'bookings' => $total_bookings,
            'sessions' => $total_sessions
        ],
        'charts' => [
            'registrations' => [
                'labels' => array_column($registrations_data, 'hour'),
                'values' => array_column($registrations_data, 'count')
            ],
            'memberships' => [
                'labels' => array_column($memberships_data, 'PlanName'),
                'values' => array_column($memberships_data, 'count')
            ],
            'bookings' => [
                'labels' => array_column($bookings_data, 'hour'),
                'values' => array_column($bookings_data, 'count')
            ],
            'sessions' => [
                'labels' => array_column($sessions_data, 'hour'),
                'values' => array_column($sessions_data, 'count')
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
