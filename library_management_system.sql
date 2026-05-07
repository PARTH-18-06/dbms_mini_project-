-- Library Management System Mini Project
-- DBMS: MySQL 8.x

DROP DATABASE IF EXISTS library_management_system;
CREATE DATABASE library_management_system;
USE library_management_system;

-- =========================
-- 1. DATABASE DESIGN
-- =========================

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255)
);

CREATE TABLE authors (
    author_id INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(120) NOT NULL,
    country VARCHAR(80)
);

CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    category_id INT NOT NULL,
    author_id INT NOT NULL,
    publisher VARCHAR(120),
    edition VARCHAR(30),
    publish_year YEAR,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_location VARCHAR(30),
    CONSTRAINT fk_books_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT fk_books_author
        FOREIGN KEY (author_id) REFERENCES authors(author_id),
    CONSTRAINT chk_books_copies
        CHECK (total_copies >= 0 AND available_copies >= 0 AND available_copies <= total_copies)
);

CREATE TABLE members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    member_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL,
    semester INT,
    phone VARCHAR(15),
    email VARCHAR(120) UNIQUE,
    join_date DATE NOT NULL,
    status ENUM('ACTIVE', 'BLOCKED') NOT NULL DEFAULT 'ACTIVE'
);

CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_name VARCHAR(120) NOT NULL,
    role VARCHAR(60) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(120) UNIQUE
);

CREATE TABLE loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    staff_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    fine_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status ENUM('ISSUED', 'RETURNED', 'OVERDUE') NOT NULL DEFAULT 'ISSUED',
    CONSTRAINT fk_loans_book
        FOREIGN KEY (book_id) REFERENCES books(book_id),
    CONSTRAINT fk_loans_member
        FOREIGN KEY (member_id) REFERENCES members(member_id),
    CONSTRAINT fk_loans_staff
        FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
);

CREATE INDEX idx_books_title ON books(title);
CREATE INDEX idx_loans_member_status ON loans(member_id, status);
CREATE INDEX idx_loans_due_date ON loans(due_date);

-- =========================
-- 2. SAMPLE DATASET
-- =========================

INSERT INTO categories (category_name, description) VALUES
('Database', 'Database systems, SQL and data modeling'),
('Programming', 'Programming languages and software development'),
('Networking', 'Computer networks and communication'),
('Artificial Intelligence', 'AI, machine learning and intelligent systems'),
('Web Technology', 'Frontend, backend and web application development'),
('Operating Systems', 'OS concepts and system programming'),
('Mathematics', 'Discrete mathematics, statistics and applied math'),
('Cyber Security', 'Security, cryptography and ethical hacking');

INSERT INTO authors (author_name, country) VALUES
('Ramez Elmasri', 'USA'),
('Abraham Silberschatz', 'USA'),
('Andrew S. Tanenbaum', 'Netherlands'),
('Herbert Schildt', 'USA'),
('E. Balagurusamy', 'India'),
('James Kurose', 'USA'),
('Stuart Russell', 'UK'),
('Peter Norvig', 'USA'),
('Jon Duckett', 'UK'),
('W. Richard Stevens', 'USA'),
('William Stallings', 'USA'),
('Thomas H. Cormen', 'USA');

