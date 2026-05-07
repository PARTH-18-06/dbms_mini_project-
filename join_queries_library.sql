-- SQL JOIN queries for Library Management System
-- Run after create_tables_library.sql and insert_sample_data_library.sql

-- 1. Issued books with member names
SELECT
    ib.issue_id,
    b.title AS book_title,
    m.full_name AS member_name,
    ib.issue_date,
    ib.due_date,
    ib.return_date,
    ib.issue_status
FROM Issue_Books ib
JOIN Books b
    ON ib.book_id = b.book_id
JOIN Members m
    ON ib.member_id = m.member_id
ORDER BY ib.issue_date;

-- 2. Books with author names
SELECT
    b.book_id,
    b.title AS book_title,
    a.author_name,
    b.category,
    b.publisher,
    b.available_copies
FROM Books b
JOIN Authors a
    ON b.author_id = a.author_id
ORDER BY b.title;

-- 3. Librarian handling issued books
SELECT
    ib.issue_id,
    b.title AS book_title,
    m.full_name AS member_name,
    l.librarian_name,
    ib.issue_date,
    ib.due_date,
    ib.issue_status
FROM Issue_Books ib
JOIN Books b
    ON ib.book_id = b.book_id
JOIN Members m
    ON ib.member_id = m.member_id
JOIN Librarians l
    ON ib.librarian_id = l.librarian_id
ORDER BY ib.issue_id;

-- 4. Members who borrowed books
SELECT DISTINCT
    m.member_id,
    m.member_code,
    m.full_name AS member_name,
    m.department,
    m.semester
FROM Members m
JOIN Issue_Books ib
    ON m.member_id = ib.member_id
ORDER BY m.member_id;

-- Extra: Members with borrowed book details
SELECT
    m.member_code,
    m.full_name AS member_name,
    b.title AS book_title,
    ib.issue_date,
    ib.due_date,
    ib.issue_status
FROM Members m
JOIN Issue_Books ib
    ON m.member_id = ib.member_id
JOIN Books b
    ON ib.book_id = b.book_id
ORDER BY m.full_name, ib.issue_date;

