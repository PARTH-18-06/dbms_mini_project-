-- SQL subqueries for Library Management System
-- Run after create_tables_library.sql and insert_sample_data_library.sql

-- Optional setup for "Most expensive book"
-- Run this only if your Books table does not already have a book_price column.
ALTER TABLE Books
ADD COLUMN book_price DECIMAL(8,2);

UPDATE Books SET book_price = 750.00 WHERE book_id = 1;
UPDATE Books SET book_price = 820.00 WHERE book_id = 2;
UPDATE Books SET book_price = 690.00 WHERE book_id = 3;
UPDATE Books SET book_price = 950.00 WHERE book_id = 4;
UPDATE Books SET book_price = 480.00 WHERE book_id = 5;
UPDATE Books SET book_price = 710.00 WHERE book_id = 6;
UPDATE Books SET book_price = 1250.00 WHERE book_id = 7;
UPDATE Books SET book_price = 890.00 WHERE book_id = 8;
UPDATE Books SET book_price = 560.00 WHERE book_id = 9;
UPDATE Books SET book_price = 640.00 WHERE book_id = 10;
UPDATE Books SET book_price = 780.00 WHERE book_id = 11;
UPDATE Books SET book_price = 860.00 WHERE book_id = 12;
UPDATE Books SET book_price = 520.00 WHERE book_id = 13;
UPDATE Books SET book_price = 1350.00 WHERE book_id = 14;
UPDATE Books SET book_price = 990.00 WHERE book_id = 15;
UPDATE Books SET book_price = 1180.00 WHERE book_id = 16;
UPDATE Books SET book_price = 870.00 WHERE book_id = 17;
UPDATE Books SET book_price = 620.00 WHERE book_id = 18;
UPDATE Books SET book_price = 540.00 WHERE book_id = 19;
UPDATE Books SET book_price = 780.00 WHERE book_id = 20;
UPDATE Books SET book_price = 920.00 WHERE book_id = 21;
UPDATE Books SET book_price = 430.00 WHERE book_id = 22;
UPDATE Books SET book_price = 1020.00 WHERE book_id = 23;
UPDATE Books SET book_price = 980.00 WHERE book_id = 24;
UPDATE Books SET book_price = 760.00 WHERE book_id = 25;
UPDATE Books SET book_price = 510.00 WHERE book_id = 26;
UPDATE Books SET book_price = 830.00 WHERE book_id = 27;
UPDATE Books SET book_price = 880.00 WHERE book_id = 28;
UPDATE Books SET book_price = 700.00 WHERE book_id = 29;
UPDATE Books SET book_price = 1100.00 WHERE book_id = 30;
UPDATE Books SET book_price = 1240.00 WHERE book_id = 31;
UPDATE Books SET book_price = 680.00 WHERE book_id = 32;
UPDATE Books SET book_price = 1150.00 WHERE book_id = 33;
UPDATE Books SET book_price = 590.00 WHERE book_id = 34;
UPDATE Books SET book_price = 840.00 WHERE book_id = 35;
UPDATE Books SET book_price = 730.00 WHERE book_id = 36;
UPDATE Books SET book_price = 1220.00 WHERE book_id = 37;
UPDATE Books SET book_price = 650.00 WHERE book_id = 38;
UPDATE Books SET book_price = 720.00 WHERE book_id = 39;
UPDATE Books SET book_price = 790.00 WHERE book_id = 40;
UPDATE Books SET book_price = 900.00 WHERE book_id = 41;
UPDATE Books SET book_price = 960.00 WHERE book_id = 42;
UPDATE Books SET book_price = 1080.00 WHERE book_id = 43;
UPDATE Books SET book_price = 1120.00 WHERE book_id = 44;
UPDATE Books SET book_price = 850.00 WHERE book_id = 45;
UPDATE Books SET book_price = 740.00 WHERE book_id = 46;
UPDATE Books SET book_price = 1050.00 WHERE book_id = 47;
UPDATE Books SET book_price = 690.00 WHERE book_id = 48;
UPDATE Books SET book_price = 1010.00 WHERE book_id = 49;
UPDATE Books SET book_price = 930.00 WHERE book_id = 50;

-- 1. Most expensive book
SELECT
    book_id,
    title,
    category,
    publisher,
    book_price
FROM Books
WHERE book_price = (
    SELECT MAX(book_price)
    FROM Books
);

-- 2. Members with maximum issued books
SELECT
    m.member_id,
    m.member_code,
    m.full_name,
    m.department,
    COUNT(ib.issue_id) AS total_issued_books
FROM Members m
JOIN Issue_Books ib
    ON m.member_id = ib.member_id
GROUP BY m.member_id, m.member_code, m.full_name, m.department
HAVING COUNT(ib.issue_id) = (
    SELECT MAX(issue_count)
    FROM (
        SELECT COUNT(issue_id) AS issue_count
        FROM Issue_Books
        GROUP BY member_id
    ) AS member_issue_counts
);

-- 3. Books never issued
SELECT
    book_id,
    title,
    category,
    publisher,
    available_copies
FROM Books
WHERE book_id NOT IN (
    SELECT book_id
    FROM Issue_Books
);

-- Alternative query for books never issued using NOT EXISTS
SELECT
    b.book_id,
    b.title,
    b.category,
    b.publisher,
    b.available_copies
FROM Books b
WHERE NOT EXISTS (
    SELECT 1
    FROM Issue_Books ib
    WHERE ib.book_id = b.book_id
);

