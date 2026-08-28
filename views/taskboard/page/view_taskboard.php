    <main class="taskboard-view">
        <div id="taskboard-container" class="sortable" hx-get="/column/list" hx-trigger="load, taskBoardColumnList from:body" hx-target="this" hx-swap="innerHTML"></div>
    </main>