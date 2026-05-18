<?php
// MySQL database connection for Library Management System.
// Change these values if your MySQL username/password is different.

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'library_management';
$DB_PORT = 3307;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    $connection = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $connection->set_charset('utf8mb4');

    return $connection;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function null_if_empty($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function table_count($connection, $table)
{
    $allowedTables = ['Authors', 'Books', 'Members', 'Librarians', 'Issue_Books', 'Book_Requests'];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = $connection->query("SELECT COUNT(*) AS total FROM $table");
    return (int) $result->fetch_assoc()['total'];
}

function table_exists($connection, $tableName)
{
    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?";

    $stmt = $connection->prepare($sql);
    $stmt->bind_param('s', $tableName);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function ensure_book_requests_table($connection)
{
    if (table_exists($connection, 'Book_Requests')) {
        return;
    }

    $connection->query(
        "CREATE TABLE IF NOT EXISTS Book_Requests (
            request_id INT AUTO_INCREMENT,
            member_id INT NOT NULL,
            book_id INT NULL,
            requested_title VARCHAR(150) NOT NULL,
            requested_author VARCHAR(100),
            requested_isbn VARCHAR(20),
            category VARCHAR(80),
            request_notes VARCHAR(255),
            request_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL,
            approved_date DATE NULL,
            purchase_date DATE NULL,
            CONSTRAINT pk_book_requests PRIMARY KEY (request_id),
            CONSTRAINT fk_book_requests_member
                FOREIGN KEY (member_id)
                REFERENCES Members(member_id),
            CONSTRAINT fk_book_requests_book
                FOREIGN KEY (book_id)
                REFERENCES Books(book_id)
        )"
    );
}

function trigger_exists($connection, $triggerName)
{
    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = DATABASE()
              AND TRIGGER_NAME = ?";

    $stmt = $connection->prepare($sql);
    $stmt->bind_param('s', $triggerName);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function render_header($title, $activePage)
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> | Library Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">SQL Mini Project</p>
            <h1>Library Management System</h1>
        </div>
        <nav>
            <a class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="index.php">Dashboard</a>
            <a class="<?php echo $activePage === 'books' ? 'active' : ''; ?>" href="books.php">Books</a>
            <a class="<?php echo $activePage === 'members' ? 'active' : ''; ?>" href="members.php">Members</a>
            <a class="<?php echo $activePage === 'issues' ? 'active' : ''; ?>" href="issue_books.php">Issue Books</a>
            <a class="<?php echo $activePage === 'requests' ? 'active' : ''; ?>" href="requests.php">Requests</a>
            <a class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>" href="reports.php">Reports</a>
        </nav>
    </header>
    <main class="page">
<?php
}

function render_footer()
{
?>
    </main>
</body>
</html>
<?php
}

function render_db_error($error)
{
    render_header('Database Error', '');
?>
    <section class="notice danger">
        <h2>Database connection failed</h2>
        <p><?php echo e($error); ?></p>
        <p>Check that MySQL is running, then run these SQL files in order:</p>
        <ol>
            <li><code>create_tables_library.sql</code></li>
            <li><code>insert_sample_data_library.sql</code></li>
        </ol>
    </section>
<?php
    render_footer();
    exit;
}
?>
