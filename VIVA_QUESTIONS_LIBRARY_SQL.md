# Viva Questions and Answers

## SQL Mini Project: Library Management System

### 1. What is SQL?

SQL stands for **Structured Query Language**. It is used to create, manage, and retrieve data from relational databases.

### 2. What is a database?

A database is an organized collection of data. In this project, the database stores details of books, authors, members, librarians, and issued books.

### 3. What is DBMS?

DBMS stands for **Database Management System**. It is software used to store, manage, and retrieve data from a database.

Example:

```text
MySQL
Oracle
PostgreSQL
SQL Server
```

### 4. Which DBMS did you use in this project?

I used **MySQL** for this Library Management System project.

### 5. What is the purpose of your project?

The purpose of this project is to manage library records such as books, authors, members, librarians, and book issue details using SQL.

### 6. What are the main tables in your project?

The main tables are:

- `Authors`
- `Books`
- `Members`
- `Librarians`
- `Issue_Books`

### 7. What is a table?

A table is a collection of related data arranged in rows and columns.

Example: The `Books` table stores book details such as title, category, publisher, and available copies.

### 8. What is a row?

A row is a single record in a table.

Example: One book record in the `Books` table is one row.

### 9. What is a column?

A column represents a specific attribute of a table.

Example: `book_id`, `title`, and `category` are columns in the `Books` table.

### 10. What is a primary key?

A primary key is a column that uniquely identifies each record in a table. It cannot contain duplicate or null values.

Example:

```sql
book_id INT PRIMARY KEY
```

### 11. Which primary keys are used in your project?

The primary keys are:

- `author_id` in `Authors`
- `book_id` in `Books`
- `member_id` in `Members`
- `librarian_id` in `Librarians`
- `issue_id` in `Issue_Books`

### 12. What is a foreign key?

A foreign key is a column that creates a relationship between two tables. It refers to the primary key of another table.

### 13. Give an example of a foreign key from your project.

In the `Books` table, `author_id` is a foreign key that refers to `author_id` in the `Authors` table.

```sql
FOREIGN KEY (author_id) REFERENCES Authors(author_id)
```

### 14. Why are foreign keys used?

Foreign keys are used to maintain referential integrity between tables. They prevent invalid data from being inserted.

### 15. What is referential integrity?

Referential integrity means that a foreign key value must match an existing primary key value in the related table.

Example: A book cannot have an `author_id` that does not exist in the `Authors` table.

### 16. What is a constraint?

A constraint is a rule applied to a table column to control the type of data stored in it.

Examples:

- `PRIMARY KEY`
- `FOREIGN KEY`
- `NOT NULL`
- `UNIQUE`

### 17. What is the use of the `Books` table?

The `Books` table stores book details such as book title, ISBN, author, category, publisher, total copies, available copies, and shelf number.

### 18. What is the use of the `Issue_Books` table?

The `Issue_Books` table stores transaction details of issued and returned books. It connects books, members, and librarians.

### 19. What is a join?

A join is used to combine rows from two or more tables based on a related column.

### 20. Why are joins used in your project?

Joins are used to display related data from multiple tables.

Example: To show issued books with member names, we join `Issue_Books`, `Books`, and `Members`.

### 21. What is an INNER JOIN?

An `INNER JOIN` returns only matching records from both tables.

Example:

```sql
SELECT b.title, a.author_name
FROM Books b
JOIN Authors a ON b.author_id = a.author_id;
```

### 22. Give an example of a join query from your project.

```sql
SELECT
    ib.issue_id,
    b.title,
    m.full_name
FROM Issue_Books ib
JOIN Books b ON ib.book_id = b.book_id
JOIN Members m ON ib.member_id = m.member_id;
```

This query displays issued books with member names.

### 23. What is a LEFT JOIN?

A `LEFT JOIN` returns all records from the left table and matching records from the right table. If there is no match, it returns `NULL`.

### 24. Where can LEFT JOIN be useful in this project?

It can be used to find books that have never been issued.

