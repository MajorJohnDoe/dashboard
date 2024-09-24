<?php
// Get recent notes or notes for a specific category
$category_id = isset($_GET['category_id']) ? (
    $_GET['category_id'] === '' ? null : (
        intval($_GET['category_id']) === 0 ? 0 : max(1, intval($_GET['category_id']))
    )
) : null;
?>
<main>
    <div class="sn-container">
        <div class="sn-body">
            <div class="sn-sidebar">
                <h3>Quick links:</h3>
                <ul class="sn-category-list">
                    <li><a href="/stickynotes">Recent</a></li>
                    <li><a href="stickynotes/bookmarks">Bookmarks</a></li>
                </ul>

                <br>
                <h3>Library:</h3>
                <ul class="sn-category-list"
                    id="note-category-list-container"
                    hx-get="/stickynotes/cat-list" 
                    hx-trigger="load, triggerNoteCatlist from:body" 
                    hx-target="this" 
                    hx-swap="innerHTML">
                </ul>

                <br>
                <button class="open-modal-btn btn btn-fullwidth btn-green"
                        hx-get="/stickynotes/category/dialog/" 
                        hx-target="body" 
                        hx-swap="beforeend">
                    Edit categories
                </button>
            </div>
            <div class="sn-content"
                 id="note-list-container"
                 hx-get="/stickynotes/note-list?category_id=<?= $category_id ?>" 
                 hx-trigger="load, triggerNotelist from:body" 
                 hx-target="this" 
                 hx-swap="outerHTML">
            </div>
        </div>
    </div>
</main>