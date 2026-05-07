-- Advanced SQL features for Library Management System
-- Includes: Views, Stored Procedures, and Triggers
-- DBMS: MySQL
-- Run after create_tables_library.sql

-- =========================
-- 1. VIEWS
-- =========================

-- View 1: Book details with author name
CREATE OR REPLACE VIEW view_book_details AS
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
    ON b.author_id = a.author_id;

-- View 2: Currently issued books with member and librarian details
CREATE OR REPLACE VIEW view_current_issued_books AS
SELECT
    ib.issue_id,
    b.title AS book_title,
    m.member_code,
    m.full_name AS member_name,
    m.department,
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
WHERE ib.return_date IS NULL;

-- View 3: Member-wise issue summary
CREATE OR REPLACE VIEW view_member_issue_summary AS
SELECT
    m.member_id,
    m.member_code,
    m.full_name,
    m.department,
    COUNT(ib.issue_id) AS total_books_issued,
    SUM(CASE WHEN ib.return_date IS NULL THEN 1 ELSE 0 END) AS currently_issued,
    SUM(CASE WHEN ib.issue_status = 'OVERDUE' THEN 1 ELSE 0 END) AS overdue_books,
    COALESCE(SUM(ib.fine_amount), 0) AS total_fine
FROM Members m
LEFT JOIN Issue_Books ib
    ON m.member_id = ib.member_id
GROUP BY
    m.member_id,
    m.member_code,
    m.full_name,
    m.department;

-- View 4: Category-wise book availability
CREATE OR REPLACE VIEW view_category_availability AS
SELECT
    category,
    COUNT(book_id) AS total_titles,
    SUM(total_copies) AS total_copies,
    SUM(available_copies) AS available_copies
FROM Books
GROUP BY category;

-- =========================
-- 2. STORED PROCEDURES
-- =========================

DELIMITER $$

-- Procedure 1: Issue a book to a member
CREATE PROCEDURE sp_issue_book (
    IN p_book_id INT,
    IN p_member_id INT,
    IN p_librarian_id INT
)
BEGIN
    DECLARE v_available_copies INT;
    DECLARE v_member_status VARCHAR(20);
    DECLARE v_active_issues INT;
    DECLARE v_librarian_count INT;

    SELECT available_copies
    INTO v_available_copies
    FROM Books
    WHERE book_id = p_book_id;

    IF v_available_copies IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Book does not exist';
    END IF;

    IF v_available_copies <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Book is not available';
    END IF;

    SELECT status
    INTO v_member_status
    FROM Members
    WHERE member_id = p_member_id;

    IF v_member_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Member does not exist';
    END IF;

    IF v_member_status <> 'ACTIVE' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Blocked member cannot issue books';
    END IF;

    SELECT COUNT(*)
    INTO v_librarian_count
    FROM Librarians
    WHERE librarian_id = p_librarian_id;

    IF v_librarian_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Librarian does not exist';
    END IF;

    SELECT COUNT(*)
    INTO v_active_issues
    FROM Issue_Books
    WHERE member_id = p_member_id
      AND return_date IS NULL;

    IF v_active_issues >= 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Member already has 3 active issued books';
    END IF;

    INSERT INTO Issue_Books
    (
        book_id,
        member_id,
        librarian_id,
        issue_date,
        due_date,
        return_date,
        fine_amount,
        issue_status
    )
    VALUES
    (
        p_book_id,
        p_member_id,
        p_librarian_id,
        CURRENT_DATE,
        DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY),
        NULL,
        0.00,
        'ISSUED'
    );

    SELECT
        'Book issued successfully' AS message,
        LAST_INSERT_ID() AS issue_id;
END$$

