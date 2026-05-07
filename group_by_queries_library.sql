-- GROUP BY SQL queries for Library Management System
-- Run after create_tables_library.sql and insert_sample_data_library.sql

-- 1. Number of books in each category
SELECT
    category,
    COUNT(book_id) AS number_of_books
FROM Books
GROUP BY category
ORDER BY number_of_books DESC;

-- 2. Most borrowed books
SELECT
    b.book_id,
    b.title AS book_title,
    COUNT(ib.issue_id) AS times_borrowed
FROM Books b
JOIN Issue_Books ib
    ON b.book_id = ib.book_id
GROUP BY b.book_id, b.title
ORDER BY times_borrowed DESC, b.title;

-- 3. Number of books issued by each member
SELECT
    m.member_id,
    m.member_code,
    m.full_name AS member_name,
    COUNT(ib.issue_id) AS total_books_issued
FROM Members m
JOIN Issue_Books ib
    ON m.member_id = ib.member_id
GROUP BY m.member_id, m.member_code, m.full_name
ORDER BY total_books_issued DESC, m.full_name;

-- Extra: Number of currently issued books by each member
SELECT
    m.member_id,
    m.member_code,
    m.full_name AS member_name,
    COUNT(ib.issue_id) AS currently_issued_books
FROM Members m
JOIN Issue_Books ib
    ON m.member_id = ib.member_id
WHERE ib.issue_status = 'ISSUED'
GROUP BY m.member_id, m.member_code, m.full_name
ORDER BY currently_issued_books DESC, m.full_name;

