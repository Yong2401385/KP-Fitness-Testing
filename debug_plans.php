<?php
require_once 'includes/config.php';
$stmt = $pdo->query("SELECT MembershipID, PlanName FROM membership");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($plans);
echo "</pre>";
?>