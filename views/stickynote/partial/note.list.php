<?php
use Dashboard\Stickynote\StickyNoteControllerFactory;

$controller = StickyNoteControllerFactory::create($db, $user);
$categories = $controller->getCategories();

$category_id = isset($_GET['category_id']) ? (
    $_GET['category_id'] === '' ? null : (
        intval($_GET['category_id']) === 0 ? 0 : max(1, intval($_GET['category_id']))
    )
) : null;

// Get recent notes or notes for a specific category
$notes = $controller->getNotes(100, $category_id);
?>
<div 
    class="sn-content"
    id="note-list-container"
    hx-get="/stickynotes/note-list?category_id=<?= $category_id ?>"
    hx-trigger="triggerNotelist from:body" 
    hx-target="this" 
    hx-swap="outerHTML">
    
    <div class="sn-content-header nice-form-group">
        <div class="flex-table">
            <div class="flex-row">
                <div class="flex-cell">
                    <div style="position: relative;">
                        <input 
                            autocomplete="off"
                            type="search" 
                            name="search-note" 
                            id="search-note" 
                            placeholder="Search notes, full words only..." 
                            hx-get="/stickynotes/search?category_id=<?= $category_id ?>"
                            hx-trigger="input changed delay:300ms, search-note"
                            hx-target="#search-results" 
                            hx-swap="innerHTML">
                    </div>
                </div>
                <div class="flex-cell flex-cell-shrink">
                    <button 
                        class="open-modal-btn btn btn-green" 
                        data-modal-target="#dialog-note"
                        hx-get="/stickynotes/note/create/0<?=($category_id != null ? "?category_id=$category_id":"")?>" 
                        hx-target="body" 
                        hx-swap="beforeend">
                        + Sticky Note
                    </button>
                </div>
            </div>
        </div>
    </div>

        <!-- New container for search results -->
    <div id="search-results" class="sn-search-results"></div>

    <div id="regular-notes">
    <?php foreach ($notes as $index => $note): 
            $strippedNoteSummary = nl2br(htmlspecialchars(html_entity_decode(strip_tags($note['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));?>
            <div 
                class="sn-note open-modal-btn" 
                data-modal-target="#dialog-note"
                style="background-color: <?=htmlspecialchars($note['category_color'] ?? '#fff');?>;"
                hx-get="/stickynotes/note/edit/<?=htmlspecialchars($note['id']); ?>" 
                hx-target="body" 
                hx-swap="beforeend">

                <div class="sn-note-header">
                    <span class="sn-note-category" >
                        <?=(isset($note['category_title']) ? htmlspecialchars($note['category_title']) : 'Uncategorized');?>
                    </span>
                    <span class="sn-note-year"><?php echo date('Y', strtotime($note['created_at'])); ?></span>
                </div>
                <h3 class="sn-note-title"><?=htmlspecialchars($note['title']); ?></h3>
                <p class="sn-note-content"><?=$strippedNoteSummary?></p>
            </div>
    <?php endforeach; ?>
    </div>
</div>