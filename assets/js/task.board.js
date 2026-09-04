/**
 * Taskboard JavaScript Module
 * This file is included in view_taskboard.php and manages taskboard functionality.
 */

// CSRF token now comes from the shared Csrf helper in http.js
// (meta tag set in header.php for all pages).
const getCsrfTokenMeta = () => Csrf.getToken();

// Utility functions
const Utilities = (() => {
    /**
     * Converts RGB color to Hex format
     * @param {string} rgb - RGB color string
     * @return {string} Hex color string
     */
    function rgbToHex(rgb) {
        if (!rgb || !rgb.startsWith('rgb')) return rgb;
        return '#' + rgb.match(/\d+/g).map(x => {
            const hex = Number(x).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    }

    /**
     * Generic function to handle fetch responses
     * @param {Response} response - Fetch response object
     * @param {string} successMessage - Message to log on success
     * @param {string} errorMessage - Message to log on error
     * @return {Promise<boolean>} Promise resolving to success status
     */
    function handleFetchResponse(response, successMessage, errorMessage) {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json().then(data => {
            if (data.success) {
                console.log(successMessage);
                return true;
            } else {
                console.error(errorMessage, data.message);
                return false;
            }
        }).catch(error => {
            console.error(errorMessage, error);
            return false;
        });
    }

    return { rgbToHex, handleFetchResponse };
})();

// Sortable management
const SortableManager = (() => {
    /**
     * Initializes a Sortable instance
     * @param {HTMLElement} element - Element to make sortable
     * @param {Object} options - Sortable options
     * @return {Sortable} Sortable instance
     */
    function initSortable(element, options) {
        return new Sortable(element, options);
    }

    /**
     * Updates column order on the server
     * @param {Array} columnIds - Array of column IDs in new order
     * @return {Promise<boolean>} Promise resolving to success status
     */
    function updateColumnOrder(columnIds) {
        return Http.postJson(APP_ROUTES.COLUMN_SAVE_ORDER, { columnOrders: columnIds }, {
            errorMessage: 'Failed to update column order'
        }).then(data => data.success === true);
    }

    /**
     * Moves a task to a new column on the server
     * @param {string} itemId - ID of the task to move
     * @param {string} newListId - ID of the new column
     * @param {Array} itemIds - Array of task IDs in new order
     * @return {Promise<boolean>} Promise resolving to success status
     */
    function moveTaskToColumn(itemId, newListId, itemIds) {
        return Http.postJson(APP_ROUTES.TASK_MOVE_TO_COLUMN, { itemId, newListId, itemIds }, {
            errorMessage: 'Failed to move task'
        }).then(data => data.success === true);
    }

    /**
     * Initializes Sortable for columns
     */
    function initColumnSortable() {
        const columnsContainer = document.querySelector("#taskboard-container .columns-container");
        if (columnsContainer) {
            initSortable(columnsContainer, {
                animation: 150,
                ghostClass: 'move-column-temp-bg',
                handle: ".move",
                draggable: ".task-column",
                onEnd: function(evt) {
                    const columnIds = Array.from(evt.to.children).map(column => column.id.replace('column-', ''));
                    console.log("New column order:", columnIds);
                    updateColumnOrder(columnIds).then(success => {
                        if (success) {
                            document.getElementById('taskboard-container').dispatchEvent(new CustomEvent(HTMX_EVENTS.TASK_BOARD_COLUMN_LIST, {bubbles: true}));
                        }
                    });
                }
            });
        }
    }

    /**
     * Initializes Sortable for tasks within columns
     */
    function initTaskSortables() {
        document.querySelectorAll('.columns-container .sortable-list').forEach(list => {
            initSortable(list, {
                group: 'tasks',
                animation: 150,
                ghostClass: 'move-task-temp-bg',
                onEnd: function(evt) {
                    const itemEl = evt.item;
                    const originListId = evt.from.id.replace('inner-column-', '');
                    const targetListId = evt.to.id.replace('inner-column-', '');
                    const itemId = itemEl.dataset.id;
                    const itemIds = Array.from(evt.to.children).map(item => item.dataset.id);

                    console.log('Item moved:', itemId, 'from column:', originListId, 'to new column:', targetListId, 'New order:', itemIds);
                    moveTaskToColumn(itemId, targetListId, itemIds);
                }
            });
        });
    }

    return { initColumnSortable, initTaskSortables };
})();

// Label management
const LabelManager = (() => {
    /**
     * Preselects labels based on hidden inputs
     */
    function preselectLabels() {
        const hiddenInputs = document.querySelectorAll('input[type="hidden"][name="selectedLabels[]"]');
        const labelCheckboxes = document.querySelectorAll('.label-checkbox input[type="checkbox"][name="label[]"]');
        
        hiddenInputs.forEach(hiddenInput => {
            const matchingCheckbox = Array.from(labelCheckboxes).find(checkbox => checkbox.value === hiddenInput.value);
            if (matchingCheckbox) matchingCheckbox.checked = true;
        });
    }

    /**
     * Handles label selection via keyboard
     * @param {Event} e - Keypress event
     */
    function handleLabelKeyPress(e) {
        if (e.target.tagName === 'LABEL' && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById(e.target.getAttribute('for')).click();
        }
    }

    /**
     * Handles label selection
     * @param {Event} e - Click event
     */
    function handleLabelSelection(e) {
        if (e.target.matches('input[type="checkbox"][name="label[]"]')) {
            console.log("picking label");
            const checkbox = e.target;
            const label = document.querySelector(`label[for="${checkbox.id}"]`);
            if (!label) {
                console.log("No corresponding label found for checkbox.");
                return;
            }

            const labelName = label.innerText;
            const labelColor = Utilities.rgbToHex(getComputedStyle(label).backgroundColor);
            const selectedLabelsContainer = document.getElementById("selectedLabelsContainer");

            if (checkbox.checked) {
                selectedLabelsContainer.innerHTML += `
                    <input type="hidden" id="hidden${checkbox.id}" name="selectedLabels[]" value="${checkbox.value}">
                    <span id="visual${checkbox.id}" style="background-color: ${labelColor}">${labelName}</span>
                `;
            } else {
                document.getElementById(`hidden${checkbox.id}`)?.remove();
                document.getElementById(`visual${checkbox.id}`)?.remove();
            }

            document.getElementById('search-label').focus();
        }
    }

    return { preselectLabels, handleLabelKeyPress, handleLabelSelection };
})();

// Checklist management
const ChecklistManager = (() => {
    const MAX_CHECKLIST_ITEMS = 15;

    /**
     * Handles adding a new checklist item
     */
    function handleAddChecklistItem() {
        console.log('Add item clicked');
        const container = document.getElementById('checklist-items');
        const existingRows = container.querySelectorAll('.flex-row').length;

        if(existingRows >= MAX_CHECKLIST_ITEMS) {
            alert(`Maximum of ${MAX_CHECKLIST_ITEMS} checklist items reached.`);
            return;
        }

        const newItem = createChecklistItemElement();
        container.appendChild(newItem);
        newItem.querySelector('input[type="text"]').focus();
    }

    /**
     * Creates a new checklist item element
     * @return {HTMLElement} New checklist item element
     */
    function createChecklistItemElement() {
        const newItem = document.createElement('div');
        newItem.classList.add('flex-row');
        newItem.innerHTML = `
            <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                <input type="checkbox" tabindex="-1" name="checklist[][checked]" />
            </div>
            <div class="flex-cell flex-cell-vcenter">
                <input type="text" name="checklist[][description]" value="" style="padding: 0.4rem;" />
            </div>
            <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                <button type="button" tabindex="-1" class="remove-item btn btn-dark-gray btn-hover-red remove-item" style="padding: 0.2rem 0.6rem;">X</button>
            </div>
        `;
        return newItem;
    }

    /**
     * Handles removing a checklist item
     * @param {Event} e - Click event
     */
    function handleRemoveChecklistItem(e) {
        if (e.target.classList.contains('remove-item')) {
            console.log('Remove item clicked');
            e.target.closest('.flex-row').remove();
        }
    }

    return { handleAddChecklistItem, handleRemoveChecklistItem };
})();

// Event management
const EventManager = (() => {
    /**
     * Attaches event listeners to various elements
     */
    function attachEventListeners() {
        attachListenerOnce('add-item', 'click', ChecklistManager.handleAddChecklistItem);
        attachListenerOnce('checklist-items', 'click', ChecklistManager.handleRemoveChecklistItem);
        attachLabelListeners();
        LabelManager.preselectLabels();
    }

    /**
     * Attaches a listener to an element only once
     * @param {string} id - Element ID
     * @param {string} event - Event type
     * @param {Function} handler - Event handler function
     */
    function attachListenerOnce(id, event, handler) {
        const element = document.getElementById(id);
        if (element && !element.dataset.listenerAttached) {
            element.dataset.listenerAttached = "true";
            element.addEventListener(event, handler);
        }
    }

    /**
     * Attaches label-related listeners
     */
    function attachLabelListeners() {
        const labelsContainer = document.querySelector(".label-checkbox");
        if (labelsContainer && !labelsContainer.dataset.eventListenerAttached) {
            labelsContainer.dataset.eventListenerAttached = 'true';
            labelsContainer.addEventListener("click", LabelManager.handleLabelSelection);
            labelsContainer.addEventListener("keypress", LabelManager.handleLabelKeyPress);
        }
    }

    /**
     * Initializes the application
     */
    function init() {
        document.body.addEventListener('htmx:afterSwap', function() {
            attachEventListeners();
            SortableManager.initColumnSortable();
            SortableManager.initTaskSortables();
        });

        // Initial setup
        attachEventListeners();
        SortableManager.initColumnSortable();
        SortableManager.initTaskSortables();
    }

    return { init };
})();

// Initialize the application
document.addEventListener('DOMContentLoaded', EventManager.init);

// ============================================================================
// Task context menu (right-click)
// Uses the reusable ContextMenuManager (assets/js/context.menu.js).
// ============================================================================

const TaskContextMenu = (() => {
    const PRIORITY_LABELS = ['Lowest', 'Low', 'Alarming', 'Critical', 'Highest'];

    /**
     * Open a modal by fetching its HTML into <body> — the same pattern used by
     * the label editor and delete-task flows (hx-target="body" beforeend).
     * Inline <script> tags in the fetched HTML are executed manually
     * (insertAdjacentHTML does not run them); htmx.process picks up any
     * hx-* attributes.
     * @param {string} url
     * @param {Object} [opts] { wrap: bool } wrap the response in a standard
     *   modal-container (for partials that are bare forms, e.g. duplicate).
     */
    async function openModalFromUrl(url, opts = {}) {
        try {
            const response = await fetch(url, { headers: { 'X-CSRF-Token': Csrf.getToken() } });
            if (!response.ok) {
                Http.toastError('Could not open dialog (HTTP ' + response.status + ')');
                return;
            }
            let html = await response.text();

            if (opts.wrap) {
                html =
                    '<div class="modal-container show" style="display:flex;">' +
                    '<div class="dialog dialog-sm">' +
                    '<div class="dialog-header"><span>Duplicate task</span>' +
                    '<button class="close-modal-btn btn">X</button></div>' +
                    '<div class="formOuter">' + html + '</div></div></div>';
            }

            // Extract the partial's inline scripts (panel open/close behaviour,
            // dialog auto-open) and strip them from the HTML so no inert
            // <script> tags are ever inserted into <body>.
            const range = document.createRange();
            const fragment = range.createContextualFragment(html);
            const scriptContents = [];
            fragment.querySelectorAll('script').forEach(oldScript => {
                scriptContents.push(oldScript.textContent);
                oldScript.remove(); // stripped before insertion
            });
            // Serialize the script-less remainder back to HTML.
            const holder = document.createElement('div');
            holder.appendChild(fragment);
            html = holder.innerHTML;

            // Insert the markup FIRST, then execute the scripts — the panel's
            // inline script calls SlideOutPanel.setup(), which needs its
            // <aside> to already be in the DOM (getElementById lookup).
            document.body.insertAdjacentHTML('beforeend', html);

            // Remember what we inserted: setup() may MOVE the last element
            // (slide panels relocate themselves into their host dialog), so
            // body.lastElementChild can no longer be trusted afterwards.
            const inserted = document.body.lastElementChild;

            scriptContents.forEach(content => {
                const script = document.createElement('script');
                script.textContent = content;
                document.body.appendChild(script); // executing insert
                script.remove();
            });

            if (typeof htmx !== 'undefined') {
                // Process the element we inserted (or its current parent
                // subtree if a script relocated it), NOT body.lastElementChild.
                const target = inserted.isConnected ? inserted : document.body.lastElementChild;
                htmx.process(target);
            }
        } catch (error) {
            Http.toastError('Network error');
        }
    }

    /**
     * POST a quick action with CSRF header; server responses drive the
     * UI via HX-Trigger events (board refresh, toasts).
     */
    async function sendRequest(url, method, body) {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'X-CSRF-Token': Csrf.getToken(),
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body ? new URLSearchParams(body).toString() : null,
            });
            if (!response.ok) {
                Http.toastError('Request failed (HTTP ' + response.status + ')');
                return;
            }
            // triggerResponse endpoints return JSON with an HX-Trigger header;
            // fire the events manually so toasts/board refresh happen.
            const triggerHeader = response.headers.get('HX-Trigger');
            if (triggerHeader) {
                try {
                    const triggers = JSON.parse(triggerHeader);
                    Object.entries(triggers).forEach(([eventName, detail]) => {
                        document.body.dispatchEvent(new CustomEvent(eventName, { detail }));
                    });
                } catch (parseError) {
                    console.error('Invalid HX-Trigger header', parseError);
                }
            }
        } catch (error) {
            Http.toastError('Network error');
        }
    }

    function getTaskId(taskEl) {
        return taskEl ? taskEl.dataset.id : null;
    }

    function getColumnId(taskEl) {
        const column = taskEl ? taskEl.closest('.task-column') : null;
        return column ? column.dataset.columnId : null;
    }

    function openEditDialog(taskEl) {
        openModalFromUrl('/task/dialog/edit/' + getColumnId(taskEl) + '/' + getTaskId(taskEl));
    }

    function openDuplicateDialog(taskEl) {
        openModalFromUrl('/task/duplicate/dupe/' + getTaskId(taskEl), { wrap: true });
    }

    function openRecurrencePanel(taskEl) {
        openModalFromUrl('/task/recurrence/panel/' + getTaskId(taskEl));
    }

    function confirmDelete(taskEl) {
        const taskId = getTaskId(taskEl);
        const columnId = getColumnId(taskEl);
        openModalFromUrl('/task/dialog/delete-confirm/' + columnId + '/' + taskId);
    }

    function setPriority(taskEl, priority) {
        sendRequest('/task/priority/' + getTaskId(taskEl), 'POST', { task_priority: priority });
    }

    /**
     * Build the task menu items. Function form so each open reflects the
     * current element.
     */
    function buildItems(taskEl) {
        return [
            { id: 'edit', label: 'Edit', icon: 'fa-pencil', action: openEditDialog },
            { id: 'duplicate', label: 'Duplicate', icon: 'fa-files-o', action: openDuplicateDialog },
            { id: 'recurring', label: 'Make recurring', icon: 'fa-repeat', action: openRecurrencePanel },
            { separator: true },
            {
                id: 'priority',
                label: 'Change priority',
                icon: 'fa-flag',
                submenu: PRIORITY_LABELS.map((name, value) => ({
                    id: 'priority-' + value,
                    label: name,
                    action: () => setPriority(taskEl, value),
                })),
            },
            { separator: true },
            { id: 'delete', label: 'Delete task', icon: 'fa-trash-o', danger: true, action: confirmDelete },
        ];
    }

    function init() {
        if (typeof ContextMenuManager === 'undefined') return;
        ContextMenuManager.register('.tm-task', buildItems);
        attachRecurrenceButtonHandler();
    }

    /**
     * "Make recurring" button inside the task edit modal. Delegated on body
     * (the modal content is swapped dynamically). Uses openModalFromUrl so
     * the response's inline scripts are executed and stripped — a plain
     * htmx swap would leave inert <script> tags in the DOM.
     */
    function attachRecurrenceButtonHandler() {
        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('#make-recurring-btn');
            if (!btn || !btn.dataset.recurrenceUrl) return;
            e.preventDefault();
            openModalFromUrl(btn.dataset.recurrenceUrl);
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', TaskContextMenu.init);