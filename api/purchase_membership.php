<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF token is missing.']);
    exit;
}

validate_csrf_token($_POST['csrf_token']);

$userId = $_SESSION['UserID'];
$action = $_POST['action'] ?? 'purchase'; // Default to purchase for backward compatibility
$response = [];

try {
    if ($action === 'purchase') {
        if (!isset($_POST['membershipId'])) {
            throw new Exception('Membership ID is missing.');
        }
        $newMembershipId = intval($_POST['membershipId']);

        // Fetch New Plan Details
        $stmt = $pdo->prepare("SELECT * FROM membership WHERE MembershipID = ?");
        $stmt->execute([$newMembershipId]);
        $newPlan = $stmt->fetch();

        if (!$newPlan) {
            throw new Exception('Invalid membership plan selected.');
        }

        // Fetch Current Plan Details
        $stmt = $pdo->prepare("
            SELECT u.MembershipID, m.Cost as CurrentCost, u.MembershipEndDate 
            FROM users u 
            LEFT JOIN membership m ON u.MembershipID = m.MembershipID 
            WHERE u.UserID = ?
        ");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        $currentCost = $currentUser['CurrentCost'] ?? 0;
        
        $pdo->beginTransaction();

        if ($newPlan['Cost'] >= $currentCost) {
            // --- UPGRADE (Immediate) ---
            $description = "Upgraded to " . $newPlan['PlanName'];
            $stmt = $pdo->prepare("INSERT INTO payments (UserID, MembershipID, Amount, PaymentMethod, Status, PaymentType, Description) VALUES (?, ?, ?, 'credit_card', 'completed', 'Membership', ?)");
            $stmt->execute([$userId, $newMembershipId, $newPlan['Cost'], $description]);
            
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$newPlan['Duration']} days"));
            
            $autoRenew = ($newPlan['Duration'] >= 30) ? 1 : 0;

            // Clear any pending next membership
            $stmt = $pdo->prepare("UPDATE users SET MembershipID = ?, MembershipStartDate = ?, MembershipEndDate = ?, AutoRenew = ?, NextMembershipID = NULL WHERE UserID = ?");
            $stmt->execute([$newMembershipId, $startDate, $endDate, $autoRenew, $userId]);
            
            create_notification($userId, 'Membership Upgraded', "Welcome to {$newPlan['PlanName']}! Your new benefits are active immediately.", 'success');
            
            $response = ['success' => true, 'message' => 'Membership upgraded successfully! New benefits applied immediately.'];

        } else {
            // --- DOWNGRADE (Scheduled) ---
            $description = "Downgrade Scheduled to " . $newPlan['PlanName'];
            // Note: We might charge now or later. Assuming charge now for simplicity of this flow, 
            // or maybe just 0 if it's a schedule?
            // "Allow user to downgrade" usually implies they set it up. 
            // I'll log a 0 amount "Scheduled" payment record for history tracking, or charge the new price?
            // If we charge now, it's weird if it starts in 3 weeks. 
            // Let's log the action but maybe not charge? 
            // But `payments` table expects Amount.
            // Let's assume we charge the new rate to "Lock it in".
            $stmt = $pdo->prepare("INSERT INTO payments (UserID, MembershipID, Amount, PaymentMethod, Status, PaymentType, Description) VALUES (?, ?, ?, 'credit_card', 'completed', 'Membership', ?)");
            $stmt->execute([$userId, $newMembershipId, $newPlan['Cost'], $description]);

            $stmt = $pdo->prepare("UPDATE users SET NextMembershipID = ? WHERE UserID = ?");
            $stmt->execute([$newMembershipId, $userId]);
            
            $formattedDate = date('d/m/Y', strtotime($currentUser['MembershipEndDate']));
            create_notification($userId, 'Downgrade Scheduled', "Your plan will switch to {$newPlan['PlanName']} on {$formattedDate}.", 'info');

            $response = ['success' => true, 'message' => "Downgrade scheduled. Your new plan will start on $formattedDate after your current plan expires."];
        }
        
        $pdo->commit();

    } elseif ($action === 'cancel') {
        // Cancel auto-renew
        $stmt = $pdo->prepare("UPDATE users SET AutoRenew = 0 WHERE UserID = ?");
        $stmt->execute([$userId]);
        
        // Fetch end date for message
        $stmt = $pdo->prepare("SELECT MembershipEndDate FROM users WHERE UserID = ?");
        $stmt->execute([$userId]);
        $endDate = $stmt->fetchColumn();
        
        $formattedDate = $endDate ? date('F j, Y', strtotime($endDate)) : 'today';
        
        create_notification($userId, 'Auto-Renewal Cancelled', "You have cancelled auto-renewal. Benefits valid until {$formattedDate}.", 'warning');

        $response = ['success' => true, 'message' => "Membership auto-renewal has been cancelled. You retain benefits until $formattedDate."];
    } else {
        $response = ['success' => false, 'message' => 'Invalid action.'];
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Differentiate between PDOException (log it) and generic Exception (show user if safe)
    if ($e instanceof PDOException) {
        error_log('Database error in purchase_membership.php: ' . $e->getMessage());
        $response = ['success' => false, 'message' => 'An internal error occurred. Please try again later.'];
    } else {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
