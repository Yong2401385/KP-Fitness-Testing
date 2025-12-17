-- Migration: Refine Schema and Add Settings
-- 1. Create system_settings table
-- 2. Standardize reservations status (Done -> attended)

-- 1. Create Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `SettingKey` varchar(50) NOT NULL,
  `SettingValue` text,
  `Description` varchar(255) DEFAULT NULL,
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SettingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default settings
INSERT IGNORE INTO `system_settings` (`SettingKey`, `SettingValue`, `Description`) VALUES
('recurring_booking_weeks', '2', 'Number of weeks ahead a premium member can recur a booking'),
('session_live_window_before', '15', 'Minutes before session start to consider it live'),
('session_live_window_after', '90', 'Minutes after session start to consider it live');

-- 2. Standardize Reservation Status
-- First, update data
UPDATE `reservations` SET `Status` = 'attended' WHERE `Status` = 'Done';

-- Modify ENUM to remove 'Done' (and keep 'Rated' for now as it drives UI logic)
ALTER TABLE `reservations` 
MODIFY COLUMN `Status` ENUM('booked','cancelled','attended','no_show','Rated') DEFAULT 'booked';
