<?php
/**
 * Migration script to update sessions table:
 * 1. Add StartTime and EndTime columns
 * 2. Migrate existing Time data to StartTime
 * 3. Calculate EndTime based on activity Duration
 * 4. Add 'ongoing' status to Status enum
 * 5. Drop old Time column
 */

require_once __DIR__ . '/../includes/config.db.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting migration...\n";
    
    // Step 1: Add StartTime and EndTime columns
    echo "Adding StartTime and EndTime columns...\n";
    $pdo->exec("ALTER TABLE sessions ADD COLUMN StartTime TIME NULL AFTER Time");
    $pdo->exec("ALTER TABLE sessions ADD COLUMN EndTime TIME NULL AFTER StartTime");
    
    // Step 2: Migrate existing Time data to StartTime
    echo "Migrating existing Time data to StartTime...\n";
    $pdo->exec("UPDATE sessions SET StartTime = Time WHERE StartTime IS NULL");
    
    // Step 3: Calculate EndTime based on activity Duration
    echo "Calculating EndTime based on activity Duration...\n";
    $pdo->exec("
        UPDATE sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        SET s.EndTime = ADDTIME(s.StartTime, SEC_TO_TIME(a.Duration * 60))
        WHERE s.EndTime IS NULL
    ");
    
    // Step 4: Modify Status enum to include 'ongoing'
    echo "Updating Status enum to include 'ongoing'...\n";
    $pdo->exec("ALTER TABLE sessions MODIFY COLUMN Status ENUM('scheduled', 'ongoing', 'cancelled', 'completed') DEFAULT 'scheduled'");
    
    // Step 5: Drop old Time column
    echo "Dropping old Time column...\n";
    $pdo->exec("ALTER TABLE sessions DROP COLUMN Time");
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

