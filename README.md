# Library Seat Reservation and Monitoring System

A complete, responsive, web-based Library Seat Reservation and Monitoring System built with **plain PHP (PDO)**, **MySQL**, **HTML5**, **CSS3**, and **Vanilla JavaScript**. Designed for local deployment using **XAMPP (Apache & MySQL)**.

---

## 🌟 Key Features

### 1. Student Portal & Course Registration
- **Self-Service Registration (`register.php`)**:
  - Full Name
  - Gmail Address (enforces `@gmail.com` validation)
  - Student ID
  - **Course** (required dropdown selection: BSIT, BSCS, BSCpE, BSBA, BSN, etc.)
  - Password & Confirm Password
- **Secure Authentication**: Log in using Student ID and password.
- **Course Recognition**: Enrolled course is displayed on the Student Dashboard and header profile.
- **Seat Availability Overview**: Real-time display of **Total Seats**, **Available Seats**, **Reserved Seats**, and **Occupied Seats**.
  - *Privacy & Policy Guarantee*: Does **not** display seat maps, floor plans, or physical locations to students. Only dynamic numerical availability counts are presented.
- **Seat Reservation**: One-click reservation of an available seat with concurrency locking to prevent double bookings.
- **Check-In & Check-Out**:
  - Configurable check-in window (default: 5 minutes). Seats automatically expire and return to available if unclaimed.
  - Active session countdown timer in `HH:MM:SS` format.
  - Notification alert when $\le 15$ minutes remain.
  - Student or administrator early check-out.
- **Reservation History**: Complete personal history showing dates, start times, check-in/out times, duration, and statuses.

### 2. Administrator Dashboard & Student Management
- **Student Course Oversight**:
  - View each student's enrolled course directly in the user records table.
  - **Filter by Course**: Dropdown filter to view students by specific degree programs.
  - **Search by Course**: Global search bar finds students by course name or code.
  - **Edit Course**: Administrators can update a student's course assignment.
- **Account Management & Deletion**:
  - **Permanent Delete User Account**: Administrators can delete student or admin accounts with built-in safeguards (self-deletion blocked, seats cleanly released, audit logged).
  - **Toggle Activation**: Activate or deactivate accounts anytime.
- **Strict Separation of Roles**:
  - Admin creation (`setup.php` and role management) strictly **excludes the Course field**, keeping Course exclusive to student accounts (`course = NULL` for admins).
- **Live Monitoring**: Track students currently occupying seats with check-in timestamps, expected end times, course, and live countdowns.
- **Seat Management**: Add new seats, edit seat codes, activate or deactivate seats (with occupied safeguards).
- **Reservation Management**: Filter bookings by status (Reserved, Occupied, Completed, Cancelled, Expired) and date.
- **System Configuration**: Adjust session duration and reservation expiration windows.
- **Reports & Export**: Daily activity breakdown and 1-click **CSV Export** including Student Course information.
- **Security Audit Logs**: Automated logging of logins, registrations, cancellations, and force check-outs.

---

## 📁 Project Folder Structure

```
Library_Monitoring/
├── index.php                      # Main Login Portal (Student & Admin Tabs + Register Link)
├── register.php                   # Student Course Registration Form (NEW)
├── login.php                      # Authentication processor
├── logout.php                     # Secure session termination
├── student_dashboard.php          # Student Portal Dashboard & Course Badge
├── admin_dashboard.php            # Administrator Overview Dashboard
├── setup.php                      # Administrator Creation Wizard (No Course field)
├── reserve_seat.php               # Action handler: Reserve seat
├── check_in.php                   # Action handler: Check in
├── check_out.php                  # Action handler: End session
├── cancel_reservation.php         # Action handler: Cancel reservation
├── config.php                     # Global constants & database credentials
├── database.php                   # PDO database connection factory
│
├── api/                           # JSON API Endpoints (Fetch API)
│   ├── availability.php           # Returns live seat status counts
│   ├── remaining_time.php         # Student remaining session seconds & warning state
│   └── reservation_status.php     # Student current booking state
│
├── admin/                         # Administrator Management Pages
│   ├── manage_seats.php           # Add, edit, toggle seat activation
│   ├── manage_students.php        # View, register, filter, and edit student courses
│   ├── manage_reservations.php    # View and cancel reservations
│   ├── manage_sessions.php        # Real-time session monitoring & force check-out
│   ├── settings.php               # System duration & expiration settings
│   └── reports.php                # Usage reports & CSV export (includes Course)
│
├── includes/                      # Reusable PHP Components
│   ├── auth.php                   # Role-based access control & session checks
│   ├── csrf.php                   # CSRF token generator & validator
│   ├── functions.php              # Business logic, course list, Gmail validator, expiration
│   ├── header.php                 # Top navigation, course badge, and sidebar
│   └── footer.php                 # Footer scripts & auto-refresh poller
│
├── assets/
│   ├── css/
│   │   └── style.css              # Custom responsive stylesheet
│   └── js/
│       ├── app.js                 # Polling, modals, toasts, tab switching
│       └── countdown.js           # Live HH:MM:SS session countdown timer
│
└── database/
    ├── library_system.sql         # Base database schema & sample data with courses
    └── add_course_column.sql      # Standalone SQL Migration to add course column
```

---

## 🗄️ Database Migration (Adding Course Column)

To apply the course update to an existing database:

### Using phpMyAdmin
1. Open `http://localhost/phpmyadmin/`
2. Select `library_system` from the left menu.
3. Click the **SQL** tab.
4. Execute:
```sql
ALTER TABLE students ADD COLUMN course VARCHAR(120) NULL DEFAULT NULL AFTER email;
```
Or import `database/add_course_column.sql`.

---

## 🧪 Testing Guide

### 1. Testing Student Registration
1. Navigate to: `http://localhost/Library_Monitoring/register.php` (or click **Register for an Account** from the login page).
2. Fill out the form:
   - **Full Name**: e.g., `Maria Clara`
   - **Gmail Address**: e.g., `maria.clara@gmail.com` *(Must end with `@gmail.com`)*
   - **Student ID**: e.g., `2024-0555`
   - **Course**: Select a course from the dropdown *(e.g., `BS in Information Technology (BSIT)`)*
   - **Password**: `Student@123`
   - **Confirm Password**: `Student@123`
3. Click **Complete Registration**.
4. You will be redirected to `index.php` with a success message.
5. Log in with your new Student ID and Password.
6. Verify your enrolled course is displayed on your Student Dashboard and in the top navigation profile.

### 2. Testing Admin Account Creation
1. Navigate to: `http://localhost/Library_Monitoring/setup.php`
2. Notice the form fields:
   - **Full Name**
   - **Gmail Address**
   - **Password**
   - **Confirm Password**
   - *(Note: The Course dropdown is strictly NOT present, keeping course exclusive to students).*
3. Create the administrator account.
4. Sign in as Administrator and verify `course` is `NULL` in the database.

### 3. Testing Admin Dashboard & Course Filtering
1. Log in as an Administrator (`admin@library.edu` / `Admin@123`).
2. Go to **Student Management** (`admin/manage_students.php`).
3. View the table: each student displays their enrolled Course badge.
4. Use the **Course Filter** dropdown to view only students in a specific course (e.g. *BS Information Technology*).
5. Click **Edit** on a student to change their course assignment.
