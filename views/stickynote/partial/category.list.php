<?php
// categories.php
use Dashboard\Stickynote\StickyNoteControllerFactory;

$controller = StickyNoteControllerFactory::create($db, $user);
$allCategories = $controller->getCategories();

?>

<ul id="category-list">
    <?php 
    foreach ($allCategories as $category): 
        $isUncategorized = $category['id'] === null;
        $categoryId = $isUncategorized ? '0' : htmlspecialchars($category['id']);
    ?>
        <li>
            <a href="#" 
               hx-get="/stickynotes/note-list?category_id=<?= $categoryId ?>"
               hx-target="#note-list-container"
               hx-trigger="click"
               hx-push-url="?category_id=<?= $categoryId ?>"
               hx-swap="outerHTML">
                <span class="sn-category-color" style="background-color: <?= htmlspecialchars($category['color']) ?>;"></span>
                <?= htmlspecialchars($category['title']) ?>
                <span class="sn-category-count"><?= intval($category['note_count']) ?></span>
            </a>
        </li>
    <?php
    endforeach; ?>
</ul>