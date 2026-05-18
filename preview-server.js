const http = require('http');
const fs = require('fs');
const path = require('path');
const { URLSearchParams } = require('url');

const PORT = Number(process.env.PORT || 8000);
const ROOT = __dirname;

const columns = {
    Authors: ['author_id', 'author_name', 'country', 'email'],
    Books: ['book_id', 'isbn', 'title', 'author_id', 'category', 'publisher', 'publication_date', 'total_copies', 'available_copies', 'shelf_no'],
    Members: ['member_id', 'member_code', 'full_name', 'department', 'semester', 'phone', 'email', 'join_date', 'status'],
    Librarians: ['librarian_id', 'librarian_name', 'phone', 'email', 'hire_date', 'shift_time'],
    Issue_Books: ['issue_id', 'book_id', 'member_id', 'librarian_id', 'issue_date', 'due_date', 'return_date', 'fine_amount', 'issue_status']
};

const data = loadSampleData();

function loadSampleData() {
    const sql = fs.readFileSync(path.join(ROOT, 'insert_sample_data_library.sql'), 'utf8');
    return Object.fromEntries(
        Object.entries(columns).map(([table, tableColumns]) => [table, parseInsert(sql, table, tableColumns)])
    );
}

function parseInsert(sql, table, tableColumns) {
    const match = sql.match(new RegExp(`INSERT\\s+INTO\\s+${table}[\\s\\S]*?VALUES\\s*([\\s\\S]*?);`, 'i'));
    if (!match) {
        return [];
    }

    return parseRows(match[1]).map(row => Object.fromEntries(
        tableColumns.map((column, index) => [column, normalizeValue(row[index])])
    ));
}

function parseRows(valueBlock) {
    const rows = [];
    let row = [];
    let value = '';
    let inString = false;
    let inRow = false;

    for (let index = 0; index < valueBlock.length; index += 1) {
        const char = valueBlock[index];
        const next = valueBlock[index + 1];

        if (char === "'" && inString && next === "'") {
            value += "'";
            index += 1;
            continue;
        }

        if (char === "'") {
            inString = !inString;
            continue;
        }

        if (!inString && char === '(') {
            inRow = true;
            row = [];
            value = '';
            continue;
        }

        if (!inString && char === ')' && inRow) {
            row.push(value.trim());
            rows.push(row);
            inRow = false;
            value = '';
            continue;
        }

        if (!inString && char === ',' && inRow) {
            row.push(value.trim());
            value = '';
            continue;
        }

        if (inRow) {
            value += char;
        }
    }

    return rows;
}