```sql
SELECT b.title
FROM Books b
LEFT JOIN Issue_Books ib ON b.book_id = ib.book_id
WHERE ib.issue_id IS NULL;
```

### 25. What is GROUP BY?

`GROUP BY` is used to group rows that have the same values in one or more columns.

### 26. Give an example of GROUP BY from your project.

```sql
SELECT category, COUNT(*) AS total_books
FROM Books
GROUP BY category;
```

This query counts the number of books in each category.

### 27. What is COUNT?

`COUNT()` is an aggregate function used to count records.

Example:

```sql
SELECT COUNT(*) AS total_members
FROM Members;
```

### 28. What is a view?

A view is a virtual table based on the result of an SQL query. It does not store data separately.

### 29. Why are views used?

Views are used to simplify complex queries, improve readability, and provide limited access to selected data.

### 30. Give an example of a view from your project.

```sql
CREATE VIEW view_book_details AS
SELECT
    b.book_id,
    b.title,
    a.author_name,
    b.category,
    b.available_copies
FROM Books b
JOIN Authors a ON b.author_id = a.author_id;
```

### 31. How do you display data from a view?

```sql
SELECT * FROM view_book_details;
```

### 32. What is a stored procedure?

A stored procedure is a saved SQL program that can be executed whenever needed.

### 33. Why are stored procedures used?

Stored procedures are used to reduce repeated code, improve reusability, and perform common database operations easily.

### 34. Give an example of a stored procedure from your project.

```sql
CREATE PROCEDURE sp_display_all_books()
BEGIN
    SELECT * FROM Books;
END;
```

### 35. How do you call a stored procedure?

```sql
CALL sp_display_all_books();
```

### 36. What is a parameterized stored procedure?

A parameterized stored procedure accepts input values.

Example:

```sql
CALL sp_search_books_by_category('Programming');
```

### 37. What is a trigger?

A trigger is a database object that automatically executes when an event occurs on a table, such as `INSERT`, `UPDATE`, or `DELETE`.

### 38. Why are triggers used?

Triggers are used to automate actions and maintain data consistency.

### 39. Give an example of a trigger from your project.

The trigger reduces available book copies when a book is issued.

```sql
CREATE TRIGGER reduce_available_copies_after_issue
AFTER INSERT ON Issue_Books
FOR EACH ROW
BEGIN
    UPDATE Books
    SET available_copies = available_copies - 1
    WHERE book_id = NEW.book_id;
END;
```

### 40. What does `NEW.book_id` mean in a trigger?

`NEW.book_id` refers to the value of `book_id` in the newly inserted row of the `Issue_Books` table.

### 41. What is the difference between a trigger and a stored procedure?

A stored procedure is executed manually using the `CALL` command, while a trigger executes automatically when a table event occurs.

### 42. What is the use of `available_copies` in the `Books` table?

`available_copies` stores the number of book copies currently available for issuing.

### 43. What happens when a book is issued?

When a book is issued, a new record is inserted into `Issue_Books`, and the trigger reduces the `available_copies` value in the `Books` table.

### 44. What is the use of `fine_amount`?

`fine_amount` stores the penalty amount charged when a member returns a book late.

### 45. What is the use of `issue_status`?

`issue_status` shows the current status of an issued book.

Examples:

- `ISSUED`
- `RETURNED`
- `OVERDUE`

### 46. What is normalization?

Normalization is the process of organizing data to reduce duplication and improve data integrity.

### 47. Is your database normalized?

Yes, the database is normalized because author, book, member, librarian, and issue details are stored in separate related tables.

### 48. What is data redundancy?

Data redundancy means storing the same data repeatedly in multiple places.

### 49. How does your project reduce redundancy?

The project stores author details only once in the `Authors` table and links books using `author_id`.

### 50. What is the conclusion of your project?

The Library Management System helps manage library records efficiently using SQL. It demonstrates table creation, keys, relationships, joins, views, stored procedures, triggers, and reporting queries.

