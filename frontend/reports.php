<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
    ensure_book_requests_table($connection);
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$stats = $connection->query(
    "SELECT
        SUM(CASE WHEN return_date IS NULL THEN 1 ELSE 0 END) AS active_loans,
        SUM(CASE WHEN return_date IS NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_loans,
        SUM(
            CASE
                WHEN return_date IS NULL AND due_date < CURDATE()
                THEN DATEDIFF(CURDATE(), due_date) * 5
                ELSE 0
            END
        ) AS estimated_overdue_fine,
        SUM(COALESCE(fine_amount, 0)) AS collected_fines
    FROM Issue_Books"
)->fetch_assoc();

$overdueLoans = $connection->query(
    "SELECT
        ib.issue_id,
        b.title AS book_title,
        m.member_code,
        m.full_name AS member_name,
        l.librarian_name,
        ib.issue_date,
        ib.due_date,
        DATEDIFF(CURDATE(), ib.issue_date) AS borrowed_days,
        DATEDIFF(CURDATE(), ib.due_date) AS overdue_days,
        DATEDIFF(CURDATE(), ib.due_date) * 5 AS estimated_fine
    FROM Issue_Books ib
    JOIN Books b ON ib.book_id = b.book_id
    JOIN Members m ON ib.member_id = m.member_id
    JOIN Librarians l ON ib.librarian_id = l.librarian_id
    WHERE ib.return_date IS NULL
      AND ib.due_date < CURDATE()
    ORDER BY ib.due_date ASC, ib.issue_id ASC"
)->fetch_all(MYSQLI_ASSOC);

$memberSummaries = $connection->query(
    "SELECT
        m.member_id,
        m.member_code,
        m.full_name,
        COUNT(ib.issue_id) AS total_issues,
        SUM(CASE WHEN ib.return_date IS NULL THEN 1 ELSE 0 END) AS active_issues,
        SUM(CASE WHEN ib.return_date IS NULL AND ib.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_issues,
        SUM(COALESCE(ib.fine_amount, 0)) AS total_fines
    FROM Members m
    LEFT JOIN Issue_Books ib ON m.member_id = ib.member_id
    GROUP BY m.member_id, m.member_code, m.full_name
    HAVING COUNT(ib.issue_id) > 0
    ORDER BY overdue_issues DESC, active_issues DESC, total_issues DESC, m.full_name ASC
    LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$recentFines = $connection->query(
    "SELECT
        ib.issue_id,
        b.title AS book_title,
        m.full_name AS member_name,
        ib.due_date,
        ib.return_date,
        ib.fine_amount
    FROM Issue_Books ib
    JOIN Books b ON ib.book_id = b.book_id
    JOIN Members m ON ib.member_id = m.member_id
    WHERE COALESCE(ib.fine_amount, 0) > 0
    ORDER BY ib.return_date DESC, ib.issue_id DESC
    LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$purchaseRecommendations = $connection->query(
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
        SUM(CASE WHEN br.status = 'APPROVED_FOR_PURCHASE' THEN 1 ELSE 0 END) AS approved_requests,
        COUNT(*) AS total_requests
    FROM Book_Requests br
    LEFT JOIN Books b ON br.book_id = b.book_id
    LEFT JOIN Authors a ON b.author_id = a.author_id
    GROUP BY
        br.book_id,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(br.requested_title)) ELSE NULL END,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(COALESCE(br.requested_author, ''))) ELSE NULL END
    ORDER BY pending_requests DESC, approved_requests DESC, total_requests DESC, requested_title ASC
    LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

render_header('Reports', 'reports');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Insights</p>
        <h2>Reports & Fine Tracking</h2>
    </div>
</section>

<section class="hero">
    <div>
        <p class="eyebrow">Overview</p>
        <h2>Track overdue books, expected fines, and the members who need attention first.</h2>
        <p class="helper-text">Estimated overdue fine uses the same rule as the project logic: <strong>5 per late day</strong>. It is shown as a live estimate until the book is returned.</p>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <span>Active Loans</span>
        <strong><?php echo e((int) ($stats['active_loans'] ?? 0)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Overdue Loans</span>
        <strong><?php echo e((int) ($stats['overdue_loans'] ?? 0)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Estimated Overdue Fine</span>
        <strong><?php echo e(number_format((float) ($stats['estimated_overdue_fine'] ?? 0), 2)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Collected Fines</span>
        <strong><?php echo e(number_format((float) ($stats['collected_fines'] ?? 0), 2)); ?></strong>
    </article>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h3>Overdue Books</h3>
            <p class="helper-text">Books that have passed their due date and are still not returned.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Issue ID</th>
                    <th>Book</th>
                    <th>Member</th>
                    <th>Librarian</th>
                    <th>Due Date</th>
                    <th>Borrowed Days</th>
                    <th>Overdue Days</th>
                    <th>Estimated Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($overdueLoans === []) : ?>
                    <tr>
                        <td colspan="8" class="empty-state">No overdue books right now.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($overdueLoans as $loan) : ?>
                        <tr>
                            <td><?php echo e($loan['issue_id']); ?></td>
                            <td><?php echo e($loan['book_title']); ?></td>
                            <td><?php echo e($loan['member_name']); ?> (<?php echo e($loan['member_code']); ?>)</td>
                            <td><?php echo e($loan['librarian_name']); ?></td>
                            <td><?php echo e($loan['due_date']); ?></td>
                            <td><?php echo e($loan['borrowed_days']); ?></td>
                            <td><?php echo e($loan['overdue_days']); ?></td>
                            <td><?php echo e(number_format((float) $loan['estimated_fine'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h3>Purchase Recommendations</h3>
            <p class="helper-text">Books with the biggest queue should be approved for purchasing first.</p>
        </div>
        <a href="requests.php">Manage request queue</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Author</th>
                    <th>Pending</th>
                    <th>Approved</th>
                    <th>Total Requests</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($purchaseRecommendations === []) : ?>
                    <tr>
                        <td colspan="5" class="empty-state">No queued demand available yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($purchaseRecommendations as $recommendation) : ?>
                        <tr>
                            <td><?php echo e($recommendation['requested_title']); ?></td>
                            <td><?php echo e($recommendation['requested_author'] ?: '-'); ?></td>
                            <td><?php echo e($recommendation['pending_requests']); ?></td>
                            <td><?php echo e($recommendation['approved_requests']); ?></td>
                            <td><?php echo e($recommendation['total_requests']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="split-layout">
    <div class="panel">
        <div class="section-title">
            <div>
                <h3>Member Borrowing Summary</h3>
                <p class="helper-text">Members ordered by highest overdue and active load first.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Total Issues</th>
                        <th>Active</th>
                        <th>Overdue</th>
                        <th>Total Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($memberSummaries === []) : ?>
                        <tr>
                            <td colspan="5" class="empty-state">No member borrowing records found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($memberSummaries as $member) : ?>
                            <tr>
                                <td><?php echo e($member['full_name']); ?> (<?php echo e($member['member_code']); ?>)</td>
                                <td><?php echo e($member['total_issues']); ?></td>
                                <td><?php echo e($member['active_issues']); ?></td>
                                <td><?php echo e($member['overdue_issues']); ?></td>
                                <td><?php echo e(number_format((float) $member['total_fines'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="section-title">
            <div>
                <h3>Recent Fines</h3>
                <p class="helper-text">Returned books that generated a non-zero fine.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Issue ID</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Returned</th>
                        <th>Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentFines === []) : ?>
                        <tr>
                            <td colspan="5" class="empty-state">No fines have been collected yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($recentFines as $fine) : ?>
                            <tr>
                                <td><?php echo e($fine['issue_id']); ?></td>
                                <td><?php echo e($fine['book_title']); ?></td>
                                <td><?php echo e($fine['member_name']); ?></td>
                                <td><?php echo e($fine['return_date']); ?></td>
                                <td><?php echo e(number_format((float) $fine['fine_amount'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php render_footer(); ?>