function normalizeValue(value) {
    if (value === undefined || /^null$/i.test(value)) {
        return null;
    }

    if (/^-?\d+(\.\d+)?$/.test(value)) {
        return Number(value);
    }

    return value;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function nextId(rows, idColumn) {
    return Math.max(0, ...rows.map(row => Number(row[idColumn]) || 0)) + 1;
}

function authorName(authorId) {
    return data.Authors.find(author => author.author_id === Number(authorId))?.author_name || 'Unknown';
}

function bookTitle(bookId) {
    return data.Books.find(book => book.book_id === Number(bookId))?.title || 'Unknown';
}

function memberName(memberId) {
    return data.Members.find(member => member.member_id === Number(memberId))?.full_name || 'Unknown';
}

function librarianName(librarianId) {
    return data.Librarians.find(librarian => librarian.librarian_id === Number(librarianId))?.librarian_name || 'Unknown';
}

function layout(title, activePage, body) {
    const nav = [
        ['dashboard', 'Dashboard', 'index.php'],
        ['books', 'Books', 'books.php'],
        ['members', 'Members', 'members.php'],
        ['issues', 'Issue Books', 'issue_books.php']
    ].map(([key, label, href]) => `<a class="${activePage === key ? 'active' : ''}" href="${href}">${label}</a>`).join('');

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${escapeHtml(title)} | Library Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">SQL Mini Project</p>
            <h1>Library Management System</h1>
        </div>
        <nav>${nav}</nav>
    </header>
    <main class="page">${body}</main>
</body>
</html>`;
}

function dashboard() {
    const recentIssues = [...data.Issue_Books]
        .sort((a, b) => String(b.issue_date).localeCompare(String(a.issue_date)) || b.issue_id - a.issue_id)
        .slice(0, 8);

    const categoryRows = Object.values(data.Books.reduce((groups, book) => {
        const category = book.category || 'Uncategorized';
        groups[category] ||= { category, total_books: 0, available_copies: 0 };
        groups[category].total_books += 1;
        groups[category].available_copies += Number(book.available_copies) || 0;
        return groups;
    }, {})).sort((a, b) => b.total_books - a.total_books).slice(0, 6);

    return layout('Dashboard', 'dashboard', `
<section class="hero">
    <div>
        <p class="eyebrow">Dashboard</p>
        <h2>Manage books, members, and issue records from one simple local preview.</h2>
    </div>
</section>
<section class="notice">Preview mode: PHP and MySQL are not installed locally, so this Node launcher uses the repo's sample SQL data in memory.</section>
<section class="stats-grid">
    ${stat('Authors', data.Authors.length)}
    ${stat('Books', data.Books.length)}
    ${stat('Members', data.Members.length)}
    ${stat('Issue Records', data.Issue_Books.length)}
</section>
<section class="split-layout">
    <div class="panel">
        <div class="section-title"><h2>Recent Issues</h2><a href="issue_books.php">View all</a></div>
        ${table(['ID', 'Book', 'Member', 'Librarian', 'Due Date', 'Status'], recentIssues.map(issue => [
            issue.issue_id,
            bookTitle(issue.book_id),
            memberName(issue.member_id),
            librarianName(issue.librarian_id),
            issue.due_date,
            badge(issue.issue_status)
        ]))}
    </div>
    <div class="panel">
        <div class="section-title"><h2>Books by Category</h2></div>
        ${table(['Category', 'Total', 'Available'], categoryRows.map(row => [
            row.category,
            row.total_books,
            row.available_copies
        ]))}
    </div>
</section>`);
}

function stat(label, value) {
    return `<article class="stat-card"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></article>`;
}

function booksPage(params, message = '') {
    const search = String(params.get('search') || '').trim().toLowerCase();
    const category = String(params.get('category') || '').trim();
    const categories = [...new Set(data.Books.map(book => book.category).filter(Boolean))].sort();

    const rows = data.Books
        .filter(book => !category || book.category === category)
        .filter(book => {
            if (!search) {
                return true;
            }
            return [book.title, book.isbn, authorName(book.author_id)].some(value => String(value).toLowerCase().includes(search));
        })
        .sort((a, b) => String(a.title).localeCompare(String(b.title)));

    return layout('Books', 'books', `
<section class="section-title"><div><p class="eyebrow">Catalog</p><h2>Books</h2></div></section>
${message ? `<div class="notice">${escapeHtml(message)}</div>` : ''}
<section class="panel">
    <h3>Add New Book</h3>
    <form method="post" class="form-grid">
        ${input('ISBN', 'isbn', '', true)}
        ${input('Title', 'title', '', true)}
        <label>Author<select name="author_id" required><option value="">Select author</option>${data.Authors.map(author => `<option value="${author.author_id}">${escapeHtml(author.author_name)}</option>`).join('')}</select></label>
        ${input('Category', 'category')}
        ${input('Publisher', 'publisher')}
        <label>Publication Date<input type="date" name="publication_date"></label>
        <label>Copies<input type="number" name="total_copies" min="1" value="1" required></label>
        ${input('Shelf No', 'shelf_no')}
        <button type="submit">Add Book</button>
    </form>
</section>
<section class="panel">
    <div class="section-title"><h3>Book List</h3></div>
    <form method="get" class="toolbar">
        <input type="text" name="search" placeholder="Search title, ISBN, author" value="${escapeHtml(params.get('search') || '')}">
        <select name="category">
            <option value="">All categories</option>
            ${categories.map(item => `<option value="${escapeHtml(item)}" ${category === item ? 'selected' : ''}>${escapeHtml(item)}</option>`).join('')}
        </select>
        <button type="submit">Search</button>
        <a class="button ghost" href="books.php">Clear</a>
    </form>
    ${table(['ID', 'Title', 'Author', 'Category', 'Publisher', 'Total', 'Available', 'Shelf'], rows.map(book => [
        book.book_id,
        book.title,
        authorName(book.author_id),
        book.category,
        book.publisher,
        book.total_copies,
        book.available_copies,
        book.shelf_no
    ]))}
</section>`);
}

function membersPage(message = '') {
    const rows = [...data.Members].sort((a, b) => b.member_id - a.member_id);

    return layout('Members', 'members', `
<section class="section-title"><div><p class="eyebrow">Students</p><h2>Members</h2></div></section>
${message ? `<div class="notice">${escapeHtml(message)}</div>` : ''}
<section class="panel">
    <h3>Add New Member</h3>
    <form method="post" class="form-grid">
        ${input('Member Code', 'member_code', 'STU031', true)}
        ${input('Full Name', 'full_name', '', true)}
        ${input('Department', 'department')}
        <label>Semester<input type="number" name="semester" min="1" max="8"></label>
        ${input('Phone', 'phone')}
        <label>Email<input type="email" name="email"></label>
        <label>Join Date<input type="date" name="join_date" value="${new Date().toISOString().slice(0, 10)}"></label>
        <label>Status<select name="status"><option value="ACTIVE">ACTIVE</option><option value="BLOCKED">BLOCKED</option></select></label>
        <button type="submit">Add Member</button>
    </form>
</section>
<section class="panel">
    <div class="section-title"><h3>Member List</h3></div>
    ${table(['ID', 'Code', 'Name', 'Department', 'Sem', 'Phone', 'Email', 'Status'], rows.map(member => [
        member.member_id,
        member.member_code,
        member.full_name,
        member.department,
        member.semester,
        member.phone,
        member.email,
        badge(member.status)
    ]))}
</section>`);
}

function issuesPage(message = '', error = '') {
    const availableBooks = data.Books.filter(book => Number(book.available_copies) > 0).sort((a, b) => String(a.title).localeCompare(String(b.title)));
    const activeMembers = data.Members.filter(member => member.status === 'ACTIVE').sort((a, b) => String(a.full_name).localeCompare(String(b.full_name)));
    const issues = [...data.Issue_Books].sort((a, b) => b.issue_id - a.issue_id);
    const today = new Date();
    const issueDate = today.toISOString().slice(0, 10);
    const dueDate = new Date(today.getTime() + 14 * 86400000).toISOString().slice(0, 10);

    return layout('Issue Books', 'issues', `
<section class="section-title"><div><p class="eyebrow">Transactions</p><h2>Issue Books</h2></div></section>
${message ? `<div class="notice">${escapeHtml(message)}</div>` : ''}
${error ? `<div class="notice danger">${escapeHtml(error)}</div>` : ''}
<section class="panel">
    <h3>Issue a Book</h3>
    <form method="post" class="form-grid">
        <label>Book<select name="book_id" required><option value="">Select book</option>${availableBooks.map(book => `<option value="${book.book_id}">${escapeHtml(book.title)} (${book.available_copies} available)</option>`).join('')}</select></label>
        <label>Member<select name="member_id" required><option value="">Select member</option>${activeMembers.map(member => `<option value="${member.member_id}">${escapeHtml(member.full_name)} - ${escapeHtml(member.member_code)}</option>`).join('')}</select></label>
        <label>Librarian<select name="librarian_id" required><option value="">Select librarian</option>${data.Librarians.map(librarian => `<option value="${librarian.librarian_id}">${escapeHtml(librarian.librarian_name)}</option>`).join('')}</select></label>
        <label>Issue Date<input type="date" name="issue_date" value="${issueDate}" required></label>
        <label>Due Date<input type="date" name="due_date" value="${dueDate}" required></label>
        <button type="submit">Issue Book</button>
    </form>
</section>
<section class="panel">
    <div class="section-title"><h3>Issue Records</h3></div>
    ${table(['ID', 'Book', 'Member', 'Librarian', 'Issue', 'Due', 'Return', 'Fine', 'Status', 'Action'], issues.map(issue => [
        issue.issue_id,
        bookTitle(issue.book_id),
        memberName(issue.member_id),
        librarianName(issue.librarian_id),
        issue.issue_date,
        issue.due_date,
        issue.return_date || '-',
        Number(issue.fine_amount || 0).toFixed(2),
        badge(issue.issue_status),
        issue.return_date ? '-' : `<form method="post" class="inline-form"><input type="hidden" name="return_issue_id" value="${issue.issue_id}"><button type="submit" class="small-button">Return</button></form>`
    ]))}
</section>`);
}

function input(label, name, placeholder = '', required = false) {
    return `<label>${escapeHtml(label)}<input type="text" name="${escapeHtml(name)}" placeholder="${escapeHtml(placeholder)}" ${required ? 'required' : ''}></label>`;
}

function badge(value) {
    return `<span class="badge">${escapeHtml(value)}</span>`;
}

function table(headers, rows) {
    return `<div class="table-wrap"><table><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${String(cell ?? '')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}

function handlePost(pathname, form) {
    if (pathname.endsWith('/books.php')) {
        const title = String(form.get('title') || '').trim();
        const isbn = String(form.get('isbn') || '').trim();
        const authorId = Number(form.get('author_id'));
        const totalCopies = Math.max(1, Number(form.get('total_copies')) || 1);

        if (!title || !isbn || !authorId) {
            return booksPage(new URLSearchParams(), 'Please fill ISBN, title, author, and valid copy count.');
        }

        data.Books.push({
            book_id: nextId(data.Books, 'book_id'),
            isbn,
            title,
            author_id: authorId,
            category: String(form.get('category') || '').trim(),
            publisher: String(form.get('publisher') || '').trim(),
            publication_date: String(form.get('publication_date') || '').trim() || null,
            total_copies: totalCopies,
            available_copies: totalCopies,
            shelf_no: String(form.get('shelf_no') || '').trim()
        });

        return booksPage(new URLSearchParams(), 'Book added successfully.');
    }

    if (pathname.endsWith('/members.php')) {
        const memberCode = String(form.get('member_code') || '').trim();
        const fullName = String(form.get('full_name') || '').trim();

        if (!memberCode || !fullName) {
            return membersPage('Please fill member code and full name.');
        }

        data.Members.push({
            member_id: nextId(data.Members, 'member_id'),
            member_code: memberCode,
            full_name: fullName,
            department: String(form.get('department') || '').trim(),
            semester: Number(form.get('semester')) || '',
            phone: String(form.get('phone') || '').trim(),
            email: String(form.get('email') || '').trim(),
            join_date: String(form.get('join_date') || '').trim(),
            status: String(form.get('status') || 'ACTIVE').trim()
        });

        return membersPage('Member added successfully.');
    }

    if (pathname.endsWith('/issue_books.php')) {
        const returnIssueId = Number(form.get('return_issue_id'));

        if (returnIssueId) {
            const issue = data.Issue_Books.find(row => row.issue_id === returnIssueId && !row.return_date);
            if (!issue) {
                return issuesPage('', 'Active issue record not found.');
            }

            const today = new Date();
            const due = new Date(issue.due_date);
            const lateDays = Math.max(Math.floor((today - due) / 86400000), 0);
            issue.return_date = today.toISOString().slice(0, 10);
            issue.fine_amount = lateDays * 5;
            issue.issue_status = 'RETURNED';

            const book = data.Books.find(row => row.book_id === issue.book_id);
            if (book) {
                book.available_copies = Number(book.available_copies) + 1;
            }

            return issuesPage('Book returned successfully.');
        }

        const bookId = Number(form.get('book_id'));
        const memberId = Number(form.get('member_id'));
        const librarianId = Number(form.get('librarian_id'));
        const book = data.Books.find(row => row.book_id === bookId);

        if (!bookId || !memberId || !librarianId || !book || Number(book.available_copies) <= 0) {
            return issuesPage('', 'Please select an available book, member, and librarian.');
        }

        data.Issue_Books.push({
            issue_id: nextId(data.Issue_Books, 'issue_id'),
            book_id: bookId,
            member_id: memberId,
            librarian_id: librarianId,
            issue_date: String(form.get('issue_date') || new Date().toISOString().slice(0, 10)),
            due_date: String(form.get('due_date') || new Date().toISOString().slice(0, 10)),
            return_date: null,
            fine_amount: 0,
            issue_status: 'ISSUED'
        });
        book.available_copies = Number(book.available_copies) - 1;

        return issuesPage('Book issued successfully.');
    }

    return dashboard();
}

function send(response, statusCode, body, contentType = 'text/html; charset=utf-8') {
    response.writeHead(statusCode, { 'Content-Type': contentType });
    response.end(body);
}

http.createServer((request, response) => {
    const requestUrl = new URL(request.url, `http://${request.headers.host}`);
    let pathname = requestUrl.pathname;

    if (pathname === '/style.css') {
        send(response, 200, fs.readFileSync(path.join(ROOT, 'frontend', 'style.css'), 'utf8'), 'text/css; charset=utf-8');
        return;
    }

    if (pathname === '/') {
        pathname = '/index.php';
    }

    if (request.method === 'POST') {
        let body = '';
        request.on('data', chunk => {
            body += chunk;
        });
        request.on('end', () => {
            send(response, 200, handlePost(pathname, new URLSearchParams(body)));
        });
        return;
    }

    if (pathname.endsWith('/books.php')) {
        send(response, 200, booksPage(requestUrl.searchParams));
        return;
    }

    if (pathname.endsWith('/members.php')) {
        send(response, 200, membersPage());
        return;
    }

    if (pathname.endsWith('/issue_books.php')) {
        send(response, 200, issuesPage());
        return;
    }

    send(response, 200, dashboard());
}).listen(PORT, () => {
    console.log(`Library Management preview running at http://localhost:${PORT}`);
});
