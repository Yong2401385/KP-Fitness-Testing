-- KP Fitness Database Schema
--
-- To use this file:
-- 1. Open phpMyAdmin in your XAMPP control panel.
-- 2. Create a new database named `kp_fitness_db`.
-- 3. Select the `kp_fitness_db` database.
-- 4. Go to the "Import" tab.
-- 5. Choose this `schema.sql` file and click "Go".

--
-- Database: `kp_fitness_db`
--
CREATE DATABASE IF NOT EXISTS kp_fitness_db;
USE kp_fitness_db;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `AttendanceID` int(11) NOT NULL,
  `SessionID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `AttendanceDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('present','absent','late') DEFAULT 'present',
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `ClassID` int(11) NOT NULL,
  `ClassName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `Duration` int(11) NOT NULL,
  `MaxCapacity` int(11) NOT NULL DEFAULT 20,
  `DifficultyLevel` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`ClassID`, `ClassName`, `Description`, `Duration`, `MaxCapacity`, `DifficultyLevel`, `IsActive`, `CreatedAt`) VALUES
(1, 'HIIT Training', 'High-Intensity Interval Training for maximum calorie burn', 45, 20, 'intermediate', 1, '2025-12-10 06:10:48'),
(2, 'Yoga Flow', 'Mindful movement and breathing exercises', 60, 15, 'beginner', 1, '2025-12-10 06:10:48'),
(3, 'Strength Training', 'Build muscle and increase strength', 50, 12, 'intermediate', 1, '2025-12-10 06:10:48'),
(4, 'Cardio Blast', 'High-energy cardio workout', 40, 25, 'beginner', 1, '2025-12-10 06:10:48'),
(5, 'Pilates Core', 'Core strengthening and flexibility', 55, 18, 'beginner', 1, '2025-12-10 06:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `membership`
--

CREATE TABLE `membership` (
  `MembershipID` int(11) NOT NULL,
  `Type` enum('monthly','yearly','onetime') NOT NULL,
  `Cost` decimal(10,2) NOT NULL,
  `Duration` int(11) NOT NULL,
  `Benefits` text DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership`
--

INSERT INTO `membership` (`MembershipID`, `Type`, `Cost`, `Duration`, `Benefits`, `IsActive`, `CreatedAt`) VALUES
(1, 'monthly', 118.00, 30, 'Unlimited classes, Access to all trainers, Priority booking', 1, '2025-12-10 06:10:48'),
(2, 'yearly', 1183.00, 365, 'All monthly benefits, 2 months free, Guest passes (up to 2), Exclusive events', 1, '2025-12-10 06:10:48'),
(3, 'onetime', 35.00, 1, 'Single class access, No commitment, Pay as you go', 1, '2025-12-10 06:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Title` varchar(100) NOT NULL,
  `Message` text NOT NULL,
  `Type` enum('info','warning','success','error') DEFAULT 'info',
  `IsRead` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `PaymentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Amount` decimal(10,2) NOT NULL,
  `PaymentMethod` enum('credit_card','debit_card','touch_n_go','cash','bank_transfer') DEFAULT 'credit_card',
  `Status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `UserID` int(11) NOT NULL,
  `MembershipID` int(11) NOT NULL,
  `TransactionID` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `ReservationID` int(11) NOT NULL,
  `BookingDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('booked','cancelled','attended','no_show') DEFAULT 'booked',
  `UserID` int(11) NOT NULL,
  `SessionID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `SessionID` int(11) NOT NULL,
  `SessionDate` date NOT NULL,
  `Time` time NOT NULL,
  `Room` varchar(50) DEFAULT NULL,
  `ClassID` int(11) NOT NULL,
  `TrainerID` int(11) NOT NULL,
  `CurrentBookings` int(11) DEFAULT 0,
  `Status` enum('scheduled','cancelled','completed') DEFAULT 'scheduled',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','trainer','client') NOT NULL DEFAULT 'client',
  `DateOfBirth` date DEFAULT NULL,
  `Height` int(11) DEFAULT NULL,
  `Weight` int(11) DEFAULT NULL,
  `ProfilePicture` varchar(255) DEFAULT NULL,
  `MembershipID` int(11) DEFAULT NULL,
  `TrainerID` int(11) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IsActive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `FullName`, `Email`, `Phone`, `Password`, `Role`, `DateOfBirth`, `Height`, `Weight`, `ProfilePicture`, `MembershipID`, `TrainerID`, `CreatedAt`, `UpdatedAt`, `IsActive`) VALUES
(1, 'System Administrator', 'admin@kpfitness.com', '012-3456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-10 06:10:48', '2025-12-10 06:10:48', 1);

-- --------------------------------------------------------

--
-- Table structure for table `workout_plans`
--

CREATE TABLE `workout_plans` (
  `PlanID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `PlanName` varchar(100) NOT NULL,
  `Age` int(11) DEFAULT NULL,
  `Height` int(11) DEFAULT NULL,
  `Weight` int(11) DEFAULT NULL,
  `Goal` enum('bulking','cutting','endurance','strength','general_fitness') NOT NULL,
  `FitnessLevel` enum('beginner','intermediate','advanced') NOT NULL,
  `PlanDetails` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`PlanDetails`)),
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsActive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `SessionID` (`SessionID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`ClassID`);

--
-- Indexes for table `membership`
--
ALTER TABLE `membership`
  ADD PRIMARY KEY (`MembershipID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `MembershipID` (`MembershipID`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`ReservationID`),
  ADD UNIQUE KEY `unique_booking` (`UserID`,`SessionID`),
  ADD KEY `SessionID` (`SessionID`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`SessionID`),
  ADD KEY `ClassID` (`ClassID`),
  ADD KEY `TrainerID` (`TrainerID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `MembershipID` (`MembershipID`),
  ADD KEY `TrainerID` (`TrainerID`);

--
-- Indexes for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD PRIMARY KEY (`PlanID`),
  ADD KEY `UserID` (`UserID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `ClassID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `membership`
--
ALTER TABLE `membership`
  MODIFY `MembershipID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `ReservationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `SessionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `workout_plans`
--
ALTER TABLE `workout_plans`
  MODIFY `PlanID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`SessionID`) REFERENCES `sessions` (`SessionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`MembershipID`) REFERENCES `membership` (`MembershipID`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`SessionID`) REFERENCES `sessions` (`SessionID`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`ClassID`) REFERENCES `classes` (`ClassID`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`TrainerID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`MembershipID`) REFERENCES `membership` (`MembershipID`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`TrainerID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD CONSTRAINT `workout_plans_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;
COMMIT;
