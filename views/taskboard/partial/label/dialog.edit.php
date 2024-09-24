<div id="dialog-label-edit" class="modal-container">
    <div class="dialog" style="width: 65rem;">
        <div class="dialog-header">
            <span>Edit labels</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->
                <div class="flex-table nice-form-group">
                    <div class="flex-row">
                        <div class="flex-cell">
                            <div>
                                <input 
                                    autocomplete="off"
                                    type="search" 
                                    name="search-label-edit" 
                                    id="search-label-edit" 
                                    placeholder="Search and filter labels" 
                                    hx-get="/label/search"
                                    hx-trigger="input changed delay:100ms, load, search-label-edit from:body"
                                    <?php //hx-trigger="input changed delay:100ms, load, search-label-edit from:body"?>
                                    hx-target="#search-label-edit-result" 
                                    hx-swap="innerHTML">
                                <div id="search-label-result-wrap">
                                    <div id="search-label-edit-result"><!-- here goes label search result --></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="label-edit-form-container"
                    hx-get="taskboard/label/form/new" 
                    hx-trigger="load, triggerLabelForm from:body" 
                    hx-target="this" 
                    hx-swap="innerHTML">
                </div>
        <!-- end of modal -->
        </div>
    </div>
</div>