INSERT INTO books
(isbn, title, category_id, author_id, publisher, edition, publish_year, total_copies, available_copies, shelf_location)
VALUES
('9780133970777', 'Fundamentals of Database Systems', 1, 1, 'Pearson', '7th', 2016, 6, 6, 'DB-A1'),
('9780078022159', 'Database System Concepts', 1, 2, 'McGraw Hill', '7th', 2019, 5, 5, 'DB-A2'),
('9780133594140', 'Computer Networks', 3, 3, 'Pearson', '5th', 2011, 4, 4, 'NW-B1'),
('9780071809252', 'Java: The Complete Reference', 2, 4, 'McGraw Hill', '11th', 2018, 5, 5, 'PR-C1'),
('9789353165130', 'Programming in ANSI C', 2, 5, 'McGraw Hill', '8th', 2019, 8, 8, 'PR-C2'),
('9789332585492', 'Computer Networking: A Top-Down Approach', 3, 6, 'Pearson', '7th', 2017, 4, 4, 'NW-B2'),
('9780134610993', 'Artificial Intelligence: A Modern Approach', 4, 7, 'Pearson', '4th', 2021, 3, 3, 'AI-D1'),
('9780321125217', 'AI Programming with Python', 4, 8, 'Addison Wesley', '2nd', 2020, 3, 3, 'AI-D2'),
('9781118008188', 'HTML and CSS: Design and Build Websites', 5, 9, 'Wiley', '1st', 2011, 6, 6, 'WEB-E1'),
('9781118531648', 'JavaScript and JQuery', 5, 9, 'Wiley', '1st', 2014, 5, 5, 'WEB-E2'),
('9780134685991', 'Modern Operating Systems', 6, 3, 'Pearson', '4th', 2014, 4, 4, 'OS-F1'),
('9781118093757', 'Operating System Concepts', 6, 2, 'Wiley', '10th', 2018, 5, 5, 'OS-F2'),
('9789332518773', 'Data Structures Using C', 2, 5, 'McGraw Hill', '2nd', 2017, 7, 7, 'PR-C3'),
('9780262033848', 'Introduction to Algorithms', 2, 12, 'MIT Press', '3rd', 2009, 4, 4, 'PR-C4'),
('9780133354690', 'Cryptography and Network Security', 8, 11, 'Pearson', '7th', 2017, 3, 3, 'SEC-G1'),
('9780136006633', 'Computer Security: Principles and Practice', 8, 11, 'Pearson', '4th', 2018, 3, 3, 'SEC-G2'),
('9780321885177', 'TCP/IP Illustrated', 3, 10, 'Addison Wesley', '2nd', 2011, 2, 2, 'NW-B3'),
('9781292025827', 'Discrete Mathematics and Its Applications', 7, 12, 'McGraw Hill', '8th', 2018, 6, 6, 'MATH-H1'),
('9789352604166', 'Probability and Statistics', 7, 5, 'McGraw Hill', '1st', 2016, 5, 5, 'MATH-H2'),
('9780132350884', 'Clean Code', 2, 4, 'Prentice Hall', '1st', 2008, 4, 4, 'PR-C5');

INSERT INTO members
(member_code, full_name, department, semester, phone, email, join_date, status)
VALUES
('STU001', 'Aarav Sharma', 'Computer Science', 5, '9876500011', 'aarav.sharma@college.edu', '2025-07-10', 'ACTIVE'),
('STU002', 'Meera Nair', 'Information Technology', 4, '9876500012', 'meera.nair@college.edu', '2025-07-12', 'ACTIVE'),
('STU003', 'Rohan Gupta', 'Computer Science', 6, '9876500013', 'rohan.gupta@college.edu', '2024-08-01', 'ACTIVE'),
('STU004', 'Sneha Iyer', 'Electronics', 3, '9876500014', 'sneha.iyer@college.edu', '2025-08-05', 'ACTIVE'),
('STU005', 'Kabir Khan', 'Information Technology', 5, '9876500015', 'kabir.khan@college.edu', '2024-07-22', 'ACTIVE'),
('STU006', 'Ananya Rao', 'Computer Science', 2, '9876500016', 'ananya.rao@college.edu', '2025-09-15', 'ACTIVE'),
('STU007', 'Vikram Singh', 'Cyber Security', 6, '9876500017', 'vikram.singh@college.edu', '2024-07-20', 'ACTIVE'),
('STU008', 'Priya Menon', 'Data Science', 4, '9876500018', 'priya.menon@college.edu', '2025-01-10', 'ACTIVE'),
('STU009', 'Nikhil Verma', 'Computer Science', 1, '9876500019', 'nikhil.verma@college.edu', '2025-09-20', 'ACTIVE'),
('STU010', 'Isha Patel', 'Information Technology', 3, '9876500020', 'isha.patel@college.edu', '2024-08-14', 'ACTIVE'),
('STU011', 'Arjun Das', 'Electronics', 5, '9876500021', 'arjun.das@college.edu', '2024-08-16', 'BLOCKED'),
('STU012', 'Kavya Reddy', 'Data Science', 6, '9876500022', 'kavya.reddy@college.edu', '2023-07-11', 'ACTIVE'),
('STU013', 'Dev Malhotra', 'Cyber Security', 4, '9876500023', 'dev.malhotra@college.edu', '2025-06-30', 'ACTIVE'),
('STU014', 'Tanya Bose', 'Computer Science', 3, '9876500024', 'tanya.bose@college.edu', '2025-08-01', 'ACTIVE'),
('STU015', 'Rahul Jain', 'Information Technology', 2, '9876500025', 'rahul.jain@college.edu', '2025-08-03', 'ACTIVE');