-- Procedure 2: Return an issued book
CREATE PROCEDURE sp_return_book (
    IN p_issue_id INT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM Issue_Books
        WHERE issue_id = p_issue_id
          AND return_date IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Active issue record not found';
    END IF;

    UPDATE Issue_Books
    SET return_date = CURRENT_DATE
    WHERE issue_id = p_issue_id;

    SELECT
        issue_id,
        book_id,
        member_id,
        issue_date,
        due_date,
        return_date,
        fine_amount,
        issue_status,
        'Book returned successfully' AS message
    FROM Issue_Books
    WHERE issue_id = p_issue_id;
END$$

-- Procedure 3: Search books by category
CREATE PROCEDURE sp_search_books_by_category (
    IN p_category VARCHAR(80)
)
BEGIN
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
    WHERE b.category = p_category
    ORDER BY b.title;
END$$

-- Procedure 4: Display issue history of a member
CREATE PROCEDURE sp_member_issue_history (
    IN p_member_id INT
)
BEGIN
    SELECT
        ib.issue_id,
        b.title AS book_title,
        ib.issue_date,
        ib.due_date,
        ib.return_date,
        ib.fine_amount,
        ib.issue_status
    FROM Issue_Books ib
    JOIN Books b
        ON ib.book_id = b.book_id
    WHERE ib.member_id = p_member_id
    ORDER BY ib.issue_date DESC;
END$$

DELIMITER ;

-- =========================
-- 3. TRIGGERS
-- =========================

DELIMITER $$

-- Trigger 1: Validate issue record before inserting
CREATE TRIGGER trg_issue_books_before_insert
BEFORE INSERT ON Issue_Books
FOR EACH ROW
BEGIN
    IF NEW.due_date < NEW.issue_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Due date cannot be before issue date';
    END IF;

    IF NEW.return_date IS NULL THEN
        IF (SELECT available_copies FROM Books WHERE book_id = NEW.book_id) <= 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Book copy is not available';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM Issue_Books
            WHERE book_id = NEW.book_id
              AND member_id = NEW.member_id
              AND return_date IS NULL
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Member already has this book issued';
        END IF;

        IF NEW.due_date < CURRENT_DATE THEN
            SET NEW.issue_status = 'OVERDUE';
        ELSE
            SET NEW.issue_status = 'ISSUED';
        END IF;

        SET NEW.fine_amount = 0.00;
    ELSE
        SET NEW.issue_status = 'RETURNED';
        SET NEW.fine_amount = GREATEST(DATEDIFF(NEW.return_date, NEW.due_date), 0) * 5.00;
    END IF;
END$$

-- Trigger 2: Reduce available copies after issuing a book
CREATE TRIGGER trg_issue_books_after_insert
AFTER INSERT ON Issue_Books
FOR EACH ROW
BEGIN
    IF NEW.return_date IS NULL THEN
        UPDATE Books
        SET available_copies = available_copies - 1
        WHERE book_id = NEW.book_id;
    END IF;
END$$

-- Trigger 3: Calculate fine and status before updating issue record
CREATE TRIGGER trg_issue_books_before_update
BEFORE UPDATE ON Issue_Books
FOR EACH ROW
BEGIN
    IF NEW.due_date < NEW.issue_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Due date cannot be before issue date';
    END IF;

    IF NEW.return_date IS NOT NULL THEN
        SET NEW.issue_status = 'RETURNED';
        SET NEW.fine_amount = GREATEST(DATEDIFF(NEW.return_date, NEW.due_date), 0) * 5.00;
    ELSEIF NEW.due_date < CURRENT_DATE THEN
        SET NEW.issue_status = 'OVERDUE';
        SET NEW.fine_amount = 0.00;
    ELSE
        SET NEW.issue_status = 'ISSUED';
        SET NEW.fine_amount = 0.00;
    END IF;
END$$

-- Trigger 4: Increase available copies after returning a book
CREATE TRIGGER trg_issue_books_after_update
AFTER UPDATE ON Issue_Books
FOR EACH ROW
BEGIN
    IF OLD.return_date IS NULL AND NEW.return_date IS NOT NULL THEN
        UPDATE Books
        SET available_copies = available_copies + 1
        WHERE book_id = NEW.book_id;
    END IF;
END$$

DELIMITER ;

-- =========================
-- 4. EXAMPLE CALLS
-- =========================

-- CALL sp_issue_book(1, 1, 1);
-- CALL sp_return_book(101);
-- CALL sp_search_books_by_category('Programming');
-- CALL sp_member_issue_history(1);

