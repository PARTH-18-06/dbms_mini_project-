# Library Management System Mini Project Using SQL

## 1. Introduction

The Library Management System is a database project designed to manage the daily operations of a college library. It stores information about authors, books, members, librarians, and issued books. The system helps track book availability, book issuing, returns, fines, and member borrowing history.

This project uses **MySQL** and demonstrates important DBMS concepts such as primary keys, foreign keys, joins, grouping, subqueries, views, stored procedures, and triggers.

## 2. Objectives

The main objectives of this project are:

- To store and manage library book details.
- To maintain author, member, and librarian records.
- To track issued and returned books.
- To check available book copies.
- To generate useful reports using SQL queries.
- To apply primary key and foreign key constraints.
- To use advanced SQL features like views, stored procedures, and triggers.

## 3. Software Requirements

| Requirement | Description |
|---|---|
| Database | MySQL |
| Tool | MySQL Workbench / phpMyAdmin / Command Line |
| Operating System | Windows / Linux / macOS |
| Language | SQL |

## 4. Database Design

Database name:

```sql
library_management
```

The database contains five main tables:

1. `Authors`
2. `Books`
3. `Members`
4. `Librarians`
5. `Issue_Books`

### ER Diagram Explanation

```text
Authors     1 ---- many Books
Books       1 ---- many Issue_Books
Members     1 ---- many Issue_Books
Librarians  1 ---- many Issue_Books
```

The `Issue_Books` table works as the transaction table. It connects books, members, and librarians.

## 5. Table Description

### Authors Table

Stores author details.

| Column | Data Type | Constraint |
|---|---|---|
| `author_id` | INT | Primary Key |
| `author_name` | VARCHAR(100) | Not Null |
| `country` | VARCHAR(60) |  |
| `email` | VARCHAR(100) | Unique |

### Books Table

Stores book details.

| Column | Data Type | Constraint |
|---|---|---|
| `book_id` | INT | Primary Key |
| `isbn` | VARCHAR(20) | Unique, Not Null |
| `title` | VARCHAR(150) | Not Null |
| `author_id` | INT | Foreign Key |
| `category` | VARCHAR(80) |  |
| `publisher` | VARCHAR(100) |  |
| `publication_date` | DATE |  |
| `total_copies` | INT | Not Null |
| `available_copies` | INT | Not Null |
| `shelf_no` | VARCHAR(20) |  |

### Members Table

Stores library member details.

| Column | Data Type | Constraint |
|---|---|---|
| `member_id` | INT | Primary Key |
| `member_code` | VARCHAR(20) | Unique, Not Null |
| `full_name` | VARCHAR(100) | Not Null |
| `department` | VARCHAR(80) |  |
| `semester` | INT |  |
| `phone` | VARCHAR(15) |  |
| `email` | VARCHAR(100) | Unique |
| `join_date` | DATE | Not Null |
| `status` | VARCHAR(20) | Not Null |

### Librarians Table

Stores librarian details.

| Column | Data Type | Constraint |
|---|---|---|
| `librarian_id` | INT | Primary Key |
| `librarian_name` | VARCHAR(100) | Not Null |
| `phone` | VARCHAR(15) |  |
| `email` | VARCHAR(100) | Unique |
| `hire_date` | DATE |  |
| `shift_time` | VARCHAR(30) |  |

### Issue_Books Table

Stores book issue and return records.

| Column | Data Type | Constraint |
|---|---|---|
| `issue_id` | INT | Primary Key |
| `book_id` | INT | Foreign Key |
| `member_id` | INT | Foreign Key |
| `librarian_id` | INT | Foreign Key |
| `issue_date` | DATE | Not Null |
| `due_date` | DATE | Not Null |
| `return_date` | DATE |  |
| `fine_amount` | DECIMAL(8,2) |  |
| `issue_status` | VARCHAR(20) | Not Null |

## 6. SQL Queries

### Create Tables