INSERT INTO staff (staff_name, role, phone, email) VALUES
('Neha Thomas', 'Librarian', '9876511001', 'neha.thomas@college.edu'),
('Suresh Kumar', 'Assistant Librarian', '9876511002', 'suresh.kumar@college.edu'),
('Fatima Ali', 'Library Clerk', '9876511003', 'fatima.ali@college.edu'),
('Joseph Mathew', 'Library Clerk', '9876511004', 'joseph.mathew@college.edu'),
('Ritika Sinha', 'Librarian', '9876511005', 'ritika.sinha@college.edu');

INSERT INTO loans
(book_id, member_id, staff_id, issue_date, due_date, return_date, fine_amount, status)
VALUES
(1, 1, 1, '2026-01-05', '2026-01-19', '2026-01-18', 0.00, 'RETURNED'),
(4, 1, 2, '2026-02-02', '2026-02-16', '2026-02-20', 20.00, 'RETURNED'),
(7, 2, 1, '2026-02-10', '2026-02-24', '2026-02-23', 0.00, 'RETURNED'),
(2, 3, 3, '2026-03-01', '2026-03-15', '2026-03-18', 15.00, 'RETURNED'),
(10, 4, 2, '2026-03-05', '2026-03-19', '2026-03-19', 0.00, 'RETURNED'),
(3, 5, 1, '2026-03-10', '2026-03-24', '2026-03-28', 20.00, 'RETURNED'),
(11, 6, 4, '2026-03-12', '2026-03-26', '2026-03-25', 0.00, 'RETURNED'),
(15, 7, 5, '2026-03-15', '2026-03-29', '2026-04-02', 20.00, 'RETURNED'),
(18, 8, 1, '2026-03-18', '2026-04-01', '2026-04-01', 0.00, 'RETURNED'),
(5, 9, 2, '2026-03-20', '2026-04-03', '2026-04-08', 25.00, 'RETURNED'),
(13, 10, 3, '2026-03-25', '2026-04-08', '2026-04-05', 0.00, 'RETURNED'),
(14, 12, 4, '2026-03-28', '2026-04-11', '2026-04-16', 25.00, 'RETURNED'),
(16, 13, 5, '2026-04-01', '2026-04-15', '2026-04-15', 0.00, 'RETURNED'),
(8, 14, 1, '2026-04-02', '2026-04-16', NULL, 0.00, 'OVERDUE'),
(9, 15, 2, '2026-04-04', '2026-04-18', NULL, 0.00, 'OVERDUE'),
(1, 3, 1, '2026-04-05', '2026-04-19', NULL, 0.00, 'OVERDUE'),
(6, 5, 3, '2026-04-08', '2026-04-22', NULL, 0.00, 'OVERDUE'),
(12, 8, 4, '2026-04-10', '2026-04-24', NULL, 0.00, 'OVERDUE'),
(20, 2, 5, '2026-04-18', '2026-05-02', NULL, 0.00, 'OVERDUE'),
(17, 7, 1, '2026-04-22', '2026-05-06', NULL, 0.00, 'OVERDUE'),
(4, 10, 2, '2026-04-25', '2026-05-09', NULL, 0.00, 'ISSUED'),
(2, 12, 3, '2026-04-26', '2026-05-10', NULL, 0.00, 'ISSUED'),
(7, 13, 4, '2026-04-28', '2026-05-12', NULL, 0.00, 'ISSUED'),
(18, 14, 5, '2026-04-30', '2026-05-14', NULL, 0.00, 'ISSUED'),
(10, 15, 1, '2026-05-01', '2026-05-15', NULL, 0.00, 'ISSUED');

-- Recalculate available copies after inserting historical loan data.
UPDATE books b
SET available_copies = total_copies - (
    SELECT COUNT(*)
    FROM loans l
    WHERE l.book_id = b.book_id
      AND l.return_date IS NULL
);

-- =========================
-- 3. VIEWS
-- =========================

CREATE VIEW vw_book_details AS
SELECT
    b.book_id,
    b.title,
    b.isbn,
    c.category_name,
    a.author_name,
    b.publisher,
    b.total_copies,
    b.available_copies,
    b.shelf_location
FROM books b
JOIN categories c ON b.category_id = c.category_id
JOIN authors a ON b.author_id = a.author_id;

