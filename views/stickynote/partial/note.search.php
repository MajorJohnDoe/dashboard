<?php
use Dashboard\Stickynote\StickyNoteControllerFactory;

$controller = StickyNoteControllerFactory::create($db, $user);

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
        $strippedNoteSummary = nl2br(htmlspecialchars(html_entity_decode(strip_tags($note['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        ?>
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
        <?php
    }
    echo '</div>
          <hr style="border: 1px solid #d3dfe9;margin: 3rem 0 3rem 0;">';
}
?>