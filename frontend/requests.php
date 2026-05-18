<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
    ensure_book_requests_table($connection);
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupType = trim($_POST['group_type'] ?? '');
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $requestedTitle = trim($_POST['requested_title'] ?? '');
    $requestedAuthor = null_if_empty($_POST['requested_author'] ?? '');
    $inTransaction = false;

    try {
        if (isset($_POST['approve_group'])) {
            if ($groupType === 'existing' && $bookId > 0) {
                $stmt = $connection->prepare(
                    "UPDATE Book_Requests
                     SET status = 'APPROVED_FOR_PURCHASE',
                         approved_date = CURRENT_DATE
                     WHERE book_id = ?
                       AND status = 'PENDING'"
                );
                $stmt->bind_param('i', $bookId);
            } elseif ($groupType === 'new' && $requestedTitle !== '') {
                $stmt = $connection->prepare(
                    "UPDATE Book_Requests
                     SET status = 'APPROVED_FOR_PURCHASE',
                         approved_date = CURRENT_DATE
                     WHERE book_id IS NULL
                       AND LOWER(requested_title) = LOWER(?)
                       AND LOWER(COALESCE(requested_author, '')) = LOWER(COALESCE(?, ''))
                       AND status = 'PENDING'"
                );
                $stmt->bind_param('ss', $requestedTitle, $requestedAuthor);
            } else {
                throw new Exception('Could not identify the request group to approve.');
            }

            $stmt->execute();

            if ($stmt->affected_rows <= 0) {
                throw new Exception('No pending requests were found for this book.');
            }

            $message = 'Purchase approval updated successfully.';
        } elseif (isset($_POST['mark_purchased'])) {
            $purchaseCopies = max((int) ($_POST['purchase_copies'] ?? 1), 1);
            $connection->begin_transaction();
            $inTransaction = true;

            if ($groupType === 'existing' && $bookId > 0) {
                $stmt = $connection->prepare(
                    "UPDATE Books
                     SET total_copies = total_copies + ?,
                         available_copies = available_copies + ?
                     WHERE book_id = ?"
                );
                $stmt->bind_param('iii', $purchaseCopies, $purchaseCopies, $bookId);
                $stmt->execute();

                $stmt = $connection->prepare(
                    "UPDATE Book_Requests
                     SET status = 'PURCHASED',
                         purchase_date = CURRENT_DATE
                     WHERE book_id = ?
                       AND status = 'APPROVED_FOR_PURCHASE'"
                );
                $stmt->bind_param('i', $bookId);
                $stmt->execute();
            } elseif ($groupType === 'new' && $requestedTitle !== '') {
                $stmt = $connection->prepare(
                    "UPDATE Book_Requests
                     SET status = 'PURCHASED',
                         purchase_date = CURRENT_DATE
                     WHERE book_id IS NULL
                       AND LOWER(requested_title) = LOWER(?)
                       AND LOWER(COALESCE(requested_author, '')) = LOWER(COALESCE(?, ''))
                       AND status = 'APPROVED_FOR_PURCHASE'"
                );
                $stmt->bind_param('ss', $requestedTitle, $requestedAuthor);
                $stmt->execute();
            } else {
                throw new Exception('Could not identify the request group to mark as purchased.');
            }

            if ($stmt->affected_rows <= 0) {
                throw new Exception('No approved requests were found for this book.');
            }

            $connection->commit();
            $inTransaction = false;
            $message = 'Request group marked as purchased successfully.';
        }
    } catch (Throwable $error) {
        if ($inTransaction) {
            $connection->rollback();
        }
        $errorMessage = $error->getMessage();
    }
}

$requestStats = $connection->query(
    "SELECT
        COUNT(*) AS total_requests,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_requests,
        SUM(CASE WHEN status = 'APPROVED_FOR_PURCHASE' THEN 1 ELSE 0 END) AS approved_requests,
        SUM(CASE WHEN status = 'PURCHASED' THEN 1 ELSE 0 END) AS purchased_requests
    FROM Book_Requests"
)->fetch_assoc();

$requestGroups = $connection->query(
    "SELECT
        CASE
            WHEN br.book_id IS NULL THEN 'new'
            ELSE 'existing'
        END AS group_type,
        br.book_id,
        COALESCE(MAX(b.title), MAX(br.requested_title)) AS requested_title,
        COALESCE(MAX(a.author_name), MAX(br.requested_author), '') AS requested_author,
        MAX(br.requested_isbn) AS requested_isbn,
        COALESCE(MAX(b.category), MAX(br.category), '') AS category,
        MAX(COALESCE(b.available_copies, 0)) AS available_copies,
        SUM(CASE WHEN br.status = 'PENDING' THEN 1 ELSE 0 END) AS pending_requests,
        SUM(CASE WHEN br.status = 'APPROVED_FOR_PURCHASE' THEN 1 ELSE 0 END) AS approved_requests,
        SUM(CASE WHEN br.status = 'PURCHASED' THEN 1 ELSE 0 END) AS purchased_requests,
        COUNT(*) AS total_requests,
        MIN(br.request_date) AS first_request_date,
        MAX(br.request_date) AS latest_request_date
    FROM Book_Requests br
    LEFT JOIN Books b ON br.book_id = b.book_id
    LEFT JOIN Authors a ON b.author_id = a.author_id
    GROUP BY
        br.book_id,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(br.requested_title)) ELSE NULL END,
        CASE WHEN br.book_id IS NULL THEN LOWER(TRIM(COALESCE(br.requested_author, ''))) ELSE NULL END
    ORDER BY pending_requests DESC, approved_requests DESC, total_requests DESC, latest_request_date DESC"
)->fetch_all(MYSQLI_ASSOC);

