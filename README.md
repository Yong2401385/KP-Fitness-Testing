# KP Fitness Class Reservation System

**KP Fitness Class Reservation System** is a robust, PHP-based web application designed to streamline gym operations. It facilitates three-tier user management (Admins, Trainers, Clients) and automates core processes such as class scheduling, membership tracking, and real-time reservations.

## Features

### 1. Admin Features
*   **Dashboard & Analytics:** Real-time overview of revenue, active members, and daily class capacity.
*   **User Management:** Full CRUD capabilities for Admins, Trainers, and Clients.
*   **Activity Management:** Create and manage fitness classes (e.g., Zumba, HIIT) with specific capacities and difficulty levels.
*   **Session Scheduling:** drag-and-drop style scheduling for classes assigned to specific trainers and rooms.
*   **Reporting:** Generate and download attendance and revenue reports in CSV or PDF format.
*   **Real-Time Monitoring:** View live class status and booking activities.
*   **Notifications:** System-wide alerts for important events (e.g., system updates, high traffic).

### 2. Trainer Features
*   **Schedule Management:** Personalized calendar view of upcoming teaching sessions.
*   **Attendance Tracking:** Digital tools to mark client attendance (Present/Absent/Late).
*   **History Logs:** View past class performance and client booking history.
*   **AI Assistant:** Integrated chatbot for quick queries about schedules or gym policies.
*   **Notifications:** Real-time alerts for new class assignments or schedule changes.

### 3. Client Features
*   **Class Booking:** Real-time booking system with conflict detection and capacity management.
*   **Membership & Billing:** View subscription status, simulate upgrades/downgrades, and view payment history.
*   **AI Workout Planner:** Generate personalized workout routines based on BMI, fitness goals, and available equipment.
*   **Profile Management:** Update personal details, track health stats (BMI), and manage password security.
*   **Notifications:** Receive alerts for successful bookings, cancellations, or membership expiry.
*   **AI Chatbot (Ollama):** Context-aware assistant powered by Llama 3.1:8b to answer queries about gym traffic, next classes, and membership status.

## Tech Stack

This project was built using the following technologies:

*   **Server-Side:** PHP 8.0+ (PDO for Database Connectivity)
*   **Database:** MySQL / MariaDB
*   **Frontend:** HTML5, CSS3, JavaScript (ES6+), **Bootstrap 5.3**
*   **AI Engine:** **Ollama (Llama 3.1:8b)** (Local LLM)
*   **Visualization:** **Chart.js** (Analytics)
*   **Styling:** Custom CSS (Responsive Design), **FontAwesome 6** (Icons)
*   **Libraries:** Vanilla-JS Calendar (Date picking), Dompdf (PDF Generation)
*   **Local Server:** XAMPP (Apache)

## Installation Guide

Follow these steps to set up the project locally:

1.  **Install XAMPP:**
    *   Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org/).
    *   Start **Apache** and **MySQL** from the XAMPP Control Panel.

2.  **Deploy Files:**
    *   Copy the project folder `KP_Fitness_Class_Reservation` into your web server's root directory (typically `C:\xampp\htdocs\`).

3.  **Setup Database:**
    *   Open your browser and navigate to `http://localhost/phpmyadmin`.
    *   Create a new database named `kp_fitness_db`.
    *   Click **Import** and select the `database/database.sql` file from the project folder.
    *   **Note:** This file already contains all necessary tables and sample data.
    *   (Optional) To refresh the system with dynamic future dates (e.g., for testing next month), you can run `http://localhost/KP_Fitness_Class_Reservation/database/seed.php` in your browser.

4.  **Configuration:**
    *   The database connection settings are located in `includes/config.db.php`.
    *   Default settings:
        *   **Host:** localhost
        *   **User:** root
        *   **Password:** (empty)
    *   Update these if your MySQL configuration differs.

5.  **Access the Application:**
    *   Open your web browser and go to: `http://localhost/KP_Fitness_Class_Reservation`

## Credentials

The system comes pre-loaded with the following test accounts (after running `seed.php` or importing seeded data):

| Role | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@kpfitness.com` | `admin123` |
| **Trainer** | `john.doe@kpfitness.com` | `trainer123` |
| **Client** | `client1@example.com` | `client123` |

## Future Improvements

*   **Payment Gateway Integration:** Replace simulated payments with Stripe or PayPal API.
*   **Mobile App:** Develop a native mobile application using Flutter or React Native.
*   **Advanced AI:** Integrate OpenAI API for more dynamic and conversational workout advice.
*   **Hardware Integration:** Link attendance system with RFID or QR code scanners at the gym entrance.
