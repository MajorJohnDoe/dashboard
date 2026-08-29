/**
 * Jobs Section JavaScript
 * Extends Core TableSelectManager and TableFilterManager with Job-specific actions and HTMX batch processing.
 */

// Helper to retrieve selected job IDs from table
window.getSelectedJobIds = function() {
    const checked = document.querySelectorAll('#jobs-data-table .job-row-checkbox:checked, #jobs-data-table [data-row-checkbox]:checked');
    return Array.from(checked).map(cb => cb.value);
};

// Helper to retrieve CSRF token
window.getJobsCsrfToken = function() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const input = document.querySelector('input[name="csrf_token"]');
    if (input && input.value) return input.value;
    return '';
};

// Batch Action Handlers
window.handleBatchStatusChange = function() {
    const selectedIds = window.getSelectedJobIds();
    if (!selectedIds.length) return;

    const statusOptions = ['wishlist', 'applied', 'interviewing', 'offer', 'rejected', 'archived'];
    const chosenStatus = prompt(
        `Select new status for ${selectedIds.length} application(s):\n(wishlist, applied, interviewing, offer, rejected, archived)`,
        'applied'
    );

    if (!chosenStatus) return;
    const normalized = chosenStatus.toLowerCase().trim();
    if (!statusOptions.includes(normalized)) {
        alert('Invalid status entered. Must be one of: ' + statusOptions.join(', '));
        return;
    }

    if (window.htmx) {
        window.htmx.ajax('POST', '/jobs/batch', {
            values: {
                batch_action: 'status',
                status: normalized,
                selected_jobs: selectedIds,
                csrf_token: window.getJobsCsrfToken()
            },
            swap: 'none'
        }).then(() => {
            document.querySelector('[data-deselect-all]')?.click();
        });
    }
};

window.handleBatchArchive = function() {
    const selectedIds = window.getSelectedJobIds();
    if (!selectedIds.length) return;

    if (!confirm(`Archive ${selectedIds.length} selected application(s)?`)) return;

    if (window.htmx) {
        window.htmx.ajax('POST', '/jobs/batch', {
            values: {
                batch_action: 'archive',
                selected_jobs: selectedIds,
                csrf_token: window.getJobsCsrfToken()
            },
            swap: 'none'
        }).then(() => {
            document.querySelector('[data-deselect-all]')?.click();
        });
    }
};

window.handleBatchDelete = function() {
    const selectedIds = window.getSelectedJobIds();
    if (!selectedIds.length) return;

    if (!confirm(`Are you sure you want to permanently delete ${selectedIds.length} selected application(s)?`)) return;

    if (window.htmx) {
        window.htmx.ajax('POST', '/jobs/batch', {
            values: {
                batch_action: 'delete',
                selected_jobs: selectedIds,
                csrf_token: window.getJobsCsrfToken()
            },
            swap: 'none'
        }).then(() => {
            document.querySelector('[data-deselect-all]')?.click();
        });
    }
};
