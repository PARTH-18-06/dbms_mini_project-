-- Library Management System Database Schema
-- DBMS: MySQL

DROP DATABASE IF EXISTS library_management;
CREATE DATABASE library_management;
USE library_management;

-- 1. Authors table
CREATE TABLE Authors (
    author_id INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(100) NOT NULL,
    country VARCHAR(60),
    email VARCHAR(100) UNIQUE
);

-- 2. Books table
CREATE TABLE Books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    author_id INT NOT NULL,
    category VARCHAR(80),
    publisher VARCHAR(100),
    publication_year YEAR,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_no VARCHAR(20),

    CONSTRAINT fk_books_authors
        FOREIGN KEY (author_id)
        REFERENCES Authors(author_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_book_copies
        CHECK (
            total_copies >= 0
            AND available_copies >= 0
            AND available_copies <= total_copies
        )
);

-- 3. Members table
CREATE TABLE Members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    member_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(80),
    semester INT,
    phone VARCHAR(15),
    email VARCHAR(100) UNIQUE,
    join_date DATE NOT NULL,
    status ENUM('ACTIVE', 'BLOCKED') NOT NULL DEFAULT 'ACTIVE'
);

-- 4. Librarians table
CREATE TABLE Librarians (
    librarian_id INT AUTO_INCREMENT PRIMARY KEY,
    librarian_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100) UNIQUE,
    hire_date DATE,
    shift_time VARCHAR(30)
);

-- 5. Issue_Books table
CREATE TABLE Issue_Books (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    member_id INT NOT NULL,
    librarian_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    fine_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    issue_status ENUM('ISSUED', 'RETURNED', 'OVERDUE') NOT NULL DEFAULT 'ISSUED',

    CONSTRAINT fk_issue_books_book
        FOREIGN KEY (book_id)
        REFERENCES Books(book_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_issue_books_member
        FOREIGN KEY (member_id)
        REFERENCES Members(member_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_issue_books_librarian
        FOREIGN KEY (librarian_id)
        REFERENCES Librarians(librarian_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_issue_dates
        CHECK (due_date >= issue_date),

    CONSTRAINT chk_fine_amount
        CHECK (fine_amount >= 0)
);

-- Useful indexes for searching and reports
CREATE INDEX idx_books_title ON Books(title);
CREATE INDEX idx_books_author_id ON Books(author_id);
CREATE INDEX idx_issue_books_member_id ON Issue_Books(member_id);
CREATE INDEX idx_issue_books_book_id ON Issue_Books(book_id);
CREATE INDEX idx_issue_books_status ON Issue_Books(issue_status);

