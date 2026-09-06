<?php
use Dashboard\Core\Sanitize;

$controller = new \Dashboard\Stickynote\StickyNoteController($db, $user);

$search_term = $_GET['search-note'] ?? '';
$category_id = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? intval($_GET['category_id']) : null;

if(strlen($search_term) < 1) {
    exit;
}
    
$notes = $controller->handleSearchNotes($search_term, $category_id);

if (empty($notes)) {
    echo '<h3>Search Results:</h3>';
    echo '<div class="sn-search-results-grid">';
    echo '<p>No results found.</p>';
    echo '</div>';
} 
else {
    echo '<h3>Search Results:</h3>';
    echo '<div class="sn-search-results-grid">';
    foreach ($notes as $note) {
        $strippedNoteSummary = nl2br(Sanitize::e(html_entity_decode(strip_tags($note['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        ?>
        <div 
            class="sn-note open-modal-btn" 
            data-modal-target="#dialog-note"
            style="--note-accent: <?=Sanitize::e($note['category_color'] ?? '#9aa7b5');?>;"
            hx-get="/stickynotes/note/edit/<?=Sanitize::e($note['id']); ?>" 
            hx-target="body" 
            hx-swap="beforeend">

            <div class="sn-note-header">
                <span class="sn-note-category" >
                    <?=(isset($note['category_title']) ? Sanitize::e($note['category_title']) : 'Uncategorized');?>
                </span>
                <span class="sn-note-date"><?php echo date('M j, Y', strtotime($note['created_at'])); ?></span>
            </div>
            <h3 class="sn-note-title"><?=Sanitize::e($note['title']); ?></h3>
            <p class="sn-note-content"><?=$strippedNoteSummary?></p>
        </div>
        <?php
    }
    echo '</div>
          <hr style="border: 1px solid #d3dfe9;margin: 3rem 0 3rem 0;">';
}
?>