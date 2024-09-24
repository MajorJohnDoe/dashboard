<?php   
use Dashboard\Taskboard\BoardController;
use Dashboard\Taskboard\ColumnController;
use Dashboard\Taskboard\Task;

// Initialize task board object
$board = new BoardController($db, $user);

// Initialize array to hold active labels
$activeLabels = [];

// Load board data
$boardData = $board->loadBoardDataById($user->getActiveTaskBoard());
$boardLabels = $board->loadBoardLabels($user->getActiveTaskBoard());

// Check if board data is loaded successfully
if (!$boardData) {
    echo 'ERROR: Board data not loaded';
    exit;
}

if(empty($boardLabels)) {
    echo '<div class="flex-table">
            <div class="flex-row">
                <div class="flex-cell">
                    <p><strong>No labels found.</strong></p>
                    <p>To create your first labels, click on the <strong>Labels</strong> button to the right.</p>
                </div>
            </div>
        </div>';
    exit;
}

// Function to safely get array value
function safeGet($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Function to render label checkbox
function renderLabelCheckbox($label, $isChecked = false) {
    $checked = $isChecked ? 'checked' : '';
    $labelId = safeGet($label, 'id', '');
    $labelName = safeGet($label, 'label_name', 'Unnamed Label');
    $labelColor = safeGet($label, 'label_color', '#CCCCCC');

    echo '<div class="flex-row">
            <div class="flex-cell flex-cell-shrink flex-vertical-center" style="background-color: ' . htmlspecialchars($labelColor) . ';">
                <input type="checkbox" id="LabelId_'.htmlspecialchars($labelId).'" name="label[]" value="'.htmlspecialchars($labelId).'" '.$checked.' tabindex="-1">
            </div>
            <label for="LabelId_'.htmlspecialchars($labelId).'" class="flex-cell flex-vertical-center" style="padding: 0 8px; cursor: pointer; background-color: '.htmlspecialchars($labelColor).';" tabindex="0">
                '.htmlspecialchars($labelName).'
            </label>
        </div>';
}

// Determine if search label is set
$searchLabelSet = isset($_REQUEST['search-label']) && strlen($_REQUEST['search-label']) > 0;

// Get selected labels
$selectedLabels = isset($_REQUEST['selectedLabels']) && is_array($_REQUEST['selectedLabels']) ? $_REQUEST['selectedLabels'] : [];

// Start rendering the HTML output
echo '<div class="flex-table label-checkbox" style="width: 40rem;">
        <div class="flex-row">';

// Column 1: Selected Labels
echo '<div class="flex-cell" style="width: 50%;">
        <div class="flex-row">
            <div class="flex-cell"><strong>Selected Labels</strong></div>
        </div>';

$selectedLabelsCount = 0;
foreach ($boardLabels as $label) {
    $labelId = safeGet($label, 'id', '');
    if (!empty($labelId) && in_array(strval($labelId), $selectedLabels)) {
        $activeLabels[] = strval($labelId);
        renderLabelCheckbox($label, true);
        $selectedLabelsCount++;
    }
}

if ($selectedLabelsCount == 0) {
    echo '<div class="flex-row">
            <div class="flex-cell">
                <p>No labels selected.</p>
            </div>
        </div>';
}

echo '</div>'; // End of Selected Labels column

// Column 2: Available Labels or Search Results
echo '<div class="flex-cell" style="width: 50%;">';

if ($searchLabelSet) {
    // Search Results
    echo '<div class="flex-row"><div class="flex-cell"><strong>Search Results</strong></div></div>';
    $searchTerm = $_REQUEST['search-label'];
    $searchResults = $board->searchBoardLabels($user->getActiveTaskBoard(), $searchTerm);

    if (!empty($searchResults)) {
        $searchCount = 0;
        foreach ($searchResults as $label) {
            $labelId = safeGet($label, 'id', '');
            if (!empty($labelId) && !in_array(strval($labelId), $activeLabels)) {
                renderLabelCheckbox($label);
                $searchCount++;
            }
        }

        if ($searchCount == 0) {
            echo '<div class="flex-row"><div class="flex-cell">No unselected labels found</div></div>';
        }
    } else {
        echo '<div class="flex-row"><div class="flex-cell">No results found</div></div>';
    }
} else {
    // Available Labels
    echo '<div class="flex-row"><div class="flex-cell"><strong>Available Labels</strong></div></div>';
    $availableLabelsCount = 0;
    foreach ($boardLabels as $label) {
        $labelId = safeGet($label, 'id', '');
        if (!empty($labelId) && !in_array(strval($labelId), $activeLabels)) {
            renderLabelCheckbox($label);
            $availableLabelsCount++;
        }
    }

    if ($availableLabelsCount == 0) {
        echo '<div class="flex-row"><div class="flex-cell">No available labels</div></div>';
    }
}

echo '</div>'; // End of Available Labels/Search Results column

echo '</div></div>'; // Close the flex-row and flex-table
?>