# PHP Frontend for Library Management System

## Files

| File | Purpose |
|---|---|
| `db.php` | MySQL connection and common helper functions |
| `index.php` | Dashboard with total counts and recent issue records |
| `books.php` | Display, search, and add books |
| `members.php` | Display and add members |
| `issue_books.php` | Issue books, queue unavailable book requests, and return books |
| `requests.php` | Review request demand and approve books for purchasing |
| `reports.php` | Show overdue books, borrowing summaries, and fine tracking |
| `style.css` | Frontend styling |

## Database Setup

Run these SQL files first in MySQL Workbench or phpMyAdmin:

```text
create_tables_library.sql
insert_sample_data_library.sql
```

The frontend expects this database:

```text
library_management
```

## MySQL Connection

The default connection in `db.php` is:

```php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'library_management';
```

If your MySQL password is different, update `db.php`.

## How to Run

From the project folder, run:

```bash
php -S localhost:8000 -t frontend
```

If PHP was installed locally inside this project, run:

```bash
.\tools\php\php.exe -S localhost:8000 -t frontend
```

Then open:

```text
http://localhost:8000
```

## Pages

- Dashboard: `index.php`
- Books: `books.php`
- Members: `members.php`
- Issue Books: `issue_books.php`
- Requests: `requests.php`
- Reports: `reports.php`
