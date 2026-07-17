(function () {
    const bootstrap = window.BOOKS_CRUD_BOOTSTRAP;

    if (!bootstrap) {
        return;
    }

    const state = {
        authors: Array.isArray(bootstrap.authors) ? bootstrap.authors : [],
        categories: Array.isArray(bootstrap.categories) ? bootstrap.categories : [],
        books: [],
        editingId: null,
    };

    const elements = {
        message: document.getElementById('book-message'),
        form: document.getElementById('book-form'),
        filterForm: document.getElementById('book-filter-form'),
        tableBody: document.getElementById('books-table-body'),
        submitButton: document.getElementById('book-submit'),
        cancelButton: document.getElementById('book-cancel'),
        searchInput: document.getElementById('book-search'),
        filterCategory: document.getElementById('book-filter-category'),
        clearButton: document.getElementById('book-clear'),
        totalCopies: document.getElementById('total_copies'),
        availableCopies: document.getElementById('available_copies'),
        fields: {
            book_id: document.getElementById('book_id'),
            isbn: document.getElementById('isbn'),
            title: document.getElementById('title'),
            author_id: document.getElementById('author_id'),
            category: document.getElementById('category'),
            publisher: document.getElementById('publisher'),
            publication_date: document.getElementById('publication_date'),
            total_copies: document.getElementById('total_copies'),
            available_copies: document.getElementById('available_copies'),
            shelf_no: document.getElementById('shelf_no'),
        },
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char];
        });
    }

    function showMessage(type, text) {
        if (!elements.message) {
            return;
        }

        if (!text) {
            elements.message.textContent = '';
            elements.message.className = 'notice is-hidden';
            return;
        }

        elements.message.textContent = text;
        elements.message.className = type === 'error' ? 'notice danger' : 'notice';
    }

    function setSubmitting(isSubmitting) {
        if (!elements.submitButton) {
            return;
        }

        elements.submitButton.disabled = isSubmitting;
        elements.submitButton.textContent = isSubmitting
            ? (state.editingId ? 'Updating...' : 'Adding...')
            : (state.editingId ? 'Update Book' : 'Add Book');
    }

    function currentFilters() {
        return {
            search: elements.searchInput ? elements.searchInput.value.trim() : '',
            category: elements.filterCategory ? elements.filterCategory.value : '',
        };
    }

    function updateCategoryFilterOptions() {
        if (!elements.filterCategory) {
            return;
        }

        const selected = elements.filterCategory.value;
        const categories = Array.from(new Set(
            state.categories
                .concat(state.books.map(function (book) { return book.category; }))
                .filter(function (value) { return value && String(value).trim() !== ''; })
                .map(function (value) { return String(value).trim(); })
        )).sort(function (left, right) {
            return left.localeCompare(right);
        });

        elements.filterCategory.innerHTML = '<option value="">All categories</option>' + categories.map(function (category) {
            const isSelected = selected === category ? ' selected' : '';
            return '<option value="' + escapeHtml(category) + '"' + isSelected + '>' + escapeHtml(category) + '</option>';
        }).join('');
    }

    function renderBooks() {
        if (!elements.tableBody) {
            return;
        }

        if (state.books.length === 0) {
            elements.tableBody.innerHTML = '<tr><td colspan="9" class="empty-state">No books matched this filter.</td></tr>';
            return;
        }

        elements.tableBody.innerHTML = state.books.map(function (book) {
            return [
                '<tr>',
                '<td>' + escapeHtml(book.book_id) + '</td>',
                '<td>' + escapeHtml(book.title) + '</td>',
                '<td>' + escapeHtml(book.author_name) + '</td>',
                '<td>' + escapeHtml(book.category || '-') + '</td>',
                '<td>' + escapeHtml(book.publisher || '-') + '</td>',
                '<td>' + escapeHtml(book.total_copies) + '</td>',
                '<td>' + escapeHtml(book.available_copies) + '</td>',
                '<td>' + escapeHtml(book.shelf_no || '-') + '</td>',
                '<td>',
                '<div class="action-buttons">',
                '<button type="button" class="small-button button ghost" data-action="edit" data-id="' + escapeHtml(book.book_id) + '">Edit</button>',
                '<button type="button" class="small-button danger-button" data-action="delete" data-id="' + escapeHtml(book.book_id) + '">Delete</button>',
                '</div>',
                '</td>',
                '</tr>'
            ].join('');
        }).join('');
    }

    function resetForm() {
        if (!elements.form) {
            return;
        }

        elements.form.reset();
        state.editingId = null;
        elements.fields.book_id.value = '';
        elements.totalCopies.value = '1';
        elements.availableCopies.value = '1';
        elements.submitButton.textContent = 'Add Book';
        elements.cancelButton.classList.add('is-hidden');
    }

    function fillForm(book) {
        state.editingId = String(book.book_id);
        elements.fields.book_id.value = String(book.book_id);
        elements.fields.isbn.value = book.isbn || '';
        elements.fields.title.value = book.title || '';
        elements.fields.author_id.value = String(book.author_id || '');
        elements.fields.category.value = book.category || '';
        elements.fields.publisher.value = book.publisher || '';
        elements.fields.publication_date.value = book.publication_date || '';
        elements.fields.total_copies.value = String(book.total_copies || 1);
        elements.fields.available_copies.value = String(book.available_copies || 0);
        elements.fields.shelf_no.value = book.shelf_no || '';
        elements.submitButton.textContent = 'Update Book';
        elements.cancelButton.classList.remove('is-hidden');
        elements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function payloadFromForm() {
        return {
            book_id: elements.fields.book_id.value ? Number(elements.fields.book_id.value) : null,
            isbn: elements.fields.isbn.value.trim(),
            title: elements.fields.title.value.trim(),
            author_id: Number(elements.fields.author_id.value || 0),
            category: elements.fields.category.value.trim(),
            publisher: elements.fields.publisher.value.trim(),
            publication_date: elements.fields.publication_date.value,
            total_copies: Number(elements.fields.total_copies.value || 0),
            available_copies: Number(elements.fields.available_copies.value || 0),
            shelf_no: elements.fields.shelf_no.value.trim(),
        };
    }

    async function parseResponse(response) {
        const data = await response.json().catch(function () {
            return {
                success: false,
                message: 'Server returned an invalid response.',
            };
        });

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Request failed.');
        }

        return data;
    }

    async function loadBooks() {
        const filters = currentFilters();
        const params = new URLSearchParams();

        if (filters.search) {
            params.set('search', filters.search);
        }

        if (filters.category) {
            params.set('category', filters.category);
        }

        elements.tableBody.innerHTML = '<tr><td colspan="9" class="empty-state">Loading books...</td></tr>';

        try {
            const response = await fetch(bootstrap.apiUrl + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await parseResponse(response);
            state.books = Array.isArray(data.books) ? data.books : [];
            state.categories = Array.isArray(data.categories) ? data.categories : state.categories;
            updateCategoryFilterOptions();
            if (filters.category) {
                elements.filterCategory.value = filters.category;
            }
            renderBooks();
        } catch (error) {
            elements.tableBody.innerHTML = '<tr><td colspan="9" class="empty-state">Could not load books.</td></tr>';
            showMessage('error', error.message);
        }
    }

    async function submitForm(event) {
        event.preventDefault();
        showMessage('success', '');
        setSubmitting(true);

        try {
            const payload = payloadFromForm();
            const method = state.editingId ? 'PUT' : 'POST';
            const response = await fetch(bootstrap.apiUrl, {
                method: method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await parseResponse(response);
            showMessage('success', data.message || 'Book saved successfully.');
            resetForm();
            await loadBooks();
        } catch (error) {
            showMessage('error', error.message);
        } finally {
            setSubmitting(false);
        }
    }

    async function handleTableClick(event) {
        const button = event.target.closest('button[data-action]');
        if (!button) {
            return;
        }

        const bookId = Number(button.getAttribute('data-id'));
        const action = button.getAttribute('data-action');
        const book = state.books.find(function (row) {
            return Number(row.book_id) === bookId;
        });

        if (!book) {
            showMessage('error', 'Selected book was not found in the current list.');
            return;
        }

        if (action === 'edit') {
            showMessage('success', '');
            fillForm(book);
            return;
        }

        if (action === 'delete') {
            const confirmed = window.confirm('Delete "' + book.title + '" from the catalog?');
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(bootstrap.apiUrl + '?book_id=' + encodeURIComponent(bookId), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                const data = await parseResponse(response);
                showMessage('success', data.message || 'Book deleted successfully.');
                if (state.editingId === String(bookId)) {
                    resetForm();
                }
                await loadBooks();
            } catch (error) {
                showMessage('error', error.message);
            }
        }
    }

    function handleFilter(event) {
        event.preventDefault();
        showMessage('success', '');
        loadBooks();
    }

    function clearFilters() {
        if (elements.searchInput) {
            elements.searchInput.value = '';
        }
        if (elements.filterCategory) {
            elements.filterCategory.value = '';
        }
        showMessage('success', '');
        loadBooks();
    }

    function keepAvailabilityInRange() {
        if (state.editingId) {
            if (Number(elements.availableCopies.value) > Number(elements.totalCopies.value || 0)) {
                elements.availableCopies.value = elements.totalCopies.value || '0';
            }
            return;
        }

        elements.availableCopies.value = elements.totalCopies.value || '0';
    }

    elements.form.addEventListener('submit', submitForm);
    elements.filterForm.addEventListener('submit', handleFilter);
    elements.tableBody.addEventListener('click', handleTableClick);
    elements.cancelButton.addEventListener('click', function () {
        showMessage('success', '');
        resetForm();
    });
    elements.clearButton.addEventListener('click', clearFilters);
    elements.totalCopies.addEventListener('input', keepAvailabilityInRange);
    elements.availableCopies.addEventListener('input', function () {
        const total = Number(elements.totalCopies.value || 0);
        const available = Number(elements.availableCopies.value || 0);
        if (available > total) {
            elements.availableCopies.value = String(total);
        }
    });

    updateCategoryFilterOptions();
    resetForm();
    loadBooks();
})();
