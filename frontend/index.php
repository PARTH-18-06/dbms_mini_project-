<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$totalAuthors = table_count($connection, 'Authors');
$totalBooks = table_count($connection, 'Books');
$totalMembers = table_count($connection, 'Members');
$totalIssues = table_count($connection, 'Issue_Books');

$recentIssues = $connection->query(
    "SELECT
        ib.issue_id,
        b.title AS book_title,
        m.full_name AS member_name,
        l.librarian_name,
        ib.issue_date,
        ib.due_date,
        ib.issue_status
    FROM Issue_Books ib
    JOIN Books b ON ib.book_id = b.book_id
    JOIN Members m ON ib.member_id = m.member_id
    JOIN Librarians l ON ib.librarian_id = l.librarian_id
    ORDER BY ib.issue_date DESC, ib.issue_id DESC
    LIMIT 8"
);

$categoryRows = $connection->query(
    "SELECT
        category,
        COUNT(book_id) AS total_books,
        SUM(available_copies) AS available_copies
    FROM Books
    GROUP BY category
    ORDER BY total_books DESC
    LIMIT 6"
);

render_header('Dashboard', 'dashboard');
?>

<section class="hero">
    <div>
        <p class="eyebrow">Dashboard</p>
        <h2>Manage books, members, and issue records from one simple PHP interface.</h2>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <span>Authors</span>
        <strong><?php echo e($totalAuthors); ?></strong>
    </article>
    <article class="stat-card">
        <span>Books</span>
        <strong><?php echo e($totalBooks); ?></strong>
    </article>
    <article class="stat-card">
        <span>Members</span>
        <strong><?php echo e($totalMembers); ?></strong>
    </article>
    <article class="stat-card">
        <span>Issue Records</span>
        <strong><?php echo e($totalIssues); ?></strong>
    </article>
</section>

<section class="split-layout">
    <div class="panel">
        <div class="section-title">
            <h2>Recent Issues</h2>
            <a href="issue_books.php">View all</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Librarian</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $recentIssues->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo e($row['issue_id']); ?></td>
                            <td><?php echo e($row['book_title']); ?></td>
                            <td><?php echo e($row['member_name']); ?></td>
                            <td><?php echo e($row['librarian_name']); ?></td>
                            <td><?php echo e($row['due_date']); ?></td>
                            <td><span class="badge"><?php echo e($row['issue_status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="section-title">
            <h2>Books by Category</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total</th>
                        <th>Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $categoryRows->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo e($row['category']); ?></td>
                            <td><?php echo e($row['total_books']); ?></td>
                            <td><?php echo e($row['available_copies']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php render_footer(); ?>
