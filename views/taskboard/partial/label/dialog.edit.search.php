<?php        
use Dashboard\Taskboard\BoardController;

$board = new BoardController($db, $user);
$boardData = $board->loadBoardDataById($user->getActiveTaskBoard(), $user->user_id());

if($boardData) {
    $boardLabels = $board->loadBoardLabels($user->getActiveTaskBoard());
}
/* var_dump($boardLabels); */
// Determine if search label is set
$searchLabelSet = isset($_GET['search-label-edit']) && strlen($_GET['search-label-edit']) > 0;

echo '<div class="flex-table">';

if ($searchLabelSet) {
    $searchTerm = $_GET['search-label-edit'];
    $searchResults = $board->searchBoardLabels($user->getActiveTaskBoard(), $searchTerm);

    echo '<div class="flex-row">
            <div class="flex-cell" style="padding: 8px 0px;"><strong>Search result</strong></div>
            </div>';

    if (!empty($searchResults)) {
        $searchCount = 0;
        echo '<div class="flex-row">
                <div class="flex-cell" style="padding: 0px;">';
            foreach ($searchResults as $label) {
                renderLabelCheckbox($label);
                $searchCount++;
            }
        echo '  </div>
                </div>';
        if ($searchCount == 0) {
            echo '<div class="flex-row">
                    <div class="flex-cell">No results</div>
                    </div>';
        }
    } else {
        echo '<div class="flex-row">
                <div class="flex-cell">No results</div>
                </div>';
    }  
} 
else {
    echo '<div class="flex-row">
            <div class="flex-cell" style="padding: 8px 0px;"><strong>All labels</strong></div>
            </div>
            <div class="flex-row">
            <div class="flex-cell" style="padding: 0px;">';
                if(count($boardLabels) > 0) {
                    foreach ($boardLabels as $label) {
                        renderLabelCheckbox($label);
                    }
                } else {
                    echo '<p>No labels created yet</p>';
                }
    echo '  </div>
            </div>';
}

echo '</div>'; // End flex table

function renderLabelCheckbox($label, $isChecked = false) {
    $checked = $isChecked ? 'checked' : '';

    $fontColor = adjustHexColorBrightness(htmlspecialchars($label['label_color']), -115);

    echo '  <div class="labelwrapper" 
                type="button"
                hx-get="/taskboard/label/form/edit?labelid='.htmlspecialchars($label['id']).'" 
                hx-target="#dialog-label-edit #label-edit-form-container"
                hx-swap="innerHTML"
            >
                <label for="LabelId_' . htmlspecialchars($label['id']) . '" style="background-color: '.htmlspecialchars($label['label_color']).'; color: '.$fontColor.';" tabindex="0">
                    <input type="radio" id="LabelId_' . htmlspecialchars($label['id']) . '" name="label[]" value="' . htmlspecialchars($label['id']) . '" ' . $checked . ' tabindex="-1">
                    ' . htmlspecialchars($label['label_name']) . '
                </label>
            </div>
        ';
}


?>
<style>
    .labelwrapper {
        display: inline-flex; 
        align-items: center; 
        margin: 0 0rem 0.5rem;
    }
    .labelwrapper label {
        padding: 0.5rem 1rem 0.5rem 0.5rem;
        cursor: pointer;
        border-radius: 0.4rem;
    }
</style>