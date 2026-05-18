<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
    ensure_book_requests_table($connection);
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$totalAuthors = table_count($connection, 'Authors');
$totalBooks = table_count($connection, 'Books');
$totalMembers = table_count($connection, 'Members');
$totalIssues = table_count($connection, 'Issue_Books');
$totalRequests = table_count($connection, 'Book_Requests');

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

$topQueueRequests = $connection->query(
    "SELECT
        CASE
            WHEN br.book_id IS NULL THEN br.requested_title
            ELSE b.title
        END AS requested_title,
        CASE
            WHEN br.book_id IS NULL THEN br.requested_author
            ELSE a.author_name
        END AS requested_author,
        SUM(CASE WHEN br.status = 'PENDING' THEN 1 ELSE 0 END) AS pending_requests,
        COUNT(*) AS total_requests
    FROM Book_Requests br
    LEFT JOIN Books b ON br.book_id = b.book_id
    LEFT JOIN Authors a ON b.author_id = a.author_id
    GROUP BY
        br.book_id,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(br.requested_title)) ELSE NULL END,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(COALESCE(br.requested_author, ''))) ELSE NULL END
    ORDER BY pending_requests DESC, total_requests DESC, requested_title ASC
    LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

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
    <article class="stat-card">
        <span>Queued Requests</span>
        <strong><?php echo e($totalRequests); ?></strong>
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

<section class="panel">
    <div class="section-title">
        <div>
            <h2>Top Requested Books</h2>
            <p class="helper-text">Queue demand helps decide which books should be approved for purchase next.</p>
        </div>
        <a href="requests.php">Open queue</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Author</th>
                    <th>Pending</th>
                    <th>Total Requests</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($topQueueRequests === []) : ?>
                    <tr>
                        <td colspan="4" class="empty-state">No queued requests yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($topQueueRequests as $row) : ?>
                        <tr>
                            <td><?php echo e($row['requested_title']); ?></td>
                            <td><?php echo e($row['requested_author'] ?: '-'); ?></td>
                            <td><?php echo e($row['pending_requests']); ?></td>
                            <td><?php echo e($row['total_requests']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>
