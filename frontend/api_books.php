<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_payload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

try {
    $connection = db();
    ensure_book_requests_table($connection);
} catch (Throwable $error) {
    respond(500, [
        'success' => false,
        'message' => 'Database connection failed.',
        'error' => $error->getMessage(),
    ]);
}

function fetch_books(mysqli $connection, string $search = '', string $category = ''): array
{
    $sql = "SELECT
                b.book_id,
                b.isbn,
                b.title,
                b.author_id,
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

    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_categories(mysqli $connection): array
{
    $rows = $connection->query(
        "SELECT DISTINCT category
         FROM Books
         WHERE category IS NOT NULL
           AND TRIM(category) <> ''
         ORDER BY category"
    )->fetch_all(MYSQLI_ASSOC);

    return array_values(array_map(
        static fn(array $row): string => (string) $row['category'],
        $rows
    ));
}

function ensure_author_exists(mysqli $connection, int $authorId): void
{
    $stmt = $connection->prepare("SELECT author_id FROM Authors WHERE author_id = ?");
    $stmt->bind_param('i', $authorId);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new InvalidArgumentException('Selected author was not found.');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');

        respond(200, [
            'success' => true,
            'books' => fetch_books($connection, $search, $category),
            'categories' => fetch_categories($connection),
        ]);
    }

    $payload = request_payload();

    if ($method === 'POST') {
        $isbn = trim((string) ($payload['isbn'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $authorId = (int) ($payload['author_id'] ?? 0);
        $category = trim((string) ($payload['category'] ?? ''));
        $publisher = trim((string) ($payload['publisher'] ?? ''));
        $publicationDate = null_if_empty($payload['publication_date'] ?? '');
        $totalCopies = (int) ($payload['total_copies'] ?? 0);
        $availableCopies = (int) ($payload['available_copies'] ?? $totalCopies);
        $shelfNo = trim((string) ($payload['shelf_no'] ?? ''));

        if ($isbn === '' || $title === '' || $authorId <= 0 || $totalCopies < 1) {
            throw new InvalidArgumentException('Please fill ISBN, title, author, and a valid total copy count.');
        }

        if ($availableCopies < 0 || $availableCopies > $totalCopies) {
            throw new InvalidArgumentException('Available copies must be between 0 and total copies.');
        }

        ensure_author_exists($connection, $authorId);

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
            $category,
            $publisher,
            $publicationDate,
            $totalCopies,
            $availableCopies,
            $shelfNo
        );
        $stmt->execute();

        respond(201, [
            'success' => true,
            'message' => 'Book created successfully.',
            'book_id' => $connection->insert_id,
        ]);
    }

    if ($method === 'PUT') {
        $bookId = (int) ($payload['book_id'] ?? 0);
        $isbn = trim((string) ($payload['isbn'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $authorId = (int) ($payload['author_id'] ?? 0);
        $category = trim((string) ($payload['category'] ?? ''));
        $publisher = trim((string) ($payload['publisher'] ?? ''));
        $publicationDate = null_if_empty($payload['publication_date'] ?? '');
        $totalCopies = (int) ($payload['total_copies'] ?? 0);
        $availableCopies = (int) ($payload['available_copies'] ?? -1);
        $shelfNo = trim((string) ($payload['shelf_no'] ?? ''));

        if ($bookId <= 0 || $isbn === '' || $title === '' || $authorId <= 0 || $totalCopies < 1) {
            throw new InvalidArgumentException('Please provide a valid book, ISBN, title, author, and total copy count.');
        }

        if ($availableCopies < 0 || $availableCopies > $totalCopies) {
            throw new InvalidArgumentException('Available copies must be between 0 and total copies.');
        }

        ensure_author_exists($connection, $authorId);

        $stmt = $connection->prepare("SELECT book_id FROM Books WHERE book_id = ?");
        $stmt->bind_param('i', $bookId);
        $stmt->execute();

        if (!$stmt->get_result()->fetch_assoc()) {
            respond(404, [
                'success' => false,
                'message' => 'Book not found.',
            ]);
        }

        $stmt = $connection->prepare(
            "UPDATE Books
             SET isbn = ?,
                 title = ?,
                 author_id = ?,
                 category = ?,
                 publisher = ?,
                 publication_date = ?,
                 total_copies = ?,
                 available_copies = ?,
                 shelf_no = ?
             WHERE book_id = ?"
        );
        $stmt->bind_param(
            'ssisssiisi',
            $isbn,
            $title,
            $authorId,
            $category,
            $publisher,
            $publicationDate,
            $totalCopies,
            $availableCopies,
            $shelfNo,
            $bookId
        );
        $stmt->execute();

        respond(200, [
            'success' => true,
            'message' => 'Book updated successfully.',
        ]);
    }

    if ($method === 'DELETE') {
        $bookId = (int) ($_GET['book_id'] ?? $payload['book_id'] ?? 0);
        if ($bookId <= 0) {
            throw new InvalidArgumentException('Please choose a valid book to delete.');
        }

        $stmt = $connection->prepare(
            "SELECT
                (SELECT COUNT(*) FROM Issue_Books WHERE book_id = ?) AS issue_count,
                (SELECT COUNT(*) FROM Book_Requests WHERE book_id = ?) AS request_count"
        );
        $stmt->bind_param('ii', $bookId, $bookId);
        $stmt->execute();
        $usage = $stmt->get_result()->fetch_assoc();

        if ((int) ($usage['issue_count'] ?? 0) > 0 || (int) ($usage['request_count'] ?? 0) > 0) {
            respond(409, [
                'success' => false,
                'message' => 'This book has related issue or request records, so it cannot be deleted safely.',
            ]);
        }

        $stmt = $connection->prepare("DELETE FROM Books WHERE book_id = ?");
        $stmt->bind_param('i', $bookId);
        $stmt->execute();

        if ($stmt->affected_rows <= 0) {
            respond(404, [
                'success' => false,
                'message' => 'Book not found.',
            ]);
        }

        respond(200, [
            'success' => true,
            'message' => 'Book deleted successfully.',
        ]);
    }

    respond(405, [
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
} catch (InvalidArgumentException $error) {
    respond(422, [
        'success' => false,
        'message' => $error->getMessage(),
    ]);
} catch (mysqli_sql_exception $error) {
    respond(500, [
        'success' => false,
        'message' => 'Database query failed.',
        'error' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    respond(500, [
        'success' => false,
        'message' => 'Unexpected server error.',
        'error' => $error->getMessage(),
    ]);
}
