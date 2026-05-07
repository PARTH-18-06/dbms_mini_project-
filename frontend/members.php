<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberCode = trim($_POST['member_code'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $semester = (int) ($_POST['semester'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $email = null_if_empty($_POST['email'] ?? '');
    $joinDate = trim($_POST['join_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'ACTIVE');

    if ($memberCode === '' || $fullName === '') {
        $message = 'Please fill member code and full name.';
    } else {
        $stmt = $connection->prepare(
            "INSERT INTO Members
            (member_code, full_name, department, semester, phone, email, join_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssissss', $memberCode, $fullName, $department, $semester, $phone, $email, $joinDate, $status);
        $stmt->execute();
        $message = 'Member added successfully.';
    }
}

$members = $connection->query(
    "SELECT
        member_id,
        member_code,
        full_name,
        department,
        semester,
        phone,
        email,
        join_date,
        status
    FROM Members
    ORDER BY member_id DESC"
);

render_header('Members', 'members');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Students</p>
        <h2>Members</h2>
    </div>
</section>

<?php if ($message !== '') : ?>
    <div class="notice"><?php echo e($message); ?></div>
<?php endif; ?>

<section class="panel">
    <h3>Add New Member</h3>
    <form method="post" class="form-grid">
        <label>
            Member Code
            <input type="text" name="member_code" placeholder="STU031" required>
        </label>
        <label>
            Full Name
            <input type="text" name="full_name" required>
        </label>
        <label>
            Department
            <input type="text" name="department">
        </label>
        <label>
            Semester
            <input type="number" name="semester" min="1" max="8">
        </label>
        <label>
            Phone
            <input type="text" name="phone">
        </label>
        <label>
            Email
            <input type="email" name="email">
        </label>
        <label>
            Join Date
            <input type="date" name="join_date" value="<?php echo e(date('Y-m-d')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <option value="ACTIVE">ACTIVE</option>
                <option value="BLOCKED">BLOCKED</option>
            </select>
        </label>
        <button type="submit">Add Member</button>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <h3>Member List</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Sem</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($member = $members->fetch_assoc()) : ?>
                    <tr>
                        <td><?php echo e($member['member_id']); ?></td>
                        <td><?php echo e($member['member_code']); ?></td>
                        <td><?php echo e($member['full_name']); ?></td>
                        <td><?php echo e($member['department']); ?></td>
                        <td><?php echo e($member['semester']); ?></td>
                        <td><?php echo e($member['phone']); ?></td>
                        <td><?php echo e($member['email']); ?></td>
                        <td><span class="badge"><?php echo e($member['status']); ?></span></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>
