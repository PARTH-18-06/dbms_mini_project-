<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$message = '';
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn = trim($_POST['isbn'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $authorId = (int) ($_POST['author_id'] ?? 0);
    $bookCategory = trim($_POST['category'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $publicationDate = null_if_empty($_POST['publication_date'] ?? '');
    $totalCopies = (int) ($_POST['total_copies'] ?? 1);
    $shelfNo = trim($_POST['shelf_no'] ?? '');

    if ($isbn === '' || $title === '' || $authorId <= 0 || $totalCopies < 1) {
        $message = 'Please fill ISBN, title, author, and valid copy count.';
    } else {
        $stmt = $connection->prepare(
            "INSERT INTO Books
            (isbn, title, author_id, category, publisher, publication_date, total_copies, available_copies, shelf_no)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssisssiis',
            $isbn,
            $title,
            $authorId,
            $bookCategory,
            $publisher,
            $publicationDate,
            $totalCopies,
            $totalCopies,
            $shelfNo
        );
        $stmt->execute();
        $message = 'Book added successfully.';
    }
}

$authors = $connection->query("SELECT author_id, author_name FROM Authors ORDER BY author_name");
$categories = $connection->query("SELECT DISTINCT category FROM Books WHERE category IS NOT NULL ORDER BY category");

$sql = "SELECT
            b.book_id,
            b.isbn,
            b.title,
            a.author_name,
            b.category,
            b.publisher,
            b.publication_date,
            b.total_copies,
            b.available_copies,
            b.shelf_no
        FROM Books b
        JOIN Authors a ON b.author_id = a.author_id
        WHERE 1 = 1";

$types = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR a.author_name LIKE ?)";
    $likeSearch = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
}

if ($category !== '') {
    $sql .= " AND b.category = ?";
    $types .= 's';
    $params[] = $category;
}

$sql .= " ORDER BY b.title";
$stmt = $connection->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$books = $stmt->get_result();

render_header('Books', 'books');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Catalog</p>
        <h2>Books</h2>
    </div>
</section>

<?php if ($message !== '') : ?>
    <div class="notice"><?php echo e($message); ?></div>
<?php endif; ?>

<section class="panel">
    <h3>Add New Book</h3>
    <form method="post" class="form-grid">
        <label>
            ISBN
            <input type="text" name="isbn" required>
        </label>
        <label>
            Title
            <input type="text" name="title" required>
        </label>
        <label>
            Author
            <select name="author_id" required>
                <option value="">Select author</option>
                <?php while ($author = $authors->fetch_assoc()) : ?>
                    <option value="<?php echo e($author['author_id']); ?>"><?php echo e($author['author_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </label>
        <label>
            Category
            <input type="text" name="category">
        </label>
        <label>
            Publisher
            <input type="text" name="publisher">
        </label>
        <label>
            Publication Date
            <input type="date" name="publication_date">
        </label>
        <label>
            Copies
            <input type="number" name="total_copies" min="1" value="1" required>
        </label>
        <label>
            Shelf No
            <input type="text" name="shelf_no">
        </label>
        <button type="submit">Add Book</button>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <h3>Book List</h3>
    </div>
    <form method="get" class="toolbar">
        <input type="text" name="search" placeholder="Search title, ISBN, author" value="<?php echo e($search); ?>">
        <select name="category">
            <option value="">All categories</option>
            <?php while ($row = $categories->fetch_assoc()) : ?>
                <option value="<?php echo e($row['category']); ?>" <?php echo $category === $row['category'] ? 'selected' : ''; ?>>
                    <?php echo e($row['category']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Search</button>
        <a class="button ghost" href="books.php">Clear</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Publisher</th>
                    <th>Total</th>
                    <th>Available</th>
                    <th>Shelf</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($book = $books->fetch_assoc()) : ?>
                    <tr>
                        <td><?php echo e($book['book_id']); ?></td>
                        <td><?php echo e($book['title']); ?></td>
                        <td><?php echo e($book['author_name']); ?></td>
                        <td><?php echo e($book['category']); ?></td>
                        <td><?php echo e($book['publisher']); ?></td>
                        <td><?php echo e($book['total_copies']); ?></td>
                        <td><?php echo e($book['available_copies']); ?></td>
                        <td><?php echo e($book['shelf_no']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php render_footer(); ?>
