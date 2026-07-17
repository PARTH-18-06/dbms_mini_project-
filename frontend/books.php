<?php
require_once __DIR__ . '/db.php';

try {
    $connection = db();
} catch (Throwable $error) {
    render_db_error($error->getMessage());
}

$authors = $connection->query(
    "SELECT author_id, author_name
     FROM Authors
     ORDER BY author_name"
)->fetch_all(MYSQLI_ASSOC);

$categories = $connection->query(
    "SELECT DISTINCT category
     FROM Books
     WHERE category IS NOT NULL
       AND TRIM(category) <> ''
     ORDER BY category"
)->fetch_all(MYSQLI_ASSOC);

$bootstrap = [
    'apiUrl' => 'api_books.php',
    'authors' => $authors,
    'categories' => array_values(array_map(
        static fn(array $row): string => (string) $row['category'],
        $categories
    )),
];

render_header('Books', 'books');
?>

<section class="section-title">
    <div>
        <p class="eyebrow">Catalog</p>
        <h2>Books</h2>
    </div>
</section>

<div id="book-message" class="notice is-hidden" role="status" aria-live="polite"></div>

<section class="panel">
    <div class="section-title">
        <div>
            <h3>AJAX CRUD for Books</h3>
            <p class="helper-text">Add, update, search, and delete books without reloading the full page.</p>
        </div>
    </div>
    <form id="book-form" class="form-grid">
        <input type="hidden" name="book_id" id="book_id">
        <label>
            ISBN
            <input type="text" name="isbn" id="isbn" required>
        </label>
        <label>
            Title
            <input type="text" name="title" id="title" required>
        </label>
        <label>
            Author
            <select name="author_id" id="author_id" required>
                <option value="">Select author</option>
                <?php foreach ($authors as $author) : ?>
                    <option value="<?php echo e($author['author_id']); ?>"><?php echo e($author['author_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Category
            <input type="text" name="category" id="category">
        </label>
        <label>
            Publisher
            <input type="text" name="publisher" id="publisher">
        </label>
        <label>
            Publication Date
            <input type="date" name="publication_date" id="publication_date">
        </label>
        <label>
            Total Copies
            <input type="number" name="total_copies" id="total_copies" min="1" value="1" required>
        </label>
        <label>
            Available Copies
            <input type="number" name="available_copies" id="available_copies" min="0" value="1" required>
        </label>
        <label>
            Shelf No
            <input type="text" name="shelf_no" id="shelf_no">
        </label>
        <div class="form-actions">
            <button type="submit" id="book-submit">Add Book</button>
            <button type="button" id="book-cancel" class="button ghost is-hidden">Cancel Edit</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <h3>Book List</h3>
    </div>
    <form id="book-filter-form" class="toolbar">
        <input type="text" name="search" id="book-search" placeholder="Search title, ISBN, author">
        <select name="category" id="book-filter-category">
            <option value="">All categories</option>
        </select>
        <button type="submit">Search</button>
        <button type="button" id="book-clear" class="button ghost">Clear</button>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="books-table-body">
                <tr>
                    <td colspan="9" class="empty-state">Loading books...</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<script>
window.BOOKS_CRUD_BOOTSTRAP = <?php echo json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script src="books-crud.js"></script>

<?php render_footer(); ?>