```sql
CREATE TABLE Authors (
    author_id INT AUTO_INCREMENT,
    author_name VARCHAR(100) NOT NULL,
    country VARCHAR(60),
    email VARCHAR(100),
    CONSTRAINT pk_authors PRIMARY KEY (author_id),
    CONSTRAINT uq_authors_email UNIQUE (email)
);

CREATE TABLE Books (
    book_id INT AUTO_INCREMENT,
    isbn VARCHAR(20) NOT NULL,
    title VARCHAR(150) NOT NULL,
    author_id INT NOT NULL,
    category VARCHAR(80),
    publisher VARCHAR(100),
    publication_date DATE,
    total_copies INT NOT NULL,
    available_copies INT NOT NULL,
    shelf_no VARCHAR(20),
    CONSTRAINT pk_books PRIMARY KEY (book_id),
    CONSTRAINT uq_books_isbn UNIQUE (isbn),
    CONSTRAINT fk_books_authors FOREIGN KEY (author_id)
        REFERENCES Authors(author_id)
);

CREATE TABLE Members (
    member_id INT AUTO_INCREMENT,
    member_code VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(80),
    semester INT,
    phone VARCHAR(15),
    email VARCHAR(100),
    join_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    CONSTRAINT pk_members PRIMARY KEY (member_id),
    CONSTRAINT uq_members_code UNIQUE (member_code),
    CONSTRAINT uq_members_email UNIQUE (email)
);

CREATE TABLE Librarians (
    librarian_id INT AUTO_INCREMENT,
    librarian_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    hire_date DATE,
    shift_time VARCHAR(30),
    CONSTRAINT pk_librarians PRIMARY KEY (librarian_id),
    CONSTRAINT uq_librarians_email UNIQUE (email)
);

CREATE TABLE Issue_Books (
    issue_id INT AUTO_INCREMENT,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    librarian_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    fine_amount DECIMAL(8,2),
    issue_status VARCHAR(20) NOT NULL,
    CONSTRAINT pk_issue_books PRIMARY KEY (issue_id),
    CONSTRAINT fk_issue_books_book FOREIGN KEY (book_id)
        REFERENCES Books(book_id),
    CONSTRAINT fk_issue_books_member FOREIGN KEY (member_id)
        REFERENCES Members(member_id),
    CONSTRAINT fk_issue_books_librarian FOREIGN KEY (librarian_id)
        REFERENCES Librarians(librarian_id)
);
```

### Basic Queries

Display all books:

```sql
SELECT * FROM Books;
```

Display available books:

```sql
SELECT book_id, title, category, available_copies
FROM Books
WHERE available_copies > 0;
```

Search books by category:

```sql
SELECT book_id, title, category, publisher
FROM Books
WHERE category = 'Programming';
```

Count total books:

```sql
SELECT COUNT(*) AS total_books
FROM Books;
```

Count total members:

```sql
SELECT COUNT(*) AS total_members
FROM Members;
```

### Join Queries

Issued books with member names:

```sql
SELECT
    ib.issue_id,
    b.title AS book_title,
    m.full_name AS member_name,
    ib.issue_date,
    ib.due_date,
    ib.issue_status
FROM Issue_Books ib
JOIN Books b ON ib.book_id = b.book_id
JOIN Members m ON ib.member_id = m.member_id;
```

Books with author names:

```sql
SELECT
    b.book_id,
    b.title,
    a.author_name,
    b.category,
    b.publisher
FROM Books b
JOIN Authors a ON b.author_id = a.author_id;
```

Librarian handling issued books:

```sql
SELECT
    ib.issue_id,
    b.title AS book_title,
    m.full_name AS member_name,
    l.librarian_name,
    ib.issue_date,
    ib.issue_status
FROM Issue_Books ib
JOIN Books b ON ib.book_id = b.book_id
JOIN Members m ON ib.member_id = m.member_id
JOIN Librarians l ON ib.librarian_id = l.librarian_id;
```

### Group By Queries

Number of books in each category:

```sql
SELECT category, COUNT(book_id) AS number_of_books
FROM Books
GROUP BY category;
```

Most borrowed books:

```sql
SELECT
    b.title,
    COUNT(ib.issue_id) AS times_borrowed
FROM Books b
JOIN Issue_Books ib ON b.book_id = ib.book_id
GROUP BY b.book_id, b.title
ORDER BY times_borrowed DESC;
```

Number of books issued by each member:

```sql
SELECT
    m.full_name,
    COUNT(ib.issue_id) AS total_books_issued
FROM Members m
JOIN Issue_Books ib ON m.member_id = ib.member_id
GROUP BY m.member_id, m.full_name;
```

### Subqueries

Members with maximum issued books:

