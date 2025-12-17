<?php
require_once '../includes/config.php';
require_admin();

// Get filter period (default to 12 months)
$period = isset($_GET['period']) ? intval($_GET['period']) : 12;
if (!in_array($period, [1, 3, 6, 12])) {
    $period = 12;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="kp_fitness_report_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// --- Section 1: Summary Headers ---
fputcsv($output, ['KP Fitness - Business Report']);
fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Period:', "Last $period Months"]);
fputcsv($output, []); // Empty line

// --- Section 2: Revenue Data ---
fputcsv($output, ['--- Monthly Revenue ---']);
fputcsv($output, ['Month', 'Revenue (RM)']);

try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(PaymentDate, '%Y-%m') as month,
            SUM(Amount) as revenue
        FROM payments 
        WHERE Status = 'completed' AND PaymentDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(PaymentDate, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$period]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['month'], number_format($row['revenue'], 2, '.', '')]);
    }
} catch (PDOException $e) {
    fputcsv($output, ['Error fetching revenue data']);
}

fputcsv($output, []); // Empty line

// --- Section 3: Popular Activities ---
fputcsv($output, ['--- Popular Activities (All Time) ---']);
fputcsv($output, ['Activity Name', 'Total Bookings']);

try {
    $stmt = $pdo->query("
        SELECT a.ClassName, COUNT(r.ReservationID) as booking_count 
        FROM activities a 
        JOIN sessions s ON a.ClassID = s.ClassID 
        JOIN reservations r ON s.SessionID = r.SessionID 
        GROUP BY a.ClassID 
        ORDER BY booking_count DESC LIMIT 10
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['ClassName'], $row['booking_count']]);
    }
} catch (PDOException $e) {
    fputcsv($output, ['Error fetching activity data']);
}

fputcsv($output, []); // Empty line

// --- Section 4: Membership Distribution ---
fputcsv($output, ['--- Active Memberships ---']);
fputcsv($output, ['Plan Name', 'Member Count']);

try {
    $stmt = $pdo->query("
        SELECT m.PlanName, COUNT(u.UserID) as member_count 
        FROM users u
        JOIN membership m ON u.MembershipID = m.MembershipID
        WHERE u.Role = 'client' AND u.IsActive = TRUE
        GROUP BY u.MembershipID
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['PlanName'], $row['member_count']]);
    }
} catch (PDOException $e) {
    fputcsv($output, ['Error fetching membership data']);
}

fclose($output);
exit;
