<?php
use Dashboard\Core\Sanitize;
// categories.php

$controller = new \Dashboard\Stickynote\StickyNoteController($db, $user);
$allCategories = $controller->getCategories();

?>

<ul id="category-list">
    <?php 
    foreach ($allCategories as $category): 
        $isUncategorized = $category['id'] === null;
        $categoryId = $isUncategorized ? '0' : Sanitize::e($category['id']);
    ?>
        <li>
            <a href="#" 
               hx-get="/stickynotes/note-list?category_id=<?= $categoryId ?>"
               hx-target="#note-list-container"
               hx-trigger="click"
               hx-push-url="?category_id=<?= $categoryId ?>"
               hx-swap="outerHTML">
                <span class="sn-category-color" style="background-color: <?= Sanitize::e($category['color']) ?>;"></span>
                <?= Sanitize::e($category['title']) ?>
                <span class="sn-category-count"><?= intval($category['note_count']) ?></span>
            </a>
        </li>
    <?php
    endforeach; ?>
</ul>