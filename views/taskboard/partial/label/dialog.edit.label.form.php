<?php   
    use Dashboard\Taskboard\BoardController;
    use Dashboard\Taskboard\ColumnController;
    use Dashboard\Taskboard\Task;

    $keyColors = ['#f5cfcf', '#f5cfe1', '#f4cff5', '#e6cff5', '#dbcff5', '#d1cff5', '#cfddf5', '#cfebf5', '#cff5f2', '#cff5e6', '#cff5db', '#d4f5cf','#e3f5cf','#ebf5cf','#f5f5cf','#f5dfcf'];
    $labelColorsList = generateGradient($keyColors, 78);

    // Extra colors
    $labelColorsListExtra[200] = '#ffffff';
    $labelColorsListExtra[201] = '#ededed';
    $labelColorsListExtra[202] = '#cfcfcf';
    
    $hexColorPattern = '/^#([a-fA-F0-9]{3}([a-fA-F0-9]{1})?|[a-fA-F0-9]{6}([a-fA-F0-9]{2})?)$/';

    $Board = new BoardController($db, $user);
    $boardData = $Board->loadBoardDataById($user->getActiveTaskBoard());

    if($boardData) {
        $boardLabels = $Board->loadBoardLabels($user->getActiveTaskBoard());
    }


    if($_GET['action'] == 'new') {
        $post_url = '/taskboard/label/form/new';
    }

    if($_GET['action'] == 'edit' && isset($_GET['labelid'])) {
        $labelId = $_GET['labelid'];
        $post_url = "/taskboard/label/form/edit?labelid=$labelId";

        // Label id belongs to user?
        $labelData = $Board->loadLabelDataByID($labelId);
        if (!$labelData['success']) {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $labelData['message']]]);
        } /* else {
            $labelInfo = $labelData['data'];
            // Use $labelInfo instead of $labelData['data'] in your form
        } */
    }   

    // #MARK: NEW label - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'new') {

        $labelName  = $_POST['labelname'] ?? '';
        $labelColor = $_POST['labelcolor'] ?? null;

        if(strlen($labelName) <1) {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Your label needs a name.']]);
        }

        if(strlen($labelName) > 25 && $formErrors) {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Label name is too long.']]);
        }        

        if (!$labelColor || !preg_match($hexColorPattern, $labelColor)) {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'You need to pick a color for the label.']]);
        }

        if(count($boardLabels) > _TASKBOARD_LABELS_MAXIMUM-1) {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => _TASKBOARD_LABELS_MAXIMUM. ' labels are the maximum.']]);
        }


        $labelResult = $Board->addLabel($user->getActiveTaskBoard(), $labelName, $labelColor);

        if ($labelResult['success']) {
            triggerResponse(['triggerLabelForm' => true, 'search-label-edit' => true, 'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $labelResult['message']]]);
        } else {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => $labelResult['message']]], false);
        }
    }

    // #MARK: SAVE label - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'edit' && $_GET['labelid']) {

        $labelId    = $_GET['labelid'];
        $labelName  = (isset($_POST['labelname']) ? $_POST['labelname']:'');
        $labelColor = (isset($_POST['labelcolor']) ? $_POST['labelcolor']:null);

        if(strlen($labelName) <1) {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => 'Your label needs a name.']]); 
        }

        if(strlen($labelName) > 25) {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => 'Label name is too long.']]); 
        }

        if (!$labelColor || !preg_match($hexColorPattern, $labelColor)) {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => 'You need to pick a color for the label.']]); 
        }

        $labelResult = $Board->editLabel($labelId, $labelName, $labelColor);

        if ($labelResult['success']) {
            triggerResponse(['taskBoardColumnList' => true, 'triggerLabelForm' => true, 'search-label-edit' => true, 'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $labelResult['message']]]);
        } else {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => $labelResult['message']]]);
        }  
    }

    // #MARK: DELETE label - form post
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['action'] == 'delete' && isset($_GET['labelid'])) {
        $labelData = $Board->deleteLabel($_GET['labelid']);
        if (!$labelData['success']) {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $labelData['message']]]);
        } else {
            triggerResponse(['taskBoardColumnList' => true, 'triggerLabelForm' => true, 'search-label-edit' => true, 'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $labelData['message']]]);
        }
    }
?>

<form   
    id="form_saveTaskLabelSettings" 
    method="POST"
    class="nice-form-group"
    hx-post="<?=$post_url?>" 
    hx-target="#label-edit-form-container"
    hx-swap="beforeend">

        <div class="mini-popup-footer">
            <div class="flex-table">
                <div class="flex-row">
                    <div class="flex-cell">
                        <div class="color-selector">
                            <?php foreach ($labelColorsList as $index => $color): ?>
                                <input type="radio" id="color<?= $index ?>" name="labelcolor" value="<?= $color ?>" hidden <?=(isset($labelData['data']['label_color']) && $labelData['data']['label_color'] == $color ? 'checked':'')?>/>
                                <label for="color<?= $index ?>" class="color-swatch" style="background-color: <?=alter_vibrance($color, 100, -15)?>; "></label>
                            <?php endforeach; ?>
                            <?php foreach ($labelColorsListExtra as $index => $color): ?>
                                <input type="radio" id="color<?= $index ?>" name="labelcolor" value="<?= $color ?>" hidden <?=(isset($labelData['data']['label_color']) && $labelData['data']['label_color'] == $color ? 'checked':'')?>/>
                                <label for="color<?= $index ?>" class="color-swatch" style="background-color: <?=$color?>; "></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell">
                        <input type="text" name="labelname" placeholder="example: label name" autocomplete="off" id="labelname" value="<?=(isset($labelData['data']['label_name']) ? htmlspecialchars($labelData['data']['label_name']):'')?>">
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell">
                        <?php if (isset($labelData['data']['label_color'])): ?>
                            <button type="submit" 
                                    class="btn btn-light-gray btn-hover-red" 
                                    hx-delete="/taskboard/label/form/delete?labelid=<?=(isset($labelData['data']['id']) ? $labelData['data']['id']:'')?>"
                                    hx-confirm="Deleting label will result in removing label from all old tasks, wish to continue?"
                                    hx-target="#label-edit-form-container"
                                    hx-swap="innerHTML" 
                                    title="Delete label">
                                    Delete
                            </button>
                            <button type="button" 
                                    class="btn btn-light-gray" 
                                    tabindex="-1"
                                    hx-get="/taskboard/label/form/new" 
                                    hx-target="#label-edit-form-container" 
                                    hx-swap="innerHTML"
                   
                                    title="Edit label">
                                    Cancel
                            </button>
                            
                            <input type="submit" class="btn btn-green" form="form_saveTaskLabelSettings" value="Save label">
                        <?php else: ?>
                            <input type="submit" class="btn btn-green" form="form_saveTaskLabelSettings" value="Add label">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>    
    </form>