```sql
SELECT
    m.member_id,
    m.full_name,
    COUNT(ib.issue_id) AS total_issued_books
FROM Members m
JOIN Issue_Books ib ON m.member_id = ib.member_id
GROUP BY m.member_id, m.full_name
HAVING COUNT(ib.issue_id) = (
    SELECT MAX(issue_count)
    FROM (
        SELECT COUNT(issue_id) AS issue_count
        FROM Issue_Books
        GROUP BY member_id
    ) AS member_issue_counts
);
```

Books never issued:

```sql
SELECT book_id, title, category
FROM Books
WHERE book_id NOT IN (
    SELECT book_id
    FROM Issue_Books
);
```

### View

```sql
CREATE OR REPLACE VIEW view_book_details AS
SELECT
    b.book_id,
    b.title,
    a.author_name,
    b.category,
    b.publisher,
    b.available_copies
FROM Books b
JOIN Authors a ON b.author_id = a.author_id;
```

### Stored Procedures

```sql
DELIMITER $$

CREATE PROCEDURE sp_display_all_books()
BEGIN
    SELECT
        b.book_id,
        b.title,
        a.author_name,
        b.category,
        b.available_copies
    FROM Books b
    JOIN Authors a ON b.author_id = a.author_id;
END$$

CREATE PROCEDURE sp_search_books_by_category(
    IN p_category VARCHAR(80)
)
BEGIN
    SELECT
        book_id,
        title,
        category,
        publisher,
        available_copies
    FROM Books
    WHERE category = p_category;
END$$

CREATE PROCEDURE sp_show_issued_books()
BEGIN
    SELECT
        ib.issue_id,
        b.title AS book_title,
        m.full_name AS member_name,
        ib.issue_date,
        ib.due_date,
        ib.issue_status
    FROM Issue_Books ib
    JOIN Books b ON ib.book_id = b.book_id
    JOIN Members m ON ib.member_id = m.member_id;
END$$

DELIMITER ;
```

### Trigger

Automatically reduce available book copies when a book is issued:

```sql
DELIMITER $$

CREATE TRIGGER reduce_available_copies_after_issue
AFTER INSERT ON Issue_Books
FOR EACH ROW
BEGIN
    UPDATE Books
    SET available_copies = available_copies - 1
    WHERE book_id = NEW.book_id
      AND available_copies > 0;
END$$

DELIMITER ;
```

## 7. Outputs

### Output 1: Display All Books

| book_id | title | category | available_copies |
|---:|---|---|---:|
| 1 | Fundamentals of Database Systems | Database | 4 |
| 2 | Database System Concepts | Database | 3 |
| 3 | Computer Networks | Networking | 2 |
| 4 | Java: The Complete Reference | Programming | 4 |

### Output 2: Available Books

| book_id | title | available_copies |
|---:|---|---:|
| 1 | Fundamentals of Database Systems | 4 |
| 2 | Database System Concepts | 3 |
| 5 | Programming in ANSI C | 6 |

### Output 3: Books With Author Names

| book_id | title | author_name | category |
|---:|---|---|---|
| 1 | Fundamentals of Database Systems | Ramez Elmasri | Database |
| 2 | Database System Concepts | Abraham Silberschatz | Database |
| 4 | Java: The Complete Reference | Herbert Schildt | Programming |

### Output 4: Number of Books in Each Category

| category | number_of_books |
|---|---:|
| Programming | 18 |
| Networking | 5 |
| Software Engineering | 6 |
| Database | 3 |

### Output 5: Issued Books With Member Names

| issue_id | book_title | member_name | issue_date | issue_status |
|---:|---|---|---|---|
| 1 | Fundamentals of Database Systems | Aarav Sharma | 2026-01-02 | RETURNED |
| 2 | Database System Concepts | Meera Nair | 2026-01-03 | RETURNED |
| 91 | Agile Software Development | Aarav Sharma | 2026-04-02 | OVERDUE |

## 8. Advantages

- Reduces manual library record keeping.
- Makes book searching faster and easier.
- Tracks issued, returned, and overdue books.
- Maintains accurate member and librarian records.
- Reduces data duplication using relational design.
- Improves data accuracy using primary and foreign keys.
- Supports useful reports using SQL queries.
- Triggers automate stock update when books are issued.
- Stored procedures make repeated operations easier.

## 9. Conclusion

The Library Management System mini project successfully demonstrates how SQL can be used to design and manage a real-world database system. It includes proper table design, primary and foreign key constraints, sample queries, joins, grouping, subqueries, views, stored procedures, and triggers.

This project is useful for understanding database design, relationship modeling, and practical SQL operations in a college library environment.

