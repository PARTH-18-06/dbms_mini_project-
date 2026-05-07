-- Stored procedures for Library Management System
-- DBMS: MySQL

DELIMITER $$

-- 1. Display all books
CREATE PROCEDURE sp_display_all_books()
BEGIN
    SELECT
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
    JOIN Authors a
        ON b.author_id = a.author_id
    ORDER BY b.title;
END$$

-- 2. Search books by category
CREATE PROCEDURE sp_search_books_by_category(
    IN p_category VARCHAR(80)
)
BEGIN
    SELECT
        b.book_id,
        b.isbn,
        b.title,
        a.author_name,
        b.category,
        b.publisher,
        b.available_copies,
        b.shelf_no
    FROM Books b
    JOIN Authors a
        ON b.author_id = a.author_id
    WHERE b.category = p_category
    ORDER BY b.title;
END$$

-- 3. Show issued books
CREATE PROCEDURE sp_show_issued_books()
BEGIN
    SELECT
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
    JOIN Books b
        ON ib.book_id = b.book_id
    JOIN Members m
        ON ib.member_id = m.member_id
    JOIN Librarians l
        ON ib.librarian_id = l.librarian_id
    ORDER BY ib.issue_date DESC;
END$$

DELIMITER ;

-- Example calls:
-- CALL sp_display_all_books();
-- CALL sp_search_books_by_category('Programming');
-- CALL sp_show_issued_books();

