-- Complete CREATE TABLE queries for Library Management System
-- DBMS: MySQL

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
    CONSTRAINT fk_books_authors
        FOREIGN KEY (author_id)
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

    CONSTRAINT fk_issue_books_book
        FOREIGN KEY (book_id)
        REFERENCES Books(book_id),

    CONSTRAINT fk_issue_books_member
        FOREIGN KEY (member_id)
        REFERENCES Members(member_id),

    CONSTRAINT fk_issue_books_librarian
        FOREIGN KEY (librarian_id)
        REFERENCES Librarians(librarian_id)
);

CREATE TABLE Book_Requests (
    request_id INT AUTO_INCREMENT,
    member_id INT NOT NULL,
    book_id INT,
    requested_title VARCHAR(150) NOT NULL,
    requested_author VARCHAR(100),
    requested_isbn VARCHAR(20),
    category VARCHAR(80),
    request_notes VARCHAR(255),
    request_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL,
    approved_date DATE,
    purchase_date DATE,

    CONSTRAINT pk_book_requests PRIMARY KEY (request_id),

    CONSTRAINT fk_book_requests_member
        FOREIGN KEY (member_id)
        REFERENCES Members(member_id),

    CONSTRAINT fk_book_requests_book
        FOREIGN KEY (book_id)
        REFERENCES Books(book_id)
);

