<?php /** Create-board card partial. No variables expected. */ ?>
<div class="widget create-board-card"
     title="Create new board"
     hx-get="/board/dialog/new"
     hx-target="body"
     hx-swap="beforeend">
    <div class="create-board-content">
        <span class="create-board-icon">+</span>
        <h3>Create Board</h3>
        <p>Add a new task board</p>
    </div>
</div>
