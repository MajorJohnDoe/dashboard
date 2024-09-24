    <main>
        <div class="wrapper">
            <div class="">
                <h2>Task boards:</h2>
                <div id="task-board-list" hx-get="/board/list" hx-trigger="load, newBoard from:body"></div>
            </div>

            <br class="clear">
            
            <div class="inner-wrapper widget box-shadow">
                <div class="nice-form-group">
                    <div class="flex-table">
                        <div class="flex-row">
                            <h3>Create a new task board:</h3>
                        </div> 
                        <form hx-post="/board/create" hx-target="#task-board-list" hx-swap="afterbegin">
                        <div class="flex-row">
                            <div class="flex-cell flex-cell-shrink flex-vertical-center">Board name:</div>
                            <div class="flex-cell"><input type="text" name="boardName" required></div>
                            <div class="flex-cell"><button type="submit" class="btn btn-green">Save</button></div>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>