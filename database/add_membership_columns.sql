-- Add missing columns to users table for membership management
-- NextMembershipID: Used to schedule downgrades (membership change after current expires)
-- AutoRenew: Tracks whether membership should auto-renew

ALTER TABLE `users` 
ADD COLUMN `NextMembershipID` int(11) DEFAULT NULL AFTER `MembershipEndDate`,
ADD COLUMN `AutoRenew` tinyint(1) DEFAULT 0 AFTER `NextMembershipID`,
ADD KEY `NextMembershipID` (`NextMembershipID`),
ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`NextMembershipID`) REFERENCES `membership` (`MembershipID`) ON DELETE SET NULL;

