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
    $inTransaction = false;

    try {
        if (isset($_POST['return_issue_id'])) {
            $issueId = (int) $_POST['return_issue_id'];
            $connection->begin_transaction();
            $inTransaction = true;

            $stmt = $connection->prepare(
                "SELECT issue_id, book_id, due_date
                 FROM Issue_Books
                 WHERE issue_id = ?
                   AND return_date IS NULL
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $issueId);
            $stmt->execute();
            $issue = $stmt->get_result()->fetch_assoc();

            if (!$issue) {
                throw new Exception('Active issue record not found.');
            }

            $fine = max((int) floor((strtotime(date('Y-m-d')) - strtotime($issue['due_date'])) / 86400), 0) * 5;
            $stmt = $connection->prepare(
                "UPDATE Issue_Books
                 SET return_date = CURRENT_DATE,
                     fine_amount = ?,
                     issue_status = 'RETURNED'
                 WHERE issue_id = ?"
            );
            $stmt->bind_param('di', $fine, $issueId);
            $stmt->execute();

            if (!trigger_exists($connection, 'trg_issue_books_after_update')) {
                $stmt = $connection->prepare(
                    "UPDATE Books
                     SET available_copies = available_copies + 1
                     WHERE book_id = ?"
                );
                $stmt->bind_param('i', $issue['book_id']);
                $stmt->execute();
            }

            $connection->commit();
            $inTransaction = false;
            $message = 'Book returned successfully.';
        } elseif (isset($_POST['submit_request'])) {
            $memberId = (int) ($_POST['request_member_id'] ?? 0);
            $requestMode = trim($_POST['request_mode'] ?? 'existing');
            $requestBookId = (int) ($_POST['request_book_id'] ?? 0);
            $requestedTitle = trim($_POST['requested_title'] ?? '');
            $requestedAuthor = null_if_empty($_POST['requested_author'] ?? '');
            $requestedIsbn = null_if_empty($_POST['requested_isbn'] ?? '');
            $requestCategory = null_if_empty($_POST['request_category'] ?? '');
            $requestNotes = null_if_empty($_POST['request_notes'] ?? '');

            if ($memberId <= 0) {
                throw new Exception('Please select the member who is requesting the book.');
            }

            $stmt = $connection->prepare(
                "SELECT member_id
                 FROM Members
                 WHERE member_id = ?
                   AND status = 'ACTIVE'"
            );
            $stmt->bind_param('i', $memberId);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                throw new Exception('Only active members can join the request queue.');
            }

            $bookId = null;

            if ($requestMode === 'existing') {
                if ($requestBookId <= 0) {
                    throw new Exception('Please select an unavailable book for the queue request.');
                }

                $stmt = $connection->prepare(
                    "SELECT
                        b.book_id,
                        b.isbn,
                        b.title,
                        b.category,
                        b.available_copies,
                        a.author_name
                     FROM Books b
                     JOIN Authors a ON b.author_id = a.author_id
                     WHERE b.book_id = ?"
                );
                $stmt->bind_param('i', $requestBookId);
                $stmt->execute();
                $bookRow = $stmt->get_result()->fetch_assoc();

                if (!$bookRow) {
                    throw new Exception('Selected book was not found.');
                }

                if ((int) $bookRow['available_copies'] > 0) {
                    throw new Exception('This book is already available. Issue it directly instead of putting it in the queue.');
                }

                $bookId = (int) $bookRow['book_id'];
                $requestedTitle = $bookRow['title'];
                $requestedAuthor = $bookRow['author_name'];
                $requestedIsbn = $bookRow['isbn'];
                $requestCategory = $bookRow['category'];

                $stmt = $connection->prepare(
                    "SELECT request_id
                     FROM Book_Requests
                     WHERE member_id = ?
                       AND book_id = ?
                       AND status IN ('PENDING', 'APPROVED_FOR_PURCHASE', 'PURCHASED')
                     LIMIT 1"
                );
                $stmt->bind_param('ii', $memberId, $bookId);
                $stmt->execute();
            } else {
                if ($requestedTitle === '') {
                    throw new Exception('Please enter the title of the requested book.');
                }

                $stmt = $connection->prepare(
                    "SELECT request_id
                     FROM Book_Requests
                     WHERE member_id = ?
                       AND book_id IS NULL
                       AND LOWER(requested_title) = LOWER(?)
                       AND LOWER(COALESCE(requested_author, '')) = LOWER(COALESCE(?, ''))
                       AND status IN ('PENDING', 'APPROVED_FOR_PURCHASE', 'PURCHASED')
                     LIMIT 1"
                );
                $stmt->bind_param('iss', $memberId, $requestedTitle, $requestedAuthor);
                $stmt->execute();
            }

            if ($stmt->get_result()->fetch_assoc()) {
                throw new Exception('This member already has an active request for the same book.');
            }

            $stmt = $connection->prepare(
                "INSERT INTO Book_Requests
                (member_id, book_id, requested_title, requested_author, requested_isbn, category, request_notes, request_date, status, approved_date, purchase_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, 'PENDING', NULL, NULL)"
            );
            $stmt->bind_param(
                'iisssss',
                $memberId,
                $bookId,
                $requestedTitle,
                $requestedAuthor,
                $requestedIsbn,
                $requestCategory,
                $requestNotes
            );
            $stmt->execute();

            $message = 'Book request added to the queue successfully.';
        } else {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $librarianId = (int) ($_POST['librarian_id'] ?? 0);
            $issueDate = trim($_POST['issue_date'] ?? date('Y-m-d'));
            $dueDate = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days')));

            if ($bookId <= 0 || $memberId <= 0 || $librarianId <= 0) {
                throw new Exception('Please select book, member, and librarian.');
            }

            $connection->begin_transaction();
            $inTransaction = true;

            $stmt = $connection->prepare(
                "SELECT available_copies
                 FROM Books
                 WHERE book_id = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $bookId);
            $stmt->execute();
            $book = $stmt->get_result()->fetch_assoc();

            if (!$book || (int) $book['available_copies'] <= 0) {
                throw new Exception('Selected book is not available.');
            }

            $stmt = $connection->prepare(
                "INSERT INTO Issue_Books
                (book_id, member_id, librarian_id, issue_date, due_date, return_date, fine_amount, issue_status)
                VALUES (?, ?, ?, ?, ?, NULL, 0.00, 'ISSUED')"
            );
            $stmt->bind_param('iiiss', $bookId, $memberId, $librarianId, $issueDate, $dueDate);
            $stmt->execute();

            if (!trigger_exists($connection, 'reduce_available_copies_after_issue')
                && !trigger_exists($connection, 'trg_issue_books_after_insert')) {
                $stmt = $connection->prepare(
                    "UPDATE Books
                     SET available_copies = available_copies - 1
                     WHERE book_id = ?
                       AND available_copies > 0"
                );
                $stmt->bind_param('i', $bookId);
                $stmt->execute();
            }

            $connection->commit();
            $inTransaction = false;
            $message = 'Book issued successfully.';
        }
    } catch (Throwable $error) {
        if ($inTransaction) {
            $connection->rollback();
        }
        $errorMessage = $error->getMessage();
    }
}

