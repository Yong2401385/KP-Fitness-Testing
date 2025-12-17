<?php
/**
 * Migration script to add NextMembershipID and AutoRenew columns to users table
 * Run this script once to fix the "Column not found" error
 */

require_once __DIR__ . '/../includes/config.db.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting migration...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'NextMembershipID'");
    $nextMembershipExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'AutoRenew'");
    $autoRenewExists = $stmt->rowCount() > 0;
    
    if ($nextMembershipExists && $autoRenewExists) {
        echo "Columns already exist. Migration not needed.\n";
        exit(0);
    }
    
    // Add NextMembershipID column if it doesn't exist
    if (!$nextMembershipExists) {
        echo "Adding NextMembershipID column...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `NextMembershipID` int(11) DEFAULT NULL AFTER `MembershipEndDate`");
        echo "NextMembershipID column added successfully.\n";
    }
    
    // Add AutoRenew column if it doesn't exist
    if (!$autoRenewExists) {
        echo "Adding AutoRenew column...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `AutoRenew` tinyint(1) DEFAULT 0 AFTER `NextMembershipID`");
        echo "AutoRenew column added successfully.\n";
    }
    
    // Add index for NextMembershipID if it doesn't exist
    $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'NextMembershipID'");
    if ($stmt->rowCount() == 0) {
        echo "Adding index for NextMembershipID...\n";
        $pdo->exec("ALTER TABLE `users` ADD KEY `NextMembershipID` (`NextMembershipID`)");
        echo "Index added successfully.\n";
    }
    
    // Add foreign key constraint if it doesn't exist
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                         WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
                         AND TABLE_NAME = 'users' 
                         AND CONSTRAINT_NAME = 'users_ibfk_3'");
    if ($stmt->rowCount() == 0) {
        echo "Adding foreign key constraint...\n";
        $pdo->exec("ALTER TABLE `users` 
                    ADD CONSTRAINT `users_ibfk_3` 
                    FOREIGN KEY (`NextMembershipID`) 
                    REFERENCES `membership` (`MembershipID`) 
                    ON DELETE SET NULL");
        echo "Foreign key constraint added successfully.\n";
    }
    
    echo "\nMigration completed successfully!\n";
    echo "You can now refresh your application.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