CREATE VIEW vw_active_loans AS
SELECT
    l.loan_id,
    m.member_code,
    m.full_name,
    m.department,
    b.title,
    l.issue_date,
    l.due_date,
    DATEDIFF(CURRENT_DATE, l.due_date) AS days_late,
    l.status
FROM loans l
JOIN members m ON l.member_id = m.member_id
JOIN books b ON l.book_id = b.book_id
WHERE l.return_date IS NULL;

CREATE VIEW vw_member_borrowing_summary AS
SELECT
    m.member_id,
    m.member_code,
    m.full_name,
    m.department,
    COUNT(l.loan_id) AS total_books_borrowed,
    SUM(CASE WHEN l.return_date IS NULL THEN 1 ELSE 0 END) AS currently_borrowed,
    COALESCE(SUM(l.fine_amount), 0) AS total_fine_paid
FROM members m
LEFT JOIN loans l ON m.member_id = l.member_id
GROUP BY m.member_id, m.member_code, m.full_name, m.department;

-- =========================
-- 4. TRIGGERS
-- =========================

DELIMITER $$

CREATE TRIGGER trg_loans_before_insert
BEFORE INSERT ON loans
FOR EACH ROW
BEGIN
    IF NEW.due_date IS NULL THEN
        SET NEW.due_date = DATE_ADD(NEW.issue_date, INTERVAL 14 DAY);
    END IF;

    IF NEW.return_date IS NULL THEN
        IF (SELECT available_copies FROM books WHERE book_id = NEW.book_id) <= 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Book is not available for issue';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM loans
            WHERE book_id = NEW.book_id
              AND member_id = NEW.member_id
              AND return_date IS NULL
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Member already has an active loan for this book';
        END IF;

        IF NEW.due_date < CURRENT_DATE THEN
            SET NEW.status = 'OVERDUE';
        ELSE
            SET NEW.status = 'ISSUED';
        END IF;
    ELSE
        SET NEW.status = 'RETURNED';
        SET NEW.fine_amount = GREATEST(DATEDIFF(NEW.return_date, NEW.due_date), 0) * 5.00;
    END IF;
END$$

CREATE TRIGGER trg_loans_after_insert
AFTER INSERT ON loans
FOR EACH ROW
BEGIN
    IF NEW.return_date IS NULL THEN
        UPDATE books
        SET available_copies = available_copies - 1
        WHERE book_id = NEW.book_id;
    END IF;
END$$

CREATE TRIGGER trg_loans_before_update
BEFORE UPDATE ON loans
FOR EACH ROW
BEGIN
    IF NEW.return_date IS NOT NULL THEN
        SET NEW.status = 'RETURNED';
        SET NEW.fine_amount = GREATEST(DATEDIFF(NEW.return_date, NEW.due_date), 0) * 5.00;
    ELSEIF NEW.due_date < CURRENT_DATE THEN
        SET NEW.status = 'OVERDUE';
    ELSE
        SET NEW.status = 'ISSUED';
    END IF;
END$$

CREATE TRIGGER trg_loans_after_update
AFTER UPDATE ON loans
FOR EACH ROW
BEGIN
    IF OLD.return_date IS NULL AND NEW.return_date IS NOT NULL THEN
        UPDATE books
        SET available_copies = available_copies + 1
        WHERE book_id = NEW.book_id
          AND available_copies < total_copies;
    END IF;
END$$

DELIMITER ;

-- =========================
-- 5. STORED PROCEDURES
-- =========================

DELIMITER $$

CREATE PROCEDURE sp_issue_book (
    IN p_book_id INT,
    IN p_member_id INT,
    IN p_staff_id INT
)
BEGIN
    DECLARE v_member_status VARCHAR(20);
    DECLARE v_available_copies INT;
    DECLARE v_active_loans INT;
    DECLARE v_staff_count INT;

    SET v_member_status = (
        SELECT status
        FROM members
        WHERE member_id = p_member_id
    );

    IF v_member_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Member does not exist';
    END IF;

    IF v_member_status <> 'ACTIVE' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only active members can borrow books';
    END IF;

    SET v_available_copies = (
        SELECT available_copies
        FROM books
        WHERE book_id = p_book_id
    );

    IF v_available_copies IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Book does not exist';
    END IF;

    IF v_available_copies <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No copies available';
    END IF;

    SELECT COUNT(*)
    INTO v_staff_count
    FROM staff
    WHERE staff_id = p_staff_id;

    IF v_staff_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Staff member does not exist';
    END IF;

    SELECT COUNT(*)
    INTO v_active_loans
    FROM loans
    WHERE member_id = p_member_id
      AND return_date IS NULL;

    IF v_active_loans >= 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Member has reached the maximum active loan limit';
    END IF;

    INSERT INTO loans (book_id, member_id, staff_id, issue_date, due_date)
    VALUES (p_book_id, p_member_id, p_staff_id, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY));

    SELECT 'Book issued successfully' AS message, LAST_INSERT_ID() AS new_loan_id;
