<?php
/**
 * Administrator: Student Management
 */

$pageTitle = "Student Management";
$activeNav = "students";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();
$courseList = get_course_list();

// Process Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Security session expired. Please try again.";
        header("Location: " . BASE_URL . "admin/manage_students.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Register Student / Admin
    if ($action === 'register_student') {
        $studentId = trim($_POST['student_id'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $password  = trim($_POST['password'] ?? '');
        $confirm   = trim($_POST['confirm_password'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['student', 'admin']) ? $_POST['role'] : 'student';
        $course    = ($role === 'student') ? trim($_POST['course'] ?? '') : null;

        if (empty($studentId) || empty($fullName) || empty($email) || empty($password) || empty($confirm)) {
            $_SESSION['flash_error'] = "All required fields must be filled.";
        } elseif (!is_valid_gmail($email)) {
            $_SESSION['flash_error'] = "Please provide a valid Gmail address (must end with @gmail.com).";
        } elseif ($role === 'student' && (empty($course) || !array_key_exists($course, $courseList))) {
            $_SESSION['flash_error'] = "Please select a valid enrolled course for the student.";
        } elseif (strlen($password) < 6) {
            $_SESSION['flash_error'] = "Password must be at least 6 characters in length.";
        } elseif ($password !== $confirm) {
            $_SESSION['flash_error'] = "Passwords do not match. Please re-enter your password.";
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
            $chk->execute([$studentId, $email]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "An account with this ID or Gmail address already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("
                    INSERT INTO students (student_id, full_name, email, course, password_hash, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $ins->execute([$studentId, $fullName, $email, $course, $hash, $role]);
                log_audit('student_registered', "Admin registered user {$studentId} ({$fullName} - " . ($course ?: 'Admin') . ").", current_user()['id']);
                $_SESSION['flash_success'] = ucfirst($role) . " account for {$fullName} ({$studentId}) registered successfully.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_students.php");
        exit;
    }

    // 2. Edit Student Information
    if ($action === 'edit_student') {
        $id        = (int)($_POST['id'] ?? 0);
        $studentId = trim($_POST['student_id'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $course    = trim($_POST['course'] ?? '');
        $password  = trim($_POST['password'] ?? '');

        // Fetch user to check role
        $stmtUser = $db->prepare("SELECT role FROM students WHERE id = ?");
        $stmtUser->execute([$id]);
        $existingUser = $stmtUser->fetch();
        $isStudent = ($existingUser && $existingUser['role'] === 'student');

        if (empty($studentId) || empty($fullName) || empty($email)) {
            $_SESSION['flash_error'] = "Name, ID, and Gmail Address cannot be empty.";
        } elseif (!is_valid_gmail($email)) {
            $_SESSION['flash_error'] = "Please provide a valid Gmail address (must end with @gmail.com).";
        } elseif ($isStudent && (empty($course) || !array_key_exists($course, $courseList))) {
            $_SESSION['flash_error'] = "Please select a valid course for the student.";
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM students WHERE (student_id = ? OR email = ?) AND id != ?");
            $chk->execute([$studentId, $email, $id]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Student ID or Gmail address is already taken by another account.";
            } else {
                $savedCourse = $isStudent ? $course : null;
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE students SET student_id = ?, full_name = ?, email = ?, course = ?, password_hash = ? WHERE id = ?");
                    $upd->execute([$studentId, $fullName, $email, $savedCourse, $hash, $id]);
                } else {
                    $upd = $db->prepare("UPDATE students SET student_id = ?, full_name = ?, email = ?, course = ? WHERE id = ?");
                    $upd->execute([$studentId, $fullName, $email, $savedCourse, $id]);
                }
                log_audit('student_updated', "Admin updated user #{$id} ({$fullName}).", current_user()['id']);
                $_SESSION['flash_success'] = "Account updated successfully.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_students.php");
        exit;
    }

    // 3. Toggle Student Active State
    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if ($target) {
            // Prevent admin deactivating their own currently logged in account
            if ($target['id'] == current_user()['id']) {
                $_SESSION['flash_error'] = "You cannot deactivate your own logged-in account.";
            } else {
                $newVal = $target['is_active'] ? 0 : 1;
                $upd = $db->prepare("UPDATE students SET is_active = ? WHERE id = ?");
                $upd->execute([$newVal, $id]);

                $label = $newVal ? "activated" : "deactivated";
                log_audit('student_status_changed', "Account {$target['student_id']} {$label}.", current_user()['id']);
                $_SESSION['flash_success'] = "Account {$target['student_id']} has been {$label}.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_students.php");
        exit;
    }

    // 4. Delete User Account
    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['flash_error'] = "User account not found or has already been deleted.";
        } elseif ($target['id'] == current_user()['id']) {
            $_SESSION['flash_error'] = "Security safeguard: You cannot delete your own logged-in administrator account.";
        } else {
            $db->beginTransaction();
            try {
                // 1. Identify any active seat reservations or sessions held by this student
                $stmtSeats = $db->prepare("
                    SELECT DISTINCT seat_id FROM (
                        SELECT seat_id FROM sessions WHERE student_id = ? AND status = 'active'
                        UNION
                        SELECT seat_id FROM reservations WHERE student_id = ? AND status = 'reserved'
                    ) active_seats
                ");
                $stmtSeats->execute([$id, $id]);
                $affectedSeats = $stmtSeats->fetchAll(PDO::FETCH_COLUMN);

                // 2. Mark active sessions as completed and active reservations as cancelled
                $db->prepare("UPDATE sessions SET status = 'completed', check_out_time = NOW() WHERE student_id = ? AND status = 'active'")->execute([$id]);
                $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE student_id = ? AND status = 'reserved'")->execute([$id]);

                // 3. Release any occupied or reserved seats back to 'available'
                if (!empty($affectedSeats)) {
                    $inPlaceholders = implode(',', array_fill(0, count($affectedSeats), '?'));
                    $stmtRelease = $db->prepare("UPDATE seats SET seat_status = 'available' WHERE id IN ($inPlaceholders)");
                    $stmtRelease->execute($affectedSeats);
                }

                // 4. Delete the student/user account from MySQL
                $stmtDelete = $db->prepare("DELETE FROM students WHERE id = ?");
                $stmtDelete->execute([$id]);

                $db->commit();

                log_audit('user_deleted', "Admin permanently deleted {$target['role']} account: {$target['student_id']} ({$target['full_name']}).", current_user()['id']);
                $_SESSION['flash_success'] = "Account for " . e($target['full_name']) . " (" . e($target['student_id']) . ") was permanently deleted.";
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error deleting user #{$id}: " . $e->getMessage());
                $_SESSION['flash_error'] = "Failed to delete user account: " . $e->getMessage();
            }
        }
        header("Location: " . BASE_URL . "admin/manage_students.php");
        exit;
    }
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');

$sql = "SELECT * FROM students WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (student_id LIKE ? OR full_name LIKE ? OR email LIKE ? OR course LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($roleFilter) && in_array($roleFilter, ['student', 'admin'])) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($courseFilter)) {
    $sql .= " AND course = ?";
    $params[] = $courseFilter;
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Student & User Management</h2>
        <p>Register new student accounts, manage credentials, and toggle account activation.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('register-student-modal')">
        ➕ Register Student
    </button>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="<?= BASE_URL; ?>admin/manage_students.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" class="form-control" placeholder="Search by name, ID, Gmail, or course..." value="<?= e($search); ?>">
            </div>
            <div style="width: 150px;">
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : ''; ?>>Students Only</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                </select>
            </div>
            <div style="width: 240px;">
                <select name="course" class="form-control">
                    <option value="">All Courses</option>
                    <?php foreach ($courseList as $cKey => $cTitle): ?>
                        <option value="<?= e($cKey); ?>" <?= $courseFilter === $cKey ? 'selected' : ''; ?>>
                            <?= e($cTitle); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-outline">Filter</button>
            <?php if (!empty($search) || !empty($roleFilter) || !empty($courseFilter)): ?>
                <a href="<?= BASE_URL; ?>admin/manage_students.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h3>👥 Registered Users (<?= count($students); ?> results)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Gmail Address</th>
                    <th>Course</th>
                    <th>System Role</th>
                    <th>Account Status</th>
                    <th>Created On</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No user records match your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $u): ?>
                        <tr>
                            <td><strong style="font-family: monospace;"><?= e($u['student_id']); ?></strong></td>
                            <td><strong><?= e($u['full_name']); ?></strong></td>
                            <td><?= e($u['email']); ?></td>
                            <td>
                                <?php if (!empty($u['course'])): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600;">
                                        🎓 <?= e($u['course']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 12.5px;"><em>N/A (Admin)</em></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $u['role'] === 'admin' ? '#ede9fe' : '#e0f2fe'; ?>; color: <?= $u['role'] === 'admin' ? '#7c3aed' : '#0369a1'; ?>;">
                                    <?= ucfirst(e($u['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Deactivated</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="openEditStudentModal(<?= $u['id']; ?>, '<?= e(addslashes($u['student_id'])); ?>', '<?= e(addslashes($u['full_name'])); ?>', '<?= e(addslashes($u['email'])); ?>', '<?= e(addslashes($u['course'] ?? '')); ?>', '<?= e(addslashes($u['role'])); ?>')">
                                    ✏️ Edit
                                </button>

                                <form action="<?= BASE_URL; ?>admin/manage_students.php" method="POST" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $u['id']; ?>">
                                    <?php if ($u['is_active']): ?>
                                        <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Deactivate student account for <?= e(addslashes($u['full_name'])); ?>?');">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-success">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <?php if ($u['id'] != current_user()['id']): ?>
                                    <form action="<?= BASE_URL; ?>admin/manage_students.php" method="POST" style="display: inline;" onsubmit="return confirm('⚠️ PERMANENT DELETION\n\nAre you sure you want to permanently delete the account for <?= e(addslashes($u['full_name'])); ?> (<?= e(addslashes($u['student_id'])); ?>)?\n\nThis action cannot be undone. Any active seat bookings will be automatically released.');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="background-color: var(--danger); color: #fff;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Register Student / User -->
<div id="register-student-modal" class="modal-overlay">
    <div class="modal-container" style="max-width: 540px;">
        <form action="<?= BASE_URL; ?>admin/manage_students.php" method="POST" id="admin-reg-form">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="register_student">

            <div class="modal-header">
                <h3>Register User Account</h3>
                <button type="button" class="modal-close" onclick="closeModal('register-student-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Account Role <span style="color: var(--danger);">*</span></label>
                    <select name="role" id="reg-role" class="form-control" onchange="toggleCourseField(this.value)">
                        <option value="student" selected>Student Account</option>
                        <option value="admin">Administrator Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Clara" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Gmail Address <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. maria.clara@gmail.com" pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" title="Must be a valid @gmail.com address" required>
                    <small class="form-text">Must be a valid Gmail address (@gmail.com)</small>
                </div>
                <div class="form-group" id="reg-student-id-group">
                    <label class="form-label" id="reg-id-label">Student ID <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="student_id" id="reg-student-id" class="form-control" placeholder="e.g. 2024-0010" required>
                </div>
                <!-- Course dropdown: Exclusively for students -->
                <div class="form-group" id="reg-course-group">
                    <label class="form-label">Course <span style="color: var(--danger);">*</span></label>
                    <select name="course" id="reg-course-select" class="form-control" required>
                        <option value="" disabled selected>-- Select Enrolled Course --</option>
                        <?php foreach ($courseList as $cKey => $cTitle): ?>
                            <option value="<?= e($cKey); ?>"><?= e($cTitle); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">Course selection is required exclusively for student accounts.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="password" id="reg-password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="confirm_password" id="reg-confirm" class="form-control" placeholder="Re-type password" minlength="6" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('register-student-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Student -->
<div id="edit-student-modal" class="modal-overlay">
    <div class="modal-container" style="max-width: 540px;">
        <form action="<?= BASE_URL; ?>admin/manage_students.php" method="POST">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit_student">
            <input type="hidden" name="id" id="edit-id" value="">

            <div class="modal-header">
                <h3>Edit User Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('edit-student-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" id="edit-id-label">Student ID</label>
                    <input type="text" name="student_id" id="edit-student-id" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="edit-full-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Gmail Address</label>
                    <input type="email" name="email" id="edit-email" class="form-control" pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" title="Must be a valid @gmail.com address" required>
                </div>
                <div class="form-group" id="edit-course-group">
                    <label class="form-label">Course <span style="color: var(--danger);">*</span></label>
                    <select name="course" id="edit-course" class="form-control">
                        <option value="" disabled>-- Select Enrolled Course --</option>
                        <?php foreach ($courseList as $cKey => $cTitle): ?>
                            <option value="<?= e($cKey); ?>"><?= e($cTitle); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password (leave empty to keep current)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('edit-student-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCourseField(role) {
    const courseGroup = document.getElementById('reg-course-group');
    const courseSelect = document.getElementById('reg-course-select');
    const idLabel = document.getElementById('reg-id-label');
    const idInput = document.getElementById('reg-student-id');

    if (role === 'admin') {
        courseGroup.style.display = 'none';
        courseSelect.removeAttribute('required');
        courseSelect.value = '';
        idLabel.textContent = 'Administrator ID *';
        idInput.placeholder = 'e.g. ADMIN-002';
    } else {
        courseGroup.style.display = 'block';
        courseSelect.setAttribute('required', 'required');
        idLabel.textContent = 'Student ID *';
        idInput.placeholder = 'e.g. 2024-0010';
    }
}

function openEditStudentModal(id, studentId, fullName, email, course, role) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-student-id').value = studentId;
    document.getElementById('edit-full-name').value = fullName;
    document.getElementById('edit-email').value = email;

    const editCourseGroup = document.getElementById('edit-course-group');
    const editCourseSelect = document.getElementById('edit-course');
    const editIdLabel = document.getElementById('edit-id-label');

    if (role === 'admin') {
        editCourseGroup.style.display = 'none';
        editCourseSelect.removeAttribute('required');
        editCourseSelect.value = '';
        editIdLabel.textContent = 'Administrator ID';
    } else {
        editCourseGroup.style.display = 'block';
        editCourseSelect.setAttribute('required', 'required');
        editCourseSelect.value = course || '';
        editIdLabel.textContent = 'Student ID';
    }

    openModal('edit-student-modal');
}

// Client-side confirmation check on admin registration form
document.getElementById('admin-reg-form').addEventListener('submit', function(e) {
    const pass = document.getElementById('reg-password').value;
    const conf = document.getElementById('reg-confirm').value;
    const role = document.getElementById('reg-role').value;
    const course = document.getElementById('reg-course-select').value;

    if (role === 'student' && !course) {
        alert('Please select an enrolled course for the student.');
        e.preventDefault();
        return false;
    }

    if (pass !== conf) {
        alert('Passwords do not match. Please verify both password fields.');
        e.preventDefault();
        return false;
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

