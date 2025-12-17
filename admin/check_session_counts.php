<?php
require_once __DIR__ . '/../includes/config.db.php';

header('Content-Type: text/plain');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Checking Session Booking Counts...\n";
    echo "--------------------------------\n";

    // Query to find discrepancies
    $sql = "
        SELECT 
            s.SessionID,
            s.SessionDate,
            s.StartTime,
            s.CurrentBookings as StoredCount,
            COUNT(r.ReservationID) as ActualCount
        FROM sessions s
        LEFT JOIN reservations r ON s.SessionID = r.SessionID AND r.Status IN ('booked', 'attended', 'Done', 'Rated')
        GROUP BY s.SessionID
        HAVING s.CurrentBookings != COUNT(r.ReservationID)
    ";

    $stmt = $pdo->query($sql);
    $discrepancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($discrepancies)) {
        echo "SUCCESS: All session booking counts match the reservation records.\n";
    } else {
        echo "WARNING: Found " . count($discrepancies) . " discrepancies!\n\n";
        foreach ($discrepancies as $row) {
            echo "Session ID: " . $row['SessionID'] . " (" . $row['SessionDate'] . " " . $row['StartTime'] . ")\n";
            echo "  Stored: " . $row['StoredCount'] . "\n";
            echo "  Actual: " . $row['ActualCount'] . "\n";
            echo "  Diff:   " . ($row['ActualCount'] - $row['StoredCount']) . "\n\n";
        }
        
        echo "To fix these, run the following SQL:\n";
        foreach ($discrepancies as $row) {
            echo "UPDATE sessions SET CurrentBookings = " . $row['ActualCount'] . " WHERE SessionID = " . $row['SessionID'] . ";\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