$recentRequests = $connection->query(
    "SELECT
        br.request_id,
        br.member_id,
        m.member_code,
        m.full_name,
        COALESCE(b.title, br.requested_title) AS requested_title,
        COALESCE(a.author_name, br.requested_author) AS requested_author,
        br.request_date,
        br.status
    FROM Book_Requests br
    JOIN Members m ON br.member_id = m.member_id
    LEFT JOIN Books b ON br.book_id = b.book_id
    LEFT JOIN Authors a ON b.author_id = a.author_id
    ORDER BY br.request_id DESC
    LIMIT 12"
)->fetch_all(MYSQLI_ASSOC);

render_header('Requests', 'requests');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Demand Queue</p>
        <h2>Book Request Queue</h2>
    </div>
    <a href="issue_books.php">Add new request</a>
</section>

<?php if ($message !== '') : ?>
    <div class="notice"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($errorMessage !== '') : ?>
    <div class="notice danger"><?php echo e($errorMessage); ?></div>
<?php endif; ?>

<section class="hero">
    <div>
        <p class="eyebrow">Purchase Workflow</p>
        <h2>Students can queue unavailable books, and the titles with the highest demand can be approved for purchase first.</h2>
        <p class="helper-text">For existing catalog books, marking a request group as purchased will also increase the stock in the `Books` table.</p>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <span>Total Requests</span>
        <strong><?php echo e((int) ($requestStats['total_requests'] ?? 0)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Pending</span>
        <strong><?php echo e((int) ($requestStats['pending_requests'] ?? 0)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Approved</span>
        <strong><?php echo e((int) ($requestStats['approved_requests'] ?? 0)); ?></strong>
    </article>
    <article class="stat-card">
        <span>Purchased</span>
        <strong><?php echo e((int) ($requestStats['purchased_requests'] ?? 0)); ?></strong>
    </article>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h3>Purchase Priority Queue</h3>
            <p class="helper-text">Higher request count means stronger demand and higher priority for approval.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Available</th>
                    <th>Pending</th>
                    <th>Approved</th>
                    <th>Total</th>
                    <th>Latest Request</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requestGroups === []) : ?>
                    <tr>
                        <td colspan="9" class="empty-state">No book requests have been added yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($requestGroups as $group) : ?>
                        <tr>
                            <td><?php echo e($group['requested_title']); ?></td>
                            <td><?php echo e($group['requested_author'] ?: '-'); ?></td>
                            <td><?php echo e($group['category'] ?: '-'); ?></td>
                            <td><?php echo e($group['available_copies']); ?></td>
                            <td><?php echo e($group['pending_requests']); ?></td>
                            <td><?php echo e($group['approved_requests']); ?></td>
                            <td><?php echo e($group['total_requests']); ?></td>
                            <td><?php echo e($group['latest_request_date']); ?></td>
                            <td>
                                <?php if ((int) $group['pending_requests'] > 0) : ?>
                                    <form method="post" class="inline-form inline-actions">
                                        <input type="hidden" name="group_type" value="<?php echo e($group['group_type']); ?>">
                                        <input type="hidden" name="book_id" value="<?php echo e($group['book_id']); ?>">
                                        <input type="hidden" name="requested_title" value="<?php echo e($group['requested_title']); ?>">
                                        <input type="hidden" name="requested_author" value="<?php echo e($group['requested_author']); ?>">
                                        <button type="submit" name="approve_group" value="1" class="small-button">Approve</button>
                                    </form>
                                <?php elseif ((int) $group['approved_requests'] > 0) : ?>
                                    <form method="post" class="inline-form inline-actions">
                                        <input type="hidden" name="group_type" value="<?php echo e($group['group_type']); ?>">
                                        <input type="hidden" name="book_id" value="<?php echo e($group['book_id']); ?>">
                                        <input type="hidden" name="requested_title" value="<?php echo e($group['requested_title']); ?>">
                                        <input type="hidden" name="requested_author" value="<?php echo e($group['requested_author']); ?>">
                                        <input type="number" name="purchase_copies" min="1" value="1" class="mini-input">
                                        <button type="submit" name="mark_purchased" value="1" class="small-button">Mark Purchased</button>
                                    </form>
                                <?php else : ?>
                                    <span class="badge">Completed</span>
                                <?php endif; ?>
                            </td>
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
            <h3>Recent Request Activity</h3>
            <p class="helper-text">Latest requests with member and status details.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Author</th>
                    <th>Request Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentRequests === []) : ?>
                    <tr>
                        <td colspan="6" class="empty-state">No request activity found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recentRequests as $request) : ?>
                        <tr>
                            <td><?php echo e($request['request_id']); ?></td>
                            <td><?php echo e($request['full_name']); ?> (<?php echo e($request['member_code']); ?>)</td>
                            <td><?php echo e($request['requested_title']); ?></td>
                            <td><?php echo e($request['requested_author'] ?: '-'); ?></td>
                            <td><?php echo e($request['request_date']); ?></td>
                            <td><span class="badge"><?php echo e($request['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>