$availableBooks = $connection->query(
    "SELECT book_id, title, available_copies
     FROM Books
     WHERE available_copies > 0
     ORDER BY title"
)->fetch_all(MYSQLI_ASSOC);

$unavailableBooks = $connection->query(
    "SELECT
        b.book_id,
        b.title,
        b.category,
        b.isbn,
        a.author_name
     FROM Books b
     JOIN Authors a ON b.author_id = a.author_id
     WHERE b.available_copies <= 0
     ORDER BY b.title"
)->fetch_all(MYSQLI_ASSOC);

$members = $connection->query(
    "SELECT member_id, member_code, full_name
     FROM Members
     WHERE status = 'ACTIVE'
     ORDER BY full_name"
)->fetch_all(MYSQLI_ASSOC);

$librarians = $connection->query(
    "SELECT librarian_id, librarian_name
     FROM Librarians
     ORDER BY librarian_name"
)->fetch_all(MYSQLI_ASSOC);

$issues = $connection->query(
    "SELECT
        ib.issue_id,
        b.title AS book_title,
        m.full_name AS member_name,
        l.librarian_name,
        ib.issue_date,
        ib.due_date,
        ib.return_date,
        ib.fine_amount,
        ib.issue_status
    FROM Issue_Books ib
    JOIN Books b ON ib.book_id = b.book_id
    JOIN Members m ON ib.member_id = m.member_id
    JOIN Librarians l ON ib.librarian_id = l.librarian_id
    ORDER BY ib.issue_id DESC"
)->fetch_all(MYSQLI_ASSOC);

