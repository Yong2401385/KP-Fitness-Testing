<?php
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$limitDate = date('Y-m-d', strtotime('-1 month'));

try {
    // 1. Fetch All Data in One Query (Optimized)
    $stmt = $pdo->prepare("
        SELECT 
            s.SessionID, s.SessionDate, s.StartTime, s.Status AS SessionStatus,
            c.ClassName,
            r.ReservationID, r.Status AS BookingStatus,
            u.FullName,
            a.Status AS AttendanceStatus
        FROM sessions s
        JOIN activities c ON s.ClassID = c.ClassID
        LEFT JOIN reservations r ON s.SessionID = r.SessionID
        LEFT JOIN users u ON r.UserID = u.UserID
        LEFT JOIN attendance a ON r.UserID = a.UserID AND s.SessionID = a.SessionID
        WHERE s.TrainerID = ? 
        AND s.SessionDate < CURDATE()
        AND s.SessionDate >= ?
        ORDER BY s.SessionDate DESC, s.StartTime ASC, u.FullName ASC
    ");
    $stmt->execute([$trainerId, $limitDate]);
    $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedSessions = [];
    foreach ($allRecords as $row) {
        $date = $row['SessionDate'];
        $sessionId = $row['SessionID'];

        // Initialize Date Group
        if (!isset($groupedSessions[$date])) {
            $groupedSessions[$date] = [];
        }

        // Initialize Session Group (keyed by ID temporarily to merge rows)
        if (!isset($groupedSessions[$date][$sessionId])) {
            $groupedSessions[$date][$sessionId] = [
                'SessionID' => $sessionId,
                'SessionDate' => $date,
                'StartTime' => $row['StartTime'],
                'ClassName' => $row['ClassName'],
                'Status' => $row['SessionStatus'],
                'records' => []
            ];
        }

        // Add Attendee Record (if exists - LEFT JOIN might return nulls for reservation columns)
        if ($row['ReservationID']) {
            $groupedSessions[$date][$sessionId]['records'][] = [
                'FullName' => $row['FullName'],
                'BookingStatus' => $row['BookingStatus'],
                'AttendanceStatus' => $row['AttendanceStatus']
            ];
        }
    }

    // Re-index to remove SessionID keys if needed (though foreach handles keys fine)
    // The previous structure was $groupedSessions[$date][] = $session. 
    // Now it is $groupedSessions[$date][$sessionId] = $session.
    // The view loop `foreach ($groupedSessions as $date => $sessions)` and `foreach ($sessions as $session)` works identical.

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    exit;
}

if (empty($groupedSessions)): ?>
    <div class="text-center p-4 text-muted">
        <i class="fas fa-history fa-2x mb-3"></i>
        <p>No class records found for the last 30 days.</p>
    </div>
<?php else: ?>
    <div class="accordion accordion-flush" id="historyAccordion">
        <?php foreach ($groupedSessions as $date => $sessions): ?>
            <!-- Date Item -->
            <div class="accordion-item bg-transparent border-0 mb-2"> <!-- Removed border-bottom, moved to button -->
                <h2 class="accordion-header" id="heading-<?php echo $date; ?>">
                    <button class="accordion-button collapsed text-white py-3 border border-secondary rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $date; ?>" aria-expanded="false">
                        <i class="fas fa-calendar-alt me-3 text-primary fa-lg"></i> 
                        <strong style="font-size: 1.1rem;"><?php echo date('l, M j, Y', strtotime($date)); ?></strong> <!-- Larger font -->
                        <span class="badge bg-primary ms-auto" style="font-size: 0.8rem;"><?php echo count($sessions); ?> Classes</span> <!-- Orange badge -->
                    </button>
                </h2>
                <div id="collapse-<?php echo $date; ?>" class="accordion-collapse collapse" data-bs-parent="#historyAccordion">
                    <div class="accordion-body p-0 border border-top-0 border-secondary rounded-bottom" style="background-color: #333;"> <!-- Darker background for body -->
                         <div class="list-group list-group-flush">
                            <?php foreach ($sessions as $session): ?>
                                <div class="list-group-item bg-dark text-white border-bottom border-secondary py-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong class="text-primary" style="font-size: 1rem;"><?php echo htmlspecialchars($session['ClassName']); ?></strong>
                                            <div class="small text-muted"><?php echo format_time($session['StartTime']); ?></div>
                                        </div>
                                        <span class="badge bg-<?php echo $session['Status'] === 'completed' ? 'success' : 'info'; ?>" style="font-size: 0.75rem;"> <!-- Changed secondary to info for better contrast -->
                                            <?php echo ucfirst($session['Status']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if(!empty($session['records'])): ?>
                                        <div class="mt-2 pt-2 border-top border-secondary">
                                            <div class="d-flex flex-wrap gap-2 justify-content-start">
                                                <?php foreach ($session['records'] as $record): 
                                                     $attStatus = $record['AttendanceStatus'];
                                                     $badgeClass = ($attStatus === 'present') ? 'bg-success' : (($attStatus === 'absent') ? 'bg-danger' : 'bg-secondary');
                                                     $icon = ($attStatus === 'present') ? 'check' : (($attStatus === 'absent') ? 'times' : 'minus');
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> p-2" title="<?php echo htmlspecialchars($record['FullName']); ?>" style="font-weight: normal; font-size: 0.8rem;">
                                                    <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                    <?php echo htmlspecialchars($record['FullName']); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted fst-italic mt-1">No bookings for this session.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                         </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<style>
    /* Custom styles for the accordion in the modal */
    #historyAccordion .accordion-button {
        background-color: #202020; /* Darker background for collapsed state */
        color: var(--dash-text-headers);
        transition: background-color 0.3s ease;
    }
    #historyAccordion .accordion-button:hover {
        background-color: #252525;
    }
    #historyAccordion .accordion-button:not(.collapsed) {
        background-color: #3a3a3a; /* Subtle dark grey when expanded */
        color: white;
        border-color: #3a3a3a !important; /* Match border to new background */
    }
    #historyAccordion .accordion-button::after {
        filter: invert(1); /* White arrow icon */
    }
    #historyAccordion .accordion-item {
        background-color: transparent;
    }
</style>

<?php endif; ?>
