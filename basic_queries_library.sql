-- Basic SQL queries for Library Management System
-- Run after create_tables_library.sql and insert_sample_data_library.sql

-- 1. Display all books
SELECT
    book_id,
    isbn,
    title,
    author_id,
    category,
    publisher,
    publication_date,
    total_copies,
    available_copies,
    shelf_no
FROM Books;

-- 2. Display available books
SELECT
    book_id,
    title,
    category,
    publisher,
    available_copies
FROM Books
WHERE available_copies > 0;

-- 3. Search books by category
SELECT
    book_id,
    title,
    category,
    publisher,
    available_copies
FROM Books
WHERE category = 'Programming';

-- 4. Search books by author
SELECT
    b.book_id,
    b.title,
    a.author_name,
    b.category,
    b.publisher,
    b.available_copies
FROM Books b
JOIN Authors a
    ON b.author_id = a.author_id
WHERE a.author_name = 'E. Balagurusamy';

-- 5. Count total books
SELECT
    COUNT(*) AS total_books
FROM Books;

-- 6. Count total members
SELECT
    COUNT(*) AS total_members
FROM Members;

