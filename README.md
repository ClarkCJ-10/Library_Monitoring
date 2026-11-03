# Library Seat Reservation and Monitoring System

A complete, responsive, web-based Library Seat Reservation and Monitoring System built with **plain PHP (PDO)**, **MySQL**, **HTML5**, **CSS3**, and **Vanilla JavaScript**. Designed for local deployment using **XAMPP (Apache & MySQL)**.

---

## 🌟 Key Features

### 1. Student Portal
- **Secure Authentication**: Log in using Student ID and password.
- **Seat Availability Overview**: Real-time display of **Total Seats**, **Available Seats**, **Reserved Seats**, and **Occupied Seats**.
  - *Privacy & Policy Guarantee*: Does **not** display seat maps, floor plans, or physical locations to students. Only dynamic numerical availability counts are presented.
- **Seat Reservation**: One-click reservation of an available seat with concurrency locking to prevent double bookings.
- **Check-In & Check-Out**:
  - Configurable check-in window (default: 5 minutes). Seats automatically expire and return to available if unclaimed.
  - Active session countdown timer in `HH:MM:SS` format.
  - Notification alert when $\le 15$ minutes remain.
  - Student or administrator early check-out.
- **Reservation History**: Complete personal history showing dates, start times, check-in/out times, duration, and statuses.

### 2. Administrator Dashboard
- **Live Monitoring**: Track students currently occupying seats with check-in timestamps, expected end times, and live countdowns.
- **Seat Management**: Add new seats, edit seat codes, activate or deactivate seats.
  - *Safety Rule*: Prevents deactivation of occupied or reserved seats until active sessions are concluded.
- **Student Management**: Register new students, edit information, and toggle active/inactive account status.
- **Reservation Management**: Filter bookings by status (Reserved, Occupied, Completed, Cancelled, Expired) and date.
- **System Configuration**: Adjust session duration (e.g. 120 minutes) and reservation expiration windows (e.g. 5 minutes).
- **Reports & Export**: Daily activity breakdown and 1-click **CSV Export** for usage records.
- **Security Audit Logs**: Automated logging of logins, cancellations, force check-outs, and configuration changes.

### 3. Reliability & Security
- **Concurrency & Double-Booking Prevention**: Uses MySQL transactions with `SELECT ... FOR UPDATE` row-level locking.
- **Server-Side Expiration**: Expired reservations and sessions are automatically detected and released by backend logic on every request and polling event.
- **PHP PDO Prepared Statements**: Comprehensive parameter binding against SQL injection.
- **Password Security**: Passwords hashed using `password_hash()` and verified with `password_verify()`.
- **CSRF Protection**: Token verification across all state-changing `POST` requests.
- **XSS Prevention**: Output escaping using `htmlspecialchars()` with strict UTF-8 flags.
- **Auto-Syncing Fetch API**: Periodic polling every 5 seconds updates seat statistics without reloading the page.

---

## 📁 Project Folder Structure

```
Library_Monitoring/
├── index.php                      # Main Login Portal (Student & Admin Tabs)
├── login.php                      # Authentication processor
├── logout.php                     # Secure session termination
├── student_dashboard.php          # Student Portal Dashboard
├── admin_dashboard.php            # Administrator Overview Dashboard
├── setup.php                      # First Administrator Creation Wizard
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
│   ├── manage_students.php        # Register, edit, deactivate students
│   ├── manage_reservations.php    # View and cancel reservations
│   ├── manage_sessions.php        # Real-time session monitoring & force check-out
│   ├── settings.php               # System duration & expiration settings
│   └── reports.php                # Usage reports & CSV export
│
├── includes/                      # Reusable PHP Components
│   ├── auth.php                   # Role-based access control & session checks
│   ├── csrf.php                   # CSRF token generator & validator
│   ├── functions.php              # Business logic, seat calculations, expiration
│   ├── header.php                 # Top navigation & sidebar
│   └── footer.php                 # Footer scripts & auto-refresh poller
│
├── assets/
│   ├── css/
│   │   └── style.css              # Custom responsive stylesheet (Inter font, cards, modals)
│   └── js/
│       ├── app.js                 # Polling, modals, toasts, tab switching
│       └── countdown.js           # Live HH:MM:SS session countdown timer
│
└── database/
    └── library_system.sql         # Database schema, table structures & sample data
```

