    <main style="background-color: #5a6875; box-shadow: none;">
        <div id="taskboard-container" class="sortable" hx-get="/column/list" hx-trigger="load, taskBoardColumnList from:body" hx-target="this" hx-swap="innerHTML"></div>
    </main>