END$$

CREATE PROCEDURE sp_return_book (
    IN p_loan_id INT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM loans
        WHERE loan_id = p_loan_id
          AND return_date IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Active loan not found';
    END IF;

    UPDATE loans
    SET return_date = CURRENT_DATE
    WHERE loan_id = p_loan_id;

    SELECT
        loan_id,
        status,
        fine_amount,
        'Book returned successfully' AS message
    FROM loans
    WHERE loan_id = p_loan_id;
END$$

CREATE PROCEDURE sp_member_loan_history (
    IN p_member_id INT
)
BEGIN
    SELECT
        l.loan_id,
        b.title,
        l.issue_date,
        l.due_date,
        l.return_date,
        l.status,
        l.fine_amount
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    WHERE l.member_id = p_member_id
    ORDER BY l.issue_date DESC;
END$$

DELIMITER ;

-- =========================
-- 6. SQL QUERIES FOR REPORT
-- =========================

-- Query 1: Book list with category and author
SELECT title, category_name, author_name, publisher, available_copies
FROM vw_book_details
ORDER BY category_name, title;

-- Query 2: Active loans using joins
SELECT
    al.loan_id,
    al.member_code,
    al.full_name,
    al.department,
    al.title,
    al.issue_date,
    al.due_date,
    al.status
FROM vw_active_loans al
ORDER BY al.due_date;

-- Query 3: Overdue books with calculated late days
SELECT
    member_code,
    full_name,
    title,
    due_date,
    days_late,
    days_late * 5 AS estimated_fine
FROM vw_active_loans
WHERE due_date < CURRENT_DATE
ORDER BY days_late DESC;

-- Query 4: Total books in each category
SELECT
    c.category_name,
    COUNT(b.book_id) AS total_titles,
    SUM(b.total_copies) AS total_copies,
    SUM(b.available_copies) AS available_copies
FROM categories c
LEFT JOIN books b ON c.category_id = b.category_id
GROUP BY c.category_id, c.category_name
ORDER BY total_titles DESC;

-- Query 5: Most borrowed books
SELECT
    b.title,
    a.author_name,
    COUNT(l.loan_id) AS times_borrowed
FROM books b
JOIN authors a ON b.author_id = a.author_id
LEFT JOIN loans l ON b.book_id = l.book_id
GROUP BY b.book_id, b.title, a.author_name
ORDER BY times_borrowed DESC, b.title
LIMIT 5;

-- Query 6: Borrowing count by department
SELECT
    m.department,
    COUNT(l.loan_id) AS total_issues,
    SUM(CASE WHEN l.return_date IS NULL THEN 1 ELSE 0 END) AS active_issues
FROM members m
LEFT JOIN loans l ON m.member_id = l.member_id
GROUP BY m.department
ORDER BY total_issues DESC;

-- Query 7: Fine collection by month
SELECT
    DATE_FORMAT(return_date, '%Y-%m') AS return_month,
    SUM(fine_amount) AS total_fine_collected
FROM loans
WHERE return_date IS NOT NULL
GROUP BY DATE_FORMAT(return_date, '%Y-%m')
ORDER BY return_month;

-- Query 8: Members who have not borrowed any book
SELECT
    m.member_code,
    m.full_name,
    m.department
FROM members m
LEFT JOIN loans l ON m.member_id = l.member_id
WHERE l.loan_id IS NULL;

-- Query 9: Books with low availability
SELECT
    title,
    total_copies,
    available_copies,
    ROUND((available_copies / total_copies) * 100, 2) AS availability_percent
FROM books
WHERE available_copies <= 2
ORDER BY availability_percent ASC;

-- Query 10: Procedure examples
-- CALL sp_issue_book(19, 6, 1);
-- CALL sp_return_book(14);
-- CALL sp_member_loan_history(1);
