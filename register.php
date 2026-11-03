<?php
/**
 * Student Registration Portal
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: " . BASE_URL . "admin_dashboard.php");
    } else {
        header("Location: " . BASE_URL . "student_dashboard.php");
    }
    exit;
}

$courses = get_course_list();
$error = null;
$success = null;

// Retain submitted values on validation failure
$formData = [
    'full_name'  => '',
    'email'      => '',
    'student_id' => '',
    'course'     => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security session expired. Please refresh and try again.";
    } else {
        $fullName   = trim($_POST['full_name'] ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $studentId  = trim($_POST['student_id'] ?? '');
        $course     = trim($_POST['course'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $confirm    = trim($_POST['confirm_password'] ?? '');

        $formData['full_name']  = $fullName;
        $formData['email']      = $email;
        $formData['student_id'] = $studentId;
        $formData['course']     = $course;

        // Validation
        if (empty($fullName) || empty($email) || empty($studentId) || empty($course) || empty($password) || empty($confirm)) {
            $error = "All fields are required. Please complete the entire form.";
        } elseif (!is_valid_gmail($email)) {
            $error = "Please provide a valid Gmail address (must end with @gmail.com, e.g., yourname@gmail.com).";
        } elseif (!array_key_exists($course, $courses)) {
            $error = "Please select a valid course from the dropdown list.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters in length.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match. Please re-enter your password.";
        } else {
            $db = get_db_connection();

            // Check duplicate Student ID or Email
            $stmtCheck = $db->prepare("SELECT student_id, email FROM students WHERE student_id = ? OR email = ? LIMIT 1");
            $stmtCheck->execute([$studentId, $email]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if (strcasecmp($existing['student_id'], $studentId) === 0) {
                    $error = "A student account with Student ID '{$studentId}' already exists.";
                } else {
                    $error = "A student account with Gmail address '{$email}' already exists.";
                }
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $db->prepare("
                    INSERT INTO students (student_id, full_name, email, course, password_hash, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 'student', 1, NOW())
                ");
                $stmtInsert->execute([$studentId, $fullName, $email, $course, $passwordHash]);
                $newUserId = (int)$db->lastInsertId();

                log_audit('student_registered', "Student {$studentId} ({$fullName} - {$course}) registered online.", $newUserId);

                $_SESSION['flash_success'] = "Registration successful! Welcome to the Library Monitoring System. Please log in with your Student ID and Password.";
                header("Location: " . BASE_URL . "index.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration — Library Seat Reservation & Monitoring</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width: 520px;">
    <div class="auth-header">
        <div class="auth-logo">🎓</div>
        <h2 class="auth-title">Student Registration</h2>
        <p class="auth-subtitle">Create your student account to reserve library seats</p>
    </div>

    <div class="auth-body">
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <span>❌</span>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL; ?>register.php" method="POST" id="student-register-form">
            <?= csrf_field(); ?>

            <!-- 1. Full Name -->
            <div class="form-group">
                <label for="full_name" class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Juan C. Dela Cruz" value="<?= e($formData['full_name']); ?>" required autofocus>
                <small class="form-text">Enter your first name, middle initial, and surname</small>
            </div>

            <!-- 2. Gmail Address -->
            <div class="form-group">
                <label for="email" class="form-label">Gmail Address <span style="color: var(--danger);">*</span></label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g. juandelacruz@gmail.com" value="<?= e($formData['email']); ?>" pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" title="Must be a valid @gmail.com address" required>
                <small class="form-text">Must be a valid Gmail address (@gmail.com)</small>
            </div>

            <!-- 3. Student ID -->
            <div class="form-group">
                <label for="student_id" class="form-label">Student ID <span style="color: var(--danger);">*</span></label>
                <input type="text" id="student_id" name="student_id" class="form-control" placeholder="e.g. 2024-0123" value="<?= e($formData['student_id']); ?>" required>
                <small class="form-text">Your official university student number</small>
            </div>

            <!-- 4. Course (Dropdown) -->
            <div class="form-group">
                <label for="course" class="form-label">Course <span style="color: var(--danger);">*</span></label>
                <select id="course" name="course" class="form-control" required>
                    <option value="" disabled <?= empty($formData['course']) ? 'selected' : ''; ?>>-- Select Your Enrolled Course --</option>
                    <?php foreach ($courses as $code => $title): ?>
                        <option value="<?= e($code); ?>" <?= $formData['course'] === $code ? 'selected' : ''; ?>>
                            <?= e($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text">Select your current degree or program</small>
            </div>

            <!-- 5. Password -->
            <div class="form-group">
                <label for="password" class="form-label">Password <span style="color: var(--danger);">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
            </div>

            <!-- 6. Confirm Password -->
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password <span style="color: var(--danger);">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type your password" minlength="6" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Complete Registration
            </button>
        </form>
    </div>

    <div class="auth-footer">
        <div>Already registered? <a href="<?= BASE_URL; ?>index.php"><strong>Sign In to Your Account &rarr;</strong></a></div>
        <div style="margin-top: 6px; color: var(--gray-400); font-size: 11.5px;">&copy; <?= date('Y'); ?> Library Monitoring System • School Project</div>
    </div>
</div>

<script>
document.getElementById('student-register-form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const course = document.getElementById('course').value;
    const email = document.getElementById('email').value.trim();

    if (!course) {
        alert('Please select your enrolled course before submitting.');
        e.preventDefault();
        return false;
    }

    if (!email.toLowerCase().endsWith('@gmail.com')) {
        alert('Please provide a valid Gmail address ending with @gmail.com');
        e.preventDefault();
        return false;
    }

    if (password !== confirm) {
        alert('Passwords do not match. Please verify both password fields.');
        e.preventDefault();
        return false;
    }
});
</script>

<script src="<?= BASE_URL; ?>assets/js/app.js"></script>
</body>
</html>

