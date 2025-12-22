KP FITNESS CLASS RESERVATION SYSTEM
================================================================================

The KP Fitness Class Reservation System is a robust, PHP-based web application 
designed to streamline gym operations. It facilitates three-tier user management 
(Administrators, Trainers, Clients) and automates core processes such as class 
scheduling, membership tracking, and real-time reservations.

================================================================================
1. FEATURES
================================================================================

1.1 ADMIN FEATURES
------------------
- Dashboard & Analytics: 
  View real-time overview of revenue, active members, and daily class capacity.
- User Management: 
  Full CRUD (Create, Read, Update, Delete) capabilities for Admins, Trainers, and Clients.
- Activity Management: 
  Create and manage fitness classes (e.g., Zumba, HIIT) with specific capacities and difficulty levels.
- Session Scheduling: 
  Drag-and-drop style scheduling for classes assigned to specific trainers and rooms.
- Reporting: 
  Generate and download attendance and revenue reports in CSV or PDF format.
- Real-Time Monitoring: 
  View live class status and booking activities as they happen.
- Notifications: 
  System-wide alerts for important events (e.g., system updates, high traffic).

1.2 TRAINER FEATURES
--------------------
- Schedule Management: 
  Personalized calendar view of upcoming teaching sessions.
- Attendance Tracking: 
  Digital tools to mark client attendance (Present/Absent/Late).
- History Logs: 
  View past class performance and client booking history.
- AI Assistant: 
  Integrated chatbot for quick queries about schedules or gym policies.
- Notifications: 
  Real-time alerts for new class assignments or schedule changes.

1.3 CLIENT FEATURES
-------------------
- Class Booking: 
  Real-time booking system with conflict detection and capacity management.
- Membership & Billing: 
  View subscription status, simulate upgrades/downgrades, and view payment history.
- AI Workout Planner: 
  Generate personalized workout routines based on BMI, fitness goals, and available equipment.
- Profile Management: 
  Update personal details, track health stats (BMI), and manage password security.
- Notifications: 
  Receive alerts for successful bookings, cancellations, or membership expiry.
- AI Chatbot (Ollama): 
  Context-aware assistant powered by Llama 3.1:8b to answer queries about gym traffic, next classes, and membership status.

================================================================================
2. TECHNICAL STACK
================================================================================

This project was built using the following technologies:

- Server-Side:   PHP 8.0+ (Utilizing PDO for secure Database Connectivity)
- Database:      MariaDB / MySQL (Relational Database)
- Frontend:      HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3 framework
- AI Engine:     Ollama (Llama 3.1:8b) - A locally hosted Large Language Model
- Visualization: Chart.js (For analytics charts and graphs)
- Styling:       Custom CSS (Responsive Design), FontAwesome 6 (Icons)
- Libraries:     Vanilla-JS Calendar (Date picking), Dompdf (PDF Generation)
- Local Server:  XAMPP (Apache Web Server)

================================================================================
3. INSTALLATION GUIDE
================================================================================

Follow these steps to set up the project locally:

STEP 1: INSTALL XAMPP
   - Download and install XAMPP from https://www.apachefriends.org/
   - Open the XAMPP Control Panel.
   - Start the 'Apache' and 'MySQL' modules.

STEP 2: DEPLOY FILES
   - Copy the entire project folder 'KP_Fitness_Class_Reservation' into your web server's root directory.
   - Typically, this path is: C:\xampp\htdocs\

STEP 3: SETUP DATABASE
   - Open your web browser and navigate to: http://localhost/phpmyadmin
   - Create a new database named 'kp_fitness_db'.
   - Click the 'Import' tab.
   - Click 'Choose File' and select the 'database/database.sql' file from the project folder.
   - Click 'Go' to execute the import.
   - Note: This file already contains all necessary tables and sample data.

   (Optional) Dynamic Seeding:
   - To refresh the system with dynamic future dates (e.g., for testing next month), you can run the seeder script.
   - Navigate to: http://localhost/KP_Fitness_Class_Reservation/database/seed.php

STEP 4: CONFIGURATION
   - The database connection settings are located in the file: includes/config.db.php
   - Default settings are configured for XAMPP:
       Host:     localhost
       User:     root
       Password: (empty)
   - If your MySQL configuration differs, please update this file accordingly.

STEP 5: ACCESS THE APPLICATION
   - Open your web browser and go to: http://localhost/KP_Fitness_Class_Reservation

================================================================================
4. DEFAULT CREDENTIALS
================================================================================

The system comes pre-loaded with the following test accounts (available after importing the database):

ROLE        | EMAIL                    | PASSWORD
------------|--------------------------|-----------
Admin       | admin@kpfitness.com      | admin123
Trainer     | john.doe@kpfitness.com   | trainer123
Client      | client1@example.com      | client123

================================================================================
5. FUTURE IMPROVEMENTS
================================================================================

- Payment Gateway Integration: Replace simulated payments with Stripe or PayPal API.
- Mobile App: Develop a native mobile application using Flutter or React Native.
- Advanced AI: Integrate OpenAI API for more dynamic and conversational workout advice.
- Hardware Integration: Link attendance system with RFID or QR code scanners at the gym entrance.
