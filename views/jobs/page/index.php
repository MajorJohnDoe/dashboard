<main class="jobs-main">
    <div class="jobs-container">
        <!-- Top Header & Stats -->
        <div class="jobs-header">
            <div class="jobs-title-group">
                <h1>Job Applications</h1>
                <?php include __DIR__ . '/../partial/stats.php'; ?>
            </div>

            <div class="jobs-header-actions">
                <button 
                    class="open-modal-btn btn btn-blue" 
                    data-modal-target="#dialog-job-form"
                    hx-get="/jobs/dialog/new" 
                    hx-target="body" 
                    hx-swap="beforeend">
                    + Add Application
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar (Using Core toolbar-card) -->
        <div class="toolbar-card" data-filter-target="#jobs-data-table">
            <div class="toolbar-group">
                <div class="search-input-box">
                    <input type="search" data-filter-search placeholder="Search company, position, skills...">
                </div>

                <select class="select-filter" data-filter-key="status">
                    <option value="all">All Statuses</option>
                    <option value="wishlist">Wishlist / Need to Apply</option>
                    <option value="applied">Applied</option>
                    <option value="interviewing">Interviewing</option>
                    <option value="offer">Offer</option>
                    <option value="rejected">Rejected</option>
                    <option value="archived">Archived</option>
                </select>

                <select class="select-filter" data-filter-key="type">
                    <option value="all">All Types & Models</option>
                    <option value="lia">LIA (Lärande i arbete)</option>
                    <option value="full-time">Full-Time</option>
                    <option value="part-time">Part-Time</option>
                    <option value="contract">Contract / Freelance</option>
                    <option value="internship">General Internship</option>
                    <option value="thesis">Thesis / Exjobb</option>
                    <option value="remote">Remote Only</option>
                    <option value="hybrid">Hybrid</option>
                    <option value="onsite">On-Site</option>
                </select>

                <button class="btn-filter-pill" type="button" data-filter-toggle="stale" title="Show applications needing follow-up">
                    ⚠ Stale (14d+)
                </button>
            </div>
        </div>

        <!-- Floating Batch Action Bar (Using Core batch-action-bar) -->
        <div id="jobs-batch-bar" class="batch-action-bar" data-batch-bar="jobs-data-table">
            <div class="batch-info">
                <span><strong data-selected-count="jobs-data-table">0</strong> application(s) selected</span>
            </div>
            <div class="batch-actions">
                <button class="btn btn-light-gray" type="button" data-deselect-all="jobs-data-table">
                    Deselect All
                </button>
                <button class="btn btn-blue" type="button" onclick="window.handleBatchStatusChange()">
                    Change Status
                </button>
                <button class="btn btn-light-gray" type="button" onclick="window.handleBatchArchive()">
                    Archive
                </button>
                <button class="btn btn-red" type="button" onclick="window.handleBatchDelete()">
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Jobs Table Container with HTMX Refresh -->
        <div id="jobs-table-container"
             hx-get="/jobs/table"
             hx-trigger="refreshJobsList from:body"
             hx-swap="innerHTML">
            <?php include __DIR__ . '/../partial/table.php'; ?>
        </div>
    </div>
</main>