---

## ⚙️ Setup Instructions for XAMPP

### Step 1: Place Files in XAMPP
Copy or clone the `Library_Monitoring` folder to your XAMPP web root directory:
```
C:\xampp\htdocs\Library_Monitoring
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Import the Database Schema
#### Option A: Using phpMyAdmin
1. Open your browser and navigate to: `http://localhost/phpmyadmin/`
2. Click on the **Import** tab at the top.
3. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\Library_Monitoring\database\library_system.sql
   ```
4. Click **Import** (or **Go**) at the bottom. The database `library_system` will be created with all tables and sample records.

#### Option B: Using the Command Line
Open PowerShell or Command Prompt and run:
```bash
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\Library_Monitoring\database\library_system.sql
```

### Step 4: Verify Database Connection
Open `config.php` and verify the settings (defaults for standard XAMPP):
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'library_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 5: Launch the Application
Open your browser and navigate to:
```
http://localhost/Library_Monitoring/
```

---

## 🔑 Default Test Accounts

The included SQL script creates the following pre-configured evaluation accounts:

### 👨‍🎓 Student Accounts
| Student ID | Full Name | Email | Password |
|---|---|---|---|
| `2024-0001` | Juan Dela Cruz | juan.delacruz@student.edu | `Student@123` |
| `2024-0002` | Maria Santos | maria.santos@student.edu | `Student@123` |
| `2024-0003` | Carlos Reyes | carlos.reyes@student.edu | `Student@123` |
| `2024-0004` | Andrea Gomez | andrea.gomez@student.edu | `Student@123` |
| `2024-0005` | Mark Johnson | mark.johnson@student.edu | `Student@123` |

### 🔐 Administrator Account
| Admin ID | Full Name | Email | Password |
|---|---|---|---|
| `ADMIN-001` | Chief Librarian | admin@library.edu | `Admin@123` |

### 🛡️ Creating the First Administrator Account
To create a custom administrator or reset administrator credentials without using default logins:
1. Navigate to: `http://localhost/Library_Monitoring/setup.php`
2. Follow the on-screen Setup Wizard instructions.

---

## 🧭 Step-by-Step Usage Guide

### Student Workflow
1. Navigate to `http://localhost/Library_Monitoring/` and click **Student Login**.
2. Log in with Student ID `2024-0001` and password `Student@123`.
3. View the current available seat count. Click **Reserve Available Seat**.
4. The system locks a seat and begins your 5-minute check-in countdown.
5. Click **Check In Now**.
6. The session begins! The live **HH:MM:SS** countdown displays your remaining time (default: 2 hours).
7. If your session falls below 15 minutes, an alert banner will notify you.
8. When finished, click **End Session & Check Out** to release the seat.

### Administrator Workflow
1. Navigate to `http://localhost/Library_Monitoring/` and click the **Administrator** tab.
2. Log in with `admin@library.edu` and password `Admin@123`.
3. The **Admin Dashboard** displays:
   - 7 metric cards (Total, Available, Reserved, Occupied, Students, Active Sessions, Today's Bookings).
   - Real-time student monitoring table with remaining countdown timers.
4. **Manage Seats**: Click *Seat Management* to add or toggle seats.
5. **Manage Students**: Click *Students* to register new student accounts.
6. **Settings**: Adjust session length (e.g. from 120 mins to 90 mins) or check-in window.
7. **Reports**: View usage trends and export records to CSV.

---

## 🛠️ Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| `Database connection failed` | MySQL service is stopped or port is blocked | Open XAMPP Control Panel and ensure MySQL is running (green). Verify credentials in `config.php`. |
| `Access Denied` on Admin pages | Logged in as a Student role | Log out via `logout.php` and sign in through the Administrator tab. |
| Countdown resets on page refresh | Using client-side timer | Our implementation computes remaining time from the server timestamp `expected_end_time` in MySQL; ensure your system clock is accurate. |
| CSV export contains garbled characters | Excel UTF-8 encoding | The export script sends a UTF-8 BOM (`EF BB BF`) for full Microsoft Excel compatibility. |

#   L i b r a r y _ M o n i t o r i n g  
 