$queuePreview = $connection->query(
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

render_header('Issue Books', 'issues');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Transactions</p>
        <h2>Issue Books</h2>
    </div>
    <a href="requests.php">Open request queue</a>
</section>

<?php if ($message !== '') : ?>
    <div class="notice"><?php echo e($message); ?></div>
<?php endif; ?>

<?php if ($errorMessage !== '') : ?>
    <div class="notice danger"><?php echo e($errorMessage); ?></div>
<?php endif; ?>

<section class="panel">
    <h3>Issue a Book</h3>
    <form method="post" class="form-grid">
        <label>
            Book
            <select name="book_id" required>
                <option value="">Select book</option>
                <?php foreach ($availableBooks as $book) : ?>
                    <option value="<?php echo e($book['book_id']); ?>">
                        <?php echo e($book['title']); ?> (<?php echo e($book['available_copies']); ?> available)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Member
            <select name="member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members as $member) : ?>
                    <option value="<?php echo e($member['member_id']); ?>">
                        <?php echo e($member['full_name']); ?> - <?php echo e($member['member_code']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Librarian
            <select name="librarian_id" required>
                <option value="">Select librarian</option>
                <?php foreach ($librarians as $librarian) : ?>
                    <option value="<?php echo e($librarian['librarian_id']); ?>">
                        <?php echo e($librarian['librarian_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Issue Date
            <input type="date" name="issue_date" value="<?php echo e(date('Y-m-d')); ?>" required>
        </label>
        <label>
            Due Date
            <input type="date" name="due_date" value="<?php echo e(date('Y-m-d', strtotime('+14 days'))); ?>" required>
        </label>
        <button type="submit">Issue Book</button>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h3>Request Unavailable or New Book</h3>
            <p class="helper-text">If a student asks for a book that is not available, add it here so it joins the purchase queue.</p>
        </div>
        <a href="requests.php">Manage approvals</a>
    </div>
    <form method="post" class="form-grid">
        <label>
            Member
            <select name="request_member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members as $member) : ?>
                    <option value="<?php echo e($member['member_id']); ?>">
                        <?php echo e($member['full_name']); ?> - <?php echo e($member['member_code']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Request Type
            <select name="request_mode" required>
                <option value="existing">Unavailable existing book</option>
                <option value="new">Completely new book</option>
            </select>
        </label>
        <label class="field-span-2">
            Unavailable Book
            <select name="request_book_id">
                <option value="">Select from unavailable books</option>
                <?php foreach ($unavailableBooks as $book) : ?>
                    <option value="<?php echo e($book['book_id']); ?>">
                        <?php echo e($book['title']); ?> - <?php echo e($book['author_name']); ?><?php echo $book['category'] ? ' (' . e($book['category']) . ')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Requested Title
            <input type="text" name="requested_title" placeholder="Use for a new book request">
        </label>
        <label>
            Requested Author
            <input type="text" name="requested_author" placeholder="Optional for new request">
        </label>
        <label>
            Requested ISBN
            <input type="text" name="requested_isbn" placeholder="Optional">
        </label>
        <label>
            Category
            <input type="text" name="request_category" placeholder="Optional">
        </label>
        <label class="field-span-full">
            Notes
            <textarea name="request_notes" rows="3" placeholder="Edition, urgency, department need, or any extra context"></textarea>
        </label>
        <button type="submit" name="submit_request" value="1">Add to Queue</button>
    </form>
</section>

<section class="split-layout">
    <div class="panel">
        <div class="section-title">
            <h3>Issue Records</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Librarian</th>
                        <th>Issue</th>
                        <th>Due</th>
                        <th>Return</th>
                        <th>Fine</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue) : ?>
                        <tr>
                            <td><?php echo e($issue['issue_id']); ?></td>
                            <td><?php echo e($issue['book_title']); ?></td>
                            <td><?php echo e($issue['member_name']); ?></td>
                            <td><?php echo e($issue['librarian_name']); ?></td>
                            <td><?php echo e($issue['issue_date']); ?></td>
                            <td><?php echo e($issue['due_date']); ?></td>
                            <td><?php echo e($issue['return_date'] ?: '-'); ?></td>
                            <td><?php echo e(number_format((float) $issue['fine_amount'], 2)); ?></td>
                            <td><span class="badge"><?php echo e($issue['issue_status']); ?></span></td>
                            <td>
                                <?php if ($issue['return_date'] === null) : ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="return_issue_id" value="<?php echo e($issue['issue_id']); ?>">
                                        <button type="submit" class="small-button">Return</button>
                                    </form>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="section-title">
            <div>
                <h3>Queue Snapshot</h3>
                <p class="helper-text">Books with the highest demand should be approved for purchase first.</p>
            </div>
            <a href="requests.php">See full queue</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Author</th>
                        <th>Pending</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($queuePreview === []) : ?>
                        <tr>
                            <td colspan="4" class="empty-state">No queued requests yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($queuePreview as $queueRow) : ?>
                            <tr>
                                <td><?php echo e($queueRow['requested_title']); ?></td>
                                <td><?php echo e($queueRow['requested_author'] ?: '-'); ?></td>
                                <td><?php echo e($queueRow['pending_requests']); ?></td>
                                <td><?php echo e($queueRow['total_requests']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php render_footer(); ?>
