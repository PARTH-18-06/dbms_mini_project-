<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
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

$books = $connection->query(
    "SELECT book_id, title, available_copies
     FROM Books
     WHERE available_copies > 0
     ORDER BY title"
);

$members = $connection->query(
    "SELECT member_id, member_code, full_name
     FROM Members
     WHERE status = 'ACTIVE'
     ORDER BY full_name"
);

$librarians = $connection->query(
    "SELECT librarian_id, librarian_name
     FROM Librarians
     ORDER BY librarian_name"
);

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
);

render_header('Issue Books', 'issues');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Transactions</p>
        <h2>Issue Books</h2>
    </div>
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
                <?php while ($book = $books->fetch_assoc()) : ?>
                    <option value="<?php echo e($book['book_id']); ?>">
                        <?php echo e($book['title']); ?> (<?php echo e($book['available_copies']); ?> available)
                    </option>
                <?php endwhile; ?>
            </select>
        </label>
        <label>
            Member
            <select name="member_id" required>
                <option value="">Select member</option>
                <?php while ($member = $members->fetch_assoc()) : ?>
                    <option value="<?php echo e($member['member_id']); ?>">
                        <?php echo e($member['full_name']); ?> - <?php echo e($member['member_code']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label>
        <label>
            Librarian
            <select name="librarian_id" required>
                <option value="">Select librarian</option>
                <?php while ($librarian = $librarians->fetch_assoc()) : ?>
                    <option value="<?php echo e($librarian['librarian_id']); ?>">
                        <?php echo e($librarian['librarian_name']); ?>
                    </option>
                <?php endwhile; ?>
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
                <?php while ($issue = $issues->fetch_assoc()) : ?>
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
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>
